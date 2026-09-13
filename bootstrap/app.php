<?php

use App\Http\Middleware\AllowIframeEmbedding;
use App\Http\Middleware\EnsureTenantActive;
use App\Http\Middleware\SetLang;
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
        $middleware->alias([
            'APILog' => \App\Http\Middleware\APILog::class,
            'tenant.active' => EnsureTenantActive::class,
        ]);

        // Append middleware to the 'web' group
        $middleware->appendToGroup('web', SetLang::class);
        $middleware->appendToGroup('web', AllowIframeEmbedding::class);

        // SPA cookie sessions: stateful Sanctum requests from the configured
        // frontend domains get session auth + CSRF on the api group.
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Exclude the legacy public booking paths from CSRF (kept for compat).
        $middleware->validateCsrfTokens(except: [
            'booking',
            'appointment-duration',
            'check-user-data',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
