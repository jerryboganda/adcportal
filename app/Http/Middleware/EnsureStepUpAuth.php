<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\PlatformAuthorizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Control-plane step-up re-authentication.
 *
 * Mutating platform actions require a FRESH password confirmation (standard
 * for SaaS control planes): an attended-but-unlocked session must not be able
 * to suspend a clinic, rewrite its white-label or re-place its deployment.
 *
 * Flow: a mutating request without a fresh confirmation is held at the door
 * with a typed 428 (`step_up_required`). The SPA shows the password modal and
 * POSTs /api/v1/platform/step-up; the confirmation is stamped on the session
 * (never a cookie the client could forge) and the request is replayed.
 * Reads stay open — this guards actions, not visibility.
 *
 * 2FA users are unaffected: their challenge is enforced by EnsurePlatformAccess.
 */
class EnsureStepUpAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('ris.platform_step_up.enabled', true)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User || ! PlatformAuthorizer::isPlatformAdmin($user)) {
            return $next($request);
        }

        // Sessionless clients (pure API consumers) can never hold a
        // confirmation — they are gated, exactly like an unconfirmed session.
        if (! $request->hasSession()) {
            return $this->challenge();
        }

        $confirmedAt = $request->session()->get('platform_step_up_at');

        if (is_int($confirmedAt) && $confirmedAt > now()->getTimestamp() - $this->windowSeconds()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Confirm your password to perform this platform action.',
            'error' => 'step_up_required',
        ], 428);
    }

    private function windowSeconds(): int
    {
        return max(1, (int) config('ris.platform_step_up.window_minutes', 15)) * 60;
    }

    private function challenge(): Response
    {
        return response()->json([
            'message' => 'Confirm your password to perform this platform action.',
            'error' => 'step_up_required',
        ], 428);
    }

    /** Shared by the verify endpoint and any future confirmation surface. */
    public static function stampSession(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $request->session()->put('platform_step_up_at', now()->getTimestamp());

        return true;
    }
}
