<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ApiShape;
use App\Models\Business;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Session authentication for the React SPA (Sanctum SPA mode, same-origin
 * cookies) plus public clinic-tenant registration.
 */
class AuthController extends BaseApiController
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, remember: true)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        $user = Auth::user();

        // Terminated / offboarding tenants lose even interactive access —
        // checked BEFORE the generic disabled flag so the operator sees why.
        if (! $user->isPlatformAdmin()) {
            $businessId = (int) ($user->business_id ?: $user->active_business ?: 0);
            $business = $businessId ? Business::find($businessId) : null;

            if ($business && in_array($business->subscription_status, ['terminated', 'offboarding'], true)) {
                Auth::logout();

                return response()->json([
                    'message' => $business->subscription_status === 'terminated'
                        ? 'This clinic account has been terminated.'
                        : 'This clinic account is being offboarded. Access is no longer available.',
                    'subscriptionStatus' => $business->subscription_status,
                ], 403);
            }
        }

        if (! $user->active_status || ! $user->is_enable_login) {
            Auth::logout();

            return response()->json(['message' => 'This account is disabled. Contact your clinic administrator.'], 403);
        }

        // Patient accounts (walk-in registration creates them login-less) have
        // no patient portal in this product; they must never reach the staff
        // data plane, which hydrates full clinic PHI on bootstrap.
        if ($user->type === 'customer') {
            Auth::logout();

            return response()->json(['message' => 'This account cannot sign in to the staff portal.'], 403);
        }

        // Session fixation defense: mint a fresh session ID now that the
        // identity is authenticated (mirrors the Breeze web login). Guarded
        // because non-stateful API clients may not have a session at all.
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // Platform 2FA: enrolled identities enter a TOTP challenge instead of
        // receiving a usable session (no-op for everyone else).
        \App\Services\TwoFactorService::onLogin($request, $user);

        $user->forceFill(['last_login_at' => now()])->save();

        // Enrolled platform identity: hold the session at the second factor.
        // The response has no user payload — possession of the password alone
        // must never yield a usable SPA session.
        if (\App\Services\TwoFactorService::pendingUser($request)) {
            return response()->json(['data' => [
                'two_factor_required' => true,
                'email' => $user->email,
            ]]);
        }

        $this->audit('login', $user, ['summary' => "Signed in from {$request->ip()}"]);

        return $this->ok(['user' => ApiShape::currentUser($user)]);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->ok(['user' => ApiShape::currentUser($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->audit('logout', $request->user());

        Auth::guard('web')->logout();

        // SPA requests are stateful, but be safe for stateless clients.
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->ok(['message' => 'Logged out']);
    }

    /** Terminal-lock unlock: re-verify the signed-in user's password. */
    public function verifyPassword(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->input('password'), $request->user()->password)) {
            return response()->json(['message' => 'Incorrect password.'], 422);
        }

        return $this->ok(['verified' => true]);
    }

    /**
     * Public tenant signup: provisions the clinic (trialing) through the
     * tenant lifecycle engine — owner admin, roles/masters bootstrap,
     * membership — then signs the new owner in. Throttled at route level.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'clinic_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => \App\Services\PasswordPolicy::rules(),
            'plan' => ['nullable', 'string', 'exists:plans,slug'],
        ]);

        $result = app(\App\Services\TenantLifecycleService::class)->provision(
            $validated['clinic_name'],
            [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'mobile_no' => $validated['phone'] ?? null,
                'password' => $validated['password'],
                'capabilities' => [
                    'canSignReports' => true,
                    'canVoidInvoices' => true,
                    'canOverrideScreening' => true,
                    'canEditMasters' => true,
                    'canAccessPacs' => true,
                ],
            ],
            planSlug: $validated['plan'] ?? 'starter',
        );

        $tenant = $result['business'];

        $this->audit('tenant_registered', $tenant, ['summary' => "New clinic registered: {$tenant->name}"]);

        Auth::attempt($request->only('email', 'password'), remember: true);

        // Session fixation defense on signup-login, same as login().
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'data' => ['user' => ApiShape::currentUser(auth()->user())],
            'meta' => ['tenant_code' => $tenant->tenant_code],
        ], 201);
    }
}
