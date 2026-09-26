<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Platform 2FA session state. Enrollment is per-user (TOTP secret on the
 * users table); enforcement is per-SESSION:
 *
 *  - login() marks the session unlocked when the user has NO second factor.
 *  - Enrolled users get a "pending challenge": identity is stashed in the
 *    session, the web guard is logged out, and every guarded request is
 *    answered 403 two_factor_required until a valid TOTP code completes
 *    the challenge and re-authenticates + unlocks the session.
 *
 * Users without a second factor never see any of this — zero behaviour
 * change for tenant staff and unenrolled platform staff.
 */
class TwoFactorService
{
    public const SESSION_KEY = 'platform_2fa_unlocked_at';

    public const PENDING_USER_KEY = 'platform_2fa_pending_user_id';

    public const CHALLENGE_TTL_MINUTES = 10;

    /** Session key holding when the pending challenge was raised. */
    public const PENDING_AT_KEY = 'platform_2fa_pending_at';

    /**
     * Cache key recording the last accepted time-step for a user.
     *
     * Shared, not per-session: a replayed code arrives on whatever session the
     * attacker controls, so session storage would not stop it.
     */
    private const LAST_COUNTER_PREFIX = 'auth.2fa.last_counter.';

    public static function hasEnabledTwoFactor(User $user): bool
    {
        return $user->two_factor_enabled_at !== null && (string) $user->two_factor_secret !== '';
    }

    /** After a successful login: enrolled users enter the challenge, others are unlocked immediately. */
    public static function onLogin(Request $request, User $user): void
    {
        if (! $request->hasSession()) {
            return; // stateless client: the platform gate will hold enrolled users at the door
        }

        if (self::hasEnabledTwoFactor($user)) {
            // Stash the identity, then drop the authenticated session state:
            // possession of the password alone must not yield a usable session.
            $request->session()->put(self::PENDING_USER_KEY, $user->id);
            $request->session()->put(self::PENDING_AT_KEY, now()->timestamp);
            $request->session()->remove(self::SESSION_KEY);
            Auth::guard('web')->logout();
        } else {
            $request->session()->put(self::SESSION_KEY, now()->timestamp);
        }
    }

    public static function pendingUser(Request $request): ?User
    {
        if (! $request->hasSession()) {
            return null;
        }

        $id = $request->session()->get(self::PENDING_USER_KEY);

        if (! $id) {
            return null;
        }

        // A pending identity used to survive until the session died, because the
        // TTL constant was declared and never read: a password verified hours
        // ago still had a live challenge waiting for a code. The window is now
        // the stated ten minutes, after which the challenge is discarded and the
        // user must authenticate again from the password.
        $raisedAt = (int) $request->session()->get(self::PENDING_AT_KEY, 0);
        if ($raisedAt <= 0 || $raisedAt < now()->subMinutes(self::CHALLENGE_TTL_MINUTES)->timestamp) {
            self::cancelChallenge($request);

            return null;
        }

        $user = User::find($id);

        if (! $user || ! self::hasEnabledTwoFactor($user)) {
            self::cancelChallenge($request);

            return null;
        }

        return $user;
    }

    /** Verify the code for the pending challenge; on success re-authenticate and unlock. */
    public static function completeChallenge(Request $request, string $code): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $user = self::pendingUser($request);

        if (! $user) {
            return false;
        }

        $counter = Totp::matchingCounter($user->two_factor_secret, $code);

        if ($counter === null) {
            return false;
        }

        // A TOTP code is valid for its whole ±1 step window — up to 90 seconds —
        // so a shoulder-surfed or proxied code could be spent again. Once a step
        // has been accepted, refuse that step and every earlier one.
        $key = self::LAST_COUNTER_PREFIX.$user->id;
        $last = Cache::get($key);

        if ($last !== null && $counter <= (int) $last) {
            return false;
        }

        Cache::put($key, $counter, now()->addMinutes(self::CHALLENGE_TTL_MINUTES));

        Auth::login($user, remember: false);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, now()->timestamp);
        $request->session()->forget(self::PENDING_USER_KEY);
        $request->session()->forget(self::PENDING_AT_KEY);

        return true;
    }

    /** Cancel a pending challenge (user aborted sign-in, or it expired). */
    public static function cancelChallenge(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->forget(self::PENDING_USER_KEY);
            $request->session()->forget(self::PENDING_AT_KEY);
        }
    }

    /** Enrollment (setup/confirm) operates on the AUTHENTICATED user; keep the session unlocked across it. */
    public static function markUnlocked(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, now()->timestamp);
        }
    }

    /** After enabling: allow the current session through (a just-enrolled device is present). */
    public static function isUnlocked(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false; // no session → no completed challenge is possible
        }

        $ts = $request->session()->get(self::SESSION_KEY);

        return is_numeric($ts);
    }
}
