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

        $query = AuditLog::query()
            ->with('user:id,name')
            ->where(function ($q) {
                $q->whereIn('action', \App\Models\AuditLog::PLATFORM_ACTIONS)
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
