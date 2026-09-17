<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Middleware\EnsureStepUpAuth;
use Illuminate\Validation\ValidationException;

/**
 * Step-up re-authentication for the control plane: the SPA asks the acting
 * platform user to re-enter THEIR OWN password before a mutating platform
 * action is accepted (EnsureStepUpAuth holds such requests with a typed 428
 * until a fresh confirmation exists on the session).
 */
class PlatformStepUpController extends PlatformController
{
    public function status(Request $request): JsonResponse
    {
        $confirmedAt = $request->hasSession() ? $request->session()->get('platform_step_up_at') : null;
        $fresh = is_int($confirmedAt) && $confirmedAt > now()->getTimestamp() - $this->windowSeconds();

        return $this->ok([
            'required' => ! $fresh,
            'confirmedAt' => $fresh ? date('c', $confirmedAt) : null,
            'windowMinutes' => (int) config('ris.platform_step_up.window_minutes', 15),
        ]);
    }

    /** Verify the acting platform user's own password and stamp the session. */
    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['That password is not correct.'],
            ]);
        }

        if (! EnsureStepUpAuth::stampSession($request)) {
            return response()->json([
                'message' => 'A session is required to confirm platform actions.',
                'error' => 'step_up_required',
            ], 428);
        }

        AuditLog::record('platform_step_up_confirmed', $user, [
            'summary' => 'Step-up password confirmation for control-plane mutations.'
                .' Acting platform user: '.$user->email.'.',
        ]);

        return $this->ok(['confirmed' => true]);
    }

    private function windowSeconds(): int
    {
        return max(1, (int) config('ris.platform_step_up.window_minutes', 15)) * 60;
    }
}
