
<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole(ROLE_MANAGER);

$pageTitle = 'Push Notification Tests';

if ($_POST['test_push'] ?? false) {
    $result = sendProblemPushNotification(999, 'Testa problēma - ' . date('H:i:s'));
    $message = $result ? 'Push notification nosūtīts!' : 'Push notification neizdevās nosūtīt';
}

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3>🔔 Push Notification Tests</h3>
    </div>
    <div class="card-body">
        <?php if (isset($message)): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <form method="post">
            <button type="submit" name="test_push" class="btn btn-primary">
                📱 Testēt Push Notification
            </button>
        </form>
        
        <hr>
        
        <h4>📊 Aktīvās Subscriptions</h4>
        <?php
        try {
            $stmt = $pdo->query("
                SELECT ps.*, l.vards, l.uzvards, l.loma
                FROM push_subscriptions ps
                JOIN lietotaji l ON ps.lietotaja_id = l.id
                WHERE ps.is_active = TRUE
                ORDER BY ps.created_at DESC
            ");
            $subscriptions = $stmt->fetchAll();
            
            if ($subscriptions): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Lietotājs</th>
                                <th>Loma</th>
                                <th>Endpoint</th>
                                <th>Izveidots</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subscriptions as $sub): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($sub['vards'] . ' ' . $sub['uzvards']); ?></td>
                                    <td><?php echo htmlspecialchars($sub['loma']); ?></td>
                                    <td><small><?php echo htmlspecialchars(substr($sub['endpoint'], 0, 50)) . '...'; ?></small></td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($sub['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">Nav aktīvo push subscriptions</div>
            <?php endif;
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">Kļūda: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>
        
        <hr>
        
        <h4>📋 Instrukcijas Android Chrome</h4>
        <ol>
            <li>Atveriet lapu Chrome pārlūkā Android</li>
            <li>Nospiediet "Atļaut" kad parādās paziņojumu lūgums</li>
            <li>Pārbaudiet vai Service Worker ir reģistrēts (Console)</li>
            <li>Nospiediet "Testēt Push Notification" pogu</li>
            <li>Paziņojumam vajadzētu parādīties arī ja lapa nav atvērta</li>
        </ol>
        
        <div class="alert alert-info">
            <strong>Debug info:</strong><br>
            <code>Service Worker: /mehi/assets/js/sw.js</code><br>
            <code>Push endpoint: /mehi/ajax/save_push_subscription.php</code>
        </div>
    </div>
</div>

<script>
// Papildu debug info
console.log('Push notification debug info:');
console.log('Service Worker supported:', 'serviceWorker' in navigator);
console.log('Push Manager supported:', 'PushManager' in window);
console.log('Notification permission:', Notification.permission);

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.ready.then(function(registration) {
        console.log('Service Worker ready:', registration);
        
        registration.pushManager.getSubscription().then(function(subscription) {
            console.log('Current subscription:', subscription);
        });
    });
}
</script>

<?php include 'includes/footer.php'; ?>
