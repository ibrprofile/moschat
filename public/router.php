<?php

// Dev router for PHP built-in server.
// Serves real files/dirs directly; routes everything else through index.php.

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // serve the file as-is
}

require __DIR__ . '/index.php';
