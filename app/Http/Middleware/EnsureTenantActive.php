<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SaaS subscription gate: suspended or expired tenants may authenticate and
 * reach billing/platform surfaces but cannot operate the clinical product.
 */
class EnsureTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Platform admins can always operate.
        if ($user->type === 'super_admin') {
            return $next($request);
        }

        $business = \App\Models\Business::find(getActiveBusiness($user->id));

        if ($business && ! $business->isSubscribable()) {
            return response()->json([
                'message' => match ($business->subscription_status) {
                    'suspended' => 'This clinic account is suspended. Contact the platform administrator.',
                    'expired' => 'This clinic subscription has expired. Renew to continue.',
                    default => 'This clinic trial has ended. Choose a plan to continue.',
                },
                'subscriptionStatus' => $business->subscription_status,
            ], 402);
        }

        return $next($request);
    }
}
