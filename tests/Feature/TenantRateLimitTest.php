<?php

namespace Tests\Feature;

use App\Models\TenantMembership;
use App\Models\User;

/**
 * Noisy-neighbor guard: one aggregate API budget per ACTIVE tenant.
 * Exceeding it trips 429 for that tenant only — a neighbouring clinic keeps
 * its own untouched counter — and the budget follows the tenant being
 * operated, not the user's home clinic.
 */
class TenantRateLimitTest extends ApiTestCase
{
    public function test_exhausted_tenant_budget_trips_429_without_affecting_other_tenants(): void
    {
        config()->set('ris.tenant_api_rate_limit_per_minute', 3);

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->adminA)->getJson('/api/v1/bootstrap')->assertOk();
        }

        $this->actingAs($this->adminA)->getJson('/api/v1/bootstrap')->assertStatus(429);

        // Tenant B has its own budget — full capacity despite A's exhaustion.
        $this->actingAs($this->adminB)->getJson('/api/v1/bootstrap')->assertOk();
    }

    public function test_switched_member_consumes_the_operated_tenant_budget(): void
    {
        config()->set('ris.tenant_api_rate_limit_per_minute', 3);

        $member = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        TenantMembership::create([
            'user_id' => $member->id,
            'business_id' => $this->businessB->id,
            'role' => 'receptionist',
            'is_default' => false,
            'status' => 'active',
        ]);

        $this->actingAs($member)
            ->postJson('/api/v1/tenant/switch', ['businessId' => $this->businessB->id])
            ->assertOk();

        // Three requests fill B's shared bucket; the member and B's owner
        // now share the same exhausted budget — A stays unaffected.
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($member)->getJson('/api/v1/bootstrap')->assertOk();
        }

        $this->actingAs($this->adminB)->getJson('/api/v1/bootstrap')->assertStatus(429);
        $this->actingAs($this->adminA)->getJson('/api/v1/bootstrap')->assertOk();
    }

    public function test_budget_is_shared_by_all_users_of_one_tenant(): void
    {
        config()->set('ris.tenant_api_rate_limit_per_minute', 3);

        $staffA = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        // Two different users of tenant A jointly exhaust the ONE tenant budget.
        $this->actingAs($this->adminA)->getJson('/api/v1/bootstrap')->assertOk();
        $this->actingAs($this->adminA)->getJson('/api/v1/bootstrap')->assertOk();
        $this->actingAs($staffA)->getJson('/api/v1/bootstrap')->assertOk();

        $this->actingAs($staffA)->getJson('/api/v1/bootstrap')->assertStatus(429);

        // A per-user limiter would have let this next request through.
        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'receptionist'))
            ->getJson('/api/v1/bootstrap')
            ->assertStatus(429);
    }
}
