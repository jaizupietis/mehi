<?php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed', 'success' => false]);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['trackingId'], $input['action'])) {
        echo json_encode(['error' => 'Invalid input', 'success' => false]);
        exit();
    }
    
    $trackingId = sanitizeInput($input['trackingId']);
    $action = sanitizeInput($input['action']);
    
    // Validēt action
    if (!in_array($action, ['delivered', 'clicked', 'failed'])) {
        echo json_encode(['error' => 'Invalid action', 'success' => false]);
        exit();
    }
    
    global $pushNotificationManager;
    
    $result = $pushNotificationManager->updateNotificationStatus($trackingId, $action);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Status updated']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update status']);
    }
    
} catch (Exception $e) {
    error_log('Notification tracking error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>