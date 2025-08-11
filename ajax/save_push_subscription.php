
<?php
require_once '../config.php';

header('Content-Type: application/json');

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

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['subscription'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing subscription data']);
    exit;
}

$subscription = $data['subscription'];

try {
    // Create table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lietotaja_id INT NOT NULL,
            endpoint TEXT NOT NULL,
            p256dh_key VARCHAR(255) NOT NULL,
            auth_key VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE,
            UNIQUE KEY unique_user_endpoint (lietotaja_id, endpoint(100))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Save or update subscription
    $stmt = $pdo->prepare("
        INSERT INTO push_subscriptions (lietotaja_id, endpoint, p256dh_key, auth_key)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            p256dh_key = VALUES(p256dh_key),
            auth_key = VALUES(auth_key),
            updated_at = CURRENT_TIMESTAMP,
            is_active = TRUE
    ");

    $result = $stmt->execute([
        $currentUser['id'],
        $subscription['endpoint'],
        $subscription['keys']['p256dh'],
        $subscription['keys']['auth']
    ]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Subscription saved']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save subscription']);
    }

} catch (Exception $e) {
    error_log("Error saving push subscription: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
