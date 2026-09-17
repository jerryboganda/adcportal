<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Control-plane authorization: platform routes require a platform identity
 * AND the exact capability. Tenant staff and guests are refused; platform
 * roles (ops/billing/support/auditor) hold distinct, non-overlapping powers;
 * user/seat/staff management stays impossible for platform roles.
 */
class PlatformAccessTest extends ApiTestCase
{
    private function makePlatformUser(string $role): User
    {
        $user = User::create([
            'name' => "Platform {$role}",
            'email' => "platform.{$role}.".md5(uniqid('', true)).'@test.local',
            'password' => 'R1s!T3st#2026x',
            'email_verified_at' => now(),
            'type' => $role === 'super_admin' ? 'super_admin' : 'platform_admin',
            'platform_role' => $role === 'super_admin' ? null : $role,
            'active_status' => 1,
            'lang' => 'en',
        ]);

        return $user;
    }

    public function test_guest_cannot_access_platform_routes(): void
    {
        $this->getJson('/api/v1/platform/overview')->assertStatus(401);
        $this->getJson('/api/v1/platform/tenants')->assertStatus(401);
    }

    public function test_tenant_staff_cannot_access_platform_routes(): void
    {
        $this->actingAs($this->adminA)
            ->getJson('/api/v1/platform/overview')
            ->assertStatus(403);

        $this->actingAs($this->adminA)
            ->getJson('/api/v1/platform/tenants')
            ->assertStatus(403);
    }

    public function test_tenant_admin_cannot_read_or_write_tenant_branding_via_platform_routes(): void
    {
        // White-label branding is platform-managed per tenant. A clinic or
        // hospital admin must get 403 on BOTH read and write — the platform
        // console is the only surface allowed to touch it.
        $this->actingAs($this->adminA)
            ->getJson("/api/v1/platform/tenants/{$this->businessA->id}/branding")
            ->assertStatus(403);

        $this->actingAs($this->adminA)
            ->confirmStepUp()->putJson("/api/v1/platform/tenants/{$this->businessA->id}/branding", [
                'appName' => 'Hijacked Name',
            ])
            ->assertStatus(403);

        // Same for the domain registry attached to branding.
        $this->actingAs($this->adminA)
            ->confirmStepUp()->postJson("/api/v1/platform/tenants/{$this->businessA->id}/domains", [
                'host' => 'evil.example.com',
            ])
            ->assertStatus(403);

        // And the branding row must be untouched after all attempts.
        $this->actingAs($this->makePlatformUser('super_admin'))
            ->getJson("/api/v1/platform/tenants/{$this->businessA->id}/branding")
            ->assertOk()
            ->assertJsonPath('data.branding.appName', fn ($v) => $v !== 'Hijacked Name');
    }

    public function test_super_admin_holds_every_capability(): void
    {
        $super = $this->makePlatformUser('super_admin');

        $this->actingAs($super)->getJson('/api/v1/platform/overview')->assertOk();
        $this->actingAs($super)->getJson('/api/v1/platform/tenants')->assertOk();
        $this->actingAs($super)->getJson('/api/v1/platform/plans')->assertOk();
        $this->actingAs($super)->getJson('/api/v1/platform/users')->assertOk();
        $this->actingAs($super)->getJson('/api/v1/platform/audit')->assertOk();
        $this->actingAs($super)->getJson('/api/v1/platform/support-sessions')->assertOk();
    }

    public function test_auditor_is_read_only(): void
    {
        $auditor = $this->makePlatformUser('auditor');

        // Reads allowed.
        $this->actingAs($auditor)->getJson('/api/v1/platform/overview')->assertOk();
        $this->actingAs($auditor)->getJson('/api/v1/platform/audit')->assertOk();

        // Mutations refused.
        $this->actingAs($auditor)->confirmStepUp()->postJson('/api/v1/platform/tenants', [
            'name' => 'X', 'adminName' => 'Y', 'adminEmail' => 'x@test.local',
        ])->assertStatus(403);

        $this->actingAs($auditor)
            ->confirmStepUp()->postJson("/api/v1/platform/tenants/{$this->businessA->id}/suspend", ['reason' => 'nope'])
            ->assertStatus(403);

        $this->actingAs($auditor)->confirmStepUp()->postJson('/api/v1/platform/plans', [
            'name' => 'Nope', 'priceMonthly' => 1, 'currency' => 'PKR', 'trialDays' => 1,
        ])->assertStatus(403);
    }

