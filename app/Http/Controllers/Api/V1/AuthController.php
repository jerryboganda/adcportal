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

        // A tenant that is not subscribable cannot obtain a session at all, and
        // is told the same thing `EnsureTenantActive` would tell it at the API
        // boundary. This used to cover only `terminated`/`offboarding` and rely
        // on `TenantLifecycleService::revokeTenantLogins()` to disable every
        // account for `suspended`/`expired` — a one-way door: nothing ever set
        // `is_enable_login` back, so one suspend/reactivate round-trip locked
        // the clinic out permanently and any deliberately-disabled account came
        // back too. Refusing the login by STATUS is symmetric, restores itself,
        // and cannot override a per-user HR decision.
        if (! $user->isPlatformAdmin()) {
            $businessId = (int) ($user->business_id ?: $user->active_business ?: 0);
            $business = $businessId ? Business::find($businessId) : null;

            if ($business && ! $business->isSubscribable()) {
                Auth::logout();

                return response()->json([
                    'message' => match ($business->subscription_status) {
                        'terminated' => 'This clinic account has been terminated.',
                        'offboarding' => 'This clinic account is being offboarded. Access is no longer available.',
                        'suspended' => 'This clinic account is suspended. Contact the platform administrator.',
                        'expired' => 'This clinic subscription has expired. Renew to continue.',
                        'provisioning' => 'This clinic is still being provisioned. Try again shortly.',
                        default => 'This clinic trial has ended. Choose a plan to continue.',
                    },
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
        $this->assertRegistrationIsOpen();

        $validated = $request->validate([
            'clinic_name' => ['required', 'string', 'max:255'],
            // Clinic and hospital tenants provision identically today; the
            // flavor is stored for hospital-specific features to come.
            // Optional + defaulted so existing API consumers keep working.
            'org_type' => ['nullable', 'in:clinic,hospital'],
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
            orgType: $validated['org_type'] ?? 'clinic',
        );

        $tenant = $result['business'];

        $orgNoun = ($validated['org_type'] ?? 'clinic') === 'hospital' ? 'hospital' : 'clinic';
        // Named explicitly: nobody is authenticated yet, so `getActiveBusiness()`
        // would resolve to `Business::first()` and attribute this signup to the
        // wrong clinic in the platform's registration history.
        $this->auditForTenant(
            (int) $tenant->id,
            'tenant_registered',
            $tenant,
            ['summary' => "New {$orgNoun} registered: {$tenant->name}"]
        );

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

    /**
     * Is the public signup door open, and is there room for another tenant?
     *
     * Refused BEFORE any validation or write, so a flood costs a `count()` and
     * nothing else. Throttling already bounds the rate; this bounds the total,
     * which is the part that fills an operator's activation queue with tenants
     * that can never pass `EnsureTenantActive`.
     */
    private function assertRegistrationIsOpen(): void
    {
        $registration = (array) config('ris.registration');

        if (! ($registration['enabled'] ?? true)) {
            abort(response()->json([
                'message' => 'Online clinic registration is currently closed. Contact us to have your clinic provisioned.',
                'error' => 'registration.closed',
            ], 403));
        }

        $max = (int) ($registration['max_pending_tenants'] ?? 0);
        $statuses = (array) ($registration['pending_statuses'] ?? ['provisioning', 'trialing']);

        if ($max > 0) {
            $pending = Business::query()->whereIn('subscription_status', $statuses)->count();

            if ($pending >= $max) {
                abort(response()->json([
                    'message' => 'Registration is temporarily paused while we work through pending clinics. Please try again shortly.',
                    'error' => 'registration.at_capacity',
                ], 503));
            }
        }
    }
}
