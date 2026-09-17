<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\TenantLifecycleEvent;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\TenantLifecycleService;
use Illuminate\Support\Facades\Storage;

/**
 * The tenant lifecycle engine: platform provisioning, activation,
 * suspension, offboarding and termination — with audited, transactional
 * transitions and correct availability behavior at every state.
 */
class TenantLifecycleTest extends ApiTestCase
{
    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Platform Owner',
            'email' => 'super.'.md5(uniqid('', true)).'@test.local',
            'password' => 'R1s!T3st#2026x',
            'email_verified_at' => now(),
            'type' => 'super_admin',
            'active_status' => 1,
            'lang' => 'en',
        ]);
    }

    public function test_platform_provisioning_creates_fully_usable_tenant(): void
    {
        $super = $this->superAdmin();

        $response = $this->actingAs($super)->confirmStepUp()->postJson('/api/v1/platform/tenants', [
            'name' => 'Gamma Health Group',
            'adminName' => 'Gamma Owner',
            'adminEmail' => 'gamma-owner@test.local',
            'adminPassword' => null,
        ]);
        $response->assertStatus(201);
        $password = $response->json('data.initialAdminPassword');
        $this->assertNotEmpty($password, 'Platform provisioning must hand over an initial password exactly once.');

        $tenant = Business::where('name', 'Gamma Health Group')->first();
        $this->assertNotNull($tenant);
        $this->assertSame('trialing', $tenant->subscription_status);
        $this->assertNotNull($tenant->trial_ends_at);

        // Admin account works with the handed-over password.
        $this->postJson('/api/v1/login', ['email' => 'gamma-owner@test.local', 'password' => $password])
            ->assertOk();

        // Membership + lifecycle history recorded.
        $this->assertTrue(TenantMembership::where('user_id', $tenant->created_by)->where('business_id', $tenant->id)->where('role', 'admin')->exists());
        $events = TenantLifecycleEvent::where('business_id', $tenant->id)->pluck('event')->all();
        $this->assertContains('tenant_created', $events);
        $this->assertContains('provisioned', $events);
        $this->assertContains('activated', $events);
    }

    public function test_suspension_blocks_tenant_api_but_preserves_data(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($this->adminA)->getJson('/api/v1/bootstrap')->assertOk();

        $this->actingAs($super)
            ->confirmStepUp()->postJson("/api/v1/platform/tenants/{$this->businessA->id}/suspend", ['reason' => 'Non-payment'])
            ->assertOk()
            ->assertJsonPath('data.tenant.subscriptionStatus', 'suspended');

        // Tenant data still exists…
        $this->assertDatabaseHas('businesses', ['id' => $this->businessA->id, 'subscription_status' => 'suspended']);

        // …but the tenant plane is refused with 402.
        $this->actingAs($this->adminA)
            ->getJson('/api/v1/bootstrap')
            ->assertStatus(402)
            ->assertJsonPath('subscriptionStatus', 'suspended');

        // Lifecycle event + audit recorded.
        $this->assertTrue(TenantLifecycleEvent::where('business_id', $this->businessA->id)->where('event', 'tenant_suspended')->exists());
    }

    public function test_reactivation_restores_access(): void
    {
        $super = $this->superAdmin();
        $service = $this->businessA->services()->first();

        $this->actingAs($super)->confirmStepUp()->postJson("/api/v1/platform/tenants/{$this->businessA->id}/suspend", ['reason' => 'Audit hold'])->assertOk();
        $this->actingAs($this->adminA)->getJson('/api/v1/bootstrap')->assertStatus(402);

        $this->actingAs($super)->confirmStepUp()->postJson("/api/v1/platform/tenants/{$this->businessA->id}/reactivate", ['reason' => 'Resolved'])->assertOk();

        $this->actingAs($this->adminA)->getJson('/api/v1/bootstrap')->assertOk();
        $this->assertSame('active', $this->businessA->fresh()->subscription_status);
        $this->assertNotNull($service);
    }

    public function test_offboarding_revokes_logins_exports_data_and_starts_retention(): void
    {
        Storage::fake('local');
        $super = $this->superAdmin();

        $staff = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        $this->actingAs($super)
            ->confirmStepUp()->postJson("/api/v1/platform/tenants/{$this->businessA->id}/offboard", ['reason' => 'Contract ended'])
            ->assertOk()
            ->assertJsonPath('data.tenant.subscriptionStatus', 'offboarding');

        $tenant = $this->businessA->fresh();
        $this->assertSame('offboarding', $tenant->subscription_status);
        $this->assertNotNull($tenant->data_retention_until);
        $this->assertTrue($tenant->data_retention_until->isFuture());

        // Interactive logins revoked for tenant users (SQLite returns 0/1 ints).
        $this->assertSame(0, (int) $staff->fresh()->is_enable_login);
        $this->assertSame(0, (int) $this->adminA->fresh()->is_enable_login);

        // Retention export captured.
        $exports = Storage::disk('local')->files('tenant-exports');
        $this->assertNotEmpty($exports, 'Offboarding must capture a data export for the retention file.');
    }

    public function test_termination_requires_tenant_code_confirmation_and_blocks_login(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super)
            ->confirmStepUp()->postJson("/api/v1/platform/tenants/{$this->businessA->id}/terminate", ['confirmCode' => 'WRONG'])
            ->assertStatus(422);

        $this->actingAs($super)
            ->confirmStepUp()->postJson("/api/v1/platform/tenants/{$this->businessA->id}/terminate", ['confirmCode' => $this->businessA->tenant_code])
            ->assertOk()
            ->assertJsonPath('data.tenant.subscriptionStatus', 'terminated');

        // Terminated tenants cannot even sign in.
        $this->postJson('/api/v1/login', ['email' => $this->adminA->email, 'password' => 'R1s!T3st#2026x'])
            ->assertStatus(403)
            ->assertJsonPath('subscriptionStatus', 'terminated');

        // Data retained (never casually deleted).
        $this->assertDatabaseHas('businesses', ['id' => $this->businessA->id]);
    }

    public function test_provision_retry_is_idempotent_for_stuck_tenants(): void
    {
        $super = $this->superAdmin();

        // Simulate a tenant stuck in provisioning (bootstrap never finished).
        $admin = User::create([
            'name' => 'Stuck Owner', 'email' => 'stuck.'.md5(uniqid('', true)).'@test.local',
            'password' => 'R1s!T3st#2026x', 'email_verified_at' => now(), 'type' => 'admin', 'active_status' => 1, 'lang' => 'en',
        ]);
        $tenant = Business::create([
            'name' => 'Stuck Imaging', 'form_type' => 'form-layout', 'layouts' => 'Formlayout11',
            'subscription_status' => 'provisioning', 'trial_ends_at' => now()->addDays(14),
            'tenant_code' => 'STK-'.random_int(1000, 9999), 'created_by' => $admin->id,
        ]);
        $admin->forceFill(['business_id' => $tenant->id, 'active_business' => $tenant->id, 'created_by' => $tenant->id])->save();

        // Retry provisions it fully and activates the trial.
        $this->actingAs($super)->confirmStepUp()->postJson("/api/v1/platform/tenants/{$tenant->id}/provision-retry")
            ->assertOk()
            ->assertJsonPath('data.tenant.subscriptionStatus', 'trialing');

        // Retry again is refused: no longer awaiting provisioning.
        $this->actingAs($super)->confirmStepUp()->postJson("/api/v1/platform/tenants/{$tenant->id}/provision-retry")->assertStatus(422);
    }

    public function test_subscription_sweep_expires_finished_trials_and_terms(): void
    {
        $this->businessA->forceFill(['subscription_status' => 'trialing', 'trial_ends_at' => now()->subDay()])->save();
        $this->businessB->forceFill(['subscription_status' => 'active', 'subscription_ends_at' => now()->subDay()])->save();

        $this->artisan('ris:subscription-sweep')->assertExitCode(0);

        $this->assertSame('expired', $this->businessA->fresh()->subscription_status);
        $this->assertSame('expired', $this->businessB->fresh()->subscription_status);
        $this->assertTrue(TenantLifecycleEvent::where('business_id', $this->businessA->id)->where('event', 'subscription_expired')->exists());

        // Expired tenants are gated.
        $this->actingAs($this->adminA)->getJson('/api/v1/bootstrap')->assertStatus(402);
    }

    public function test_provisioning_via_lifecycle_service_is_transactional_on_failure(): void
    {
        // Inject a deterministic mid-provision failure (users.email has no
        // DB-level unique index, so a duplicate email alone won't throw).
        User::creating(function ($user) {
            if ($user->email === 'boom.mid-provision@test.local') {
                throw new \RuntimeException('simulated provision failure');
            }
        });

        try {
            app(TenantLifecycleService::class)->provision('Should Roll Back', [
                'name' => 'Doomed Owner',
                'email' => 'boom.mid-provision@test.local',
                'password' => 'R1s!T3st#2026x',
            ]);
            $this->fail('Provisioning should have thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated provision failure', $e->getMessage());
        } finally {
            User::flushEventListeners();
        }

        // The transaction rolled back: no tenant, no membership, no owner.
        $this->assertDatabaseMissing('businesses', ['name' => 'Should Roll Back']);
        $this->assertDatabaseMissing('users', ['email' => 'boom.mid-provision@test.local']);
        $this->assertDatabaseMissing('tenant_lifecycle_events', ['event' => 'tenant_created']);
    }
}
