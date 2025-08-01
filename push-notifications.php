<?php
/**
 * Uzlabots Push Notifications Server-side Handler
 * AVOTI Task Management System
 */

class PushNotificationManager {
    private $pdo;
    private $vapidPublicKey;
    private $vapidPrivateKey;
    private $vapidContact;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->vapidPublicKey = defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : null;
        $this->vapidPrivateKey = defined('VAPID_PRIVATE_KEY') ? VAPID_PRIVATE_KEY : null;
        $this->vapidContact = defined('VAPID_CONTACT') ? VAPID_CONTACT : 'mailto:admin@avoti.lv';
        
        // Create push subscriptions table if it doesn't exist
        $this->createPushSubscriptionsTable();
    }
    
    public function createPushSubscriptionsTable() {
        try {
            // Pārbaudīt vai lietotāju tabula eksistē
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'lietotaji'");
            if ($stmt->rowCount() == 0) {
                error_log("Error: lietotaji table does not exist");
                return false;
            }
            
            // Izveidot push_subscriptions tabulu BEZ foreign key (drošāk)
            $sql = "
                CREATE TABLE IF NOT EXISTS push_subscriptions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    lietotaja_id INT NOT NULL,
                    endpoint VARCHAR(500) NOT NULL,
                    p256dh_key VARCHAR(255) NOT NULL,
                    auth_key VARCHAR(255) NOT NULL,
                    user_agent TEXT,
                    ip_address VARCHAR(45),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    last_used TIMESTAMP NULL,
                    is_active BOOLEAN DEFAULT TRUE,
                    UNIQUE KEY unique_subscription (lietotaja_id, endpoint(100))
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            $result1 = $this->pdo->exec($sql);
            
            // Izveidot notification_tracking tabulu
            $sql = "
                CREATE TABLE IF NOT EXISTS notification_tracking (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tracking_id VARCHAR(50) NOT NULL UNIQUE,
                    lietotaja_id INT NOT NULL,
                    notification_type VARCHAR(50) NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    body TEXT,
                    data JSON,
                    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    delivered_at TIMESTAMP NULL,
                    clicked_at TIMESTAMP NULL,
                    status ENUM('sent', 'delivered', 'clicked', 'failed') DEFAULT 'sent',
                    error_message TEXT,
                    INDEX idx_lietotaja_id (lietotaja_id),
                    INDEX idx_tracking_id (tracking_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            $result2 = $this->pdo->exec($sql);
            
            // Log success
            error_log("Push notification tables created successfully");
            return true;
            
        } catch (PDOException $e) {
            error_log("Error creating push subscription tables: " . $e->getMessage());
            return false;
        }
    }
    
    public function saveSubscription($lietotajaId, $subscription) {
        try {
            $endpoint = $subscription['endpoint'];
            $keys = $subscription['keys'];
            $p256dhKey = $keys['p256dh'];
            $authKey = $keys['auth'];
            
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $ipAddress = $this->getRealIpAddr();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO push_subscriptions (lietotaja_id, endpoint, p256dh_key, auth_key, user_agent, ip_address)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    p256dh_key = VALUES(p256dh_key),
                    auth_key = VALUES(auth_key),
                    user_agent = VALUES(user_agent),
                    ip_address = VALUES(ip_address),
                    updated_at = CURRENT_TIMESTAMP,
                    is_active = TRUE
            ");
            
            return $stmt->execute([$lietotajaId, $endpoint, $p256dhKey, $authKey, $userAgent, $ipAddress]);
            
        } catch (PDOException $e) {
            error_log("Error saving push subscription: " . $e->getMessage());
            return false;
        }
    }
    
    public function removeSubscription($lietotajaId, $subscription) {
        try {
            $endpoint = $subscription['endpoint'];
            
            $stmt = $this->pdo->prepare("
                UPDATE push_subscriptions 
                SET is_active = FALSE 
                WHERE lietotaja_id = ? AND endpoint = ?
            ");
            
            return $stmt->execute([$lietotajaId, $endpoint]);
            
        } catch (PDOException $e) {
            error_log("Error removing push subscription: " . $e->getMessage());
            return false;
        }
    }
    
    public function getUserSubscriptions($lietotajaId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT endpoint, p256dh_key, auth_key 
                FROM push_subscriptions 
                WHERE lietotaja_id = ? AND is_active = TRUE
                ORDER BY updated_at DESC
            ");
            $stmt->execute([$lietotajaId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting user subscriptions: " . $e->getMessage());
            return [];
        }
    }
    
    public function sendNotification($lietotajaId, $title, $body, $data = [], $options = []) {
        try {
            $subscriptions = $this->getUserSubscriptions($lietotajaId);
            
            if (empty($subscriptions)) {
                return ['success' => false, 'error' => 'No active subscriptions found'];
            }
            
            $trackingId = $this->generateTrackingId();
            $sent = 0;
            $failed = 0;
            $errors = [];
            
            // Create notification tracking record
            $this->trackNotification($trackingId, $lietotajaId, $options['type'] ?? 'general', $title, $body, $data);
            
            // Prepare notification payload
            $payload = [
                'title' => $title,
                'body' => $body,
                'icon' => $options['icon'] ?? '/assets/images/icon-192x192.png',
                'badge' => $options['badge'] ?? '/assets/images/icon-192x192.png',
                'image' => $options['image'] ?? null,
                'data' => array_merge($data, [
                    'trackingId' => $trackingId,
                    'timestamp' => time()
                ]),
                'tag' => $options['tag'] ?? 'avoti-notification',
                'requireInteraction' => $options['requireInteraction'] ?? false,
                'actions' => $options['actions'] ?? []
            ];
            
            foreach ($subscriptions as $subscription) {
                $result = $this->sendPushNotification($subscription, $payload);
                
                if ($result['success']) {
                    $sent++;
                } else {
                    $failed++;
                    $errors[] = $result['error'];
                    
                    // If subscription is invalid, mark as inactive
                    if (strpos($result['error'], '410') !== false || strpos($result['error'], '404') !== false) {
                        $this->markSubscriptionInactive($subscription['endpoint']);
                    }
                }
            }
            
            return [
                'success' => $sent > 0,
                'sent' => $sent,
                'failed' => $failed,
                'errors' => $errors,
                'trackingId' => $trackingId
            ];
            
        } catch (Exception $e) {
            error_log("Error sending notification: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function sendPushNotification($subscription, $payload) {
        try {
            $endpoint = $subscription['endpoint'];
            
            // Vienkārša implementācija - reālajā projektā izmantojiet web-push bibliotēku
            // Šī ir tikai demonstrācija
            $headers = [
                'TTL: 86400',
                'Content-Type: application/json',
                'Content-Encoding: aes128gcm'
            ];
            
            $encryptedPayload = json_encode($payload);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $endpoint,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $encryptedPayload,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false, // Development only!
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new Exception("cURL error: $error");
            }
            
            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true];
            } else {
                throw new Exception("HTTP error: $httpCode - $response");
            }
            
        } catch (Exception $e) {
            error_log("Push notification send error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function trackNotification($trackingId, $lietotajaId, $type, $title, $body, $data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notification_tracking 
                (tracking_id, lietotaja_id, notification_type, title, body, data)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $trackingId,
                $lietotajaId,
                $type,
                $title,
                $body,
                json_encode($data)
            ]);
            
        } catch (PDOException $e) {
            error_log("Error tracking notification: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateNotificationStatus($trackingId, $status) {
        try {
            $validStatuses = ['delivered', 'clicked', 'failed'];
            if (!in_array($status, $validStatuses)) {
                return false;
            }
            
            $column = $status . '_at';
            
            $stmt = $this->pdo->prepare("
                UPDATE notification_tracking 
                SET status = ?, $column = CURRENT_TIMESTAMP 
                WHERE tracking_id = ?
            ");
            
            return $stmt->execute([$status, $trackingId]);
            
        } catch (PDOException $e) {
            error_log("Error updating notification status: " . $e->getMessage());
            return false;
        }
    }
    
    private function markSubscriptionInactive($endpoint) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE push_subscriptions 
                SET is_active = FALSE 
                WHERE endpoint = ?
            ");
            
            return $stmt->execute([$endpoint]);
            
        } catch (PDOException $e) {
            error_log("Error marking subscription inactive: " . $e->getMessage());
            return false;
        }
    }
    
    private function generateTrackingId() {
        return uniqid('ntf_', true);
    }
    
    private function getRealIpAddr() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
    
    public function getVapidPublicKey() {
        return $this->vapidPublicKey;
    }
    
    // Palīgmetodes uzdevumu un problēmu paziņojumiem
    public function sendTaskNotification($lietotajaId, $taskTitle, $taskId, $type = 'new_task') {
        $title = '';
        $body = '';
        $data = [
            'type' => 'task',
            'taskId' => $taskId,
            'url' => '/my_tasks.php?task_id=' . $taskId
        ];
        
        switch ($type) {
            case 'new_task':
                $title = 'Jauns uzdevums piešķirts';
                $body = "Jums ir piešķirts jauns uzdevums: $taskTitle";
                break;
                
            case 'task_completed':
                $title = 'Uzdevums pabeigts';
                $body = "Uzdevums '$taskTitle' ir pabeigts";
                break;
                
            case 'task_overdue':
                $title = 'Uzdevums nokavēts';
                $body = "Uzdevums '$taskTitle' ir nokavēts";
                break;
        }
        
        $options = [
            'type' => $type,
            'icon' => '/assets/images/icon-192x192.png',
            'tag' => 'task-' . $taskId,
            'requireInteraction' => $type === 'task_overdue',
            'actions' => [
                [
                    'action' => 'open',
                    'title' => 'Atvērt uzdevumu'
                ],
                [
                    'action' => 'dismiss',
                    'title' => 'Aizvērt'
                ]
            ]
        ];
        
        return $this->sendNotification($lietotajaId, $title, $body, $data, $options);
    }
    
    public function sendProblemNotification($lietotajaId, $problemTitle, $problemId) {
        $title = 'Jauna problēma ziņota';
        $body = "Ziņota jauna problēma: $problemTitle";
        
        $data = [
            'type' => 'problem',
            'problemId' => $problemId,
            'url' => '/problems.php?problem_id=' . $problemId
        ];
        
        $options = [
            'type' => 'new_problem',
            'icon' => '/assets/images/icon-192x192.png',
            'tag' => 'problem-' . $problemId,
            'actions' => [
                [
                    'action' => 'open',
                    'title' => 'Skatīt problēmu'
                ],
                [
                    'action' => 'dismiss',
                    'title' => 'Aizvērt'
                ]
            ]
        ];
        
        return $this->sendNotification($lietotajaId, $title, $body, $data, $options);
    }
}

// Pārliecinieties, ka globālais mainīgais ir pieejams
if (!isset($GLOBALS['pushNotificationManager']) && isset($pdo)) {
    try {
        $GLOBALS['pushNotificationManager'] = new PushNotificationManager($pdo);
    } catch (Exception $e) {
        error_log("Global PushNotificationManager initialization failed: " . $e->getMessage());
    }
}
?>