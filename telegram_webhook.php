
<?php
require_once 'config.php';

// Pārbaudīt vai Telegram ir aktivizēts
if (!defined('TELEGRAM_NOTIFICATIONS_ENABLED') || !TELEGRAM_NOTIFICATIONS_ENABLED) {
    http_response_code(404);
    exit('Telegram notifications disabled');
}

// Iegūt webhook datus
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    exit('Invalid JSON');
}

// Logot webhook datus
error_log("Telegram webhook received: " . $input);

try {
    if (isset($data['message'])) {
        $message = $data['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $from = $message['from'];
        
        // Ja ziņojums sākas ar /start vai /register
        if (strpos($text, '/start') === 0 || strpos($text, '/register') === 0) {
            handleRegistration($chatId, $from, $text);
        } 
        // Ja ziņojums ir /status
        elseif ($text === '/status') {
            handleStatusRequest($chatId);
        }
        // Ja ziņojums ir /help
        elseif ($text === '/help') {
            handleHelpRequest($chatId);
        }
        // Citādi nosūtīt vispārīgu atbildi
        else {
            handleGeneralMessage($chatId, $text);
        }
    }
    
    // Atgriezt 200 OK
    http_response_code(200);
    echo 'OK';
    
} catch (Exception $e) {
    error_log("Telegram webhook error: " . $e->getMessage());
    http_response_code(500);
    echo 'Error';
}

function handleRegistration($chatId, $from, $text) {
    global $telegramManager, $pdo;
    
    // Mēģināt atrast lietotāju pēc Telegram username
    $username = $from['username'] ?? null;
    $firstName = $from['first_name'] ?? '';
    $lastName = $from['last_name'] ?? '';
    
    if ($username) {
        try {
            // Meklēt lietotāju sistēmā pēc username (pieņemot, ka tas sakrīt ar sistēmas username)
            $stmt = $pdo->prepare("
                SELECT id, CONCAT(vards, ' ', uzvards) as pilns_vards, loma 
                FROM lietotaji 
                WHERE username = ? AND statuss = 'Aktīvs'
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Reģistrēt lietotāju Telegram sistēmā
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
                $message .= "Jūsu Telegram username '@{$username}' nesakrīt ar nevienu aktīvu lietotāju sistēmā.\n\n";
                $message .= "Lūdzu sazinieties ar administratoru vai pārbaudiet, vai jūsu username ir pareizi norādīts sistēmā.";
            }
            
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            $message = "❌ Sistēmas kļūda. Lūdzu mēģiniet vēlāk.";
        }
    } else {
        $message = "❌ <b>Nav Telegram username</b>\n\n";
        $message .= "Lai reģistrētos, jums ir nepieciešams Telegram username.\n\n";
        $message .= "Lūdzu iestatiet username savā Telegram profilā un mēģiniet vēlreiz.";
    }
    
    $telegramManager->sendMessage($chatId, $message);
}

function handleStatusRequest($chatId) {
    global $telegramManager, $pdo;
    
    try {
        // Meklēt lietotāju pēc chat ID
        $stmt = $pdo->prepare("
            SELECT tu.*, l.vards, l.uzvards, l.loma
            FROM telegram_users tu
            JOIN lietotaji l ON tu.lietotaja_id = l.id
            WHERE tu.telegram_chat_id = ? AND tu.is_active = TRUE
        ");
        $stmt->execute([$chatId]);
        $telegramUser = $stmt->fetch();
        
        if ($telegramUser) {
            // Iegūt statistiku par uzdevumiem
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as kopejie,
                    SUM(CASE WHEN statuss = 'Jauns' THEN 1 ELSE 0 END) as jauni,
                    SUM(CASE WHEN statuss = 'Procesā' THEN 1 ELSE 0 END) as procesa,
                    SUM(CASE WHEN statuss = 'Pabeigts' THEN 1 ELSE 0 END) as pabeigti
                FROM uzdevumi 
                WHERE piešķirts_id = ? AND izveidots >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute([$telegramUser['lietotaja_id']]);
            $stats = $stmt->fetch();
            
            $message = "📊 <b>Jūsu statuss sistēmā</b>\n\n";
            $message .= "👤 <b>Lietotājs:</b> {$telegramUser['vards']} {$telegramUser['uzvards']}\n";
            $message .= "🏷️ <b>Loma:</b> {$telegramUser['loma']}\n";
            $message .= "📅 <b>Reģistrēts:</b> " . date('d.m.Y H:i', strtotime($telegramUser['registered_at'])) . "\n\n";
            
            $message .= "📋 <b>Uzdevumi (pēdējās 30 dienas):</b>\n";
            $message .= "• Kopējie: {$stats['kopejie']}\n";
            $message .= "• Jauni: {$stats['jauni']}\n";
            $message .= "• Procesā: {$stats['procesa']}\n";
            $message .= "• Pabeigti: {$stats['pabeigti']}\n\n";
            
            $message .= "🔗 <a href='" . SITE_URL . "'>Atvērt sistēmu</a>";
        } else {
            $message = "❌ <b>Jūs neesat reģistrēts</b>\n\n";
            $message .= "Lūdzu nosūtiet /start lai reģistrētos sistēmā.";
        }
        
    } catch (Exception $e) {
        error_log("Status request error: " . $e->getMessage());
        $message = "❌ Kļūda iegūstot statusu. Lūdzu mēģiniet vēlāk.";
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
    
    $message .= "💡 <b>Ko dara šis bots:</b>\n";
    $message .= "• Paziņo par jauniem uzdevumiem\n";
    $message .= "• Informē par problēmām\n";
    $message .= "• Sūta atgādinājumus par termiņiem\n\n";
    
    $message .= "🔗 <a href='" . SITE_URL . "'>Atvērt sistēmu</a>\n\n";
    
    $message .= "❓ Ja rodas problēmas, sazinieties ar sistēmas administratoru.";
    
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
