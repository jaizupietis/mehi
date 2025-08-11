
<?php
/**
 * Telegram Notifications Manager
 * AVOTI Task Management System
 */

class TelegramNotificationManager {
    private $pdo;
    private $botToken;
    private $apiUrl;
    
    public function __construct($pdo, $botToken) {
        $this->pdo = $pdo;
        $this->botToken = $botToken;
        $this->apiUrl = "https://api.telegram.org/bot{$botToken}/";
        
        // Izveidot Telegram lietotāju tabulu
        $this->createTelegramUsersTable();
    }
    
    private function createTelegramUsersTable() {
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS telegram_users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    lietotaja_id INT NOT NULL,
                    telegram_chat_id VARCHAR(50) NOT NULL,
                    telegram_username VARCHAR(100),
                    telegram_first_name VARCHAR(100),
                    telegram_last_name VARCHAR(100),
                    is_active BOOLEAN DEFAULT TRUE,
                    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_message_at TIMESTAMP NULL,
                    UNIQUE KEY unique_user_chat (lietotaja_id, telegram_chat_id),
                    INDEX idx_lietotaja_id (lietotaja_id),
                    INDEX idx_chat_id (telegram_chat_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            $this->pdo->exec($sql);
            
            error_log("Telegram users table created successfully");
            
        } catch (PDOException $e) {
            error_log("Error creating telegram_users table: " . $e->getMessage());
        }
    }
    
