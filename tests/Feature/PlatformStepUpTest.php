<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Control-plane step-up re-authentication: every mutating platform action
 * requires a fresh password confirmation (428 gate) — an attended-but-
 * unlocked session must not be able to suspend a clinic, rewrite white-label
 * branding or provision tenants. Reads stay open. Also covers the tenant-side
 * READ-ONLY branding view (visibility without write access).
 */
class PlatformStepUpTest extends ApiTestCase
{
    private function makePlatformUser(string $role = 'super_admin'): User
    {
        return User::create([
            'name' => "Platform {$role}",
            'email' => "stepup.{$role}.".md5(uniqid('', true)).'@test.local',
            'password' => 'R1s!T3st#2026x',
            'email_verified_at' => now(),
            'type' => $role === 'super_admin' ? 'super_admin' : 'platform_admin',
            'platform_role' => $role === 'super_admin' ? null : $role,
            'active_status' => 1,
            'lang' => 'en',
        ]);
    }

    public function test_mutating_platform_action_requires_fresh_confirmation(): void
    {
        $super = $this->makePlatformUser();

        $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/suspend", ['reason' => 'step-up probe'])
            ->assertStatus(428)
            ->assertJsonPath('error', 'step_up_required');

        // The suspend must NOT have happened.
        $this->assertDatabaseHas('businesses', [
            'id' => $this->businessA->id,
            'subscription_status' => 'active',
        ]);
    }

    public function test_reads_are_not_gated_by_step_up(): void
    {
        $super = $this->makePlatformUser();

        $this->actingAs($super)->getJson('/api/v1/platform/tenants')->assertOk();
        $this->actingAs($super)
            ->getJson("/api/v1/platform/tenants/{$this->businessA->id}/branding")
            ->assertOk();
    }

    public function test_confirm_with_wrong_password_is_refused_and_not_stamped(): void
    {
        $super = $this->makePlatformUser();

        $this->stateful();
        $this->actingAs($super)
            ->postJson('/api/v1/platform/step-up', ['password' => 'wrong-password'])
            ->assertStatus(422);

        // Still gated afterwards.
        $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/suspend", ['reason' => 'step-up probe'])
            ->assertStatus(428);
    }

    public function test_confirm_then_mutate_flows_end_to_end_and_is_audited(): void
    {
        $super = $this->makePlatformUser();
        $this->stateful();

        // Status endpoint reports a confirmation is required.
        $this->actingAs($super)
            ->getJson('/api/v1/platform/step-up')
            ->assertOk()
            ->assertJsonPath('data.required', true);

        $this->actingAs($super)
            ->postJson('/api/v1/platform/step-up', ['password' => 'R1s!T3st#2026x'])
            ->assertOk()
            ->assertJsonPath('data.confirmed', true);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $super->id,
            'action' => 'platform_step_up_confirmed',
        ]);

        // Status endpoint now reports fresh confirmation.
        $this->actingAs($super)
            ->getJson('/api/v1/platform/step-up')
            ->assertOk()
            ->assertJsonPath('data.required', false);

        // The originally blocked mutation now succeeds.
        $this->actingAs($super)
            ->confirmStepUp()->postJson("/api/v1/platform/tenants/{$this->businessA->id}/suspend", ['reason' => 'step-up e2e'])
            ->assertOk();

        $this->assertDatabaseHas('businesses', [
            'id' => $this->businessA->id,
            'subscription_status' => 'suspended',
        ]);
    }

    public function test_confirmation_is_per_session_not_shared(): void
    {
        $superA = $this->makePlatformUser();
        $superB = $this->makePlatformUser();

        // superA confirms within a stateful session.
        $this->stateful();
        $this->actingAs($superA)
            ->postJson('/api/v1/platform/step-up', ['password' => 'R1s!T3st#2026x'])
            ->assertOk();

        // A DIFFERENT platform session holds no confirmation -> still gated.
        $this->flushHeaders(); // simulate an independent (sessionless) client
        $this->actingAs($superB)
            ->postJson('/api/v1/platform/tenants', [
                'name' => 'Nope', 'adminName' => 'Y', 'adminEmail' => 'stepup.nope@test.local',
            ])
            ->assertStatus(428);
    }

    public function test_tenant_identity_cannot_use_step_up_endpoints(): void
    {
        // Tenant admins are refused by the platform guard itself.
        $this->actingAs($this->adminA)
            ->getJson('/api/v1/platform/step-up')
            ->assertStatus(403);

        $this->actingAs($this->adminA)
            ->postJson('/api/v1/platform/step-up', ['password' => 'anything'])
            ->assertStatus(403);
    }

    public function test_gate_can_be_disabled_by_config(): void
    {
        $super = $this->makePlatformUser();

        $this->withoutStepUp();
        $this->stateful();

        $this->actingAs($super)
            ->confirmStepUp()->postJson("/api/v1/platform/tenants/{$this->businessA->id}/suspend", ['reason' => 'kill-switch'])
            ->assertOk();

        $this->assertDatabaseHas('businesses', [
            'id' => $this->businessA->id,
            'subscription_status' => 'suspended',
        ]);
    }

    public function test_tenant_admin_sees_own_branding_read_only(): void
    {
        // Give tenant A a branding override so `overridden` is true.
        $this->stateful();
        $this->actingAs($this->makePlatformUser())
            ->confirmStepUp()->putJson("/api/v1/platform/tenants/{$this->businessA->id}/branding", [
                'appName' => 'Alpha Branded Portal',
            ])
            ->assertOk();

        // Tenant admins view their own brand (sessionless client here — the
        // read endpoint is open to tenant identities, no session needed).
        $this->flushHeaders();
        $this->actingAs($this->adminA)
            ->getJson('/api/v1/settings/branding')
            ->assertOk()
            ->assertJsonPath('data.branding.appName', 'Alpha Branded Portal')
            ->assertJsonPath('data.overridden', true)
            ->assertJsonPath('data.managedBy', 'platform');

        // Tenant B never sees tenant A's brand.
        $this->actingAs($this->adminB)
            ->getJson('/api/v1/settings/branding')
            ->assertOk()
            ->assertJsonPath('data.overridden', false)
            ->assertJsonPath('data.branding.appName', fn ($v) => $v !== 'Alpha Branded Portal');
    }
}
