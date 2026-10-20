<?php

/**
 * Vercel serverless entry point — forwards all HTTP traffic to Laravel's front controller.
 */
chdir(dirname(__DIR__).'/public');

require dirname(__DIR__).'/public/index.php';