    public function sendMessage($chatId, $message, $parseMode = 'HTML') {
        try {
            $data = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => $parseMode,
                'disable_web_page_preview' => true
            ];
            
            return $this->makeApiCall('sendMessage', $data);
            
        } catch (Exception $e) {
            error_log("Telegram sendMessage error: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendTaskNotification($lietotajaId, $taskTitle, $taskId, $type = 'new_task') {
        try {
            $chatIds = $this->getUserChatIds($lietotajaId);
            
            if (empty($chatIds)) {
                return ['success' => false, 'error' => 'Nav Telegram chat ID'];
            }
            
            $message = $this->formatTaskMessage($taskTitle, $taskId, $type);
            $sent = 0;
            $errors = [];
            
            foreach ($chatIds as $chatId) {
                $result = $this->sendMessage($chatId, $message);
                
                if ($result) {
                    $sent++;
                    $this->updateLastMessageTime($lietotajaId, $chatId);
                } else {
                    $errors[] = "Failed to send to chat: $chatId";
                }
            }
            
            return [
                'success' => $sent > 0,
                'sent' => $sent,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            error_log("Telegram task notification error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function sendProblemNotification($lietotajaId, $problemTitle, $problemId) {
        try {
            $chatIds = $this->getUserChatIds($lietotajaId);
            
            if (empty($chatIds)) {
                return ['success' => false, 'error' => 'Nav Telegram chat ID'];
            }
            
            $message = $this->formatProblemMessage($problemTitle, $problemId);
            $sent = 0;
            $errors = [];
            
            foreach ($chatIds as $chatId) {
                $result = $this->sendMessage($chatId, $message);
                
                if ($result) {
                    $sent++;
                    $this->updateLastMessageTime($lietotajaId, $chatId);
                } else {
                    $errors[] = "Failed to send to chat: $chatId";
                }
            }
            
            return [
                'success' => $sent > 0,
                'sent' => $sent,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            error_log("Telegram problem notification error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function formatTaskMessage($taskTitle, $taskId, $type) {
        $emoji = $this->getTaskEmoji($type);
        $typeText = $this->getTaskTypeText($type);
        
        $message = "{$emoji} <b>{$typeText}</b>\n\n";
        $message .= "📋 <b>Uzdevums:</b> " . htmlspecialchars($taskTitle) . "\n";
        $message .= "🆔 <b>ID:</b> #{$taskId}\n";
        $message .= "🕐 <b>Laiks:</b> " . date('d.m.Y H:i') . "\n\n";
        $message .= "🔗 <a href='" . SITE_URL . "/my_tasks.php?task_id={$taskId}'>Skatīt uzdevumu</a>";
        
        return $message;
    }
    
    private function formatProblemMessage($problemTitle, $problemId) {
        $message = "🚨 <b>Jauna problēma ziņota</b>\n\n";
        $message .= "⚠️ <b>Problēma:</b> " . htmlspecialchars($problemTitle) . "\n";
        $message .= "🆔 <b>ID:</b> #{$problemId}\n";
        $message .= "🕐 <b>Laiks:</b> " . date('d.m.Y H:i') . "\n\n";
        $message .= "🔗 <a href='" . SITE_URL . "/problems.php?problem_id={$problemId}'>Skatīt problēmu</a>";
        
        return $message;
    }
    
    private function getTaskEmoji($type) {
        switch ($type) {
            case 'new_task':
                return '📝';
            case 'task_completed':
                return '✅';
            case 'task_overdue':
                return '⏰';
            default:
                return '📋';
        }
    }
    
    private function getTaskTypeText($type) {
        switch ($type) {
            case 'new_task':
                return 'Jauns uzdevums piešķirts';
            case 'task_completed':
                return 'Uzdevums pabeigts';
            case 'task_overdue':
                return 'Uzdevums nokavēts';
            default:
                return 'Uzdevuma atjauninājums';
        }
    }
    
    public function registerUser($lietotajaId, $chatId, $username = null, $firstName = null, $lastName = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO telegram_users 
                (lietotaja_id, telegram_chat_id, telegram_username, telegram_first_name, telegram_last_name)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    telegram_username = VALUES(telegram_username),
                    telegram_first_name = VALUES(telegram_first_name),
                    telegram_last_name = VALUES(telegram_last_name),
                    is_active = TRUE
            ");
            
            return $stmt->execute([$lietotajaId, $chatId, $username, $firstName, $lastName]);
            
        } catch (PDOException $e) {
            error_log("Error registering Telegram user: " . $e->getMessage());
            return false;
        }
    }
    
    public function getUserChatIds($lietotajaId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT telegram_chat_id 
                FROM telegram_users 
                WHERE lietotaja_id = ? AND is_active = TRUE
            ");
            $stmt->execute([$lietotajaId]);
            
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
            
        } catch (PDOException $e) {
            error_log("Error getting user chat IDs: " . $e->getMessage());
            return [];
        }
    }
    
    private function updateLastMessageTime($lietotajaId, $chatId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE telegram_users 
                SET last_message_at = CURRENT_TIMESTAMP 
                WHERE lietotaja_id = ? AND telegram_chat_id = ?
            ");
            
            return $stmt->execute([$lietotajaId, $chatId]);
            
        } catch (PDOException $e) {
            error_log("Error updating last message time: " . $e->getMessage());
            return false;
        }
    }
    
    private function makeApiCall($method, $data) {
        $url = $this->apiUrl . $method;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'TelegramBot/1.0',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: $error");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP error: $httpCode - $response");
        }
        
        $result = json_decode($response, true);
        
        if (!$result['ok']) {
            throw new Exception("Telegram API error: " . $result['description']);
        }
        
        return $result;
    }
    
    public function getWebhookInfo() {
        try {
            return $this->makeApiCall('getWebhookInfo', []);
        } catch (Exception $e) {
            error_log("Error getting webhook info: " . $e->getMessage());
            return false;
        }
    }
    
    public function setWebhook($url) {
        try {
            return $this->makeApiCall('setWebhook', ['url' => $url]);
        } catch (Exception $e) {
            error_log("Error setting webhook: " . $e->getMessage());
            return false;
        }
    }
    
    public function getUpdates($offset = 0, $limit = 100, $timeout = 10) {
        try {
            $data = [
                'offset' => $offset,
                'limit' => $limit,
                'timeout' => $timeout
            ];
            
            return $this->makeApiCall('getUpdates', $data);
        } catch (Exception $e) {
            error_log("Error getting updates: " . $e->getMessage());
            return false;
        }
    }
}
?>
