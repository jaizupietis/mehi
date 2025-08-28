
<?php
require_once 'config.php';

echo "=== TELEGRAM BOT INFORMĀCIJA ===\n";

if (!defined('TELEGRAM_BOT_TOKEN') || empty(TELEGRAM_BOT_TOKEN)) {
    echo "❌ Bot token nav iestatīts\n";
    exit;
}

try {
    $botToken = TELEGRAM_BOT_TOKEN;
    $url = "https://api.telegram.org/bot{$botToken}/getMe";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        
        if ($data && $data['ok']) {
            $bot = $data['result'];
            
            echo "✅ Bot ir aktīvs!\n\n";
            echo "Bot ID: {$bot['id']}\n";
            echo "Bot Username: @{$bot['username']}\n";
            echo "Bot Name: {$bot['first_name']}\n";
            echo "Can Join Groups: " . ($bot['can_join_groups'] ? 'Jā' : 'Nē') . "\n";
            echo "Can Read Messages: " . ($bot['can_read_all_group_messages'] ? 'Jā' : 'Nē') . "\n";
            echo "Supports Inline: " . ($bot['supports_inline_queries'] ? 'Jā' : 'Nē') . "\n\n";
            
            echo "🔗 Bot saite: https://t.me/{$bot['username']}\n\n";
            
            echo "=== INSTRUKCIJAS MEHĀNIĶIEM ===\n";
            echo "1. Atveriet šo saiti: https://t.me/{$bot['username']}\n";
            echo "2. Nosūtiet /start\n";
            echo "3. Ja nedarbojas, pārbaudiet vai jūsu @username sakrīt ar sistēmas username\n\n";
            
        } else {
            echo "❌ Bot atbilde nav derīga\n";
        }
    } else {
        echo "❌ Nevar sasniegt botu. HTTP kods: $httpCode\n";
    }
    
} catch (Exception $e) {
    echo "❌ Kļūda: " . $e->getMessage() . "\n";
}

echo "=== PAŠREIZĒJAIS STATUSS ===\n";

try {
    // Pārbaudīt cik mehāniķi ir reģistrēti
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as kopejie,
               SUM(CASE WHEN tu.telegram_chat_id IS NOT NULL THEN 1 ELSE 0 END) as registreti
        FROM lietotaji l
        LEFT JOIN telegram_users tu ON l.id = tu.lietotaja_id AND tu.is_active = TRUE
        WHERE l.loma = 'Mehāniķis' AND l.statuss = 'Aktīvs'
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
    echo "Kopējie aktīvie mehāniķi: {$stats['kopejie']}\n";
    echo "Reģistrēti Telegram: {$stats['registreti']}\n";
    echo "Nav reģistrēti: " . ($stats['kopejie'] - $stats['registreti']) . "\n";
    
} catch (Exception $e) {
    echo "❌ Kļūda iegūstot statistiku: " . $e->getMessage() . "\n";
}

?>
