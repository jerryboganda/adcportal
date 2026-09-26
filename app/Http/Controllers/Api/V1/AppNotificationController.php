<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ApiShape;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * In-app clinical notification center.
 *
 * One boundary, drawn once: a user sees their tenant's notifications, and within
 * that tenant only the ones addressed to them or addressed to nobody. Every
 * action here is a READ or a state change on that user's own notifications —
 * including "clear all", which clears what this person can see and never the
 * clinic's clinical alert trail.
 *
 * The permission a user holds is deliberately NOT the gate here. A STAT booking
 * or a critical result is a patient-safety signal that every member of a small
 * clinic's team needs regardless of role, and `/bootstrap` already ships this
 * collection to every authenticated session — gating this endpoint alone would
 * hide a button while the same data kept arriving. Scoping by recipient is what
 * actually contains the data.
 */
class AppNotificationController extends BaseApiController
{
    private function visible()
    {
        return AppNotification::visibleTo(Auth::id(), $this->tenantId());
    }

    public function index(): JsonResponse
    {
        $notifications = $this->visible()
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

        // Count what actually changed, not what was asked for. Reporting
        // `count($ids)` told a caller "marked: 3" when a foreign tenant's id —
        // or another user's targeted notification — was silently skipped.
        $marked = $this->visible()
            ->whereIn('id', $validated['ids'])
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return $this->ok(['marked' => $marked]);
    }

    public function markAllRead(): JsonResponse
    {
        $marked = $this->visible()
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return $this->ok(['marked' => $marked]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $deleted = $this->visible()->where('id', $id)->delete();

        return $this->ok(['deleted' => $deleted > 0]);
    }

    public function clear(): JsonResponse
    {
        $cleared = $this->visible()->delete();

        return $this->ok(['cleared' => true, 'count' => $cleared]);
    }
}
