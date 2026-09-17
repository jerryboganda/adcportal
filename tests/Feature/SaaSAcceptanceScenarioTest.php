<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Plan;
use App\Models\SupportSession;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\StorageMeter;
use Illuminate\Support\Facades\Cache;

/**
 * The mandatory end-to-end acceptance scenario from the SaaS re-engineering
 * charter: two independent tenants (Alpha Hospital Network, Beta Diagnostic
 * Center) with distinct staff, patients, studies and settings; a platform
 * super admin operating the control plane; break-glass support; suspension,
 * reactivation and entitlement changes — and ZERO unauthorized cross-tenant
 * leakage through any access path.
 */
class SaaSAcceptanceScenarioTest extends ApiTestCase
{
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::create([
            'name' => 'SaaS Platform Owner',
            'email' => 'owner.'.md5(uniqid('', true)).'@platform.test',
            'password' => 'R1s!T3st#2026x',
            'email_verified_at' => now(),
            'type' => 'super_admin',
            'active_status' => 1,
            'lang' => 'en',
        ]);
    }

    private function book(Business $tenant, User $actor, string $patientName): Appointment
    {
        $response = $this->actingAs($actor)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => $patientName],
            'serviceId' => $tenant->services()->first()->id,
            'date' => now()->toDateString(),
            'time' => '11:00 AM',
            'priority' => $patientName === 'Beta STAT Patient' ? 'stat' : 'routine',
        ]);
        $response->assertStatus(201);

        return Appointment::where('business_id', $tenant->id)->where('name', $patientName)->latest('id')->first();
    }

    public function test_full_saas_acceptance_scenario(): void
    {
        // ============ 1. Platform provisions both tenants ============
        $alphaOwner = $this->adminA;            // Alpha Hospital Network (fixture tenant A)
        $betaOwner = $this->adminB;             // Beta Diagnostic Center (fixture tenant B)
        $this->assertSame('Alpha Diagnostic Centre', $this->businessA->name);
        $this->assertSame('Beta Imaging Centre', $this->businessB->name);

        // ============ 2. Distinct clinical data per tenant ============
        $alphaRadiologist = $this->makeStaff($this->businessA, $alphaOwner, 'radiologist');
        $betaRadiologist = $this->makeStaff($this->businessB, $betaOwner, 'radiologist');

        $alphaStudy = $this->book($this->businessA, $alphaOwner, 'Alpha Private Patient');
        $betaStudy = $this->book($this->businessB, $betaOwner, 'Beta Private Patient');
        $this->assertNotSame($alphaStudy->id, $betaStudy->id);

        // ============ 3. Tenant admins see only their own world ============
        $alphaBootstrap = $this->actingAs($alphaOwner)->getJson('/api/v1/bootstrap')->assertOk()->decodeResponseJson();
        $betaBootstrap = $this->actingAs($betaOwner)->getJson('/api/v1/bootstrap')->assertOk()->decodeResponseJson();

        $this->assertStringContainsString('Alpha Private Patient', json_encode($alphaBootstrap->json('data.studies')));
        $this->assertStringNotContainsString('Beta Private Patient', json_encode($alphaBootstrap->json()));
        $this->assertStringContainsString('Beta Private Patient', json_encode($betaBootstrap->json('data.studies')));
        $this->assertStringNotContainsString('Alpha Private Patient', json_encode($betaBootstrap->json()));

        // ============ 4. Platform admin: control plane, NOT clinical data ============
        $platform = $this->actingAs($this->superAdmin)->getJson('/api/v1/bootstrap')->assertOk()->decodeResponseJson();
        $this->assertTrue($platform->json('data.platform'));
        $this->assertNull($platform->json('data.patients'), 'Platform bootstrap must not expose tenant PHI.');
        $this->assertNull($platform->json('data.studies'));

        $tenants = $this->actingAs($this->superAdmin)->getJson('/api/v1/platform/tenants')->assertOk()->decodeResponseJson();
        $names = collect($tenants->json('data.tenants'))->pluck('name')->all();
        $this->assertContains('Alpha Diagnostic Centre', $names);
        $this->assertContains('Beta Imaging Centre', $names);

        $tenant360 = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/platform/tenants/'.$this->businessB->id)
            ->assertOk()
            ->decodeResponseJson();
        $this->assertStringNotContainsString('Beta Private Patient', json_encode($tenant360->json()), 'Tenant 360 must use operational metadata, not patient records.');

        // ============ 5. Cross-tenant attacks: Alpha → Beta ============
        // 5a. Direct resource ID manipulation.
        $this->actingAs($alphaOwner)->putJson("/api/v1/studies/{$betaStudy->id}", ['priority' => 'stat'])->assertStatus(404);

        // A radiologist lacks 'appointment edit' entirely, so the permission
        // layer refuses with 403 before ownership — equally existence-safe
        // (identical response for nonexistent and foreign studies).
        $radiologistStatus = $this->actingAs($alphaRadiologist)
            ->putJson("/api/v1/studies/{$betaStudy->id}", ['priority' => 'stat'])
            ->getStatusCode();
        $this->assertContains($radiologistStatus, [403, 404]);

        $this->actingAs($alphaOwner)->postJson("/api/v1/studies/{$betaStudy->id}/reports", [
            'findings' => 'intrusion', 'impression' => 'intrusion',
        ])->assertStatus(404);

        // 5b. Tenant id injection through the payload can never re-scope a query.
        $this->actingAs($alphaOwner)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Injection Attempt'],
            'serviceId' => $this->businessB->services()->first()->id, // Beta's service id, sent by Alpha
            'date' => now()->toDateString(),
            'time' => '10:00 AM',
            'priority' => 'routine',
        ])->assertStatus(404); // service resolved inside Alpha's scope only

        // 5c. "Search" surface (bootstrap hydration) never contains the other tenant.
        $this->assertStringNotContainsString('Beta Private Patient', json_encode($alphaBootstrap->json()));

        // 5d. Exports are strictly tenant-scoped.
        $alphaExport = $this->actingAs($alphaOwner)->getJson('/api/v1/backup')->assertOk()->decodeResponseJson();
        $this->assertStringContainsString('Alpha Private Patient', json_encode($alphaExport->json()));
        $this->assertStringNotContainsString('Beta Private Patient', json_encode($alphaExport->json()));

        // 5e. Storage meter cache keys are tenant-scoped — no cross-reading.
        $alphaBytes = StorageMeter::bytes($this->businessA->id);
        $betaBytes = StorageMeter::bytes($this->businessB->id);
        $this->assertTrue(Cache::has("tenant:{$this->businessA->id}:storage_bytes"));
        $this->assertTrue(Cache::has("tenant:{$this->businessB->id}:storage_bytes"));
        $this->assertEqualsCanonicalizing([$alphaBytes, $betaBytes], [
            Cache::get("tenant:{$this->businessA->id}:storage_bytes"),
            Cache::get("tenant:{$this->businessB->id}:storage_bytes"),
        ]);

        // ============ 6. Context-reuse: same worker, A then B, then A again ============
        $first = $this->actingAs($alphaOwner)->getJson('/api/v1/bootstrap')->assertOk()->decodeResponseJson();
        $second = $this->actingAs($betaOwner)->getJson('/api/v1/bootstrap')->assertOk()->decodeResponseJson();
        $third = $this->actingAs($alphaOwner)->getJson('/api/v1/bootstrap')->assertOk()->decodeResponseJson();

        $this->assertSame($this->businessA->id, (int) $first->json('data.user.businessId'));
        $this->assertSame($this->businessB->id, (int) $second->json('data.user.businessId'));
        $this->assertSame($this->businessA->id, (int) $third->json('data.user.businessId'));
        $this->assertStringNotContainsString('Beta Private Patient', json_encode($third->json()));

        // ============ 7. Suspension, gate behavior, reactivation ============
        $this->actingAs($this->superAdmin)
            ->confirmStepUp()->postJson("/api/v1/platform/tenants/{$this->businessB->id}/suspend", ['reason' => 'Acceptance: suspension check'])
            ->assertOk();

        $this->actingAs($betaOwner)->getJson('/api/v1/bootstrap')->assertStatus(402);
        $this->actingAs($betaRadiologist)->getJson('/api/v1/studies')->assertStatus(402);

        $this->actingAs($this->superAdmin)
            ->confirmStepUp()->postJson("/api/v1/platform/tenants/{$this->businessB->id}/reactivate", ['reason' => 'Acceptance: restore'])
            ->assertOk();
        $this->actingAs($betaOwner)->getJson('/api/v1/bootstrap')->assertOk();

        // ============ 8. Entitlement change is enforced by the backend ============
        $quotaPlan = Plan::create([
            'name' => 'Acceptance Tiny', 'slug' => 'acceptance-tiny-'.uniqid(),
            'price_monthly' => 1000, 'currency' => 'PKR', 'trial_days' => 1,
            'max_studies_per_month' => 1, 'is_active' => true,
        ]);
        $this->actingAs($this->superAdmin)
            ->confirmStepUp()->patchJson("/api/v1/platform/tenants/{$this->businessB->id}", ['planId' => $quotaPlan->id])
            ->assertOk();

        // Usage counters already hold Beta's booked studies this month → quota hit.
        $this->actingAs($betaOwner)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Beta Over Quota'],
            'serviceId' => $this->businessB->services()->first()->id,
            'date' => now()->toDateString(),
            'time' => '12:00 PM',
            'priority' => 'routine',
        ])->assertStatus(403)->assertJsonPath('error', 'quota_exceeded');

        // Restore an unlimited plan for the remainder of the scenario.
        $this->actingAs($this->superAdmin)
            ->confirmStepUp()->patchJson("/api/v1/platform/tenants/{$this->businessB->id}", ['planId' => null])
            ->assertOk();

        // ============ 9. Break-glass support session, audited ============
        $opened = $this->actingAs($this->superAdmin)->confirmStepUp()->postJson('/api/v1/platform/support-sessions', [
            'businessId' => $this->businessB->id,
            'reason' => 'Acceptance: verifying controlled support access',
        ])->assertStatus(201)->decodeResponseJson();
        $supportSessionId = $opened->json('data.session.id');

        $this->actingAs($this->superAdmin)->postJson('/api/v1/tenant/enter', ['businessId' => $this->businessB->id])->assertOk();
        $this->actingAs($this->superAdmin)->getJson('/api/v1/bootstrap')->assertOk(); // operational access inside Beta
        $this->actingAs($this->superAdmin)->postJson('/api/v1/tenant/leave')->assertOk();

        // Closing the grant entirely is a separate, audited edge.
        $this->actingAs($this->superAdmin)->confirmStepUp()->postJson("/api/v1/platform/support-sessions/{$supportSessionId}/end", ['note' => 'Acceptance complete.'])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['business_id' => $this->businessB->id, 'action' => 'support_session_started']);
        $this->assertDatabaseHas('audit_logs', ['business_id' => $this->businessB->id, 'action' => 'support_session_ended']);

        // Platform user back outside: no tenant access again.
        $this->actingAs($this->superAdmin)->getJson('/api/v1/studies')->assertStatus(403);

        // ============ 10. Health of tenants from the control plane ============
        $overview = $this->actingAs($this->superAdmin)->getJson('/api/v1/platform/overview')->assertOk()->decodeResponseJson();
        $this->assertGreaterThanOrEqual(2, $overview->json('data.stats.tenants.total'));
        $this->assertNotNull($overview->json('data.stats.usage.studiesThisMonth'));
        $this->assertGreaterThanOrEqual(2, $overview->json('data.stats.usage.studiesThisMonth'));

        // ============ 11. Zero unauthorized leakage, final sweep ============
        $leakProbe = $this->actingAs($alphaOwner)->getJson('/api/v1/bootstrap')->assertOk()->decodeResponseJson();
        $this->assertStringNotContainsString('Beta Private Patient', json_encode($leakProbe->json()));
        $this->assertStringNotContainsString('Beta Over Quota', json_encode($leakProbe->json()));
    }

    public function test_tenant_administration_scope_is_complete_for_tenant_owner(): void
    {
        // Tenant admin manages their own organization without seeing others.
        $staff = $this->actingAs($this->adminA)->getJson('/api/v1/staff')->assertOk()->decodeResponseJson();
        foreach ($staff->json('data.staff') as $member) {
            $this->assertStringNotContainsString($this->businessB->name, json_encode($member));
        }

        // Membership registry exists for the tenant's users (control-plane record).
        $this->assertTrue(TenantMembership::where('business_id', $this->businessA->id)->exists());
        $this->assertTrue(SupportSession::count() === 0); // no standing grants

        // One write action ⇒ one audited, tenant-attributed record.
        $this->actingAs($this->adminA)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Audit Probe Patient'],
            'serviceId' => $this->businessA->services()->first()->id,
            'date' => now()->toDateString(),
            'time' => '01:00 PM',
            'priority' => 'routine',
        ])->assertStatus(201);
        $this->assertTrue(AuditLog::where('business_id', $this->businessA->id)->count() > 0);
    }
}
