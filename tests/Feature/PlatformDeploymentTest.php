<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;

/**
 * Deployment topology in the control plane: the placement catalog is
 * config-owned, re-placing a tenant is capability-gated + audited, and the
 * tenant plane never sees platform infrastructure metadata.
 */
class PlatformDeploymentTest extends ApiTestCase
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

    public function test_deployment_catalog_exposes_config_owned_placements_and_distribution(): void
    {
        $super = $this->platformUser('super_admin');

        $res = $this->actingAs($super)->getJson('/api/v1/platform/infrastructure')->assertOk();
        $body = $res->decodeResponseJson()['data'];

        $this->assertNotEmpty($body['catalog']['regions']);
        $this->assertNotEmpty($body['catalog']['deploymentStamps']);
        $this->assertContains('pooled', array_column($body['catalog']['isolationProfiles'], 'key'));

        // Both fixture tenants are backfilled to the declared default placement.
        $regions = collect($body['placement']['regions'])->keyBy('key');
        $this->assertSame(2, $regions->sum('tenants'));
        $this->assertSame(0, $body['unplacedTenants']);
    }

    public function test_super_admin_can_re_place_a_tenant_and_it_is_audited(): void
    {
        $super = $this->platformUser('super_admin');
        $region = array_key_first(config('ris.regions'));
        $stamp = array_key_first(config('ris.deployment_stamps'));

        $this->actingAs($super)
            ->confirmStepUp()->patchJson("/api/v1/platform/tenants/{$this->businessA->id}/deployment", [
                'region' => $region,
                'deploymentStamp' => $stamp,
                'isolationProfile' => 'database',
                'databaseCluster' => 'ris-db-eu-01',
                'storageRegion' => $region,
                'reason' => 'Enterprise contract requires dedicated database isolation.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('businesses', [
            'id' => $this->businessA->id,
            'isolation_profile' => 'database',
            'database_cluster' => 'ris-db-eu-01',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $this->businessA->id,
            'action' => 'tenant_deployment_updated',
        ]);

        $this->assertDatabaseHas('tenant_lifecycle_events', [
            'business_id' => $this->businessA->id,
            'event' => 'deployment_updated',
        ]);

        // Tenant B was not touched by A's re-placement.
        $this->assertSame('pooled', Business::find($this->businessB->id)->isolation_profile);
    }

    public function test_unknown_region_or_stamp_is_rejected(): void
    {
        $super = $this->platformUser('super_admin');

        $this->actingAs($super)
            ->confirmStepUp()->patchJson("/api/v1/platform/tenants/{$this->businessA->id}/deployment", [
                'region' => 'atlantis-1',
                'deploymentStamp' => array_key_first(config('ris.deployment_stamps')),
                'isolationProfile' => 'pooled',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('region');

        $this->actingAs($super)
            ->confirmStepUp()->patchJson("/api/v1/platform/tenants/{$this->businessA->id}/deployment", [
                'region' => array_key_first(config('ris.regions')),
                'deploymentStamp' => 'stamp-zzz',
                'isolationProfile' => 'pooled',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('deploymentStamp');

        $this->actingAs($super)
            ->confirmStepUp()->patchJson("/api/v1/platform/tenants/{$this->businessA->id}/deployment", [
                'region' => array_key_first(config('ris.regions')),
                'deploymentStamp' => array_key_first(config('ris.deployment_stamps')),
                'isolationProfile' => 'sneaky-extra-isolation',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('isolationProfile');
    }

    public function test_infrastructure_capability_is_separated_from_other_platform_roles(): void
    {
        $payload = [
            'region' => array_key_first(config('ris.regions')),
            'deploymentStamp' => array_key_first(config('ris.deployment_stamps')),
            'isolationProfile' => 'pooled',
        ];

        // ops holds infrastructure.manage.
        $this->actingAs($this->platformUser('ops'))
            ->confirmStepUp()->patchJson("/api/v1/platform/tenants/{$this->businessA->id}/deployment", $payload)
            ->assertOk();

        // billing / support / auditor do not.
        foreach (['billing', 'support', 'auditor'] as $role) {
            $this->actingAs($this->platformUser($role))
                ->confirmStepUp()->patchJson("/api/v1/platform/tenants/{$this->businessA->id}/deployment", $payload)
                ->assertStatus(403);
        }

        // …but auditor may read the catalog (tenants.view).
        $this->actingAs($this->platformUser('auditor'))
            ->getJson('/api/v1/platform/infrastructure')
            ->assertOk();
    }

    public function test_tenant_staff_can_never_reach_platform_infrastructure(): void
    {
        $this->actingAs($this->adminA)
            ->getJson('/api/v1/platform/infrastructure')
            ->assertStatus(403);

        $this->actingAs($this->adminA)
            ->confirmStepUp()->patchJson("/api/v1/platform/tenants/{$this->businessA->id}/deployment", [
                'region' => array_key_first(config('ris.regions')),
                'deploymentStamp' => array_key_first(config('ris.deployment_stamps')),
                'isolationProfile' => 'dedicated',
            ])
            ->assertStatus(403);

        // The tenant plane payload never carries platform placement metadata.
        $bootstrap = $this->actingAs($this->adminA)->getJson('/api/v1/bootstrap')->assertOk();
        $this->assertStringNotContainsString('deploymentStamp', $bootstrap->getContent());
        $this->assertStringNotContainsString('isolationProfile', $bootstrap->getContent());
    }
}
