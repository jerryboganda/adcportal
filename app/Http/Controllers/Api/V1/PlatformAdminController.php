<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ApiShape;
use App\Models\Business;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Platform administration (super admin only): tenant directory, subscription
 * lifecycle and plan catalog. Activation is manual/offline in v1 — no payment
 * gateway credentials exist, so nothing pretends to charge cards.
 */
class PlatformAdminController extends BaseApiController
{
    private function denyUnlessSuperAdmin(): void
    {
        if (auth()->user()?->type !== 'super_admin') {
            abort(403, 'Platform administration is restricted.');
        }
    }

    public function tenants(): JsonResponse
    {
        $this->denyUnlessSuperAdmin();

        $tenants = Business::with('plan')->withCount(['users', 'appointments'])->orderByDesc('id')->get();

        return $this->ok(['tenants' => $tenants->map(fn ($t) => ApiShape::tenant($t))->all()]);
    }

    public function updateTenant(Request $request, Business $tenant): JsonResponse
    {
        $this->denyUnlessSuperAdmin();

        $validated = $request->validate([
            'subscriptionStatus' => ['sometimes', Rule::in(['trialing', 'active', 'suspended', 'expired'])],
            'planId' => ['sometimes', 'nullable', 'integer', 'exists:plans,id'],
            'isActive' => ['sometimes', 'boolean'],
            'subscriptionEndsAt' => ['sometimes', 'nullable', 'date'],
        ]);

        $updates = collect($validated)->mapWithKeys(fn ($v, $k) => [\Str::snake($k) => $v])->all();
        $tenant->update($updates);

        $this->audit('tenant_updated', $tenant, [
            'summary' => "Tenant {$tenant->name} updated: ".implode(', ', array_keys($updates)),
        ]);

        return $this->ok(['tenant' => ApiShape::tenant($tenant->fresh('plan'))]);
    }

    public function plans(): JsonResponse
    {
        $plans = Plan::where('is_active', true)->orderBy('price_monthly')->get();

        return $this->ok(['plans' => $plans->map(fn ($p) => ApiShape::plan($p))->all()]);
    }

    public function platformStats(): JsonResponse
    {
        $this->denyUnlessSuperAdmin();

        return $this->ok([
            'stats' => [
                'tenants' => Business::count(),
                'active' => Business::where('subscription_status', 'active')->count(),
                'trialing' => Business::where('subscription_status', 'trialing')->count(),
                'suspended' => Business::where('subscription_status', 'suspended')->count(),
                'studies' => \App\Models\Appointment::count(),
                'users' => \App\Models\User::where('type', '!=', 'customer')->count(),
            ],
        ]);
    }
}
