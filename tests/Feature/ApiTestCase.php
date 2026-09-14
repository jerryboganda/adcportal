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
            'password' => 'Secret#12345',
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
            'password' => 'Secret#12345',
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
