<?php

namespace App\Http\Middleware;

use App\Services\PlatformAuthorizer;
use App\Services\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Control-plane route guard. Requires a platform identity (super_admin or a
 * platform_admin with a mapped role) and — when a capability parameter is
 * given — that exact platform capability, e.g. middleware('platform:plans.manage').
 *
 * 2FA enforcement is per-SESSION: an enrolled platform user whose session has
 * not completed the TOTP challenge is held at the control-plane door with a
 * typed 403 (two_factor_required). Unenrolled users are unaffected.
 */
class EnsurePlatformAccess
{
    public function handle(Request $request, Closure $next, ?string $capability = null): Response
    {
        $user = $request->user();

        if (! $user || ! PlatformAuthorizer::isPlatformAdmin($user)) {
            abort(403, 'Platform administration is restricted.');
        }

        if (TwoFactorService::hasEnabledTwoFactor($user) && ! TwoFactorService::isUnlocked($request)) {
            abort(response()->json([
                'message' => 'Two-factor authentication is required for platform access.',
                'error' => 'two_factor_required',
            ], 403));
        }

        if ($capability !== null && $capability !== '') {
            PlatformAuthorizer::denyUnless($user, $capability);
        }

        return $next($request);
    }
}
