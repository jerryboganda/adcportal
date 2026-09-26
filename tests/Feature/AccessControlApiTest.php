<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Tenant RBAC administration API: /api/v1/access/*.
 * Covers role CRUD + guards, permission sync persistence, dependency
 * expansion, the admin lockout floor, non-admin denial, and cross-tenant
 * isolation (a tenant admin must never touch another tenant's roles).
 */
class AccessControlApiTest extends ApiTestCase
{
    // ==================== catalog ====================

    public function test_admin_can_read_the_permission_catalog(): void
    {
        $this->actingAs($this->adminA)
            ->getJson('/api/v1/access/catalog')
            ->assertOk()
            ->assertJsonStructure(['data' => ['catalog' => [['group', 'permissions' => [['name', 'label', 'dangerous', 'implies']]]]]]);

        $groups = collect($this->actingAs($this->adminA)->getJson('/api/v1/access/catalog')->json('data.catalog'));
        $names = $groups->flatMap(fn ($g) => collect($g['permissions'])->pluck('name'))->all();

        $this->assertContains('report sign', $names);
        $this->assertContains('role manage', $names);
        $this->assertContains('reception view', $names);
        $this->assertCount(count(PermissionCatalog::names()), $names);
    }

    public function test_non_admin_cannot_read_the_catalog_or_roles(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $this->actingAs($radiologist)->getJson('/api/v1/access/catalog')->assertForbidden();
        $this->actingAs($radiologist)->getJson('/api/v1/access/roles')->assertForbidden();
    }

    // ==================== roles listing ====================

    public function test_roles_listing_is_scoped_to_the_active_tenant(): void
    {
        $roles = collect($this->actingAs($this->adminA)->getJson('/api/v1/access/roles')->assertOk()->json('data.roles'));
        $names = $roles->pluck('name')->all();

        foreach (['admin', 'radiologist', 'technician', 'receptionist', 'billing'] as $system) {
            $this->assertContains($system, $names);
        }

        // Tenant A's list must not contain any role owned by tenant B users.
        $tenantBAdminIds = User::where('business_id', $this->businessB->id)->pluck('id');
        $foreignRoleIds = DB::table('roles')->whereIn('created_by', $tenantBAdminIds)->pluck('id')->map(fn ($v) => (string) $v);
        $this->assertEmpty(
            $roles->pluck('id')->intersect($foreignRoleIds),
            'tenant A can see tenant B roles'
        );
    }

    // ==================== role creation ====================