    public function test_billing_can_manage_plans_but_not_tenants(): void
    {
        $billing = $this->makePlatformUser('billing');

        $this->actingAs($billing)->getJson('/api/v1/platform/tenants')->assertOk();
        $this->actingAs($billing)->confirmStepUp()->postJson('/api/v1/platform/plans', [
            'name' => 'Billing Plan', 'priceMonthly' => 999, 'currency' => 'PKR', 'trialDays' => 7,
        ])->assertStatus(201)->assertJsonPath('data.plan.name', 'Billing Plan');

        $this->actingAs($billing)
            ->confirmStepUp()->postJson("/api/v1/platform/tenants/{$this->businessA->id}/suspend", ['reason' => 'nope'])
            ->assertStatus(403);
    }

    public function test_support_can_open_sessions_but_not_provision(): void
    {
        $support = $this->makePlatformUser('support');

        $this->actingAs($support)->confirmStepUp()->postJson('/api/v1/platform/tenants', [
            'name' => 'X', 'adminName' => 'Y', 'adminEmail' => 'x2@test.local',
        ])->assertStatus(403);

        $this->actingAs($support)->confirmStepUp()->postJson('/api/v1/platform/support-sessions', [
            'businessId' => $this->businessA->id,
            'reason' => 'Ticket 42 — reception cannot print invoices',
        ])->assertStatus(201);
    }

    public function test_only_super_admin_manages_platform_users(): void
    {
        $ops = $this->makePlatformUser('ops');
        $super = $this->makePlatformUser('super_admin');

        $this->actingAs($ops)->confirmStepUp()->postJson('/api/v1/platform/users', [
            'name' => 'New', 'email' => 'new.ops@test.local', 'password' => 'R1s!T3st#2026x', 'role' => 'ops',
        ])->assertStatus(403);

        $this->actingAs($super)->confirmStepUp()->postJson('/api/v1/platform/users', [
            'name' => 'New Ops', 'email' => 'new.ops.'.md5(uniqid('', true)).'@test.local', 'password' => 'R1s!T3st#2026x', 'role' => 'ops',
        ])->assertStatus(201);
    }

    public function test_last_super_admin_cannot_be_disabled(): void
    {
        $superA = $this->makePlatformUser('super_admin');
        $superB = $this->makePlatformUser('super_admin');

        $this->actingAs($superA)
            ->confirmStepUp()->patchJson("/api/v1/platform/users/{$superB->id}", ['isActive' => false])
            ->assertOk();

        // superB disabled; superA cannot disable themselves now.
        $this->actingAs($superA)
            ->confirmStepUp()->patchJson("/api/v1/platform/users/{$superA->id}", ['isActive' => false])
            ->assertStatus(422);
    }

    public function test_platform_admin_passwords_are_hashed_and_working(): void
    {
        $super = $this->makePlatformUser('super_admin');

        $this->actingAs($super)->confirmStepUp()->postJson('/api/v1/platform/users', [
            'name' => 'Creds', 'email' => 'creds.'.md5(uniqid('', true)).'@test.local', 'password' => 'R1s!T3st#2026x', 'role' => 'billing',
        ])->assertStatus(201);

        $created = User::where('email', 'like', 'creds.%@test.local')->first();
        $this->assertNotNull($created);
        $this->assertTrue(Hash::check('R1s!T3st#2026x', $created->password));
        $this->assertSame('platform_admin', $created->type);
    }
}
