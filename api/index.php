<?php

/**
 * Laravel entry point for Vercel PHP runtime (vercel-php@0.7.2)
 * All HTTP requests are routed here via vercel.json rewrites.
 *
 * Vercel functions run in a read-only filesystem except for /tmp.
 * We redirect writable runtime paths to /tmp before bootstrapping.
 */

define('LARAVEL_START', microtime(true));

// ── Vercel: create writable runtime dirs in /tmp ──────────────────────────
foreach ([
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/views',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/logs',
    '/tmp/storage/app',
    '/tmp/bootstrap/cache',
] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// ── Composer autoloader ───────────────────────────────────────────────────
require __DIR__ . '/../vendor/autoload.php';

// ── Bootstrap Laravel ─────────────────────────────────────────────────────
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Point writable runtime storage to /tmp (Vercel's only writable dir)
$app->useStoragePath('/tmp/storage');

// ── Handle the request ────────────────────────────────────────────────────
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$response->send();

$kernel->terminate($request, $response);
