<?php

/**
 * Laravel entry point for Vercel PHP runtime (vercel-php@0.7.2)
 * All HTTP requests are routed here via vercel.json
 */

define('LARAVEL_START', microtime(true));

// Composer autoloader
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Handle the incoming request
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$response->send();

$kernel->terminate($request, $response);
