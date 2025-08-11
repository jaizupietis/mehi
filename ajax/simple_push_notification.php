
<?php
require_once '../config.php';

// Vienkārša push paziņojumu sistēma
header('Content-Type: application/json');

// Pārbaudīt autentifikāciju
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$currentUser = getCurrentUser();

try {
    // Iegūt jaunos paziņojumus (pēdējās 2 minūtes visām lomām)
    $stmt = $pdo->prepare("
        SELECT id, virsraksts, zinojums, saistitas_tips, saistitas_id, 
               DATE_FORMAT(izveidots, '%Y-%m-%d %H:%i:%s') as izveidots
        FROM pazinojumi 
        WHERE lietotaja_id = ? 
        AND skatīts = 0 
        AND izveidots > DATE_SUB(NOW(), INTERVAL 2 MINUTE)
        ORDER BY izveidots DESC 
        LIMIT 10
    ");
    $stmt->execute([$currentUser['id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($notifications)) {
        echo json_encode(['hasNew' => false, 'count' => 0]);
        exit;
    }

    // Sagatavot push paziņojuma datus visām lomām
    $pushData = [];
    foreach ($notifications as $notification) {
        // Noteikt URL atkarībā no lietotāja lomas un paziņojuma tipa
        $url = '/mehi/notifications.php';
        if ($notification['saistitas_tips'] === 'Uzdevums') {
            if ($currentUser['loma'] === 'Mehāniķis') {
                $url = '/mehi/my_tasks.php?highlight=' . $notification['saistitas_id'];
            } else {
                $url = '/mehi/tasks.php?highlight=' . $notification['saistitas_id'];
            }
        } elseif ($notification['saistitas_tips'] === 'Problēma') {
            if ($currentUser['loma'] === 'Operators') {
                $url = '/mehi/my_problems.php?highlight=' . $notification['saistitas_id'];
            } else {
                $url = '/mehi/problems.php?highlight=' . $notification['saistitas_id'];
            }
        }

        $pushData[] = [
            'title' => '🔔 ' . mb_substr($notification['virsraksts'], 0, 40) . (mb_strlen($notification['virsraksts']) > 40 ? '...' : ''),
            'body' => mb_substr($notification['zinojums'], 0, 100) . (mb_strlen($notification['zinojums']) > 100 ? '...' : ''),
            'icon' => '/mehi/assets/images/icon-192x192.png',
            'badge' => '/mehi/assets/images/icon-72x72.png',
            'data' => [
                'url' => $url,
                'notificationId' => $notification['id'],
                'timestamp' => strtotime($notification['izveidots']),
                'userRole' => $currentUser['loma']
            ],
            'tag' => 'avoti-notification-' . $notification['id'],
            'requireInteraction' => true,
            'vibrate' => [200, 100, 200, 100, 200],
            'actions' => [
                [
                    'action' => 'open',
                    'title' => 'Atvērt'
                ],
                [
                    'action' => 'dismiss',
                    'title' => 'Aizvērt'
                ]
            ]
        ];
    }

    echo json_encode([
        'hasNew' => true,
        'count' => count($notifications),
        'notifications' => $pushData
    ]);

} catch (Exception $e) {
    error_log("Push notification error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'hasNew' => false]);
}
?>
