<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Resources\ApiShape;
use App\Models\Business;
use App\Models\SupportSession;
use App\Services\SupportSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Break-glass support sessions: open, list, close. Sessions are time-boxed,
 * reason-mandated and audited; there is never a standing cross-tenant grant.
 */
class PlatformSupportSessionController extends PlatformController
{
    public function index(): JsonResponse
    {
        $this->denyUnlessCapability('support.manage');

        $sessions = SupportSession::query()
            ->with(['platformUser:id,name,email', 'business:id,name,tenant_code'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return $this->ok(['sessions' => $sessions->map(fn ($s) => [
            ...ApiShape::supportSession($s),
            'platformUserName' => $s->platformUser?->name,
            'tenantName' => $s->business?->name,
            'tenantCode' => $s->business?->tenant_code,
        ])->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->denyUnlessCapability('support.manage');

        $validated = $request->validate([
            'businessId' => ['required', 'integer', 'exists:businesses,id'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'minutes' => ['nullable', 'integer', 'min:5', 'max:'.(int) config('ris.support_session_max_minutes', 240)],
        ]);

        $tenant = Business::findOrFail($validated['businessId']);

        $session = SupportSessionService::start(
            $this->actor(),
            $tenant,
            $validated['reason'],
            $validated['minutes'] ?? 60
        );

        return response()->json(['data' => ['session' => ApiShape::supportSession($session->fresh())]], 201);
    }

    public function end(Request $request, SupportSession $session): JsonResponse
    {
        $this->denyUnlessCapability('support.manage');

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        SupportSessionService::end($session, $validated['note'] ?? '');

        return $this->ok(['session' => ApiShape::supportSession($session->fresh())]);
    }
}
