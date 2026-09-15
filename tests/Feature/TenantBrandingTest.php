<?php

namespace Tests\Feature;

use App\Models\TenantDomain;
use App\Models\TenantFeatureOverride;
use App\Models\User;
use App\Services\TenantDomainVerifier;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * White-labeling and custom domains (master-prompt §37/§38).
 *
 * The critical property under test: a request host may only ever change what
 * the login screen *looks like*. It cannot select a tenant for data access,
 * and an unverified (unproven) host serves nothing at all.
 */
class TenantBrandingTest extends ApiTestCase
{
    /**
     * A 5xx in this suite is always a bug, never an expected outcome, so let
     * an unexpected exception surface as a real stack trace instead of a bare
     * "received 500" (which is all the default handler leaves behind).
     *
     * HTTP exceptions are excluded on purpose: the tests below assert 403/404/
     * 422 deliberately via abort(), and those must keep rendering normally.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling([HttpException::class, ValidationException::class]);
    }

    private function platformUser(string $role = 'super_admin'): User
    {
        return User::create([
            'name' => "Platform {$role}",
            'email' => "platform.{$role}.".md5(uniqid('', true)).'@test.local',
            'password' => 'Secret#12345',
            'email_verified_at' => now(),
            'type' => $role === 'super_admin' ? 'super_admin' : 'platform_admin',
            'platform_role' => $role === 'super_admin' ? null : $role,
            'active_status' => 1,
            'lang' => 'en',
        ]);
    }

    /** Deterministic DNS seam: pretend the TXT record exists for the given code. */
    private function fakeDns(?string $expectedCode): void
    {
        app()->instance(TenantDomainVerifier::class, new TenantDomainVerifier(function () use ($expectedCode) {
            return $expectedCode === null ? [] : ['polytronx-ris-verify='.$expectedCode];
        }));
    }

    // ==================== branding ====================

    public function test_tenant_without_branding_row_falls_back_to_platform_defaults(): void
    {
        $super = $this->platformUser();

        $body = $this->actingAs($super)
            ->getJson("/api/v1/platform/tenants/{$this->businessA->id}/branding")
            ->assertOk()
            ->decodeResponseJson()['data'];

        $this->assertFalse($body['brandingOverridden']);
        $this->assertSame(config('ris.platform_brand.app_name'), $body['branding']['appName']);
        $this->assertSame([], $body['domains']);
        $this->assertTrue($body['entitlements']['branding']);
        $this->assertTrue($body['entitlements']['customDomains']);
    }

    public function test_super_admin_can_white_label_a_tenant_and_the_tenant_plane_reflects_it(): void
    {
        $super = $this->platformUser();

        $this->actingAs($super)
            ->putJson("/api/v1/platform/tenants/{$this->businessA->id}/branding", [
                'appName' => 'Alpha Radiology Suite',
                'primaryColor' => '#0f766e',
                'loginMessage' => 'Authorised staff of Alpha Hospital Network only.',
                'reportHeader' => 'Alpha Hospital Network — Department of Radiology',
                'emailFromAddress' => 'radiology@alpha.test',
            ])
            ->assertOk()
            ->assertJsonPath('data.branding.appName', 'Alpha Radiology Suite');

        $this->assertDatabaseHas('tenant_branding', [
            'business_id' => $this->businessA->id,
            'app_name' => 'Alpha Radiology Suite',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $this->businessA->id,
            'action' => 'tenant_branding_updated',
        ]);

        // The tenant's own app shell receives its brand from the server.
        $bootstrap = $this->actingAs($this->adminA)->getJson('/api/v1/bootstrap')->assertOk();
        $this->assertSame('Alpha Radiology Suite', $bootstrap->decodeResponseJson()['data']['branding']['appName']);

        // Tenant B is untouched.
        $this->assertDatabaseMissing('tenant_branding', ['business_id' => $this->businessB->id]);
    }

    public function test_branding_write_requires_the_branding_entitlement(): void
    {
        $super = $this->platformUser();

        TenantFeatureOverride::create([
            'business_id' => $this->businessA->id,
            'feature' => 'branding',
            'enabled' => false,
        ]);

        $this->actingAs($super)
            ->putJson("/api/v1/platform/tenants/{$this->businessA->id}/branding", ['appName' => 'Nope'])
            ->assertStatus(403);
    }

    // ==================== domains ====================

