<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Full tenant manageability from the control plane: profile/subscription
 * editing, tenant user administration (create / edit / password rotation /
 * access revocation with last-admin protection) and facility CRUD (with the
 * study-reference guard). Every mutation is capability-gated and audited.
 */
class PlatformTenantManageTest extends ApiTestCase
{
    private function makePlatformUser(string $role): User
    {
        return User::create([
            'name' => "Platform {$role}",
            'email' => "platform.{$role}.".md5(uniqid('', true)).'@test.local',
            'password' => 'R1s!T3st#2026x',
            'email_verified_at' => now(),
            'type' => $role === 'super_admin' ? 'super_admin' : 'platform_admin',
            'platform_role' => $role === 'super_admin' ? null : $role,
            'active_status' => 1,
            'lang' => 'en',
        ]);
    }

    private function superAdmin(): User
    {
        return $this->makePlatformUser('super_admin');
    }

    // ==================== profile / subscription ====================

    public function test_super_admin_can_rename_tenant_and_change_plan_terms(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super)
            ->patchJson("/api/v1/platform/tenants/{$this->businessA->id}", [
                'name' => 'Alpha Health Group',
                'subscriptionEndsAt' => now()->addMonths(6)->toDateString(),
            ])
            ->assertOk();

        $this->assertDatabaseHas('businesses', ['id' => $this->businessA->id, 'name' => 'Alpha Health Group']);

