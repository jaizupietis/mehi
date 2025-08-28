
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
            
            // Iegūt mehāniķa vārdu un prioritāti no uzdevuma
            $mechanicName = null;
            $priority = null;
            try {
                $stmt = $this->pdo->prepare("
                    SELECT CONCAT(l.vards, ' ', l.uzvards) as mehaniķa_vards, u.prioritate
                    FROM uzdevumi u
                    LEFT JOIN lietotaji l ON u.piešķirts_id = l.id
                    WHERE u.id = ?
                ");
                $stmt->execute([$taskId]);
                $result = $stmt->fetch();
                if ($result) {
                    if ($result['mehaniķa_vards']) {
                        $mechanicName = $result['mehaniķa_vards'];
                    }
                    $priority = $result['prioritate'];
                }
            } catch (Exception $e) {
                error_log("Error getting mechanic name and priority for task $taskId: " . $e->getMessage());
            }
            
            $message = $this->formatTaskMessage($taskTitle, $taskId, $type, $mechanicName, $priority);
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
            
            // Iegūt operatora vārdu un prioritāti no problēmas
            $operatorName = null;
            $priority = null;
            try {
                $stmt = $this->pdo->prepare("
                    SELECT CONCAT(l.vards, ' ', l.uzvards) as operatora_vards, p.prioritate
                    FROM problemas p
                    LEFT JOIN lietotaji l ON p.zinotajs_id = l.id
                    WHERE p.id = ?
                ");
                $stmt->execute([$problemId]);
                $result = $stmt->fetch();
                if ($result) {
                    if ($result['operatora_vards']) {
                        $operatorName = $result['operatora_vards'];
                    }
                    $priority = $result['prioritate'];
                }
            } catch (Exception $e) {
                error_log("Error getting operator name and priority for problem $problemId: " . $e->getMessage());
            }
            
            $message = $this->formatProblemMessage($problemTitle, $problemId, $operatorName, $priority);
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
    
    private function formatTaskMessage($taskTitle, $taskId, $type, $mechanicName = null, $priority = null) {
        $emoji = $this->getTaskEmoji($type);
        $typeText = $this->getTaskTypeText($type);
        
        // Dekodēt HTML entities un sagatavot tekstu
        $cleanTitle = html_entity_decode($taskTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Papildu dekodēšana īpašiem gadījumiem
        $cleanTitle = str_replace(['&#039;', '&quot;', '&amp;', '&lt;', '&gt;'], ["'", '"', '&', '<', '>'], $cleanTitle);
        
        // Īpaša formatēšana kritiskajiem uzdevumiem
        if ($priority === 'Kritiska') {
            $message = "🚨🔴 <b>KRITISKS UZDEVUMS!</b> 🔴🚨\n\n";
            $message .= "{$emoji} <b>{$typeText}</b>\n\n";
        } else {
            $message = "{$emoji} <b>{$typeText}</b>\n\n";
        }
        
        $message .= "📋 <b>Uzdevums:</b> " . htmlspecialchars($cleanTitle) . "\n";
        
        // Pievienot prioritāti ar emoji
        if ($priority) {
            $priorityEmoji = $this->getPriorityEmoji($priority);
            $message .= "{$priorityEmoji} <b>Prioritāte:</b> {$priority}\n";
        }
        
        // Pievienot mehāniķa vārdu, ja pieejams
        if ($mechanicName) {
            $message .= "👤 <b>Mehāniķis:</b> {$mechanicName}\n";
        }
        
        $message .= "🕐 <b>Laiks:</b> " . date('d.m.Y H:i') . "\n\n";
        $message .= "🔗 <a href='" . SITE_URL . "/view_task.php?task_id={$taskId}'>Skatīt uzdevumu</a>";
        
        return $message;
    }
    
    private function formatProblemMessage($problemTitle, $problemId, $operatorName = null, $priority = null) {
        // Dekodēt HTML entities un sagatavot tekstu
        $cleanTitle = html_entity_decode($problemTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Papildu dekodēšana īpašiem gadījumiem
        $cleanTitle = str_replace(['&#039;', '&quot;', '&amp;', '&lt;', '&gt;'], ["'", '"', '&', '<', '>'], $cleanTitle);
        
        // Īpaša formatēšana kritiskajām problēmām
        if ($priority === 'Kritiska') {
            $message = "🚨🔴 <b>KRITISKA PROBLĒMA!</b> 🔴🚨\n\n";
            $message .= "⚠️ <b>Jauna problēma ziņota</b>\n\n";
        } else {
            $message = "🚨 <b>Jauna problēma ziņota</b>\n\n";
        }
        
        $message .= "⚠️ <b>Problēma:</b> " . htmlspecialchars($cleanTitle) . "\n";
        
        // Pievienot prioritāti ar emoji
        if ($priority) {
            $priorityEmoji = $this->getPriorityEmoji($priority);
            $message .= "{$priorityEmoji} <b>Prioritāte:</b> {$priority}\n";
        }
        
        // Pievienot operatora vārdu, ja pieejams
        if ($operatorName) {
            $message .= "👤 <b>Operators:</b> {$operatorName}\n";
        }
        
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
    
    private function getPriorityEmoji($priority) {
        switch (strtolower($priority)) {
            case 'kritiska':
                return '🔴⚡';
            case 'augsta':
                return '🟠';
            case 'vidēja':
                return '🟡';
            case 'zema':
                return '🟢';
            default:
                return '⚪';
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
    
    public function findUserByTelegramUsername($username) {
        try {
            // Noņemt @ simbolu, ja ir
            $cleanUsername = ltrim($username, '@');
            
            $stmt = $this->pdo->prepare("
                SELECT id, CONCAT(vards, ' ', uzvards) as pilns_vards, loma
                FROM lietotaji 
                WHERE telegram_username = ? AND statuss = 'Aktīvs'
                LIMIT 1
            ");
            $stmt->execute([$cleanUsername]);
            
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Error finding user by Telegram username: " . $e->getMessage());
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
