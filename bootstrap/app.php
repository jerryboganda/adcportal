<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\SetLang;
use App\Http\Middleware\AllowIframeEmbedding;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'APILog' => \App\Http\Middleware\APILog::class,
        ]);

        // Append middleware to the 'web' group
        $middleware->appendToGroup('web', SetLang::class);
        $middleware->appendToGroup('web', AllowIframeEmbedding::class);
        // Exclude specific routes from CSRF protection
        $middleware->validateCsrfTokens(
            except: ['booking',
                    'appointment-duration',
                    'check-user-data',
                    ] // Add your routes here
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
