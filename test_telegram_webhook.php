<?php
require_once 'config.php';

echo "=== TELEGRAM WEBHOOK PĀRBAUDE ===\n";

if (!defined('TELEGRAM_NOTIFICATIONS_ENABLED') || !TELEGRAM_NOTIFICATIONS_ENABLED) {
    echo "❌ Telegram paziņojumi ir deaktivizēti\n";
    exit;
}

if (!isset($GLOBALS['telegramManager'])) {
    echo "❌ Telegram Manager nav inicializēts\n";
    exit;
}

$telegramManager = $GLOBALS['telegramManager'];

// Pārbaudīt webhook statusu
try {
    $webhookInfo = $telegramManager->getWebhookInfo();
    
    if ($webhookInfo && $webhookInfo['ok']) {
        echo "✅ Bot ir sasniedzams\n";
        echo "Webhook URL: " . ($webhookInfo['result']['url'] ?? 'Nav iestatīts') . "\n";
        echo "Pēdējā kļūda: " . ($webhookInfo['result']['last_error_message'] ?? 'Nav') . "\n";
        echo "Gaida ziņojumu skaits: " . ($webhookInfo['result']['pending_update_count'] ?? 0) . "\n";
        
        if (empty($webhookInfo['result']['url'])) {
            echo "\n🔧 Iestatām webhook...\n";
            $webhookUrl = SITE_URL . '/telegram_webhook.php';
            $result = $telegramManager->setWebhook($webhookUrl);
            
            if ($result) {
                echo "✅ Webhook iestatīts: $webhookUrl\n";
            } else {
                echo "❌ Neizdevās iestatīt webhook\n";
            }
        }
    } else {
        echo "❌ Nevar sasniegt botu\n";
    }
    
} catch (Exception $e) {
    echo "❌ Kļūda: " . $e->getMessage() . "\n";
}

// Testēt vienkāršu ziņojumu sūtīšanu
echo "\n=== TESTA ZIŅOJUMA SŪTĪŠANA ===\n";

try {
    // Atrast pirmo administratoru ar telegram_chat_id
    $stmt = $pdo->prepare("
        SELECT l.id, l.vards, l.uzvards, tu.telegram_chat_id
        FROM lietotaji l
        LEFT JOIN telegram_users tu ON l.id = tu.lietotaja_id
        WHERE l.loma = 'Administrators' 
        AND l.statuss = 'Aktīvs'
        AND tu.telegram_chat_id IS NOT NULL
        AND tu.is_active = TRUE
        LIMIT 1
    ");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "Testējam sūtīšanu uz: {$admin['vards']} {$admin['uzvards']} (Chat ID: {$admin['telegram_chat_id']})\n";
        
        $message = "🧪 <b>Tests no AVOTI sistēmas</b>\n\n";
        $message .= "⏰ <b>Laiks:</b> " . date('d.m.Y H:i:s') . "\n";
        $message .= "✅ Telegram integrācija darbojas!";
        
        $result = $telegramManager->sendMessage($admin['telegram_chat_id'], $message);
        
        if ($result) {
            echo "✅ Testa ziņojums nosūtīts veiksmīgi!\n";
        } else {
            echo "❌ Neizdevās nosūtīt testa ziņojumu\n";
        }
    } else {
        echo "❌ Nav atrasts administratora konts ar Telegram reģistrāciju\n";
        echo "Lūdzu reģistrējieties Telegram botā ar /start\n";
    }
    
} catch (Exception $e) {
    echo "❌ Kļūda testējot: " . $e->getMessage() . "\n";
}

echo "\n=== TESTS PABEIGTS ===\n";
?>
