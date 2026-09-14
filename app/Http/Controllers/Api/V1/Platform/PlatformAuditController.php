<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Resources\ApiShape;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Platform-wide audit stream: security-significant events, lifecycle
 * changes, support sessions and platform staff actions — with actor
 * attribution. Audit access is itself a gated capability.
 */
class PlatformAuditController extends PlatformController
{
    public function index(Request $request): JsonResponse
    {
        $this->denyUnlessCapability('audit.view');

        $platformActions = [
            'tenant_created', 'tenant_provisioned', 'tenant_activated', 'tenant_suspended',
            'tenant_reactivated', 'tenant_offboarding_started', 'tenant_terminated',
            'subscription_updated', 'tenant_features_updated', 'tenant_data_exported',
            'support_session_started', 'support_session_ended',
            'plan_created', 'plan_updated', 'platform_user_created', 'platform_user_updated',
            'tenant_updated', 'tenant_registered', 'subscription_expired',
        ];

        $query = AuditLog::query()
            ->with('user:id,name')
            ->where(function ($q) use ($platformActions) {
                $q->whereIn('action', $platformActions)
                    ->orWhereIn('user_id', User::query()->whereIn('type', ['super_admin', 'platform_admin'])->select('id'));
            });

        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        if ($businessId = $request->query('tenantId')) {
            $query->where('business_id', (int) $businessId);
        }

        $logs = $query->orderByDesc('id')->limit(200)->get();

        return $this->ok(['audit' => $logs->map(fn ($l) => ApiShape::platformAuditEntry($l))->all()]);
    }
}
