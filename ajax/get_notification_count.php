<?php
require_once '../config.php';

// Pārbaudīt autentifikāciju
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$currentUser = getCurrentUser();

try {
    $count = getUnreadNotificationCount($currentUser['id']);
    
    // Atgriezt JSON atbildi
    header('Content-Type: application/json');
    echo json_encode([
        'count' => $count,
        'success' => true
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'success' => false
    ]);
}
?>