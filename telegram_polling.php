
<?php
require_once 'config.php';

// Pārbaudīt vai Telegram ir aktivizēts
if (!defined('TELEGRAM_NOTIFICATIONS_ENABLED') || !TELEGRAM_NOTIFICATIONS_ENABLED) {
    die("Telegram notifications disabled\n");
}

if (!isset($GLOBALS['telegramManager'])) {
    die("Telegram Manager not initialized\n");
}

$telegramManager = $GLOBALS['telegramManager'];
$lastUpdateId = 0;

// Ielādēt pēdējo update ID no faila
$offsetFile = 'telegram_offset.txt';
if (file_exists($offsetFile)) {
    $lastUpdateId = (int)file_get_contents($offsetFile);
}

echo "Starting Telegram polling... Last update ID: $lastUpdateId\n";

while (true) {
    try {
        $updates = $telegramManager->getUpdates($lastUpdateId + 1);
        
        if ($updates && isset($updates['result']) && !empty($updates['result'])) {
            foreach ($updates['result'] as $update) {
                processUpdate($update);
                $lastUpdateId = $update['update_id'];
            }
            
            // Saglabāt pēdējo update ID
            file_put_contents($offsetFile, $lastUpdateId);
        }
        
        // Gaidīt 1 sekunde pirms nākamās pārbaudes
        sleep(1);
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        sleep(5); // Gaidīt īsāk, ja kļūda
    }
}

function processUpdate($update) {
    global $telegramManager;
    
    if (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $from = $message['from'];
        
        echo "Received message: $text from chat: $chatId\n";
        
        // Procesēt ziņojumu (tāda pat loģika kā webhook)
        if (strpos($text, '/start') === 0 || strpos($text, '/register') === 0) {
            handleRegistration($chatId, $from, $text);
        } 
        elseif ($text === '/status') {
            handleStatusRequest($chatId);
        }
        elseif ($text === '/help') {
            handleHelpRequest($chatId);
        }
        else {
            handleGeneralMessage($chatId, $text);
        }
    }
}

// Iekļaut funkcijas no webhook
function handleRegistration($chatId, $from, $text) {
    global $telegramManager, $pdo;
    
    $username = $from['username'] ?? null;
    $firstName = $from['first_name'] ?? '';
    $lastName = $from['last_name'] ?? '';
    
    if ($username) {
        try {
            $stmt = $pdo->prepare("
                SELECT id, CONCAT(vards, ' ', uzvards) as pilns_vards, loma 
                FROM lietotaji 
                WHERE username = ? AND statuss = 'Aktīvs'
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user) {
                $result = $telegramManager->registerUser(
                    $user['id'],
                    $chatId,
                    $username,
                    $firstName,
                    $lastName
                );
                
                if ($result) {
                    $message = "✅ <b>Reģistrācija veiksmīga!</b>\n\n";
                    $message .= "👤 <b>Lietotājs:</b> {$user['pilns_vards']}\n";
                    $message .= "🏷️ <b>Loma:</b> {$user['loma']}\n\n";
                    $message .= "Tagad jūs saņemsiet paziņojumus par uzdevumiem un problēmām.";
                } else {
                    $message = "❌ Kļūda reģistrējot lietotāju. Lūdzu mēģiniet vēlāk.";
                }
            } else {
                $message = "❌ <b>Lietotājs nav atrasts</b>\n\n";
                $message .= "Jūsu Telegram username '@{$username}' nesakrīt ar nevienu aktīvu lietotāju sistēmā.";
            }
            
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            $message = "❌ Sistēmas kļūda. Lūdzu mēģiniet vēlāk.";
        }
    } else {
        $message = "❌ <b>Nav Telegram username</b>\n\n";
        $message .= "Lai reģistrētos, jums ir nepieciešams Telegram username.";
    }
    
    $telegramManager->sendMessage($chatId, $message);
}

function handleStatusRequest($chatId) {
    global $telegramManager, $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT tu.*, l.vards, l.uzvards, l.loma
            FROM telegram_users tu
            JOIN lietotaji l ON tu.lietotaja_id = l.id
            WHERE tu.telegram_chat_id = ? AND tu.is_active = TRUE
        ");
        $stmt->execute([$chatId]);
        $telegramUser = $stmt->fetch();
        
        if ($telegramUser) {
            $message = "📊 <b>Jūsu statuss sistēmā</b>\n\n";
            $message .= "👤 <b>Lietotājs:</b> {$telegramUser['vards']} {$telegramUser['uzvards']}\n";
            $message .= "🏷️ <b>Loma:</b> {$telegramUser['loma']}\n";
            $message .= "🔗 <a href='" . SITE_URL . "'>Atvērt sistēmu</a>";
        } else {
            $message = "❌ <b>Jūs neesat reģistrēts</b>\n\nLūdzu nosūtiet /start lai reģistrētos.";
        }
        
    } catch (Exception $e) {
        $message = "❌ Kļūda iegūstot statusu.";
    }
    
    $telegramManager->sendMessage($chatId, $message);
}

function handleHelpRequest($chatId) {
    global $telegramManager;
    
    $message = "🤖 <b>AVOTI Uzdevumu sistēmas bots</b>\n\n";
    $message .= "📝 <b>Pieejamās komandas:</b>\n";
    $message .= "/start - Reģistrēties sistēmā\n";
    $message .= "/status - Skatīt savu statusu\n";
    $message .= "/help - Šis palīdzības ziņojums\n\n";
    $message .= "🔗 <a href='" . SITE_URL . "'>Atvērt sistēmu</a>";
    
    $telegramManager->sendMessage($chatId, $message);
}

function handleGeneralMessage($chatId, $text) {
    global $telegramManager;
    
    $message = "🤖 Sveiki! Es esmu AVOTI uzdevumu sistēmas bots.\n\n";
    $message .= "Lai reģistrētos, nosūtiet: /start\n";
    $message .= "Palīdzībai nosūtiet: /help";
    
    $telegramManager->sendMessage($chatId, $message);
}
?>
