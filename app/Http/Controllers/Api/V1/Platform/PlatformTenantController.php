<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Resources\ApiShape;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Customer;
use App\Models\DicomNode;
use App\Models\Location;
use App\Models\Plan;
use App\Models\TenantFeatureOverride;
use App\Models\TenantMembership;
use App\Models\UsageCounter;
use App\Models\User;
use App\Services\EntitlementService;
use App\Services\FeatureResolver;
use App\Services\StorageMeter;
use App\Services\TenantLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Tenant directory + 360° view + lifecycle actions for the control plane.
 * The vendor operates on metadata and operational telemetry here — patient
 * records and clinical content are never listed in platform payloads.
 */
class PlatformTenantController extends PlatformController
{
    public function index(Request $request): JsonResponse
    {
        $this->denyUnlessCapability('tenants.view');

        $query = Business::query()->with('plan')->withCount(['users', 'appointments']);

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('tenant_code', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('subscription_status', $status);
        }

        if ($planId = $request->query('planId')) {
            $query->where('plan_id', (int) $planId);
        }

        $tenants = $query->orderByDesc('id')->limit(500)->get();

        return $this->ok(['tenants' => $tenants->map(fn ($t) => ApiShape::tenant($t))->all()]);
    }

    /** Provision a tenant end-to-end from the control plane. */
    public function store(Request $request): JsonResponse
    {
        $this->denyUnlessCapability('tenants.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'adminName' => ['required', 'string', 'max:255'],
            'adminEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
            'adminPhone' => ['nullable', 'string', 'max:40'],
            'planId' => ['nullable', 'integer', 'exists:plans,id'],
            'trialDays' => ['nullable', 'integer', 'min:1', 'max:365'],
            'subscriptionEndsAt' => ['nullable', 'date'],
        ]);

        $plan = $validated['planId'] ?? null ? Plan::find($validated['planId']) : null;

        $result = app(TenantLifecycleService::class)->provision(
            $validated['name'],
            [
                'name' => $validated['adminName'],
                'email' => $validated['adminEmail'],
                'mobile_no' => $validated['adminPhone'] ?? null,
            ],
            $plan,
            null,
            $validated['trialDays'] ?? null,
            $validated['subscriptionEndsAt'] ?? null,
        );

        AuditLog::record('tenant_provisioned', $result['business'], [
            'summary' => "Tenant {$result['business']->name} provisioned from the platform with admin {$validated['adminEmail']}.",
        ], $result['business']->id);