        // The tenant plane reflects the new identity (no stale caches).
        $bootstrap = $this->actingAs($this->adminA)->getJson('/api/v1/bootstrap')->assertOk();
        $this->assertStringContainsString('Alpha Health Group', $bootstrap->decodeResponseJson()['data']['user']['businessName'] ?? '');
    }

    public function test_billing_role_can_edit_subscription_but_not_manage_users(): void
    {
        $billing = $this->makePlatformUser('billing');
        $super = $this->superAdmin();

        // billing holds subscriptions.manage.
        $this->actingAs($billing)
            ->patchJson("/api/v1/platform/tenants/{$this->businessA->id}", ['trialEndsAt' => now()->addDays(10)->toDateString()])
            ->assertOk();

        // billing does NOT hold tenants.manage.
        $this->actingAs($billing)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/users", [
                'name' => 'X', 'email' => 'x.'.uniqid().'@test.local', 'role' => 'receptionist',
            ])
            ->assertStatus(403);

        // Keep the super admin referenced for symmetry with other tests.
        $this->assertNotNull($super->id);
    }

    // ==================== tenant user administration ====================

    public function test_platform_can_create_tenant_user_with_generated_one_time_password(): void
    {
        $super = $this->superAdmin();
        $email = 'tech.'.uniqid().'@test.local';

        $res = $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/users", [
                'name' => 'New Technologist', 'email' => $email, 'role' => 'technologist',
            ])
            ->assertStatus(201);

        $body = $res->decodeResponseJson()['data'];
        $this->assertNotEmpty($body['initialPassword'], 'generated password must be handed over once');
        $this->assertLessThanOrEqual(60, strlen($body['initialPassword']));
        $this->assertEquals('technologist', $body['user']['role']);

        // The handed-over password actually logs into the tenant plane.
        $this->postJson('/api/v1/login', ['email' => $email, 'password' => $body['initialPassword']])->assertOk();

        // Membership row exists so the user can be switched/managed coherently.
        $this->assertDatabaseHas('tenant_memberships', [
            'business_id' => $this->businessA->id, 'role' => 'technologist',
        ]);
    }

    public function test_platform_can_edit_user_identity_and_role(): void
    {
        $super = $this->superAdmin();
        $staff = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        $this->actingAs($super)
            ->patchJson("/api/v1/platform/tenants/{$this->businessA->id}/users/{$staff->id}", [
                'name' => 'Renamed Staff', 'role' => 'billing',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'billing');

        $this->assertDatabaseHas('users', ['id' => $staff->id, 'name' => 'Renamed Staff']);
        $this->assertDatabaseHas('tenant_memberships', [
            'user_id' => $staff->id, 'business_id' => $this->businessA->id, 'role' => 'billing',
        ]);
    }

    public function test_password_rotation_revokes_sessions_and_old_password_stops_working(): void
    {
        $super = $this->superAdmin();
        $staff = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        // Give the user a live session to prove revocation.
        $this->actingAs($staff)->getJson('/api/v1/bootstrap')->assertOk();
        $sessionCount = DB::table('sessions')->where('user_id', $staff->id)->count();
        if ($sessionCount === 0) {
            $this->markTestSkipped('session driver does not expose rows in this environment');
        }

        $res = $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/users/{$staff->id}/reset-password")
            ->assertOk();

        $newPassword = $res->decodeResponseJson()['data']['newPassword'];
        $this->assertNotEmpty($newPassword);
        $this->assertEquals(0, DB::table('sessions')->where('user_id', $staff->id)->count());

        // Old password no longer authenticates; new one does.
        $this->postJson('/api/v1/login', ['email' => $staff->email, 'password' => 'R1s!T3st#2026x'])->assertStatus(401);
        $this->postJson('/api/v1/login', ['email' => $staff->email, 'password' => $newPassword])->assertOk();
    }

    public function test_disabling_login_revokes_sessions_but_last_admin_is_protected(): void
    {
        $super = $this->superAdmin();
        $extraAdmin = $this->makeStaff($this->businessA, $this->adminA, 'admin');

        // With two active admins, revoking one is fine.
        $this->actingAs($super)
            ->patchJson("/api/v1/platform/tenants/{$this->businessA->id}/users/{$extraAdmin->id}", ['loginEnabled' => false])
            ->assertOk();

        // Revoking the owner too must be refused — no active admin would remain.
        $this->actingAs($super)
            ->patchJson("/api/v1/platform/tenants/{$this->businessA->id}/users/{$this->adminA->id}", ['loginEnabled' => false])
            ->assertStatus(422);

        // Demoting the last admin's role is equally refused.
        $this->actingAs($super)
            ->patchJson("/api/v1/platform/tenants/{$this->businessA->id}/users/{$this->adminA->id}", ['role' => 'receptionist'])
            ->assertStatus(422);
    }

    public function test_platform_identities_are_unreachable_through_tenant_user_routes(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super)
            ->patchJson("/api/v1/platform/tenants/{$this->businessA->id}/users/{$super->id}", ['name' => 'Hacked'])
            ->assertStatus(404);

        $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/users/{$super->id}/reset-password")
            ->assertStatus(404);
    }

    // ==================== facilities ====================

    public function test_facility_crud_with_study_reference_guard(): void
    {
        $super = $this->superAdmin();

        $created = $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/facilities", [
                'name' => 'Alpha Satellite Clinic', 'address' => '12.example Street', 'phone' => '+61 3 5555 0100',
            ])
            ->assertStatus(201);

        $facilityId = (int) $created->decodeResponseJson()['data']['facility']['id'];

        $this->actingAs($super)
            ->patchJson("/api/v1/platform/tenants/{$this->businessA->id}/facilities/{$facilityId}", ['name' => 'Alpha Satellite (renamed)'])
            ->assertOk()
            ->assertJsonPath('data.facility.name', 'Alpha Satellite (renamed)');

        // A facility referenced by a study cannot be deleted (the FK would
        // cascade-delete the tenant's studies).
        $service = \App\Models\Service::where('code', 'DX-CHEST-PA')->where('business_id', $this->businessA->id)->firstOrFail();
        $receptionist = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');
        $studyId = $this->actingAs($receptionist)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Facility Guard Patient', 'phone' => '+61 3 5555 0199', 'age' => 41, 'gender' => 'female'],
            'serviceId' => $service->id,
            'date' => now()->toDateString(),
            'time' => '10:00 AM',
            'priority' => 'routine',
        ])->assertCreated()->json('data.study.id');

        \App\Models\Appointment::where('id', $studyId)->update(['location_id' => $facilityId]);

        $this->actingAs($super)
            ->deleteJson("/api/v1/platform/tenants/{$this->businessA->id}/facilities/{$facilityId}")
            ->assertStatus(422);

        // A facility with no references deletes fine.
        $spare = Location::create(['name' => 'Unused', 'address' => '', 'business_id' => $this->businessA->id, 'created_by' => $this->adminA->id]);
        $this->actingAs($super)
            ->deleteJson("/api/v1/platform/tenants/{$this->businessA->id}/facilities/{$spare->id}")
            ->assertOk();

        // Cross-tenant facility access is a 404.
        $foreign = Location::create(['name' => 'Foreign', 'address' => '', 'business_id' => $this->businessB->id, 'created_by' => $this->adminB->id]);
        $this->actingAs($super)
            ->patchJson("/api/v1/platform/tenants/{$this->businessA->id}/facilities/{$foreign->id}", ['name' => 'Nope'])
            ->assertStatus(404);
    }
}
