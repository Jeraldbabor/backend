<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php', // Register API routes (/api/*)
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Using token-based auth (Bearer tokens), not cookie-based SPA auth
        // so statefulApi() is not needed

        // Register custom middleware aliases
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'kiosk' => \App\Http\Middleware\KioskApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
