<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Database\Seeders\TenantBootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shared fixture for the API suite: two fully-provisioned clinic tenants
 * with role-assigned staff, exactly as the SaaS signup path would create.
 */
abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected Business $businessA;
    protected Business $businessB;
    protected User $adminA;
    protected User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests reuse one PHP process: drop all per-request runtime
        // memoization so nothing leaks between RefreshDatabase rebuilds.
        \App\Services\TenantAuthorizer::flushAll();
        flush_active_business_cache();

        [$this->businessA, $this->adminA] = $this->makeTenant('Alpha Diagnostic Centre');
        [$this->businessB, $this->adminB] = $this->makeTenant('Beta Imaging Centre');
    }

    /** @return array{0: Business, 1: User} */
    protected function makeTenant(string $name): array
    {
        $admin = User::create([
            'name' => "Owner of {$name}",
            'email' => 'owner.'.md5($name.uniqid('', true)).'@test.local',
            'password' => 'R1s!T3st#2026x',
            'email_verified_at' => now(),
            'type' => 'admin',
            'active_status' => 1,
            'lang' => 'en',
        ]);

        $business = Business::create([
            'name' => $name,
            'form_type' => 'form-layout',
            'layouts' => 'Formlayout11',
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->addYear(),
            'tenant_code' => strtoupper(substr(md5($name), 0, 3)).'-'.random_int(1000, 9999),
            'created_by' => $admin->id,
            // Mirrors TenantLifecycleService::provision: every tenant is placed
            // on the operator's declared default deployment placement.
            'region' => array_key_first(config('ris.regions', ['default' => []])) ?: 'default',
            'deployment_stamp' => array_key_first(config('ris.deployment_stamps', ['stamp-a' => []])) ?: 'stamp-a',
            'isolation_profile' => 'pooled',
            'database_cluster' => 'primary',
            'storage_region' => array_key_first(config('ris.regions', ['default' => []])) ?: 'default',
        ]);

        $admin->forceFill([
            'business_id' => $business->id,
            'active_business' => $business->id,
            'created_by' => $business->id,
        ])->save();

        // Mirror production (register/provision): the owner holds a membership.
        \App\Models\TenantMembership::create([
            'user_id' => $admin->id,
            'business_id' => $business->id,
            'role' => 'admin',
            'is_default' => true,
            'status' => 'active',
        ]);

        $admin->MakeRole();
        app(TenantBootstrap::class)->run($business, $admin);
        if (method_exists($admin, 'flushCache')) {
            $admin->flushCache();
        }

        // Self-diagnosing fixture: surface RBAC wiring problems with exact state.
        if (! $admin->hasRole('admin')) {
            $pivot = \DB::table('role_user')->where('user_id', $admin->id)->get();
            $rolesTable = \DB::table('roles')->get(['id', 'name', 'guard_name', 'created_by']);
            throw new \RuntimeException('owner missing admin role; pivot='.json_encode($pivot).' roles='.json_encode($rolesTable));
        }
        if (! $admin->isAbleTo('appointment create')) {
            throw new \RuntimeException('owner lacks appointment create; roles=['
                .implode(',', $admin->getRoles()).'] permissions=['
                .$admin->allPermissions()->pluck('name')->implode(',').']');
        }

        return [$business, $admin];
    }

    /**
     * Step-up re-auth control for platform-mutation tests.
     *
     * confirmStepUp(): stamp the acting user's session as freshly
     * re-authenticated (mirrors POST /platform/step-up) before the request.
     * withoutStepUp(): disable the gate entirely for suites that test other
     * concerns; a session-stamp is also fine, but this is explicit.
     */
    /**
     * actingAs with Sanctum's AuthenticateSession kept consistent: when a
     * stateful (SPA-origin) test client switches users mid-test, the session
     * still holds the previous user's password hash and the next request
     * would be logged out. Seeding the hash for the new acting user mirrors
     * what a real login writes to the session.
     */
    public function actingAs($user, $guard = null)
    {
        $this->session(['password_hash_web' => $user->password]);

        return parent::actingAs($user, $guard);
    }

    /**
     * Make subsequent requests stateful (Sanctum SPA origin) so a session
     * exists and persists across calls — required by the step-up flow.
     */
    protected function stateful(): static
    {
        $this->withHeader('referer', 'http://localhost:3000/'); // SPA origin = stateful domain

        return $this;
    }

    protected function confirmStepUp(): static
    {
        // Stamp the (stateful) session as freshly re-authenticated. The
        // referer makes the request stateful (Sanctum) so a session exists;
        // the acting user's hash keeps AuthenticateSession consistent.
        $this->withHeader('referer', 'http://localhost:3000/');
        $this->session([
            'platform_step_up_at' => now()->getTimestamp(),
            'password_hash_web' => auth()->user()?->password,
        ]);

        return $this;
    }

    protected function withoutStepUp(): static
    {
        config(['ris.platform_step_up.enabled' => false]);

        return $this;
    }

    protected function makeStaff(Business $business, User $owner, string $portalRole): User
    {
        $roleName = match ($portalRole) {
            'radiologist' => 'radiologist',
            'technologist' => 'technician',
            'billing' => 'billing',
            'admin' => 'admin',
            default => 'receptionist',
        };

        $user = User::create([
            'name' => ucfirst($portalRole).' of '.$business->name.' '.uniqid(),
            'email' => $portalRole.'.'.md5($business->name.uniqid('', true)).'@test.local',
            'password' => 'R1s!T3st#2026x',
            'email_verified_at' => now(),
            'type' => 'staff',
            'active_status' => 1,
            'business_id' => $business->id,
            'created_by' => $business->id,
        ]);

        $role = \App\Models\Role::where('name', $roleName)
            ->where('guard_name', 'web')
            ->where('created_by', $owner->id)
            ->first();

        if ($role) {
            $user->addRole($role);
            if (method_exists($user, 'flushCache')) {
                $user->flushCache();
            }
            if (! $user->hasRole($roleName)) {
                throw new \RuntimeException("addRole({$roleName}) did not attach to user {$user->id}");
            }
        } else {
            throw new \RuntimeException("role {$roleName} (created_by={$owner->id}) not found");
        }

        // Mirror production: StaffUserController::store always records the
        // tenant membership alongside the account.
        \App\Models\TenantMembership::create([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'role' => $portalRole,
            'is_default' => false,
            'status' => 'active',
        ]);

        return $user;
    }
}
