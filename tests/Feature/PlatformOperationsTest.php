<?php

namespace Tests\Feature;

use App\Models\FailedJob;
use App\Models\Plan;
use App\Models\TenantFeatureOverride;
use App\Models\TenantIntegration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Platform operations: the observability dashboard (§80) and the safe
 * operational tooling (§81).
 *
 * The properties that matter: every figure is real (never a plausible zero),
 * a failed job's payload is never exposed, the retry actually re-queues the
 * job, and reconciliation reports drift before it is ever allowed to prune.
 */
class PlatformOperationsTest extends ApiTestCase
{
    private function platformUser(string $role): User
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

    /** A failed job whose serialized command carries a value that must never leak. */
    private function failedJob(array $payloadOverrides = [], array $rowOverrides = []): FailedJob
    {
        $command = new \stdClass;
        $command->tenantId = $this->businessA->id;
        $command->leakedSecret = 'PATIENT-SECRET-9c1f';

        $payload = json_encode([
            'uuid' => (string) Str::uuid(),
            'displayName' => 'App\\Mail\\SendLoginDetail',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'attempts' => 3,
            'data' => [
                'commandName' => 'App\\Mail\\SendLoginDetail',
                'command' => serialize($command),
            ],
            ...$payloadOverrides,
        ]);

        return FailedJob::create([
            'uuid' => (string) Str::uuid(),
            'connection' => (string) config('queue.default'),
            'queue' => 'default',
            'payload' => $payload,
            'exception' => "RuntimeException: SMTP timeout while delivering login details\n#0 /app/x.php(12): send()",
            'failed_at' => now(),
            ...$rowOverrides,
        ]);
    }

    private function makePlan(array $overrides = []): Plan
    {
        return Plan::create([
            'name' => 'Ops Plan '.md5(uniqid('', true)),
            'slug' => 'ops-'.md5(uniqid('', true)),
            'price_monthly' => 5000,
            'currency' => 'PKR',
            'trial_days' => 7,
            'is_active' => true,
            ...$overrides,
        ]);
    }

    // ==================== §80 observability ====================

    public function test_dashboard_reports_real_system_facts_and_tenant_health(): void
    {
        $body = $this->actingAs($this->platformUser('ops'))
            ->getJson('/api/v1/platform/operations')
            ->assertOk()
            ->decodeResponseJson()['data'];

        // System facts are measured, not asserted.
        $this->assertSame('ok', $body['system']['database']);
        $this->assertSame(config('queue.default'), $body['system']['queueConnection']);
        $this->assertSame(config('ris.app_version'), $body['system']['appVersion']);
        $this->assertIsInt($body['system']['pendingJobs']);
        $this->assertIsInt($body['system']['failedJobs']);
        $this->assertIsBool($body['system']['storageWritable']);

        // Both fixture tenants are healthy: no integrations, no plan limits.
        $this->assertSame(2, $body['summary']['total']);
        $this->assertSame(2, $body['summary']['ok']);
        $this->assertSame(0, $body['summary']['critical']);
        $this->assertSame([], $body['provisioningStuck']);

        $tenant = collect($body['tenants'])->firstWhere('tenantId', $this->businessA->id);
        $this->assertSame('ok', $tenant['health']);
        $this->assertSame([], $tenant['reasons']);
        $this->assertIsInt($tenant['storageBytes']);

        // Placement rollup answers "which stamp is affected?".
        $this->assertNotEmpty($body['deployments']);
        $this->assertSame(2, collect($body['deployments'])->sum('tenants'));
    }

    public function test_a_failing_integration_makes_its_tenant_critical_and_explains_why(): void
    {
        TenantIntegration::create([
            'business_id' => $this->businessA->id,
            'type' => 'dicom',
            'name' => 'Broken PACS',
            'config' => ['host' => '127.0.0.1', 'port' => '1', 'aeTitle' => 'RIS'],
            'status' => 'error',
            'last_error' => 'TCP unreachable.',
        ]);

        $body = $this->actingAs($this->platformUser('ops'))
            ->getJson('/api/v1/platform/operations')
            ->assertOk()
            ->decodeResponseJson()['data'];

        $this->assertSame(1, $body['summary']['failingIntegrations']);
        $this->assertSame(1, $body['summary']['critical']);

        // Worst first: the degraded tenant leads the list.
        $this->assertSame($this->businessA->id, $body['tenants'][0]['tenantId']);
        $this->assertSame('critical', $body['tenants'][0]['health']);
        $this->assertSame(1, $body['tenants'][0]['failingIntegrations']);
        $this->assertStringContainsString('failing', implode(' ', $body['tenants'][0]['reasons']));
    }

