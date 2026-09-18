<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Tenant-scoped per-user permission overrides (allow / deny) and the
 * effective-access provenance endpoint. Overrides must never leak across
 * tenants for multi-tenant members.
 */
class UserPermissionOverridesTest extends ApiTestCase
{
    private function overrideRoleGrant(User $admin, int $userId, array $allow = [], array $deny = []): void
    {
        $tenantId = $admin->business_id;
        DB::table('user_permission_overrides')->where('business_id', $tenantId)->where('user_id', $userId)->delete();

        foreach (['allow' => $allow, 'deny' => $deny] as $mode => $names) {
            foreach ($names as $name) {
                $permissionId = DB::table('permissions')->where('name', $name)->value('id');
                DB::table('user_permission_overrides')->insert([
                    'business_id' => $tenantId,
                    'user_id' => $userId,
                    'permission_id' => $permissionId,
                    'mode' => $mode,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function test_non_admin_cannot_manage_access_control(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $other = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        $this->actingAs($radiologist)
            ->putJson("/api/v1/access/users/{$other->id}/overrides", ['allow' => [], 'deny' => []])
            ->assertForbidden();

        $this->actingAs($radiologist)
            ->getJson("/api/v1/access/users/{$other->id}/effective")
            ->assertForbidden();
    }

    public function test_allow_override_grants_a_permission_the_role_lacks(): void
    {
        $technician = $this->makeStaff($this->businessA, $this->adminA, 'technologist');

        // Technicians have no billing permissions: the invoice list is 403.
        $this->actingAs($technician)->getJson('/api/v1/invoices')->assertForbidden();

        $this->actingAs($this->adminA)
            ->putJson("/api/v1/access/users/{$technician->id}/overrides", [
                'allow' => ['invoice manage'], 'deny' => [],
            ])->assertOk();

        // Grant is live immediately…
        $this->actingAs($technician)->getJson('/api/v1/invoices')->assertOk();

        // …and the provenance endpoint reports the source.
        $access = $this->actingAs($this->adminA)
            ->getJson("/api/v1/access/users/{$technician->id}/effective")
            ->assertOk()
            ->json('data.access');

        $this->assertContains('invoice manage', $access['allowedOverrides']);
        $this->assertContains('invoice manage', $access['permissions']);
        $this->assertNotContains('invoice manage', $access['rolePermissions']);
    }

    public function test_deny_override_subtracts_a_permission_the_role_grants(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        // Radiologists can list studies (appointment manage) by default.
        $this->actingAs($radiologist)->getJson('/api/v1/studies')->assertOk();

        $this->actingAs($this->adminA)
            ->putJson("/api/v1/access/users/{$radiologist->id}/overrides", [
                'allow' => [], 'deny' => ['appointment manage'],
            ])->assertOk();

        $this->actingAs($radiologist)->getJson('/api/v1/studies')->assertForbidden();

        $access = $this->actingAs($this->adminA)
            ->getJson("/api/v1/access/users/{$radiologist->id}/effective")
            ->assertOk()
            ->json('data.access');

        $this->assertContains('appointment manage', $access['deniedOverrides']);
        $this->assertNotContains('appointment manage', $access['permissions']);
    }

    public function test_structured_denial_identifies_the_missing_permission(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $this->actingAs($this->adminA)
            ->putJson("/api/v1/access/users/{$radiologist->id}/overrides", [
                'allow' => [], 'deny' => ['appointment manage'],
            ])->assertOk();

        $response = $this->actingAs($radiologist)->getJson('/api/v1/studies')->assertForbidden();

        $this->assertEquals('permission.denied', $response->json('error'));
        $this->assertEquals('appointment manage', $response->json('permission'));
    }

    public function test_permission_cannot_be_both_allowed_and_denied(): void
    {
        $staff = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        $this->actingAs($this->adminA)
            ->putJson("/api/v1/access/users/{$staff->id}/overrides", [
                'allow' => ['report create'], 'deny' => ['report create'],
            ])->assertStatus(422);
    }

    public function test_overrides_are_tenant_scoped_for_multi_tenant_members(): void
    {
        // One identity, two tenants: receptionist in A, radiologist in B.
        $member = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');
        $roleB = \App\Models\Role::where('name', 'radiologist')
            ->whereIn('created_by', User::where('business_id', $this->businessB->id)->pluck('id'))
            ->firstOrFail();
        $member->addRole($roleB);
        \App\Models\TenantMembership::create([
            'user_id' => $member->id,
            'business_id' => $this->businessB->id,
            'role' => 'radiologist',
            'is_default' => false,
            'status' => 'active',
        ]);

        // Deny `study screen` only in tenant A.
        $this->actingAs($this->adminA)
            ->putJson("/api/v1/access/users/{$member->id}/overrides", [
                'allow' => [], 'deny' => ['study screen'],
            ])->assertOk();

        $permissionsA = $this->actingAs($member)->getJson('/api/v1/me')->json('data.user.permissions');
        $this->assertNotContains('study screen', $permissionsA);

        // In tenant B the same identity keeps the full radiologist set
        // (mirror the server-side tenant switch: active context moves to B).
        $member->forceFill([
            'business_id' => $this->businessB->id,
            'active_business' => $this->businessB->id,
        ])->save();
        \App\Services\TenantAuthorizer::flushAll();
        flush_active_business_cache();

        $permissionsB = $this->actingAs($member)->getJson('/api/v1/me')->json('data.user.permissions');
        $this->assertContains('study screen', $permissionsB);
        $this->assertContains('report sign', $permissionsB);
    }
}
