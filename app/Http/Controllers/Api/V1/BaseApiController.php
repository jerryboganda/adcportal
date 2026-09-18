<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\NotificationService;
use App\Services\TenantAuthorizer;
use Illuminate\Http\JsonResponse;

/**
 * Shared plumbing for the React portal API: tenant resolution, permission
 * gating, audit + notification helpers, uniform response envelope.
 */
abstract class BaseApiController extends Controller
{
    protected function tenantId(): int
    {
        return (int) getActiveBusiness();
    }

    /**
     * Permission check is TENANT-SCOPED: only roles belonging to the active
     * tenant count, so a user's privileges in one clinic never leak into
     * another (critical for multi-tenant members and support sessions).
     */
    protected function denyUnless(string $permission): void
    {
        $this->denyUnlessAny([$permission], $permission);
    }

    /** Grants access when the user holds ANY of the listed permissions. */
    protected function denyUnlessAny(array $permissions, ?string $label = null): void
    {
        $user = auth()->user();
        $tenantId = $this->tenantId();

        foreach ($permissions as $permission) {
            if ($user && TenantAuthorizer::allows($user, $permission, $tenantId)) {
                return;
            }
        }

        // Supportability trail ("why can't I access this?") — internal log
        // only; the client never learns more than the denied permission key.
        \Illuminate\Support\Facades\Log::warning('Access denied', [
            'permission' => $label ?? implode('|', $permissions),
            'user_id' => $user?->id,
            'tenant' => $tenantId,
            'route' => request()->path(),
        ]);

        abort(response()->json([
            'message' => 'Permission denied.',
            'error' => 'permission.denied',
            'permission' => $label ?? $permissions[0],
        ], 403));
    }

    protected function audit(string $action, $subject, array $changes = []): void
    {
        AuditLog::record($action, $subject, $changes, $this->tenantId() ?: null);
    }

    /**
     * Server-enforced module entitlement. Reads stay available (historical
     * clinical data must remain visible); mutating a disabled module is
     * refused regardless of what the SPA shows.
     */
    protected function denyFeatureUnlessEnabled(string $feature): void
    {
        $business = \App\Models\Business::find($this->tenantId());

        if ($business && ! \App\Services\FeatureResolver::enabled($business, $feature)) {
            abort(response()->json([
                'message' => "The [{$feature}] module is not part of this clinic's subscription.",
                'error' => 'feature_disabled',
                'feature' => $feature,
            ], 403));
        }
    }

    protected function notify(array $attributes): void
    {
        NotificationService::push($attributes);
    }

    protected function ok($data, array $meta = []): JsonResponse
    {
        return response()->json(['data' => $data] + ($meta ? ['meta' => $meta] : []));
    }

    protected function withNotifications($data, array $notifications, array $meta = []): JsonResponse
    {
        $payload = ['data' => $data, 'meta' => $meta ?: null];

        return response()->json(array_filter([
            'data' => $data,
            'meta' => $meta ?: null,
            'notifications' => $notifications ?: null,
        ]));
    }
}
