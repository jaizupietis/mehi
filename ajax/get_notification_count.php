<?php
require_once '../config.php';

// Set proper headers for AJAX response with caching
header('Content-Type: application/json');
header('Cache-Control: public, max-age=10'); // Cache for 10 seconds

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$currentUser = getCurrentUser();
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['error' => 'User not found']);
    exit;
}

try {
    // Optimized query with limit for faster execution
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pazinojumi WHERE lietotaja_id = ? AND skatīts = 0 LIMIT 100");
    $stmt->execute([$currentUser['id']]);
    $count = $stmt->fetchColumn();

    echo json_encode(['count' => (int)$count]);
} catch (Exception $e) {
    error_log("Error getting notification count: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'count' => 0]);
}
?>