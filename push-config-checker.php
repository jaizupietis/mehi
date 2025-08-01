<?php
/**
 * Vienkārša Push Notifications konfigurācijas pārbaude
 */

require_once 'config.php';

// Pārbaudīt atļaujas
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

$results = [];

// 1. VAPID atslēgu pārbaude
if (defined('VAPID_PUBLIC_KEY') && VAPID_PUBLIC_KEY !== 'YOUR_VAPID_PUBLIC_KEY_HERE') {
    $results[] = ['type' => 'success', 'message' => '✅ VAPID_PUBLIC_KEY ir konfigurēta'];
} else {
    $results[] = ['type' => 'error', 'message' => '❌ VAPID_PUBLIC_KEY nav konfigurēta'];
}

if (defined('VAPID_PRIVATE_KEY') && VAPID_PRIVATE_KEY !== 'YOUR_VAPID_PRIVATE_KEY_HERE') {
    $results[] = ['type' => 'success', 'message' => '✅ VAPID_PRIVATE_KEY ir konfigurēta'];
} else {
    $results[] = ['type' => 'error', 'message' => '❌ VAPID_PRIVATE_KEY nav konfigurēta'];
}

// 2. Failu pārbaude
$required_files = [
    'manifest.json' => 'PWA manifest',
    'service-worker.js' => 'Service Worker',
    'assets/js/push-notifications.js' => 'Push Notifications JavaScript',
    'includes/push_notifications.php' => 'Push Notifications PHP klase'
];

foreach ($required_files as $file => $description) {
    if (file_exists($file)) {
        $results[] = ['type' => 'success', 'message' => "✅ $description ($file) eksistē"];
    } else {
        $results[] = ['type' => 'error', 'message' => "❌ $description ($file) neeksistē"];
    }
}

// 3. Push Notification Manager inicializācijas pārbaude
try {
    // Mēģināt inicializēt
    $testManager = getPushNotificationManager();
    
    if ($testManager !== null) {
        $results[] = ['type' => 'success', 'message' => '✅ Push Notification Manager veiksmīgi inicializēts'];
        
        // Pārbaudīt VAPID atslēgu pieejamību
        $vapidKey = $testManager->getVapidPublicKey();
        if ($vapidKey) {
            $results[] = ['type' => 'success', 'message' => '✅ VAPID public key ir pieejama Manager objektā'];
        } else {
            $results[] = ['type' => 'warning', 'message' => '⚠️ VAPID public key nav pieejama Manager objektā'];
        }
        
    } else {
        $results[] = ['type' => 'error', 'message' => '❌ Push Notification Manager nav inicializēts'];
    }
} catch (Exception $e) {
    $results[] = ['type' => 'error', 'message' => '❌ Push Notification Manager kļūda: ' . $e->getMessage()];
}

// 4. Datubāzes tabulu pārbaude
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'push_subscriptions'");
    if ($stmt->rowCount() > 0) {
        $results[] = ['type' => 'success', 'message' => '✅ Push subscriptions tabula eksistē'];
    } else {
        $results[] = ['type' => 'warning', 'message' => '⚠️ Push subscriptions tabula neeksistē (tiks izveidota automātiski)'];
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'notification_tracking'");
    if ($stmt->rowCount() > 0) {
        $results[] = ['type' => 'success', 'message' => '✅ Notification tracking tabula eksistē'];
    } else {
        $results[] = ['type' => 'warning', 'message' => '⚠️ Notification tracking tabula neeksistē (tiks izveidota automātiski)'];
    }
} catch (PDOException $e) {
    $results[] = ['type' => 'error', 'message' => '❌ Datubāzes kļūda: ' . $e->getMessage()];
}

// 5. SSL pārbaude
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $results[] = ['type' => 'success', 'message' => '✅ Vietne izmanto HTTPS'];
} else {
    $results[] = ['type' => 'warning', 'message' => '⚠️ Vietne neizmanto HTTPS - push notifications var nedarboties'];
}

?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Push Notifications Konfigurācijas Pārbaude</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .result { padding: 10px; margin: 5px 0; border-radius: 4px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .actions { margin: 20px 0; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        button:hover { background: #005a87; }
        .test-button { background: #28a745; }
        .test-button:hover { background: #218838; }
    </style>
</head>
<body>
    <h1>🔔 Push Notifications Konfigurācijas Pārbaude</h1>
    
    <div class="actions">
        <button onclick="location.reload()">🔄 Atjaunot pārbaudi</button>
        <button onclick="testNotifications()" class="test-button">🧪 Testēt paziņojumus</button>
        <a href="index.php"><button>🏠 Atgriezties</button></a>
    </div>
    
    <?php foreach ($results as $result): ?>
        <div class="result <?php echo $result['type']; ?>"><?php echo htmlspecialchars($result['message']); ?></div>
    <?php endforeach; ?>
    
    <?php
    $hasErrors = false;
    foreach ($results as $result) {
        if ($result['type'] === 'error') {
            $hasErrors = true;
            break;
        }
    }
    ?>
    
    <?php if (!$hasErrors): ?>
        <div class="result success">
            <strong>🎉 Konfigurācija ir gatava!</strong>
            <p>Varat sākt izmantot push notifications sistēmu.</p>
        </div>
        
        <div class="actions">
            <h3>Nākamie soļi:</h3>
            <ol>
                <li>Atveriet galveno aplikāciju un atļaujiet paziņojumus</li>
                <li>Izveidojiet jaunu uzdevumu</li>
                <li>Pārbaudiet vai push notification tiek saņemts</li>
            </ol>
        </div>
    <?php else: ?>
        <div class="result error">
            <strong>❌ Konfigurācija nav pabeigta!</strong>
            <p>Lūdzu novērsiet augstāk norādītās kļūdas.</p>
        </div>
    <?php endif; ?>
    
    <script>
        async function testNotifications() {
            try {
                const response = await fetch('/ajax/send_test_notification.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    credentials: 'same-origin',
                    body: JSON.stringify({test: true})
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✅ Testa paziņojums nosūtīts veiksmīgi!');
                } else {
                    alert('❌ Kļūda: ' + (data.error || 'Nezināma kļūda'));
                }
            } catch (error) {
                alert('❌ Tīkla kļūda: ' + error.message);
            }
        }
    </script>
</body>
</html>