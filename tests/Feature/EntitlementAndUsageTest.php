<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\TenantFeatureOverride;
use App\Models\UsageCounter;
use App\Models\User;

/**
 * Server-enforced entitlements: plan quotas (study volume, user seats),
 * module feature flags (plan features + platform overrides) and the usage
 * metering that feeds them. The SPA only mirrors what the server enforces.
 */
class EntitlementAndUsageTest extends ApiTestCase
{
    private function makePlan(array $overrides = []): Plan
    {
        return Plan::create([
            'name' => 'Quota Plan '.md5(uniqid('', true)),
            'slug' => 'quota-'.md5(uniqid('', true)),
            'price_monthly' => 5000,
            'currency' => 'PKR',
            'trial_days' => 7,
            'max_users' => 3,
            'max_studies_per_month' => 2,
            'is_active' => true,
            ...$overrides,
        ]);
    }

    private function bookStudy()
    {
        $service = $this->businessA->services()->first();

        return $this->actingAs($this->adminA)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Quota Patient '.uniqid()],
            'serviceId' => $service->id,
            'date' => now()->toDateString(),
            'time' => '10:00 AM',
            'priority' => 'routine',
        ]);
    }

    public function test_monthly_study_quota_is_enforced_server_side(): void
    {
        $plan = $this->makePlan();
        $this->businessA->forceFill(['plan_id' => $plan->id])->save();

        $this->bookStudy()->assertStatus(201);
        $this->bookStudy()->assertStatus(201);

        // Third study in the period crosses max_studies_per_month = 2.
        $this->bookStudy()
            ->assertStatus(403)
            ->assertJsonPath('error', 'quota_exceeded')
            ->assertJsonPath('quota', 'studies');
    }

    public function test_user_seat_quota_is_enforced(): void
    {
        $plan = $this->makePlan(['max_users' => 2, 'max_studies_per_month' => null]);
        $this->businessA->forceFill(['plan_id' => $plan->id])->save();

        // businessA already has 1 staff (owner). Two more fill the 2-seat plan…
        $this->actingAs($this->adminA)->postJson('/api/v1/staff', [
            'name' => 'Seat Two', 'email' => 'seat2.'.uniqid().'@test.local', 'password' => 'R1s!T3st#2026x', 'role' => 'receptionist',
        ])->assertStatus(201);

        // …the third is refused.
        $this->actingAs($this->adminA)->postJson('/api/v1/staff', [
            'name' => 'Seat Three', 'email' => 'seat3.'.uniqid().'@test.local', 'password' => 'R1s!T3st#2026x', 'role' => 'receptionist',
        ])->assertStatus(403)
            ->assertJsonPath('error', 'quota_exceeded')
            ->assertJsonPath('quota', 'users');
    }

    public function test_study_creation_increments_the_usage_meter_in_the_same_transaction(): void
    {
        $period = now()->format('Y-m');
        $before = UsageCounter::totalFor($this->businessA->id, 'studies', $period);

        $this->bookStudy()->assertStatus(201);

        $this->assertSame($before + 1, UsageCounter::totalFor($this->businessA->id, 'studies', $period));
    }

    public function test_usage_counters_are_tenant_scoped(): void
    {
        $period = now()->format('Y-m');
        $this->bookStudy()->assertStatus(201);

        $this->assertSame(1, UsageCounter::totalFor($this->businessA->id, 'studies', $period));
        $this->assertSame(0, UsageCounter::totalFor($this->businessB->id, 'studies', $period));
    }

    public function test_dicom_module_can_be_disabled_via_plan_features(): void
    {
        $plan = $this->makePlan(['features' => ['dicom' => false]]);
        $this->businessA->forceFill(['plan_id' => $plan->id])->save();

        $this->actingAs($this->adminA)->postJson('/api/v1/dicom-nodes', [
            'nodeName' => 'CT1', 'aeTitle' => 'CT1', 'ipAddress' => '192.168.1.50', 'port' => 104,
        ])->assertStatus(403)->assertJsonPath('error', 'feature_disabled');

        // Tenant B (no restrictive plan) still works.
        $this->actingAs($this->adminB)->postJson('/api/v1/dicom-nodes', [
            'nodeName' => 'CT1', 'aeTitle' => 'CT1-B', 'ipAddress' => '192.168.1.51', 'port' => 104,
        ])->assertStatus(201);
    }

    public function test_platform_override_wins_over_plan_features(): void
    {
        $plan = $this->makePlan(['features' => ['dicom' => false]]);
        $this->businessA->forceFill(['plan_id' => $plan->id])->save();

        TenantFeatureOverride::create(['business_id' => $this->businessA->id, 'feature' => 'dicom', 'enabled' => true, 'actor_id' => 0]);

        $this->actingAs($this->adminA)->postJson('/api/v1/dicom-nodes', [
            'nodeName' => 'CT9', 'aeTitle' => 'CT9', 'ipAddress' => '192.168.1.52', 'port' => 104,
        ])->assertStatus(201);
    }

    public function test_entitlements_are_mirrored_in_the_tenant_bootstrap(): void
    {
        $plan = $this->makePlan(['max_users' => 5]);
        $this->businessA->forceFill(['plan_id' => $plan->id])->save();

        $this->actingAs($this->adminA)->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath('data.entitlements.plan.id', (string) $plan->id)
            ->assertJsonPath('data.entitlements.limits.maxUsers', 5)
            ->assertJsonPath('data.entitlements.subscriptionStatus', 'active');
    }

    public function test_public_plan_catalog_is_available_without_auth(): void
    {
        Plan::create([
            'name' => 'Public Plan', 'slug' => 'public-plan-'.uniqid(), 'price_monthly' => 1234,
            'currency' => 'PKR', 'trial_days' => 3, 'is_active' => true,
        ]);

        $this->getJson('/api/v1/plans')
            ->assertOk()
            ->assertJsonStructure(['data' => ['plans' => [['id', 'name', 'priceMonthly']]]]);
    }
}
