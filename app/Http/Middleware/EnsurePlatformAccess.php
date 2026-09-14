<?php

namespace App\Http\Middleware;

use App\Services\PlatformAuthorizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Control-plane route guard. Requires a platform identity (super_admin or a
 * platform_admin with a mapped role) and — when a capability parameter is
 * given — that exact platform capability, e.g. middleware('platform:plans.manage').
 */
class EnsurePlatformAccess
{
    public function handle(Request $request, Closure $next, ?string $capability = null): Response
    {
        $user = $request->user();

        if (! $user || ! PlatformAuthorizer::isPlatformAdmin($user)) {
            abort(403, 'Platform administration is restricted.');
        }

        if ($capability !== null && $capability !== '') {
            PlatformAuthorizer::denyUnless($user, $capability);
        }

        return $next($request);
    }
}