    public function test_domain_registration_normalizes_hosts_and_rejects_junk_or_duplicates(): void
    {
        $super = $this->platformUser();

        $created = $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/domains", [
                'host' => 'HTTPS://Ris.Alpha-Hospital.test:8443/login',
                'isPrimary' => true,
            ])
            ->assertStatus(201);

        $this->assertSame('ris.alpha-hospital.test', $created->decodeResponseJson()['data']['domains'][0]['host']);
        $this->assertTrue($created->decodeResponseJson()['data']['domains'][0]['isPrimary']);

        foreach (['not a host', 'localhost', '-leading-hyphen.test', 'ris..test'] as $bad) {
            $this->actingAs($super)
                ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/domains", ['host' => $bad])
                ->assertStatus(422);
        }

        // Same host cannot be claimed by another tenant (no cross-tenant hijack).
        $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessB->id}/domains", ['host' => 'ris.alpha-hospital.test'])
            ->assertStatus(422);
    }

    public function test_domain_add_requires_the_custom_domains_entitlement(): void
    {
        $super = $this->platformUser();

        TenantFeatureOverride::create([
            'business_id' => $this->businessA->id,
            'feature' => 'custom_domains',
            'enabled' => false,
        ]);

        $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/domains", ['host' => 'blocked.test'])
            ->assertStatus(403);
    }

    public function test_unverified_domain_serves_nothing_and_verification_unlocks_branding_only(): void
    {
        $super = $this->platformUser();

        $this->actingAs($super)
            ->putJson("/api/v1/platform/tenants/{$this->businessA->id}/branding", ['appName' => 'Alpha Brand'])
            ->assertOk();

        $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/domains", ['host' => 'portal.alpha.test'])
            ->assertStatus(201);

        // Unverified: the public lookup falls back to platform defaults.
        $public = $this->getJson('/api/v1/tenant-context?host=portal.alpha.test')->assertOk();
        $this->assertFalse($public->decodeResponseJson()['data']['branding']['matched']);
        $this->assertSame(config('ris.platform_brand.app_name'), $public->decodeResponseJson()['data']['branding']['appName']);

        // Ownership not yet published → verification fails and nothing is served.
        $this->fakeDns(null);
        $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/domains/".$this->domainId('portal.alpha.test')."/verify")
            ->assertOk()
            ->assertJsonPath('data.verification.verified', false);

        $this->assertNull(TenantDomain::where('host', 'portal.alpha.test')->first()->verified_at);

        // Correct TXT record published → verified, and the host now serves the brand.
        $this->fakeDns($this->businessA->tenant_code);
        $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/domains/".$this->domainId('portal.alpha.test')."/verify")
            ->assertOk()
            ->assertJsonPath('data.verification.verified', true);

        $this->assertNotNull(TenantDomain::where('host', 'portal.alpha.test')->first()->verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant_domain_verified', 'business_id' => $this->businessA->id]);

        $public = $this->getJson('/api/v1/tenant-context?host=portal.alpha.test')->assertOk();
        $branding = $public->decodeResponseJson()['data']['branding'];
        $this->assertTrue($branding['matched']);
        $this->assertSame('Alpha Brand', $branding['appName']);
        // Presentation only: the public payload carries no clinical or user data.
        $this->assertArrayNotHasKey('users', $branding);
        $this->assertArrayNotHasKey('permissions', $branding);
    }

    public function test_unknown_host_falls_back_to_platform_defaults(): void
    {
        $body = $this->getJson('/api/v1/tenant-context?host=nobody-here.test')
            ->assertOk()
            ->decodeResponseJson()['data'];

        $this->assertFalse($body['branding']['matched']);
        $this->assertSame(config('ris.platform_brand.app_name'), $body['branding']['appName']);
    }

    public function test_a_host_can_never_be_used_to_reach_another_tenants_data(): void
    {
        $super = $this->platformUser();

        $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/domains", ['host' => 'portal.alpha.test'])
            ->assertStatus(201);
        $this->fakeDns($this->businessA->tenant_code);
        $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/domains/".$this->domainId('portal.alpha.test')."/verify")
            ->assertOk();

        // Beta's admin hitting Alpha's host still gets Beta's own tenant data:
        // the host is cosmetic, the tenant comes from the session.
        $bootstrap = $this->actingAs($this->adminB)
            ->getJson('/api/v1/bootstrap', ['Host' => 'portal.alpha.test'])
            ->assertOk();

        $body = $bootstrap->decodeResponseJson()['data'];
        $this->assertSame($this->businessB->id, (int) $body['user']['businessId']);
        $this->assertNotSame('Alpha Brand', $body['branding']['appName'] ?? null);
    }

    public function test_cross_tenant_domain_operations_are_404_and_tenant_staff_are_blocked(): void
    {
        $super = $this->platformUser();

        $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessB->id}/domains", ['host' => 'portal.beta.test'])
            ->assertStatus(201);
        $betaDomainId = $this->domainId('portal.beta.test');

        // Alpha's platform route cannot touch Beta's domain.
        $this->actingAs($super)
            ->deleteJson("/api/v1/platform/tenants/{$this->businessA->id}/domains/{$betaDomainId}")
            ->assertStatus(404);
        $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/domains/{$betaDomainId}/verify")
            ->assertStatus(404);

        // Tenant staff never reach the control plane.
        $this->actingAs($this->adminA)
            ->getJson("/api/v1/platform/tenants/{$this->businessA->id}/branding")
            ->assertStatus(403);
        $this->actingAs($this->adminA)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/domains", ['host' => 'sneaky.test'])
            ->assertStatus(403);

        $this->assertDatabaseHas('tenant_domains', ['id' => $betaDomainId, 'host' => 'portal.beta.test']);
    }

    public function test_removing_the_primary_domain_promotes_another(): void
    {
        $super = $this->platformUser();

        $this->actingAs($super)->postJson("/api/v1/platform/tenants/{$this->businessA->id}/domains", ['host' => 'one.alpha.test', 'isPrimary' => true])->assertStatus(201);
        $this->actingAs($super)->postJson("/api/v1/platform/tenants/{$this->businessA->id}/domains", ['host' => 'two.alpha.test'])->assertStatus(201);

        $this->actingAs($super)
            ->deleteJson("/api/v1/platform/tenants/{$this->businessA->id}/domains/".$this->domainId('one.alpha.test'))
            ->assertOk();

        $this->assertTrue((bool) TenantDomain::where('host', 'two.alpha.test')->first()->is_primary);
        $this->assertDatabaseMissing('tenant_domains', ['host' => 'one.alpha.test']);
    }

    private function domainId(string $host): int
    {
        return (int) TenantDomain::where('host', $host)->value('id');
    }
}
