<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ApiShape;
use App\Models\AuditLog;
use App\Models\TenantMembership;
use App\Services\SupportSessionService;
use App\Services\TenantAuthorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Explicit tenant context: membership listing, tenant switching for
 * multi-tenant members, and break-glass tenant entry for platform support.
 */
class TenantContextController extends BaseApiController
{
    /** GET /api/v1/memberships — tenants the user may enter. */
    public function memberships(): JsonResponse
    {
        $user = auth()->user();

        if ($user->isPlatformAdmin()) {
            return $this->ok(['memberships' => []]);
        }

        $memberships = TenantMembership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->with(['business:id,name,subscription_status', 'business.branding:business_id,app_name'])
            ->orderByDesc('is_default')
            ->get()
            ->map(fn ($m) => [
                'businessId' => (int) $m->business_id,
                'businessName' => $m->business?->name ?? '',
                'businessBrandName' => $m->business?->branding?->app_name,
                'role' => $m->role,
                'isDefault' => (bool) $m->is_default,
                'subscriptionStatus' => $m->business?->subscription_status ?? 'unknown',
            ])->all();

        return $this->ok(['memberships' => $memberships]);
    }

    /** POST /api/v1/tenant/switch — move this session to a tenant of which the user is a member. */
    public function switchTenant(Request $request): JsonResponse
    {
        $user = auth()->user();
        abort_if($user->isPlatformAdmin(), 403, 'Platform staff use support sessions, not tenant switching.');

        $validated = $request->validate(['businessId' => ['required', 'integer']]);

        $membership = TenantMembership::query()
            ->where('user_id', $user->id)
            ->where('business_id', $validated['businessId'])
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            // Safe denial — do not reveal whether the tenant exists.
            abort(403, 'You are not a member of that clinic.');
        }

        $user->forceFill([
            'business_id' => $membership->business_id,
            'active_business' => $membership->business_id,
        ])->save();

        // Drop per-process memoization so every later request/worker job in
        // this process resolves the NEW tenant context from the database.
        TenantAuthorizer::flushAll();
        flush_active_business_cache();

        AuditLog::record('tenant_switched', $user, [
            'summary' => "Switched active clinic context to business #{$membership->business_id}.",
        ], $membership->business_id);

        return $this->ok(['user' => ApiShape::currentUser($user->fresh())]);
    }

    /** POST /api/v1/tenant/enter — platform staff enter a tenant under an active support session. */
    public function enter(Request $request): JsonResponse
    {
        $user = auth()->user();
        abort_unless($user->isPlatformAdmin(), 403, 'Support sessions are restricted to platform staff.');

        // Break-glass is the most sensitive action in the product: an enrolled
        // platform identity must have completed its 2FA challenge this session.
        if (\App\Services\TwoFactorService::hasEnabledTwoFactor($user) && ! \App\Services\TwoFactorService::isUnlocked($request)) {
            abort(response()->json([
                'message' => 'Two-factor authentication is required before entering a clinic.',
                'error' => 'two_factor_required',
            ], 403));
        }

        $validated = $request->validate(['businessId' => ['required', 'integer']]);

        SupportSessionService::enter($user, (int) $validated['businessId']);

        TenantAuthorizer::flushAll();
        flush_active_business_cache();

        return $this->ok(['user' => ApiShape::currentUser($user->fresh())]);
    }

    /** POST /api/v1/tenant/leave — drop the support-session tenant context. */
    public function leave(): JsonResponse
    {
        $user = auth()->user();
        abort_unless($user->isPlatformAdmin(), 403, 'Support sessions are restricted to platform staff.');

        SupportSessionService::leaveContext();

        TenantAuthorizer::flushAll();
        flush_active_business_cache();

        return $this->ok(['user' => ApiShape::currentUser($user->fresh())]);
    }
}