        return response()->json([
            'data' => [
                'tenant' => ApiShape::tenant($result['business']->fresh('plan')),
                // Shown once to the operator for secure handover to the tenant owner.
                'initialAdminPassword' => $result['initialPassword'],
            ],
        ], 201);
    }

    /** Tenant 360° view. */
    public function show(Business $tenant): JsonResponse
    {
        $this->denyUnlessCapability('tenants.view');

        $tenant->load('plan');

        $lifecycle = $tenant->lifecycleEvents()->limit(50)->get()->map(fn ($e) => [
            'id' => (string) $e->id,
            'event' => $e->event,
            'fromStatus' => $e->from_status,
            'toStatus' => $e->to_status,
            'actorId' => $e->actor_id ? (string) $e->actor_id : null,
            'details' => $e->details,
            'at' => $e->created_at?->toIso8601String(),
        ])->all();

        $users = User::query()
            ->where('business_id', $tenant->id)
            ->where('type', '!=', 'customer')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'type', 'active_status', 'is_enable_login', 'last_login_at'])
            ->map(fn ($u) => [
                'id' => (string) $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->portalRole(),
                'isAdmin' => $u->type === 'admin',
                'active' => (bool) $u->active_status,
                'loginEnabled' => (bool) $u->is_enable_login,
                'lastLogin' => $u->last_login_at?->toIso8601String(),
            ])->all();

        $memberships = TenantMembership::query()
            ->where('business_id', $tenant->id)->where('status', 'active')
            ->with('user:id,name,email')
            ->get()
            ->map(fn ($m) => [
                'id' => (string) $m->id,
                'userId' => (string) $m->user_id,
                'userName' => $m->user?->name,
                'role' => $m->role,
                'isDefault' => (bool) $m->is_default,
            ])->all();

        $facilities = Location::where('business_id', $tenant->id)->orderBy('name')->get(['id', 'name', 'address', 'phone'])->map(fn ($l) => [
            'id' => (string) $l->id,
            'name' => $l->name,
            'address' => (string) $l->address,
            'phone' => (string) ($l->phone ?? ''),
        ])->all();

        // PHI boundary: the platform sees control-plane events only — the
        // tenant's clinical audit trail (which contains patient context)
        // stays inside the tenant plane.
        $audit = AuditLog::query()
            ->where('business_id', $tenant->id)
            ->whereIn('action', \App\Models\AuditLog::PLATFORM_ACTIONS)
            ->with('user:id,name')
            ->orderByDesc('id')->limit(30)->get()
            ->map(fn ($l) => ApiShape::platformAuditEntry($l))->all();

        $supportSessions = $tenant->supportSessions()->with('platformUser:id,name')->orderByDesc('id')->limit(10)->get()->map(fn ($s) => ApiShape::supportSession($s))->all();

        return $this->ok([
            'tenant' => [
                ...ApiShape::tenant($tenant),
                'patientCount' => Customer::where('business_id', $tenant->id)->count(),
                'dicomNodeCount' => DicomNode::where('business_id', $tenant->id)->count(),
                'lifecycle' => $lifecycle,
                'users' => $users,
                'memberships' => $memberships,
                'facilities' => $facilities,
                'entitlements' => EntitlementService::payload($tenant),
                'featureOverrides' => TenantFeatureOverride::where('business_id', $tenant->id)->get()
                    ->map(fn ($o) => ['feature' => $o->feature, 'enabled' => (bool) $o->enabled])->all(),
                'audit' => $audit,
                'supportSessions' => $supportSessions,
            ],
        ]);
    }

    /** Tenant profile + subscription modification: rename, plan, term dates. Status changes go through the lifecycle actions. */
    public function updateSubscription(Request $request, Business $tenant): JsonResponse
    {
        $this->denyUnlessCapability('subscriptions.manage');

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'planId' => ['sometimes', 'nullable', 'integer', 'exists:plans,id'],
            'subscriptionEndsAt' => ['sometimes', 'nullable', 'date'],
            'trialEndsAt' => ['sometimes', 'nullable', 'date'],
        ]);

        $updates = [];
        if (array_key_exists('name', $validated) && trim($validated['name']) !== '') {
            $updates['name'] = trim($validated['name']);
        }
        if (array_key_exists('planId', $validated)) {
            $updates['plan_id'] = $validated['planId'];
        }
        if (array_key_exists('subscriptionEndsAt', $validated)) {
            $updates['subscription_ends_at'] = $validated['subscriptionEndsAt'] ? now()->parse($validated['subscriptionEndsAt']) : null;
        }
        if (array_key_exists('trialEndsAt', $validated)) {
            $updates['trial_ends_at'] = $validated['trialEndsAt'] ? now()->parse($validated['trialEndsAt']) : null;
        }

        $tenant->update($updates);

        \App\Models\TenantLifecycleEvent::record($tenant, 'subscription_updated', $tenant->subscription_status, $tenant->subscription_status, [
            'summary' => 'Tenant profile/subscription updated: '.implode(', ', array_keys($updates)).'.',
        ]);
        AuditLog::record('subscription_updated', $tenant, [
            'summary' => "Tenant {$tenant->name} updated: ".implode(', ', array_keys($updates)).'.',
        ], $tenant->id);

        return $this->ok(['tenant' => ApiShape::tenant($tenant->fresh('plan'))]);
    }

    /** Per-tenant feature override write (PUT full map). */
    public function updateFeatures(Request $request, Business $tenant): JsonResponse
    {
        $this->denyUnlessCapability('tenants.manage');

        $known = array_keys(config('ris.features', []));
        $validated = $request->validate([
            'overrides' => ['required', 'array'],
            'overrides.*' => ['array'],
        ]);

        DB::transaction(function () use ($validated, $tenant, $known) {
            foreach ($validated['overrides'] as $feature => $value) {
                if (! in_array($feature, $known, true)) {
                    continue;
                }
                TenantFeatureOverride::updateOrCreate(
                    ['business_id' => $tenant->id, 'feature' => $feature],
                    ['enabled' => (bool) $value, 'actor_id' => $this->actor()->id]
                );
            }
        });

        AuditLog::record('tenant_features_updated', $tenant, [
            'summary' => "Feature overrides updated for {$tenant->name}: ".json_encode($validated['overrides']).'.',
        ], $tenant->id);

        return $this->ok([
            'features' => FeatureResolver::features($tenant),
            'overrides' => TenantFeatureOverride::where('business_id', $tenant->id)->get()
                ->map(fn ($o) => ['feature' => $o->feature, 'enabled' => (bool) $o->enabled])->all(),
        ]);
    }

    public function usage(Business $tenant): JsonResponse
    {
        $this->denyUnlessCapability('usage.view');

        $metrics = ['studies', 'reports'];
        $periods = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'))->values();

        $rows = UsageCounter::query()
            ->where('business_id', $tenant->id)
            ->whereIn('metric', $metrics)
            ->whereIn('period', $periods)
            ->get();

        $series = collect($periods)->mapWithKeys(fn ($p) => [$p => collect($metrics)->mapWithKeys(fn ($m) => [$m => (int) ($rows->first(fn ($r) => $r->metric === $m && $r->period === $p)->value ?? 0)])->all()])->all();

        return $this->ok([
            'usage' => [
                'limits' => EntitlementService::limits($tenant),
                'current' => EntitlementService::usage($tenant),
                'monthly' => $series,
            ],
        ]);
    }

    public function tenantAudit(Business $tenant): JsonResponse
    {
        $this->denyUnlessCapability('audit.view');

        // PHI boundary: platform audit view = control-plane events only.
        $logs = AuditLog::query()
            ->where('business_id', $tenant->id)
            ->whereIn('action', \App\Models\AuditLog::PLATFORM_ACTIONS)
            ->with('user:id,name')
            ->orderByDesc('id')->limit(200)->get();

        return $this->ok(['audit' => $logs->map(fn ($l) => ApiShape::platformAuditEntry($l))->all()]);
    }

    /** Full tenant data export (offboarding/archival), authorized + audited. */
    public function export(Business $tenant): JsonResponse
    {
        $this->denyUnlessCapability('tenants.manage');

        $tenantId = $tenant->id;

        $payload = [
            'exportedAt' => now()->toIso8601String(),
            'system' => 'PolytronX - RIS Portal',
            'tenant' => ApiShape::tenant($tenant),
            'data' => [
                'patients' => Customer::where('business_id', $tenantId)->get()->map(fn ($c) => ApiShape::patient($c))->all(),
                'clinicSettings' => ApiShape::clinicSettings($tenantId),
            ],
            'counts' => [
                'studies' => \App\Models\Appointment::forClinic($tenantId)->count(),
                'invoices' => \App\Models\Invoice::forClinic($tenantId)->count(),
            ],
        ];

        AuditLog::record('tenant_data_exported', $tenant, [
            'summary' => "Platform export generated for {$tenant->name}.",
        ], $tenant->id);

        return $this->ok(['export' => $payload]);
    }

    public function activate(Request $request, Business $tenant): JsonResponse
    {
        $this->denyUnlessCapability('tenants.lifecycle');
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->lifecycleResponse(app(TenantLifecycleService::class)->activate($tenant, $data['reason'] ?? ''));
    }

    public function suspend(Request $request, Business $tenant): JsonResponse
    {
        $this->denyUnlessCapability('tenants.lifecycle');
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->lifecycleResponse(app(TenantLifecycleService::class)->suspend($tenant, $data['reason'] ?? ''));
    }

    public function reactivate(Request $request, Business $tenant): JsonResponse
    {
        $this->denyUnlessCapability('tenants.lifecycle');
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->lifecycleResponse(app(TenantLifecycleService::class)->reactivate($tenant, $data['reason'] ?? ''));
    }

    public function offboard(Request $request, Business $tenant): JsonResponse
    {
        $this->denyUnlessCapability('tenants.lifecycle');
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->lifecycleResponse(app(TenantLifecycleService::class)->startOffboarding($tenant, $data['reason'] ?? ''));
    }

    /** Termination requires the operator to type the tenant code as confirmation. */
    public function terminate(Request $request, Business $tenant): JsonResponse
    {
        $this->denyUnlessCapability('tenants.lifecycle');
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'confirmCode' => ['required', 'string'],
        ]);

        abort_unless($data['confirmCode'] === $tenant->tenant_code, 422, 'Confirmation code does not match the tenant code.');

        return $this->lifecycleResponse(app(TenantLifecycleService::class)->terminate($tenant, $data['reason'] ?? ''));
    }

    public function retryProvisioning(Business $tenant): JsonResponse
    {
        $this->denyUnlessCapability('provisioning.manage');

        $tenant = app(TenantLifecycleService::class)->retryProvisioning($tenant);

        return $this->lifecycleResponse($tenant);
    }

    // ==================== tenant user administration ====================

    /**
     * Create a staff user inside the tenant. The password is optional: when
     * omitted the platform generates one and returns it exactly once for
     * secure handover (same semantics as tenant provisioning).
     */
    public function storeUser(Request $request, Business $tenant): JsonResponse
    {
        $this->denyUnlessCapability('tenants.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:admin,radiologist,technologist,receptionist,billing'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:8'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $initialPassword = $validated['password'] ?? \Illuminate\Support\Str::password(16);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $initialPassword,
            'mobile_no' => $validated['phone'] ?? null,
            'email_verified_at' => now(),
            'type' => 'staff',
            'active_status' => (int) ($validated['isActive'] ?? true),
            'business_id' => $tenant->id,
            'created_by' => $tenant->id,
            'lang' => 'en',
        ]);

        $this->assignTenantRole($tenant, $user, $validated['role']);

        TenantMembership::create([
            'user_id' => $user->id,
            'business_id' => $tenant->id,
            'role' => $validated['role'],
            'is_default' => false,
            'status' => 'active',
        ]);

        AuditLog::record('tenant_user_created', $user, [
            'summary' => "Platform created {$validated['role']} account {$user->email} for {$tenant->name}.",
        ], $tenant->id);

        return response()->json([
            'data' => [
                'user' => $this->tenantUserShape($user->fresh()),
                // Shown once to the operator; never stored in plaintext.
                'initialPassword' => $validated['password'] ?? null ? null : $initialPassword,
            ],
        ], 201);
    }

    /** Edit a tenant user's identity, role, activation or login flag. */
    public function updateUser(Request $request, Business $tenant, User $user): JsonResponse
    {
        $this->denyUnlessCapability('tenants.manage');

        // Platform identities are never manageable through a tenant.
        if ($user->business_id !== $tenant->id || in_array($user->type, ['super_admin', 'platform_admin', 'customer'], true)) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['sometimes', 'in:admin,radiologist,technologist,receptionist,billing'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'isActive' => ['sometimes', 'boolean'],
            'loginEnabled' => ['sometimes', 'boolean'],
        ]);

        $revokingAccess = (isset($validated['isActive']) && ! $validated['isActive'])
            || (isset($validated['loginEnabled']) && ! $validated['loginEnabled']);
        $demotingAdmin = isset($validated['role']) && $validated['role'] !== 'admin' && $this->holdsAdminRole($tenant, $user);
        $needsAdminGuard = ($revokingAccess || $demotingAdmin)
            && $user->active_status && $user->is_enable_login
            && $this->holdsAdminRole($tenant, $user)
            && ! $this->otherActiveAdminExists($tenant, $user);

        if ($needsAdminGuard) {
            abort(422, 'This is the last active administrator of the tenant — at least one must keep access.');
        }

        if (array_key_exists('name', $validated)) {
            $user->name = $validated['name'];
        }
        if (array_key_exists('email', $validated)) {
            $user->email = $validated['email'];
        }
        if (array_key_exists('phone', $validated)) {
            $user->mobile_no = $validated['phone'];
        }
        if (isset($validated['isActive'])) {
            $user->active_status = (int) $validated['isActive'];
        }
        if (isset($validated['loginEnabled'])) {
            $user->is_enable_login = (int) $validated['loginEnabled'];
        }
        $user->save();

        if (isset($validated['role'])) {
            $this->assignTenantRole($tenant, $user, $validated['role']);
            TenantMembership::query()
                ->where('user_id', $user->id)->where('business_id', $tenant->id)
                ->update(['role' => $validated['role']]);
        }

        if ($revokingAccess) {
            $this->revokeUserSessions($user);
        }

        AuditLog::record('tenant_user_updated', $user, [
            'summary' => "Platform updated tenant user {$user->email} (".implode(', ', array_keys($validated)).') for '.$tenant->name.'.'.$this->actorNote(),
        ], $tenant->id);

        return $this->ok(['user' => $this->tenantUserShape($user->fresh())]);
    }

    /** Rotate a tenant user's password; the new value is returned exactly once. */
    public function resetUserPassword(Request $request, Business $tenant, User $user): JsonResponse
    {
        $this->denyUnlessCapability('tenants.manage');

        if ($user->business_id !== $tenant->id || in_array($user->type, ['super_admin', 'platform_admin', 'customer'], true)) {
            abort(404);
        }

        $newPassword = \Illuminate\Support\Str::password(16);
        $user->password = $newPassword;
        $user->save();

        $this->revokeUserSessions($user);

        AuditLog::record('tenant_user_password_reset', $user, [
            'summary' => "Platform rotated the password for {$user->email} ({$tenant->name}). Live sessions revoked.".$this->actorNote(),
        ], $tenant->id);

        return $this->ok([
            'user' => $this->tenantUserShape($user->fresh()),
            // Shown once to the operator; never stored or logged in plaintext.
            'newPassword' => $newPassword,
        ]);
    }

    // ==================== tenant facilities (locations) ====================

    public function storeFacility(Request $request, Business $tenant): JsonResponse
    {
        $this->denyUnlessCapability('tenants.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $location = Location::create([
            'name' => $validated['name'],
            'address' => (string) ($validated['address'] ?? ''),
            'phone' => $validated['phone'] ?? null,
            'description' => $validated['description'] ?? null,
            'business_id' => $tenant->id,
            'created_by' => $this->actor()->id,
        ]);

        AuditLog::record('tenant_facility_created', $location, [
            'summary' => "Platform added facility {$location->name} to {$tenant->name}.".$this->actorNote(),
        ], $tenant->id);

        return response()->json(['data' => ['facility' => $this->facilityShape($location)]], 201);
    }

    public function updateFacility(Request $request, Business $tenant, Location $location): JsonResponse
    {
        $this->denyUnlessCapability('tenants.manage');
        abort_unless($location->business_id === $tenant->id, 404);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $location->update($validated);

        AuditLog::record('tenant_facility_updated', $location, [
            'summary' => "Platform updated facility {$location->name} ({$tenant->name}): ".implode(', ', array_keys($validated)).'.'.$this->actorNote(),
        ], $tenant->id);

        return $this->ok(['facility' => $this->facilityShape($location->fresh())]);
    }

    /**
     * Deleting a facility would CASCADE-DELETE the tenant's studies
     * (appointments.location_id FK) — refuse while any study references it.
     */
    public function destroyFacility(Request $request, Business $tenant, Location $location): JsonResponse
    {
        $this->denyUnlessCapability('tenants.manage');
        abort_unless($location->business_id === $tenant->id, 404);

        $studies = \App\Models\Appointment::forClinic($tenant->id)->where('location_id', $location->id)->count();
        abort_unless($studies === 0, 422, "This facility is referenced by {$studies} study record(s) — reassign or archive those studies first.");

        $name = $location->name;
        $location->delete();

        AuditLog::record('tenant_facility_deleted', $location, [
            'summary' => "Platform deleted facility {$name} from {$tenant->name}.".$this->actorNote(),
        ], $tenant->id);

        return $this->ok(['deleted' => true]);
    }

    // ==================== internals ====================

    /**
     * Attach the tenant's own laratrust role (portal role vocabulary). This is
     * a REPLACE within the tenant: the user ends up holding exactly one of the
     * tenant's roles, while roles owned by OTHER tenants stay untouched
     * (multi-tenant members keep their memberships elsewhere).
     */
    private function assignTenantRole(Business $tenant, User $user, string $portalRole): void
    {
        $roleName = match ($portalRole) {
            'radiologist' => 'radiologist',
            'technologist' => 'technician',
            'receptionist' => 'receptionist',
            'billing' => 'billing',
            'admin' => 'admin',
            default => 'receptionist',
        };

        $role = \App\Models\Role::where('name', $roleName)
            ->where('guard_name', 'web')
            ->where('created_by', $tenant->created_by)
            ->first();

        if (! $role) {
            return;
        }

        $supersededIds = \App\Models\Role::where('guard_name', 'web')
            ->where('created_by', $tenant->created_by)
            ->where('id', '!=', $role->id)
            ->pluck('id')
            ->all();

        if ($supersededIds !== []) {
            $user->roles()->detach($supersededIds);
        }
        $user->roles()->syncWithoutDetaching([$role->id]);

        if (method_exists($user, 'flushCache')) {
            $user->flushCache();
        }
    }

    /**
     * A tenant must always keep one reachable administrator: an active,
     * login-enabled user that is either the owner (type admin) or holds the
     * tenant's admin role.
     */
    private function holdsAdminRole(Business $tenant, User $user): bool
    {
        if ($user->type === 'admin') {
            return true;
        }

        $adminRoleId = \App\Models\Role::where('name', 'admin')
            ->where('guard_name', 'web')
            ->where('created_by', $tenant->created_by)
            ->value('id');

        return (bool) ($adminRoleId && $user->hasRole('admin'));
    }

    private function otherActiveAdminExists(Business $tenant, User $except): bool
    {
        $adminRoleId = \App\Models\Role::where('name', 'admin')
            ->where('guard_name', 'web')
            ->where('created_by', $tenant->created_by)
            ->value('id');

        return User::query()
            ->where('business_id', $tenant->id)
            ->where('active_status', 1)
            ->where('is_enable_login', 1)
            ->where('id', '!=', $except->id)
            ->where(function ($q) use ($adminRoleId) {
                $q->where('type', 'admin');
                if ($adminRoleId) {
                    $q->orWhereHas('roles', fn ($r) => $r->where('roles.id', $adminRoleId));
                }
            })
            ->exists();
    }

    /** Cookie sessions are database-backed — revoke them on access changes. */
    private function revokeUserSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    private function actorNote(): string
    {
        return ' Acting platform user: '.$this->actor()->email.'.';
    }

    private function tenantUserShape(User $u): array
    {
        return [
            'id' => (string) $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->portalRole(),
            'isAdmin' => $u->type === 'admin',
            'active' => (bool) $u->active_status,
            'loginEnabled' => (bool) $u->is_enable_login,
            'lastLogin' => $u->last_login_at?->toIso8601String(),
        ];
    }

    private function facilityShape(Location $l): array
    {
        return [
            'id' => (string) $l->id,
            'name' => $l->name,
            'address' => (string) $l->address,
            'phone' => (string) ($l->phone ?? ''),
            'description' => (string) ($l->description ?? ''),
        ];
    }

    private function lifecycleResponse(Business $tenant): JsonResponse
    {
        return $this->ok(['tenant' => ApiShape::tenant($tenant->fresh('plan'))]);
    }
}
