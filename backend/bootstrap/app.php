<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();

        // Ingestion is for third-party webhook senders — they can't carry a
        // CSRF token, and the web group would otherwise 419 every POST.
        $middleware->validateCsrfTokens(except: [
            'event/hooks/*',
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);

        // Keep the guest session id as a plaintext random token so the browser
        // extension can read it from the cookie jar (chrome.cookies) and
        // auto-discover the browser's guest URLs — no login, no pairing code.
        // It's an opaque 32-char secret; encryption added integrity but nothing
        // a DB lookup of a random value doesn't already provide.
        $middleware->encryptCookies(except: [
            'guest_session_id',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
