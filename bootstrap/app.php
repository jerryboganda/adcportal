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
        // This is an API-only application: there is no `login` web route. The
        // framework's default guest redirect is `fn () => route('login')`, which
        // is invoked from inside the `auth` middleware (Authenticate::redirectTo),
        // so it throws RouteNotFoundException *before* the exception handler can
        // turn an AuthenticationException into a 401. The result was a 500
        // "Route [login] not defined." for any unauthenticated API call that did
        // not send `Accept: application/json` — masking "you are not signed in"
        // as a server fault, polluting monitoring, and leaking a stack trace in
        // debug mode. Guests are therefore left without a redirect target, and
        // the exception handler below renders a clean 401.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'tenant.active' => EnsureTenantActive::class,
            'platform' => \App\Http\Middleware\EnsurePlatformAccess::class,
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
        // Pairs with redirectGuestsTo(null) above: with no redirect target, an
        // unauthenticated API call is answered here. Rendering JSON for the whole
        // /api/* prefix (not just when the client asks for it) keeps that answer a
        // 401 for every client — a browser hitting the URL directly, a monitoring
        // probe, or an integration that omits the Accept header.
        $exceptions->shouldRenderJsonWhen(
            fn ($request, $e) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
