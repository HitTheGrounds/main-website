<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'guest.company' => \App\Http\Middleware\EnsureCompanyGuest::class,
            'auth.company' => \App\Http\Middleware\EnsureCompanyAuthenticated::class,
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'company' => \App\Http\Middleware\EnsureUserIsCompany::class,
        ]);

        // Add security headers to all web requests
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
