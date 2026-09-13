<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ApiShape;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** In-app clinical notification center. */
class AppNotificationController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $notifications = AppNotification::where('business_id', $this->tenantId())
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return $this->ok(['notifications' => $notifications->map(fn ($n) => ApiShape::appNotification($n))->all()]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        AppNotification::where('business_id', $this->tenantId())
            ->whereIn('id', $validated['ids'])
            ->update(['is_read' => true]);

        return $this->ok(['marked' => count($validated['ids'])]);
    }

    public function markAllRead(): JsonResponse
    {
        AppNotification::where('business_id', $this->tenantId())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return $this->ok(['marked' => 'all']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        AppNotification::where('business_id', $this->tenantId())->where('id', $id)->delete();

        return $this->ok(['deleted' => true]);
    }

    public function clear(): JsonResponse
    {
        AppNotification::where('business_id', $this->tenantId())->delete();

        return $this->ok(['cleared' => true]);
    }
}
