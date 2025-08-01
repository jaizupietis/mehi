<?php
// Izveidojiet šo failu kā push_notifications_check.php projekta saknē
// Palaidiet to pārlūkā, lai pārbaudītu konfigurāciju

require_once 'config.php';

// Pārbaudīt atļaujas
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

$errors = [];
$warnings = [];
$success = [];

// 1. Pārbaudīt VAPID atslēgas
if (!defined('VAPID_PUBLIC_KEY') || VAPID_PUBLIC_KEY === 'YOUR_VAPID_PUBLIC_KEY_HERE') {
    $errors[] = 'VAPID_PUBLIC_KEY nav konfigurēta config.php failā';
} else {
    $success[] = 'VAPID_PUBLIC_KEY ir konfigurēta';
}

if (!defined('VAPID_PRIVATE_KEY') || VAPID_PRIVATE_KEY === 'YOUR_VAPID_PRIVATE_KEY_HERE') {
    $errors[] = 'VAPID_PRIVATE_KEY nav konfigurēta config.php failā';
} else {
    $success[] = 'VAPID_PRIVATE_KEY ir konfigurēta';
}

// 2. Pārbaudīt failu esamību
$required_files = [
    'manifest.json' => 'PWA manifest fails',
    'service-worker.js' => 'Service Worker fails',
    'assets/js/push-notifications.js' => 'Push Notifications JavaScript',
    'includes/push_notifications.php' => 'Push Notifications PHP klase',
    'ajax/get_vapid_key.php' => 'VAPID atslēgas AJAX endpoint',
    'ajax/save_push_subscription.php' => 'Push subscription AJAX endpoint',
    'ajax/send_test_notification.php' => 'Testa paziņojuma AJAX endpoint',
    'ajax/track_notification.php' => 'Paziņojuma tracking AJAX endpoint'
];

foreach ($required_files as $file => $description) {
    if (file_exists($file)) {
        $success[] = "$description ($file) eksistē";
    } else {
        $errors[] = "$description ($file) neeksistē";
    }
}

// 3. Pārbaudīt ikonu failus
$icon_sizes = [72, 96, 128, 144, 152, 192, 384, 512];
$missing_icons = [];

foreach ($icon_sizes as $size) {
    $icon_path = "assets/images/icon-{$size}x{$size}.png";
    if (!file_exists($icon_path)) {
        $missing_icons[] = $icon_path;
    }
}

if (!empty($missing_icons)) {
    $warnings[] = 'Trūkst PWA ikonu: ' . implode(', ', $missing_icons);
} else {
    $success[] = 'Visas PWA ikonas ir pieejamas';
}

// 4. Pārbaudīt datubāzes tabulas
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'push_subscriptions'");
    if ($stmt->rowCount() > 0) {
        $success[] = 'Push subscriptions tabula eksistē';
    } else {
        $warnings[] = 'Push subscriptions tabula neeksistē (tiks izveidota automātiski)';
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'notification_tracking'");
    if ($stmt->rowCount() > 0) {
        $success[] = 'Notification tracking tabula eksistē';
    } else {
        $warnings[] = 'Notification tracking tabula neeksistē (tiks izveidota automātiski)';
    }
} catch (PDOException $e) {
    $errors[] = 'Datubāzes kļūda: ' . $e->getMessage();
}

// 5. Pārbaudīt push notification manager
if (isset($pushNotificationManager)) {
    $success[] = 'Push Notification Manager ir inicializēts';
} else {
    $errors[] = 'Push Notification Manager nav inicializēts';
}