    public function test_a_quota_breach_is_reported_as_critical_with_the_breached_key(): void
    {
        $this->businessA->forceFill(['plan_id' => $this->makePlan(['max_users' => 1])->id])->save();
        $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $body = $this->actingAs($this->platformUser('ops'))
            ->getJson('/api/v1/platform/operations')
            ->assertOk()
            ->decodeResponseJson()['data'];

        $tenant = collect($body['tenants'])->firstWhere('tenantId', $this->businessA->id);

        $this->assertSame('critical', $tenant['health']);
        $this->assertContains('users', $tenant['quotasBreached']);
        $this->assertTrue($tenant['quotas']['users']['exceeded']);
        $this->assertSame(2, $tenant['quotas']['users']['used']);
        $this->assertSame(1, $tenant['quotas']['users']['limit']);
        $this->assertSame(1, $body['summary']['quotaBreaches']);
    }

    public function test_operations_surface_is_capability_gated_and_never_reaches_tenant_staff(): void
    {
        // ops + support hold operations.manage; billing + auditor do not.
        $this->actingAs($this->platformUser('ops'))->getJson('/api/v1/platform/operations')->assertOk();
        $this->actingAs($this->platformUser('support'))->getJson('/api/v1/platform/operations')->assertOk();
        $this->actingAs($this->platformUser('billing'))->getJson('/api/v1/platform/operations')->assertStatus(403);
        $this->actingAs($this->platformUser('auditor'))->getJson('/api/v1/platform/operations')->assertStatus(403);
        $this->actingAs($this->platformUser('billing'))->getJson('/api/v1/platform/operations/jobs')->assertStatus(403);

        // Tenant staff never reach the control plane at all.
        $this->actingAs($this->adminA)->getJson('/api/v1/platform/operations')->assertStatus(403);
        $this->actingAs($this->adminA)
            ->confirmStepUp()->postJson("/api/v1/platform/tenants/{$this->businessA->id}/entitlements/reconcile")
            ->assertStatus(403);
    }

    // ==================== §81 job inspection ====================

    public function test_failed_jobs_are_inspected_without_ever_exposing_payload_values(): void
    {
        $job = $this->failedJob();

        $body = $this->actingAs($this->platformUser('ops'))
            ->getJson('/api/v1/platform/operations/jobs')
            ->assertOk()
            ->decodeResponseJson()['data'];

        $this->assertCount(1, $body['jobs']);
        $row = $body['jobs'][0];

        $this->assertSame($job->uuid, $row['uuid']);
        $this->assertSame('App\Mail\SendLoginDetail', $row['jobClass']);
        $this->assertSame(3, $row['attempts']);
        $this->assertSame('default', $row['queue']);
        $this->assertSame('RuntimeException', $row['exceptionType']);
        $this->assertStringContainsString('SMTP timeout', $row['exceptionSummary']);

        // Property names come from the job class (reflection), not the payload:
        // the bogus property planted in the serialized command must not appear.
        $this->assertNotEmpty($row['payloadPropertyNames']);
        $this->assertNotContains('leakedSecret', $row['payloadPropertyNames']);

        // The payload itself is never serialized out, and neither is any value
        // inside it — this is the PHI guarantee of the whole endpoint.
        $this->assertArrayNotHasKey('payload', $row);
        $response = $this->actingAs($this->platformUser('ops'))
            ->getJson('/api/v1/platform/operations/jobs')->getContent();
        $this->assertStringNotContainsString('PATIENT-SECRET-9c1f', $response);
        $this->assertStringNotContainsString('leakedSecret', $response);
    }

