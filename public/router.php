<?php

if (preg_match('/\.(?:jpg|jpeg|gif|png|js|css|svg|txt|pdf|ico|woff|woff2)$/i', $_SERVER["REQUEST_URI"])) {
    return false;
}

if ($_SERVER["REQUEST_URI"] === '/') {
    require __DIR__ . '/index.php';
    return;
}

if (file_exists($document_root = $_SERVER["DOCUMENT_ROOT"]) && is_dir($document_root . $_SERVER["REQUEST_URI"])) {
    if (substr($_SERVER["REQUEST_URI"], -1) !== '/') {
        header("Location: " . $_SERVER["REQUEST_URI"] . "/");
        return;
    }
}

if (strpos($_SERVER["REQUEST_URI"], '/.') === false) {
    require __DIR__ . '/index.php';
} else {
    return false;
}
