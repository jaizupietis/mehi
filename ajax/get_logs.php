
<?php
require_once '../config.php';

// Pārbaudīt atļaujas
requireRole(ROLE_ADMIN);

// Atgriezt tikai log saturu
header('Content-Type: text/plain; charset=utf-8');

$log_file = __DIR__ . '/../logs/cron_scheduler.log';

if (file_exists($log_file)) {
    $log_lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($log_lines) {
        // Atgriezt pēdējās 100 rindas
        $last_lines = array_slice($log_lines, -100);
        echo implode("\n", $last_lines);
    } else {
        echo "Log fails ir tukšs.";
    }
} else {
    echo "Log fails neeksistē vai nav lasāms.";
}
?>
