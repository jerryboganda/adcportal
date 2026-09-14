<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\TenantBootstrap;

/**
 * Multi-tenant membership: a user with legitimate memberships in several
 * clinics can switch explicitly — and switching can never leak one clinic's
 * data, privileges, or cache into another.
 */
class TenantSwitchTest extends ApiTestCase
{
    private function multiTenantRadiologist(): array
    {
        // An existing user from tenant A is granted membership in tenant B.
        $user = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        TenantMembership::create([
            'user_id' => $user->id,
            'business_id' => $this->businessB->id,
            'role' => 'receptionist',
            'is_default' => false,
            'status' => 'active',
        ]);

        return [$user, $this->seedMemberRole($user, 'receptionist')];
    }

    /** Attach the B-tenant laratrust role so tenant-scoped checks resolve. */
    private function seedMemberRole(User $user, string $roleName): void
    {
        $role = \App\Models\Role::where('name', $roleName)
            ->where('guard_name', 'web')
            ->where('created_by', $this->adminB->id)
            ->first();

        if ($role) {
            $user->addRole($role);
            if (method_exists($user, 'flushCache')) {
                $user->flushCache();
            }
        }
    }

    public function test_switch_requires_membership(): void
    {
        $outsider = $this->makeStaff($this->businessA, $this->adminA, 'technologist');

        $this->actingAs($outsider)
            ->postJson('/api/v1/tenant/switch', ['businessId' => $this->businessB->id])
            ->assertStatus(403);
    }

    public function test_switch_to_a_nonexistent_tenant_is_a_safe_denial(): void
    {
        [$user] = $this->multiTenantRadiologist();

        $this->actingAs($user)
            ->postJson('/api/v1/tenant/switch', ['businessId' => 999999])
            ->assertStatus(403);
    }

    public function test_switch_moves_context_and_membership_list_follows(): void
    {
        [$user] = $this->multiTenantRadiologist();

        $memberships = $this->actingAs($user)->getJson('/api/v1/memberships')->assertOk()->decodeResponseJson();
        $ids = collect($memberships->json('data.memberships'))->pluck('businessId')->all();
        $this->assertEqualsCanonicalizing([$this->businessA->id, $this->businessB->id], $ids);

        $this->actingAs($user)->postJson('/api/v1/tenant/switch', ['businessId' => $this->businessB->id])
            ->assertOk()
            ->assertJsonPath('data.user.businessId', $this->businessB->id);

        $this->assertSame($this->businessB->id, $user->fresh()->business_id);
    }

    public function test_switched_user_sees_only_the_new_tenant_data(): void
    {
        [$user] = $this->multiTenantRadiologist();

        // Clinical data exists in A (user's origin tenant).
        $this->actingAs($this->adminA)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Origin Patient'],
            'serviceId' => $this->businessA->services()->first()->id,
            'date' => now()->toDateString(),
            'time' => '09:00 AM',
            'priority' => 'routine',
        ])->assertStatus(201);

        $this->actingAs($user)->postJson('/api/v1/tenant/switch', ['businessId' => $this->businessB->id])->assertOk();

        // After switching, the session is inside B: zero A data anywhere.
        $bootstrap = $this->actingAs($user)->getJson('/api/v1/bootstrap')->assertOk()->decodeResponseJson();
        foreach ($bootstrap->json('data.studies') as $study) {
            $this->assertStringNotContainsString('Origin Patient', json_encode($study));
        }
        $this->assertSame($this->businessB->id, (int) $bootstrap->json('data.user.businessId'));
    }

    public function test_cross_tenant_resource_ids_stay_inaccessible_after_switch(): void
    {
        [$user] = $this->multiTenantRadiologist();

        $this->actingAs($this->adminA)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Hidden Patient'],
            'serviceId' => $this->businessA->services()->first()->id,
            'date' => now()->toDateString(),
            'time' => '09:30 AM',
            'priority' => 'routine',
        ])->assertStatus(201);

        $studyId = \App\Models\Appointment::where('business_id', $this->businessA->id)->latest('id')->value('id');

        // The user was a radiologist in A — but their context is now B.
        $this->actingAs($user)->postJson('/api/v1/tenant/switch', ['businessId' => $this->businessB->id])->assertOk();

        // A-specific IDs must 404 from the B context (no existence leak).
        $this->actingAs($user)->putJson("/api/v1/studies/{$studyId}", ['priority' => 'stat'])->assertStatus(404);
    }

    public function test_privileges_do_not_travel_across_tenants(): void
    {
        // Receptionist-in-B, radiologist-in-A: after switching to B the user
        // must NOT hold radiologist powers that belong to tenant A only.
        [$user] = $this->multiTenantRadiologist();

        $this->actingAs($user)->postJson('/api/v1/tenant/switch', ['businessId' => $this->businessB->id])->assertOk();

        // 'report create' is a radiologist permission — in B the user is a
        // receptionist, so signing off a report must be refused.
        $this->actingAs($user)->postJson("/api/v1/studies/1/reports", [
            'findings' => 'x', 'impression' => 'y',
        ])->assertStatus(403);
    }

    public function test_memberships_endpoint_stays_reachable_for_suspended_tenant(): void
    {
        [$user] = $this->multiTenantRadiologist();

        // Suspend the user's CURRENT tenant — the escape hatch must survive.
        $this->businessA->forceFill(['subscription_status' => 'suspended'])->save();

        $this->actingAs($user)->getJson('/api/v1/memberships')->assertOk();
        $this->actingAs($user)->postJson('/api/v1/tenant/switch', ['businessId' => $this->businessB->id])->assertOk();
        $this->actingAs($user)->getJson('/api/v1/bootstrap')->assertOk();
    }

    public function test_platform_staff_cannot_use_tenant_switching(): void
    {
        $super = User::create([
            'name' => 'Platform', 'email' => 'pswitch.'.md5(uniqid('', true)).'@test.local',
            'password' => 'Secret#12345', 'email_verified_at' => now(),
            'type' => 'super_admin', 'active_status' => 1, 'lang' => 'en',
        ]);

        $this->actingAs($super)
            ->postJson('/api/v1/tenant/switch', ['businessId' => $this->businessA->id])
            ->assertStatus(403);
    }
}
