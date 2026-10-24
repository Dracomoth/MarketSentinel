<?php
/**
 * Local Development Router for PHP Built-in Web Server
 * 
 * Usage:
 *   php -S localhost:8000 router.php
 * 
 * This script simulates Apache's .htaccess behavior locally:
 * 1. Blocks direct access to backend directories (source, config, database, hidden files).
 * 2. Directly serves static files (assets, images, etc.).
 * 3. Dispatches all other routes to index.php for authentication and routing.
 */

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$decodedUri = urldecode($requestUri);
$targetFile = __DIR__ . $decodedUri;

// 1. Block direct access to sensitive directories and hidden files (matches .htaccess rules)
if (preg_match('#^/(source|config|database|\.git|\.env|\.gitignore)#i', $decodedUri)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "403 Forbidden: Direct access to this resource is prohibited.\n";
    exit;
}

// 2. If the request matches an existing file (CSS, JS, images, etc.), let PHP serve it directly
if ($decodedUri !== '/' && file_exists($targetFile) && !is_dir($targetFile)) {
    return false; // Built-in web server serves the static file as-is
}

// 3. Forward all application requests to index.php (Front Controller)
require_once __DIR__ . '/index.php';
