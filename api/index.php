<?php

/**
 * Vercel serverless entry point — forwards all HTTP traffic to Laravel's front controller.
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($uri === '/_health') {
    header('Content-Type: text/plain');
    echo 'ok';
    return;
}

chdir(dirname(__DIR__).'/public');

try {
    require dirname(__DIR__).'/public/index.php';
} catch (Throwable $exception) {
    header('Content-Type: text/plain; charset=UTF-8');
    http_response_code(500);
    echo $exception::class.': '.$exception->getMessage()."\n";
    echo $exception->getFile().':'.$exception->getLine()."\n";
}
