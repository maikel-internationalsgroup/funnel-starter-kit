<?php

// Router for PHP's built-in server: php -S localhost:8080 -t public tools/router.php
// Serves real files directly, sends everything else to the front controller.

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path !== '/' && is_file(__DIR__ . '/../public' . $path)) {
    return false;
}
require __DIR__ . '/../public/index.php';
