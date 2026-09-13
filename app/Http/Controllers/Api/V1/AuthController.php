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

        if (! $user->active_status || ! $user->is_enable_login) {
            Auth::logout();

            return response()->json(['message' => 'This account is disabled. Contact your clinic administrator.'], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();

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
        $request->session()->invalidate();
        $request->session()->regenerateToken();

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
     * Public tenant signup: creates the clinic (trialing), its admin user,
     * default roles/settings and starter masters. Throttled at route level.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'clinic_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', Password::min(8)],
            'plan' => ['nullable', 'string', 'exists:plans,slug'],
        ]);

        $tenant = DB::transaction(function () use ($validated, $request) {
            $plan = Plan::where('slug', $validated['plan'] ?? 'starter')->where('is_active', true)->first()
                ?? Plan::where('is_active', true)->orderBy('price_monthly')->first();

            $business = Business::create([
                'name' => $validated['clinic_name'],
                'form_type' => 'form-layout',
                'layouts' => 'Formlayout11',
                'theme_color' => 'color1-Formlayout11',
                'plan_id' => $plan?->id,
                'subscription_status' => 'trialing',
                'trial_ends_at' => now()->addDays($plan?->trial_days ?? 14),
                'tenant_code' => strtoupper(Str::random(3)).'-'.random_int(1000, 9999),
                'created_by' => 0,
            ]);

            $admin = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'mobile_no' => $validated['phone'] ?? null,
                'email_verified_at' => now(),
                'type' => 'admin',
                'active_status' => 1,
                'active_business' => $business->id,
                'business_id' => $business->id,
                'created_by' => $business->id,
                'lang' => 'en',
                'initials' => mb_strtoupper(mb_substr($validated['name'], 0, 1)),
                'department' => 'Administration',
                'capabilities' => [
                    'canSignReports' => true,
                    'canVoidInvoices' => true,
                    'canOverrideScreening' => true,
                    'canEditMasters' => true,
                    'canAccessPacs' => true,
                ],
            ]);

            // Tenant-scoped role + permission wiring (laratrust, per-clinic).
            $admin->MakeRole();
            app(\Database\Seeders\TenantBootstrap::class)->run($business, $admin);

            return $business;
        });

        $this->audit('tenant_registered', $tenant, ['summary' => "New clinic registered: {$tenant->name}"]);

        Auth::attempt($request->only('email', 'password'), remember: true);

        return response()->json([
            'data' => ['user' => ApiShape::currentUser(auth()->user())],
            'meta' => ['tenant_code' => $tenant->tenant_code],
        ], 201);
    }
}
