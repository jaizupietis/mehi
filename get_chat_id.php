
<?php
require_once 'config.php';

echo "=== INSTRUKCIJAS CHAT ID IEGŪŠANAI ===\n\n";

// Iegūt visus mehāniķus
try {
    $stmt = $pdo->prepare("
        SELECT l.id, l.lietotajvards, CONCAT(l.vards, ' ', l.uzvards) as pilns_vards,
               tu.telegram_chat_id, tu.telegram_username
        FROM lietotaji l
        LEFT JOIN telegram_users tu ON l.id = tu.lietotaja_id AND tu.is_active = TRUE
        WHERE l.loma = 'Mehāniķis' AND l.statuss = 'Aktīvs'
        ORDER BY l.uzvards, l.vards
    ");
    $stmt->execute();
    $mechanics = $stmt->fetchAll();

    echo "MEHĀNIĶU SARAKSTS:\n";
    echo str_repeat("=", 50) . "\n";
    
    foreach ($mechanics as $mechanic) {
        echo "\n👤 {$mechanic['pilns_vards']} (@{$mechanic['lietotajvards']})\n";
        
        if ($mechanic['telegram_chat_id']) {
            echo "   ✅ Jau reģistrēts Telegram: {$mechanic['telegram_username']}\n";
        } else {
            echo "   ❌ Nav reģistrēts Telegram\n";
            echo "   📋 INSTRUKCIJAS:\n";
            echo "      1. Atveriet Telegram\n";
            echo "      2. Meklējiet: @userinfobot\n";
            echo "      3. Nosūtiet /start\n";
            echo "      4. Nokopējiet savu Chat ID\n";
            echo "      5. Dodiet Chat ID administratoram\n";
            echo "      6. Pēc reģistrācijas meklējiet: @AVOTI_TMS_Bot\n";
            echo "      7. Nosūtiet /start botam\n";
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "ADMINISTRATORA INSTRUKCIJAS:\n";
    echo "1. Iegūstiet Chat ID no katras mehāniķa\n";
    echo "2. Atveriet manual_register_mechanics.php\n";
    echo "3. Nomainiet 'chat_id' => null ar īstajiem Chat ID\n";
    echo "4. Palaidiet skriptu\n\n";
    
    echo "ALTERNATĪVA - AUTOMĀTISKA REĢISTRĀCIJA:\n";
    echo "Katrs mehāniķis var:\n";
    echo "1. Atvērt: https://t.me/AVOTI_TMS_Bot\n";
    echo "2. Nosūtīt /start\n";
    echo "3. Sistēma automātiski reģistrēs, ja username sakrīt\n\n";
    
    echo "SVARĪGI:\n";
    echo "- Telegram username (@lietotājvārds) JĀSAKRĪT ar sistēmas username\n";
    echo "- Ja nav username, jāiestrādā Telegram profilā\n";
    echo "- Ja username nesakrīt, jāmaina sistēmā vai Telegram\n\n";

} catch (Exception $e) {
    echo "❌ Kļūda: " . $e->getMessage() . "\n";
}
?>