    public function test_admin_can_create_a_custom_role_and_assign_it_end_to_end(): void
    {
        $created = $this->actingAs($this->adminA)->postJson('/api/v1/access/roles', [
            'name' => 'Junior Radiologist',
            'displayName' => 'Junior Radiologist',
            'permissions' => ['user manage', 'user logs history'],
        ])->assertCreated()->json('data.role');

        $this->assertFalse($created['system']);
        $this->assertContains('user manage', $created['permissions']);

        // Assign the custom role to a new staff member by roleId.
        $this->actingAs($this->adminA)->postJson('/api/v1/staff', [
            'name' => 'Junior Doc',
            'email' => 'junior.'.uniqid().'@test.local',
            'password' => 'S3cret#Passw0rd!x',
            'roleId' => (int) $created['id'],
        ])->assertCreated();

        $junior = User::where('name', 'Junior Doc')->first();
        $this->assertNotNull($junior);

        // The custom role is live: the member may list staff (user manage)…
        $this->actingAs($junior)->getJson('/api/v1/staff')->assertOk();
        // …and see audit logs (user logs history — previously never granted).
        $this->actingAs($junior)->getJson('/api/v1/audit-logs')->assertOk();

        // …but cannot author reports (never granted). A real study is used so
        // the denial is the permission gate (403), not route binding (404).
        $svc = \App\Models\Service::where('code', 'DX-CHEST-PA')
            ->where('business_id', $this->businessA->id)->firstOrFail();
        $studyId = $this->actingAs($this->adminA)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Junior Probe', 'gender' => 'male'],
            'serviceId' => $svc->id,
            'date' => now()->toDateString(),
            'time' => '3:00 PM',
            'priority' => 'routine',
        ])->assertCreated()->json('data.study.id');

        $this->actingAs($junior)->postJson("/api/v1/studies/{$studyId}/reports", [
            'findings' => 'x', 'impression' => 'y',
        ])->assertForbidden();
    }

    public function test_custom_role_names_must_be_unique_per_tenant(): void
    {
        $this->actingAs($this->adminA)->postJson('/api/v1/access/roles', [
            'name' => 'Night Radiologist', 'permissions' => [],
        ])->assertCreated();

        $this->actingAs($this->adminA)->postJson('/api/v1/access/roles', [
            'name' => 'Night Radiologist', 'permissions' => [],
        ])->assertStatus(422);

        // Same name in ANOTHER tenant is fine (roles are tenant-scoped).
        $this->actingAs($this->adminB)->postJson('/api/v1/access/roles', [
            'name' => 'Night Radiologist', 'permissions' => [],
        ])->assertCreated();
    }

    public function test_unknown_permission_names_are_rejected(): void
    {
        // A crafted payload can never inject permissions outside the catalog —
        // and the catalog contains no platform capabilities at all.
        $this->actingAs($this->adminA)->postJson('/api/v1/access/roles', [
            'name' => 'Escalator', 'permissions' => ['platform.super_admin'],
        ])->assertStatus(422);

        // Address the role by its real id, never a hardcoded 1. Role ids come
        // from a shared sequence across every tenant, so "role 1" is only the
        // admin's role by luck of insertion order: on PostgreSQL tenant A's
        // roles start higher, and tenantRoleOrFail() correctly 404'd a role
        // belonging to another tenant. That guard is the point of the test.
        $roleId = $this->systemRoleId($this->adminA, 'radiologist');

        $this->actingAs($this->adminA)->putJson("/api/v1/access/roles/{$roleId}/permissions", [
            'permissions' => ['not a permission'],
        ])->assertStatus(422);
    }

    // ==================== permission sync ====================

    public function test_saving_role_permissions_persists_and_expands_dependencies(): void
    {
        $roleId = $this->systemRoleId($this->adminA, 'radiologist');

        // `report sign` implies `report edit` implies `report manage`.
        $updated = $this->actingAs($this->adminA)
            ->putJson("/api/v1/access/roles/{$roleId}/permissions", [
                'permissions' => ['reports view', 'report sign', 'appointment manage'],
            ])
            ->assertOk()
            ->json('data.role');

        foreach (['reports view', 'report sign', 'report edit', 'report manage', 'appointment manage'] as $perm) {
            $this->assertContains($perm, $updated['permissions']);
        }
        $this->assertNotContains('invoice manage', $updated['permissions']);

        // Enforced behavior follows immediately: a radiologist can still sign
        // (has report sign)…
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $this->assertTrue(str_contains(
            collect($this->actingAs($radiologist)->getJson('/api/v1/me')->json('data.user.permissions'))->join(','),
            'report sign'
        ));
    }

    public function test_revoking_a_permission_takes_effect_on_the_next_request(): void
    {
        $billing = $this->makeStaff($this->businessA, $this->adminA, 'billing');
        $this->actingAs($billing)->getJson('/api/v1/invoices')->assertOk();

        $roleId = $this->systemRoleId($this->adminA, 'billing');
        $remaining = collect(PermissionCatalog::defaultBundles()['billing'])
            ->reject(fn ($p) => in_array($p, ['invoice manage', 'invoice create', 'invoice edit', 'invoice delete', 'invoice payment', 'invoice refund', 'billing view', 'customer manage', 'customer create', 'customer edit'], true))
            ->values()
            ->all();

        $this->actingAs($this->adminA)
            ->putJson("/api/v1/access/roles/{$roleId}/permissions", ['permissions' => $remaining])
            ->assertOk();

        // Same session, next request: authorization fails — no stale cache.
        $this->actingAs($billing)->getJson('/api/v1/invoices')->assertForbidden();
    }

    public function test_admin_role_cannot_lose_the_lockout_floor(): void
    {
        $roleId = $this->systemRoleId($this->adminA, 'admin');

        $updated = $this->actingAs($this->adminA)
            ->putJson("/api/v1/access/roles/{$roleId}/permissions", [
                'permissions' => ['queue view'], // attempts to strip everything
            ])
            ->assertOk()
            ->json('data.role');

        foreach (PermissionCatalog::ADMIN_LOCKOUT_FLOOR as $perm) {
            $this->assertContains($perm, $updated['permissions'], "admin lost {$perm}");
        }
    }

    // ==================== duplication & deletion guards ====================

    public function test_role_can_be_duplicated_and_the_copy_is_independent(): void
    {
        $roleId = $this->systemRoleId($this->adminA, 'radiologist');

        $copy = $this->actingAs($this->adminA)
            ->postJson("/api/v1/access/roles/{$roleId}/duplicate", ['name' => 'Senior Radiologist'])
            ->assertCreated()
            ->json('data.role');

        $this->assertEqualsCanonicalizing(
            collect($this->actingAs($this->adminA)->getJson("/api/v1/access/roles")->json('data.roles'))
                ->firstWhere('id', (string) $roleId)['permissions'],
            $copy['permissions']
        );
        $this->assertFalse($copy['system']);

        // Shrink the copy — the system role must stay intact.
        $this->actingAs($this->adminA)
            ->putJson("/api/v1/access/roles/{$copy['id']}/permissions", ['permissions' => ['reports view']])
            ->assertOk();

        $original = collect($this->actingAs($this->adminA)->getJson('/api/v1/access/roles')->json('data.roles'))
            ->firstWhere('id', (string) $roleId);
        $this->assertContains('report sign', $original['permissions']);
    }

    public function test_admin_role_cannot_be_deleted(): void
    {
        $roleId = $this->systemRoleId($this->adminA, 'admin');

        $this->actingAs($this->adminA)
            ->deleteJson("/api/v1/access/roles/{$roleId}")
            ->assertStatus(403);
    }

    public function test_role_in_use_cannot_be_deleted(): void
    {
        $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $roleId = $this->systemRoleId($this->adminA, 'radiologist');

        $this->actingAs($this->adminA)
            ->deleteJson("/api/v1/access/roles/{$roleId}")
            ->assertStatus(422);
    }

    public function test_unused_custom_role_can_be_deleted(): void
    {
        $created = $this->actingAs($this->adminA)->postJson('/api/v1/access/roles', [
            'name' => 'Temporary', 'permissions' => [],
        ])->assertCreated()->json('data.role');

        $this->actingAs($this->adminA)
            ->deleteJson("/api/v1/access/roles/{$created['id']}")
            ->assertOk();
    }

    // ==================== cross-tenant isolation ====================

    public function test_tenant_admin_cannot_read_or_mutate_other_tenant_roles(): void
    {
        $foreignRoleId = $this->systemRoleId($this->adminB, 'radiologist');

        $this->actingAs($this->adminA)
            ->putJson("/api/v1/access/roles/{$foreignRoleId}/permissions", ['permissions' => []])
            ->assertNotFound();

        $this->actingAs($this->adminA)
            ->postJson("/api/v1/access/roles/{$foreignRoleId}/duplicate")
            ->assertNotFound();

        $this->actingAs($this->adminA)
            ->deleteJson("/api/v1/access/roles/{$foreignRoleId}")
            ->assertNotFound();
    }

    public function test_privilege_escalation_is_structurally_impossible(): void
    {
        // No tenant endpoint accepts platform capabilities: they are config,
        // not catalog rows. A role sync cannot smuggle them in.
        $radiologistId = $this->systemRoleId($this->adminA, 'radiologist');
        $this->actingAs($this->adminA)
            ->putJson("/api/v1/access/roles/{$radiologistId}/permissions", [
                'permissions' => ['tenants.manage', '*'],
            ])
            ->assertStatus(422);

        // A staff user cannot self-assign: role assignment requires `user edit`
        // and only accepts roles of the acting tenant.
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $adminRoleId = $this->systemRoleId($this->adminA, 'admin');
        $this->actingAs($radiologist)
            ->postJson('/api/v1/staff', [
                'name' => 'Self', 'email' => 'self.'.uniqid().'@test.local',
                'password' => 'S3cret#Passw0rd!x', 'roleId' => (int) $adminRoleId,
            ])->assertForbidden();
    }

    // ==================== audit ====================

    public function test_role_permission_changes_are_audited_with_old_and_new(): void
    {
        $roleId = $this->systemRoleId($this->adminA, 'billing');
        $role = DB::table('roles')->where('id', $roleId)->first();

        $this->actingAs($this->adminA)
            ->putJson("/api/v1/access/roles/{$roleId}/permissions", ['permissions' => ['billing view']])
            ->assertOk();

        $log = AuditLog::query()->where('action', 'role_permissions_updated')
            ->where('subject_id', $roleId)
            ->latest('id')->first();

        $this->assertNotNull($log, 'role_permissions_updated audit entry missing');
        $changes = $log->changes;
        $this->assertNotEmpty($changes['old']);
        $this->assertEqualsCanonicalizing(['billing view'], $changes['new']);
    }

    // ==================== helpers ====================

    private function systemRoleId(User $owner, string $name): int
    {
        $adminIds = User::where('business_id', $owner->business_id)->pluck('id');
        $role = DB::table('roles')->where('name', $name)->whereIn('created_by', $adminIds)->first();
        $this->assertNotNull($role, "system role {$name} missing");

        return (int) $role->id;
    }
}