// 6. SSL pārbaude
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    $warnings[] = 'Vietne neizmanto HTTPS. Push notifications var nedarboties HTTP protokolā.';
} else {
    $success[] = 'Vietne izmanto HTTPS';
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
        .status { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .test-section { margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        button:hover { background: #005a87; }
        .test-button { background: #28a745; }
        .test-button:hover { background: #218838; }
    </style>
</head>
<body>
    <h1>Push Notifications Konfigurācijas Pārbaude</h1>
    
    <h2>Konfigurācijas Statuss</h2>
    
    <?php if (!empty($errors)): ?>
        <h3>❌ Kļūdas (jānovērš):</h3>
        <?php foreach ($errors as $error): ?>
            <div class="status error"><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (!empty($warnings)): ?>
        <h3>⚠️ Brīdinājumi:</h3>
        <?php foreach ($warnings as $warning): ?>
            <div class="status warning"><?php echo htmlspecialchars($warning); ?></div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <h3>✅ Veiksmīgi konfigurēts:</h3>
        <?php foreach ($success as $item): ?>
            <div class="status success"><?php echo htmlspecialchars($item); ?></div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (empty($errors)): ?>
        <div class="test-section">
            <h3>Funkcionalitātes Tests</h3>
            <p>Konfigurācija izskatās pareiza! Tagad varat testēt push notifications:</p>
            
            <button onclick="testServiceWorker()" class="test-button">Testēt Service Worker</button>
            <button onclick="testNotificationPermission()" class="test-button">Testēt Paziņojumu Atļaujas</button>
            <button onclick="testPushSubscription()" class="test-button">Testēt Push Subscription</button>
            
            <div id="test-results" style="margin-top: 20px;"></div>
        </div>
        
        <div class="test-section">
            <h3>Nākamie Soļi</h3>
            <ol>
                <li>Atveriet galveno vietni un atļaujiet paziņojumus</li>
                <li>Izveidojiet jaunu uzdevumu vai problēmu</li>
                <li>Pārbaudiet vai push notification tiek saņemts</li>
                <li>Testējiet PWA instalāciju (Add to Home Screen)</li>
            </ol>
        </div>
    <?php else: ?>
        <div class="status error">
            <strong>❌ Konfigurācija nav pabeigta!</strong>
            <p>Lūdzu novērsiet augstāk norādītās kļūdas pirms turpināt.</p>
        </div>
    <?php endif; ?>

    <script>
        function showResult(message, type = 'info') {
            const results = document.getElementById('test-results');
            const div = document.createElement('div');
            div.className = `status ${type}`;
            div.textContent = message;
            results.appendChild(div, results.firstChild);
        }
        
        async function testServiceWorker() {
            if ('serviceWorker' in navigator) {
                try {
                    const registration = await navigator.serviceWorker.register('/service-worker.js');
                    showResult('✅ Service Worker reģistrēts veiksmīgi', 'success');
                } catch (error) {
                    showResult('❌ Service Worker reģistrācijas kļūda: ' + error.message, 'error');
                }
            } else {
                showResult('❌ Service Worker nav atbalstīts šajā pārlūkā', 'error');
            }
        }
        
        async function testNotificationPermission() {
            if ('Notification' in window) {
                const permission = await Notification.requestPermission();
                if (permission === 'granted') {
                    showResult('✅ Paziņojumu atļauja piešķirta', 'success');
                    new Notification('AVOTI TMS Tests', {
                        body: 'Paziņojumu sistēma darbojas!',
                        icon: '/assets/images/icon-192x192.png'
                    });
                } else {
                    showResult('❌ Paziņojumu atļauja nav piešķirta', 'error');
                }
            } else {
                showResult('❌ Paziņojumi nav atbalstīti šajā pārlūkā', 'error');
            }
        }
        
        async function testPushSubscription() {
            if ('serviceWorker' in navigator && 'PushManager' in window) {
                try {
                    const registration = await navigator.serviceWorker.ready;
                    const response = await fetch('/ajax/get_vapid_key.php');
                    const data = await response.json();
                    
                    if (data.success) {
                        showResult('✅ VAPID atslēga iegūta veiksmīgi', 'success');
                        
                        // Testēt subscription
                        const subscription = await registration.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: urlBase64ToUint8Array(data.publicKey)
                        });
                        
                        showResult('✅ Push subscription izveidots veiksmīgi', 'success');
                    } else {
                        showResult('❌ VAPID atslēgas kļūda: ' + data.error, 'error');
                    }
                } catch (error) {
                    showResult('❌ Push subscription kļūda: ' + error.message, 'error');
                }
            } else {
                showResult('❌ Push Messaging nav atbalstīts', 'error');
            }
        }
        
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }
    </script>
</body>
</html>