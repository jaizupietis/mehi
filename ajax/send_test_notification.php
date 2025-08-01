<?php
require_once '../config.php';

// Pārbaudīt atļaujas
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'success' => false]);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed', 'success' => false]);
    exit();
}

$currentUser = getCurrentUser();

try {
    global $pushNotificationManager;
    
    $title = 'Testa paziņojums';
    $body = 'Šis ir testa paziņojums no AVOTI TMS sistēmas - ' . date('H:i:s');
    
    $data = [
        'type' => 'test',
        'timestamp' => time(),
        'url' => '/notifications.php'
    ];
    
    $options = [
        'type' => 'test',
        'icon' => '/assets/images/icon-192x192.png',
        'badge' => '/assets/images/icon-192x192.png',
        'tag' => 'test-notification',
        'requireInteraction' => false,
        'actions' => [
            [
                'action' => 'open',
                'title' => 'Atvērt sistēmu'
            ],
            [
                'action' => 'dismiss',
                'title' => 'Aizvērt'
            ]
        ]
    ];
    
    $result = $pushNotificationManager->sendNotification($currentUser['id'], $title, $body, $data, $options);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Testa paziņojums nosūtīts!',
            'sent' => $result['sent'],
            'failed' => $result['failed']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Neizdevās nosūtīt testa paziņojumu: ' . implode(', ', $result['errors'])
        ]);
    }
    
} catch (Exception $e) {
    error_log('Test notification error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>