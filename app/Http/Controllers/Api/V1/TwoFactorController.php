<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\TwoFactorService;
use App\Services\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * TOTP two-factor authentication for platform (control-plane) identities.
 *
 * Flow:
 *   POST /two-factor/setup     — generate a pending secret (session-stashed)
 *   POST /two-factor/confirm   — verify the first code, activate enrollment
 *   GET  /two-factor/status    — { enabled, pending }
 *   POST /two-factor/challenge — complete the login challenge (pending session)
 *   POST /two-factor/challenge/cancel — abort a pending sign-in
 *   POST /two-factor/disable   — require password + current code
 *
 * Secrets live encrypted on the users table; the pending enrollment secret
 * lives only in the session until confirmed.
 */
class TwoFactorController extends BaseApiController
{
    public function status(Request $request): JsonResponse
    {
        $pending = TwoFactorService::pendingUser($request);
        $user = $request->user();

        if (! $user && $pending) {
            return $this->ok(['enabled' => true, 'pending' => true, 'email' => $pending->email]);
        }

        return $this->ok([
            'enabled' => $user ? TwoFactorService::hasEnabledTwoFactor($user) : false,
            'pending' => (bool) $pending,
        ]);
    }

    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isPlatformAdmin(), 403, 'Two-factor enrollment is restricted to platform staff.');
        abort_unless($request->hasSession(), 422, 'Two-factor enrollment requires a stateful session.');

        if (TwoFactorService::hasEnabledTwoFactor($user)) {
            abort(422, 'Two-factor authentication is already enabled.');
        }

        $secret = Totp::generateSecret();
        $request->session()->put('two_factor_pending_secret', $secret);

        $this->audit('two_factor_setup_started', $user, ['summary' => 'Two-factor enrollment started.']);

        return $this->ok([
            'secret' => $secret,
            'otpauthUrl' => Totp::otpauthUri($secret, $user->email),
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isPlatformAdmin(), 403, 'Two-factor enrollment is restricted to platform staff.');
        abort_unless($request->hasSession(), 422, 'Two-factor enrollment requires a stateful session.');

        $validated = $request->validate(['code' => ['required', 'string']]);

        $secret = (string) $request->session()->get('two_factor_pending_secret');

        if ($secret === '') {
            abort(422, 'No enrollment in progress. Start again.');
        }

        if (! Totp::verify($secret, $validated['code'])) {
            return response()->json(['message' => 'That code is not valid (yet). Check your authenticator clock and try again.'], 422);
        }

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_enabled_at' => now(),
        ])->save();

        $request->session()->forget('two_factor_pending_secret');
        TwoFactorService::markUnlocked($request);

        $this->audit('two_factor_enabled', $user, ['summary' => 'Two-factor authentication enabled.']);

        return $this->ok(['enabled' => true]);
    }

    public function challenge(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string']]);

        if (! TwoFactorService::completeChallenge($request, $validated['code'])) {
            $user = TwoFactorService::pendingUser($request);

            if ($user) {
                $this->audit('two_factor_challenge_failed', $user, ['summary' => "Invalid 2FA code from {$request->ip()}"]);
            }

            return response()->json(['message' => 'Invalid authentication code.'], 422);
        }

        $user = $request->user();

        $this->audit('two_factor_challenge_passed', $user, ['summary' => "Second factor accepted from {$request->ip()}"]);

        return $this->ok(['message' => 'Sign-in complete.']);
    }

    public function cancelChallenge(Request $request): JsonResponse
    {
        TwoFactorService::cancelChallenge($request);

        return $this->ok(['message' => 'Sign-in cancelled.']);
    }

    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isPlatformAdmin(), 403, 'Two-factor enrollment is restricted to platform staff.');
        abort_unless(TwoFactorService::isUnlocked($request), 403, 'Confirm your identity to manage two-factor settings.');

        $validated = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        if (! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Incorrect password.'], 422);
        }

        if (! Totp::verify($user->two_factor_secret, $validated['code'])) {
            return response()->json(['message' => 'Invalid authentication code.'], 422);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_enabled_at' => null,
        ])->save();

        $this->audit('two_factor_disabled', $user, ['summary' => 'Two-factor authentication disabled.']);

        return $this->ok(['enabled' => false]);
    }
}
