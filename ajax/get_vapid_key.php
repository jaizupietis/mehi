<?php
require_once '../config.php';

// Pārbaudīt atļaujas
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'success' => false]);
    exit();
}

header('Content-Type: application/json');

try {
    if (defined('VAPID_PUBLIC_KEY') && VAPID_PUBLIC_KEY !== 'YOUR_VAPID_PUBLIC_KEY_HERE') {
        echo json_encode([
            'success' => true,
            'publicKey' => VAPID_PUBLIC_KEY
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'VAPID keys not configured'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error'
    ]);
}
?>