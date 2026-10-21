
<?php

error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', '0');

try {
    require __DIR__ . '/../public/index.php';
} catch (\Throwable $e) {
    error_log('LARAVEL_BOOTSTRAP_ERROR: ' . $e);
    http_response_code(500);
    echo 'Application startup error. Check Vercel runtime logs.';
}
