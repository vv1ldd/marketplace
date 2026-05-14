<?php

// Silence PHP 8.5 deprecations locally to prevent header issues
if (file_exists(__DIR__ . '/../.env')) {
    $env = file_get_contents(__DIR__ . '/../.env');
    if (strpos($env, 'APP_ENV=local') !== false) {
        ini_set('display_errors', '0');
        error_reporting(E_ALL & ~E_DEPRECATED);
    }
}

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Suppress annoying notices during local development (like Broken Pipe)
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
