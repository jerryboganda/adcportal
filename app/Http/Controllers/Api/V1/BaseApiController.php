<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\NotificationService;
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

    protected function denyUnless(string $permission): void
    {
        if (! auth()->user()?->isAbleTo($permission)) {
            abort(403, 'Permission denied.');
        }
    }

    protected function audit(string $action, $subject, array $changes = []): void
    {
        AuditLog::record($action, $subject, $changes);
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
