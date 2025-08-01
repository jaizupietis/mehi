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
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['subscription'], $input['action'])) {
        echo json_encode(['error' => 'Invalid input', 'success' => false]);
        exit();
    }
    
    $subscription = $input['subscription'];
    $action = $input['action'];
    
    // Validēt subscription formātu
    if (!isset($subscription['endpoint'], $subscription['keys']['p256dh'], $subscription['keys']['auth'])) {
        echo json_encode(['error' => 'Invalid subscription format', 'success' => false]);
        exit();
    }
    
    global $pushNotificationManager;
    
    if ($action === 'subscribe') {
        $result = $pushNotificationManager->saveSubscription($currentUser['id'], $subscription);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Subscription saved']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save subscription']);
        }
    } elseif ($action === 'unsubscribe') {
        $result = $pushNotificationManager->removeSubscription($currentUser['id'], $subscription);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Subscription removed']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to remove subscription']);
        }
    } else {
        echo json_encode(['error' => 'Invalid action', 'success' => false]);
    }
    
} catch (Exception $e) {
    error_log('Push subscription error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>