    public function test_retrying_a_failed_job_really_requeues_it_and_is_audited(): void
    {
        // Push onto the database queue instead of executing: the point of the
        // endpoint is to re-queue the job, not to run it inside the request.
        config(['queue.default' => 'database']);
        $job = $this->failedJob([], ['connection' => 'database']);

        $this->actingAs($this->platformUser('ops'))
            ->confirmStepUp()->postJson("/api/v1/platform/operations/jobs/{$job->uuid}/retry")
            ->assertOk()
            ->assertJsonPath('data.requeued', true);

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $job->uuid]);
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame(0, (int) DB::table('jobs')->value('attempts'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'failed_job_retried']);
    }

    public function test_forgetting_a_failed_job_removes_it_and_is_audited(): void
    {
        $job = $this->failedJob();

        $this->actingAs($this->platformUser('ops'))
            ->confirmStepUp()->deleteJson("/api/v1/platform/operations/jobs/{$job->uuid}")
            ->assertOk()
            ->assertJsonPath('data.removed', true);

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $job->uuid]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'failed_job_forgotten']);
    }

    public function test_unknown_or_unauthorised_job_operations_are_safe_denials(): void
    {
        $missing = (string) Str::uuid();

        $this->actingAs($this->platformUser('ops'))
            ->confirmStepUp()->postJson("/api/v1/platform/operations/jobs/{$missing}/retry")->assertStatus(404);
        $this->actingAs($this->platformUser('ops'))
            ->confirmStepUp()->deleteJson("/api/v1/platform/operations/jobs/{$missing}")->assertStatus(404);

        $job = $this->failedJob();
        $this->actingAs($this->platformUser('billing'))
            ->confirmStepUp()->postJson("/api/v1/platform/operations/jobs/{$job->uuid}/retry")->assertStatus(403);
        $this->assertDatabaseHas('failed_jobs', ['uuid' => $job->uuid]);
    }

    // ==================== §81 entitlement reconciliation ====================

    public function test_reconciliation_reports_drift_and_only_prunes_when_asked(): void
    {
        $this->businessA->forceFill(['plan_id' => $this->makePlan(['features' => ['dicom' => true]])->id])->save();

        // Redundant: restates what the plan already says.
        TenantFeatureOverride::create(['business_id' => $this->businessA->id, 'feature' => 'dicom', 'enabled' => true]);
        // Stale: the catalog no longer declares this feature at all.
        TenantFeatureOverride::create(['business_id' => $this->businessA->id, 'feature' => 'telepathy', 'enabled' => true]);

        $ops = $this->platformUser('ops');

        $report = $this->actingAs($ops)
            ->confirmStepUp()->postJson("/api/v1/platform/tenants/{$this->businessA->id}/entitlements/reconcile")
            ->assertOk()
            ->decodeResponseJson()['data'];

        $this->assertFalse($report['applied']);
        $this->assertTrue($report['driftDetected']);
        $this->assertSame(['telepathy'], $report['stale']);
        $this->assertSame(['dicom'], $report['redundant']);
        $this->assertSame([], $report['pruned']);

        // A report changes nothing, and is not audited as a change.
        $this->assertDatabaseHas('tenant_feature_overrides', ['feature' => 'telepathy']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'entitlements_reconciled']);

        $applied = $this->actingAs($ops)
            ->confirmStepUp()->postJson("/api/v1/platform/tenants/{$this->businessA->id}/entitlements/reconcile", ['apply' => true])
            ->assertOk()
            ->decodeResponseJson()['data'];

        $this->assertTrue($applied['applied']);
        $this->assertSame(['telepathy'], $applied['pruned']);

        // Only the inert override is pruned; the redundant one is left alone.
        $this->assertDatabaseMissing('tenant_feature_overrides', ['feature' => 'telepathy']);
        $this->assertDatabaseHas('tenant_feature_overrides', ['feature' => 'dicom']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'entitlements_reconciled', 'business_id' => $this->businessA->id]);
    }

    public function test_reconciliation_never_deletes_an_override_that_contradicts_the_plan(): void
    {
        $this->businessA->forceFill(['plan_id' => $this->makePlan(['features' => ['dicom' => true]])->id])->save();
        TenantFeatureOverride::create(['business_id' => $this->businessA->id, 'feature' => 'dicom', 'enabled' => false]);

        $applied = $this->actingAs($this->platformUser('ops'))
            ->confirmStepUp()->postJson("/api/v1/platform/tenants/{$this->businessA->id}/entitlements/reconcile", ['apply' => true])
            ->assertOk()
            ->decodeResponseJson()['data'];

        $this->assertSame(['dicom'], $applied['contradicting']);
        $this->assertSame([], $applied['pruned']);

        // A commercial decision, not drift: the override survives and still wins.
        $this->assertDatabaseHas('tenant_feature_overrides', ['feature' => 'dicom', 'enabled' => false]);
        $this->assertFalse($applied['effective']['dicom']);
    }

    public function test_reconciliation_is_tenant_scoped(): void
    {
        TenantFeatureOverride::create(['business_id' => $this->businessB->id, 'feature' => 'telepathy', 'enabled' => true]);

        $this->actingAs($this->platformUser('ops'))
            ->confirmStepUp()->postJson("/api/v1/platform/tenants/{$this->businessA->id}/entitlements/reconcile", ['apply' => true])
            ->assertOk();

        // Beta's override is untouched by a reconciliation of Alpha.
        $this->assertDatabaseHas('tenant_feature_overrides', [
            'business_id' => $this->businessB->id,
            'feature' => 'telepathy',
        ]);
    }
}
