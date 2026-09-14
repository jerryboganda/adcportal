<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The tenant-plane availability gate: when a tenant is not subscribable
 * (provisioning, suspended, expired, offboarding, terminated) its clinical
 * data stays intact but every tenant API request is refused with 402.
 * Platform staff bypass the gate — control-plane routes carry their own
 * platform guard instead.
 */
class EnsureTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Platform admins can always operate (control plane / support sessions).
        if ($user->isPlatformAdmin()) {
            return $next($request);
        }

        $business = Business::find(getActiveBusiness($user->id));

        if ($business && ! $business->isSubscribable()) {
            return response()->json([
                'message' => match ($business->subscription_status) {
                    'suspended' => 'This clinic account is suspended. Contact the platform administrator.',
                    'expired' => 'This clinic subscription has expired. Renew to continue.',
                    'provisioning' => 'This clinic is still being provisioned. Try again shortly.',
                    'offboarding' => 'This clinic account is being offboarded. Access is no longer available.',
                    'terminated' => 'This clinic account has been terminated.',
                    default => 'This clinic trial has ended. Choose a plan to continue.',
                },
                'subscriptionStatus' => $business->subscription_status,
            ], 402);
        }

        return $next($request);
    }
}
