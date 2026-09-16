<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $user = User::find($id);

        if (! $user || ! self::hasEnabledTwoFactor($user)) {
            $request->session()->forget(self::PENDING_USER_KEY);

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

        if (! Totp::verify($user->two_factor_secret, $code)) {
            return false;
        }

        Auth::login($user, remember: false);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, now()->timestamp);
        $request->session()->forget(self::PENDING_USER_KEY);

        return true;
    }

    /** Cancel a pending challenge (user aborted sign-in). */
    public static function cancelChallenge(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->forget(self::PENDING_USER_KEY);
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
