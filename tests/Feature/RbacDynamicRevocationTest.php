<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

/**
 * Dynamic permission changes: a tenant admin edits a role and the change is
 * visible on the very next request of every affected member — including the
 * permissions version bump that drives SPA auto-refresh.
 */
class RbacDynamicRevocationTest extends ApiTestCase
{
    public function test_revoking_invoice_access_updates_me_version_and_api_immediately(): void
    {
        $billing = $this->makeStaff($this->businessA, $this->adminA, 'billing');

        $before = $this->actingAs($billing)->getJson('/api/v1/me')->assertOk()->json('data.user');
        $this->assertContains('invoice manage', $before['permissions']);

        $role = DB::table('roles')->where('name', 'billing')
            ->whereIn('created_by', \App\Models\User::where('business_id', $this->businessA->id)->pluck('id'))
            ->first();

        $remaining = collect(\App\Support\PermissionCatalog::defaultBundles()['billing'])
            ->reject(fn ($p) => str_starts_with($p, 'invoice'))
            ->push('billing view')
            ->values()
            ->all();

        $this->actingAs($this->adminA)
            ->putJson("/api/v1/access/roles/{$role->id}/permissions", ['permissions' => $remaining])
            ->assertOk();

        $after = $this->actingAs($billing)->getJson('/api/v1/me')->assertOk()->json('data.user');

        $this->assertNotContains('invoice manage', $after['permissions']);
        $this->assertGreaterThan($before['permissionsVersion'], $after['permissionsVersion'], 'permissions_version must move on RBAC changes');

        // And the enforced surface closes for the same session:
        $this->actingAs($billing)->getJson('/api/v1/invoices')->assertForbidden();
    }

    public function test_granting_a_permission_opens_the_surface_immediately(): void
    {
        $technician = $this->makeStaff($this->businessA, $this->adminA, 'technologist');
        $this->actingAs($technician)->getJson('/api/v1/audit-logs')->assertForbidden();

        $role = DB::table('roles')->where('name', 'technician')
            ->whereIn('created_by', \App\Models\User::where('business_id', $this->businessA->id)->pluck('id'))
            ->first();

        $perms = collect(\App\Support\PermissionCatalog::defaultBundles()['technician'])
            ->push('user logs history')
            ->values()
            ->all();

        $this->actingAs($this->adminA)
            ->putJson("/api/v1/access/roles/{$role->id}/permissions", ['permissions' => $perms])
            ->assertOk();

        $this->actingAs($technician)->getJson('/api/v1/audit-logs')->assertOk();

        $fresh = $this->actingAs($technician)->getJson('/api/v1/me')->json('data.user');
        $this->assertContains('user logs history', $fresh['permissions']);
    }

    public function test_tenant_isolation_of_the_version_counter(): void
    {
        $beforeA = $this->actingAs($this->adminA)->getJson('/api/v1/me')->json('data.user.permissionsVersion');
        $beforeB = $this->actingAs($this->adminB)->getJson('/api/v1/me')->json('data.user.permissionsVersion');

        $roleA = DB::table('roles')->where('name', 'radiologist')
            ->whereIn('created_by', \App\Models\User::where('business_id', $this->businessA->id)->pluck('id'))
            ->first();

        $this->actingAs($this->adminA)
            ->putJson("/api/v1/access/roles/{$roleA->id}/permissions", [
                'permissions' => \App\Support\PermissionCatalog::defaultBundles()['radiologist'],
            ])->assertOk();

        $afterA = $this->actingAs($this->adminA)->getJson('/api/v1/me')->json('data.user.permissionsVersion');
        $afterB = $this->actingAs($this->adminB)->getJson('/api/v1/me')->json('data.user.permissionsVersion');

        $this->assertGreaterThan($beforeA, $afterA, 'tenant A version must move');
        $this->assertEquals($beforeB, $afterB, 'tenant B version must not move');
    }
}
