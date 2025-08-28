
<?php
require_once 'config.php';

echo "=== TELEGRAM USERNAME PĀRBAUDE ===\n\n";

// Iegūt visus mehāniķus
try {
    $stmt = $pdo->prepare("
        SELECT l.id, l.lietotajvards, l.telegram_username, 
               CONCAT(l.vards, ' ', l.uzvards) as pilns_vards,
               tu.telegram_chat_id, tu.telegram_username as registered_telegram_username
        FROM lietotaji l
        LEFT JOIN telegram_users tu ON l.id = tu.lietotaja_id AND tu.is_active = TRUE
        WHERE l.loma = 'Mehāniķis' AND l.statuss = 'Aktīvs'
        ORDER BY l.uzvards, l.vards
    ");
    $stmt->execute();
    $mechanics = $stmt->fetchAll();

    echo "MEHĀNIĶU TELEGRAM STATUSS:\n";
    echo str_repeat("=", 60) . "\n";
    
    foreach ($mechanics as $mechanic) {
        echo "\n👤 {$mechanic['pilns_vards']}\n";
        echo "   Sistēmas lietotājvārds: {$mechanic['lietotajvards']}\n";
        
        if ($mechanic['telegram_username']) {
            echo "   Telegram lietotājvārds (sistēmā): @{$mechanic['telegram_username']}\n";
        } else {
            echo "   ❌ Nav iestatīts Telegram lietotājvārds sistēmā\n";
        }
        
        if ($mechanic['telegram_chat_id']) {
            echo "   ✅ Reģistrēts Telegram: @{$mechanic['registered_telegram_username']}\n";
            echo "   Chat ID: {$mechanic['telegram_chat_id']}\n";
        } else {
            echo "   ❌ Nav reģistrēts Telegram botā\n";
        }
        
        // Ieteikumi
        if (!$mechanic['telegram_username']) {
            echo "   💡 DARBĪBA: Iestatiet Telegram lietotājvārdu sistēmā\n";
        } elseif (!$mechanic['telegram_chat_id']) {
            echo "   💡 DARBĪBA: Mehāniķim jāreģistrējas botā ar @{$mechanic['telegram_username']}\n";
        } else {
            echo "   ✅ GATAVS: Var saņemt Telegram paziņojumus\n";
        }
        
        echo "   " . str_repeat("-", 50) . "\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "KOPSAVILKUMS:\n";
    echo "1. ✅ Sistēma tagad meklē tikai pēc telegram_username kolonnas\n";
    echo "2. 📋 Katram mehāniķim jāiestatīts Telegram lietotājvārds sistēmā\n";
    echo "3. 🔗 Telegram @username jāsakrīt ar iestatīto telegram_username\n\n";
    
    echo "NEPIECIEŠAMĀS DARBĪBAS:\n";
    echo "A) ✅ Sistēma jau modificēta - izmanto telegram_username kolonnu\n";
    echo "B) 📝 Administratoram jāiestatīt Telegram lietotājvārdi mehāniķiem\n";
    echo "C) 📱 Mehāniķiem jāreģistrējas botā ar pareizo @username\n\n";

} catch (Exception $e) {
    echo "❌ Kļūda: " . $e->getMessage() . "\n";
}
?>
