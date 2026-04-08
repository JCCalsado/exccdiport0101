<?php
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publicDir = __DIR__ . '/public';

// Serve real files from public directory
if ($requestUri !== '/' && file_exists($publicDir . $requestUri)) {
    return false;
}

// Route all requests through index.php
require $publicDir . '/index.php';
