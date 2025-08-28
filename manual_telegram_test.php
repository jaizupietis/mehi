
<?php
require_once 'config.php';

echo "=== MANUĀLS TELEGRAM TESTS ===\n";

if (!isset($GLOBALS['telegramManager'])) {
    echo "❌ Telegram Manager nav inicializēts\n";
    exit;
}

$telegramManager = $GLOBALS['telegramManager'];

// Manuāli reģistrēt admin lietotāju (nomainiet datus)
$adminChatId = '7486360988'; // Iegūstiet no @userinfobot
$adminUsername = 'aizups'; // Bez @

try {
    // Atrast admin lietotāju
    $stmt = $pdo->prepare("SELECT id FROM lietotaji WHERE loma = 'Administrators' AND statuss = 'Aktīvs' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin) {
        // Manuāli reģistrēt
        $result = $telegramManager->registerUser($admin['id'], $adminChatId, $adminUsername, 'Admin', 'User');
        
        if ($result) {
            echo "✅ Administrators reģistrēts manuāli\n";
            
            // Testēt ziņojuma sūtīšanu
            $message = "🧪 <b>Manuāls tests</b>\n\nŠis ir tests no AVOTI sistēmas pēc manuālas reģistrācijas.";
            $sendResult = $telegramManager->sendMessage($adminChatId, $message);
            
            if ($sendResult) {
                echo "✅ Testa ziņojums nosūtīts!\n";
            } else {
                echo "❌ Neizdevās nosūtīt ziņojumu\n";
            }
        } else {
            echo "❌ Neizdevās reģistrēt administratoru\n";
        }
    } else {
        echo "❌ Nav atrasts administrators\n";
    }
    
} catch (Exception $e) {
    echo "❌ Kļūda: " . $e->getMessage() . "\n";
}

echo "\n=== INSTRUKCIJAS ===\n";
echo "1. Nomainiet JŪSU_TELEGRAM_CHAT_ID un JŪSU_TELEGRAM_USERNAME\n";
echo "2. Chat ID iegūstiet no @userinfobot Telegram\n";
echo "3. Pēc tam palaidiet šo skriptu\n";
?>
