<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks the legacy (unversioned) /api/* surface as deprecated.
 * Clients are pointed at /api/v1. Contract keeps working unchanged.
 */
class DeprecateLegacyApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is('api/v1') && ! $request->is('api/v1/*')) {
            $response->headers->set('Deprecation', 'true');
            $response->headers->set('Sunset', 'Wed, 31 Dec 2025 23:59:59 GMT');
            $response->headers->set('Link', '<'.url('/api/v1').'>; rel="successor-version"');
        }

        return $response;
    }
}
