
<?php
// Simple server test
echo "<!DOCTYPE html>";
echo "<html><head><title>Server Test</title></head><body>";
echo "<h1>PHP Server Test</h1>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Test database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=mehu_uzd;charset=utf8mb4", "tasks", "Astalavista1920");
    echo "<p style='color: green;'>Database connection: SUCCESS</p>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database connection: FAILED - " . $e->getMessage() . "</p>";
}

echo "<p>Server is working correctly!</p>";
echo "</body></html>";
?>
