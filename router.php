
<?php
// Simple router for deployment
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Handle manifest.json
if ($path === '/manifest.json') {
    header('Content-Type: application/manifest+json');
    readfile(__DIR__ . '/manifest.json');
    exit;
}

// Handle service worker
if ($path === '/sw.js' || $path === '/assets/js/sw.js') {
    header('Content-Type: application/javascript');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    readfile(__DIR__ . '/assets/js/sw.js');
    exit;
}

// Handle static files
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico)$/', $path)) {
    $file = __DIR__ . $path;
    if (file_exists($file)) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $mimes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon'
        ];
        
        if (isset($mimes[$ext])) {
            header('Content-Type: ' . $mimes[$ext]);
            header('Cache-Control: public, max-age=31536000');
        }
        readfile($file);
        exit;
    }
}

// Handle PHP files
if (preg_match('/\.php$/', $path) || $path === '/') {
    $file = $path === '/' ? '/index.php' : $path;
    $file = __DIR__ . $file;
    
    if (file_exists($file)) {
        require $file;
    } else {
        http_response_code(404);
        require __DIR__ . '/error_404.php';
    }
} else {
    // Fallback to file system
    $file = __DIR__ . $path;
    if (file_exists($file) && is_file($file)) {
        readfile($file);
    } else {
        http_response_code(404);
        require __DIR__ . '/error_404.php';
    }
}
?>
