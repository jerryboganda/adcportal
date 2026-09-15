<?php

namespace Tests\Feature;

use App\Models\TenantFeatureOverride;
use App\Models\TenantIntegration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Tenant integration registry (master-prompt §33/§44).
 *
 * Proves: integrations are tenant/facility scoped, credentials are encrypted at
 * rest and never returned, entitlement gating is server-side, cross-tenant
 * access is impossible, and health checks are real (socket / HTTP) rather than
 * a cosmetic "connected" badge.
 */
class TenantIntegrationTest extends ApiTestCase
{
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

    public function test_catalog_lists_only_implemented_types_with_their_entitlements(): void
    {
        $body = $this->actingAs($this->platformUser())
            ->getJson("/api/v1/platform/tenants/{$this->businessA->id}/integrations")
            ->assertOk()
            ->decodeResponseJson()['data'];

        $types = array_column($body['catalog'], 'type');
        $this->assertContains('dicom', $types);
        $this->assertContains('fhir', $types);
        $this->assertNotContains('ai', $types, 'unimplemented types must not be advertised');
        $this->assertSame([], $body['integrations']);
        $this->assertTrue($body['entitlements']['dicom']);
    }

    public function test_integration_credentials_are_encrypted_at_rest_and_never_returned(): void
    {
        $super = $this->platformUser();
        $plaintext = 'super-secret-signing-value';

        $res = $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/integrations", [
                'type' => 'webhook',
                'name' => 'EHR outbound webhook',
                'config' => ['url' => 'https://ehr.alpha.test/hooks/ris'],
                'secrets' => ['signingSecret' => $plaintext],
            ])
            ->assertStatus(201);

        $body = $res->decodeResponseJson()['data'];
        $id = (int) $body['integration']['id'];

        // The API reports presence + a mask, never the value.
        $this->assertTrue($body['integration']['secrets']['signingSecret']['present']);
        $this->assertSame('••••••••', $body['integration']['secrets']['signingSecret']['mask']);
        $this->assertStringNotContainsString($plaintext, $res->getContent());

        // At rest it is ciphertext…
        $raw = (string) DB::table('tenant_integrations')->where('id', $id)->value('secrets');
        $this->assertStringNotContainsString($plaintext, $raw);
        // …and the model still decrypts it back for server-side use.
        $this->assertSame($plaintext, TenantIntegration::find($id)->secrets['signingSecret']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant_integration_created', 'business_id' => $this->businessA->id]);
    }

    public function test_unknown_secret_keys_are_discarded_and_missing_config_is_unconfigured(): void
    {
        $super = $this->platformUser();

        $body = $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/integrations", [
                'type' => 'fhir',
                'name' => 'FHIR — not yet configured',
                'config' => [],
                'secrets' => ['clientSecret' => 'x', 'totallyUnrelated' => 'y'],
            ])
            ->assertStatus(201)
            ->decodeResponseJson()['data'];

        $id = (int) $body['integration']['id'];

        $this->assertSame('unconfigured', $body['integration']['status']);
        // Only declared credential keys are ever stored.
        $this->assertSame(['clientSecret'], array_keys(TenantIntegration::find($id)->secrets));
    }

    public function test_integration_type_is_gated_by_its_entitlement(): void
    {
        $super = $this->platformUser();

        TenantFeatureOverride::create([
            'business_id' => $this->businessA->id,
            'feature' => 'interop',
            'enabled' => false,
        ]);

        $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/integrations", [
                'type' => 'fhir',
                'name' => 'Blocked FHIR',
                'config' => ['baseUrl' => 'https://fhir.alpha.test'],
            ])
            ->assertStatus(403);

        // A type whose entitlement is still on keeps working.
        $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/integrations", [
                'type' => 'dicom',
                'name' => 'PACS',
                'config' => ['host' => '127.0.0.1', 'port' => '104', 'aeTitle' => 'RIS'],
            ])
            ->assertStatus(201);
    }

    public function test_facility_scope_must_belong_to_the_same_tenant(): void
    {
        $super = $this->platformUser();

        $betaFacility = \App\Models\Location::create([
            'name' => 'Beta Clinic',
            'address' => 'Beta',
            'business_id' => $this->businessB->id,
            'created_by' => $this->adminB->id,
        ]);

        $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/integrations", [
                'type' => 'dicom',
                'name' => 'Cross-facility attempt',
                'facilityId' => $betaFacility->id,
                'config' => ['host' => '127.0.0.1', 'port' => '104', 'aeTitle' => 'RIS'],
            ])
            ->assertStatus(422);
    }

    public function test_tcp_probe_reports_a_real_unreachable_endpoint_as_error(): void
    {
        $super = $this->platformUser();

        $id = (int) $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/integrations", [
                'type' => 'dicom',
                'name' => 'Dead PACS',
                'config' => ['host' => '127.0.0.1', 'port' => '1', 'aeTitle' => 'RIS'],
            ])
            ->assertStatus(201)
            ->decodeResponseJson()['data']['integration']['id'];

        $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/integrations/{$id}/probe")
            ->assertOk()
            ->assertJsonPath('data.probe.status', 'error')
            ->assertJsonPath('data.probe.check', 'tcp');

        $this->assertSame('error', TenantIntegration::find($id)->status);
        $this->assertNotNull(TenantIntegration::find($id)->last_error);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant_integration_probed', 'business_id' => $this->businessA->id]);
    }

    public function test_http_probe_uses_a_real_request_and_reports_server_errors(): void
    {
        $super = $this->platformUser();

        $id = (int) $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/integrations", [
                'type' => 'fhir',
                'name' => 'FHIR server',
                'config' => ['baseUrl' => 'https://fhir.alpha.test/baseR4'],
                'secrets' => ['clientId' => 'id', 'clientSecret' => 'secret'],
            ])
            ->assertStatus(201)
            ->decodeResponseJson()['data']['integration']['id'];

        Http::fake(['https://fhir.alpha.test/*' => Http::response(['resourceType' => 'CapabilityStatement'], 200)]);
        $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/integrations/{$id}/probe")
            ->assertOk()
            ->assertJsonPath('data.probe.status', 'active')
            ->assertJsonPath('data.probe.check', 'http');

        Http::fake(['https://fhir.alpha.test/*' => Http::response('boom', 503)]);
        $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/integrations/{$id}/probe")
            ->assertOk()
            ->assertJsonPath('data.probe.status', 'error');
    }

    public function test_configuration_only_probe_never_claims_delivery(): void
    {
        $super = $this->platformUser();

        $id = (int) $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/integrations", [
                'type' => 'sms',
                'name' => 'SMS gateway',
                'config' => ['provider' => 'twilio'],
                'secrets' => ['apiKey' => 'k', 'senderId' => 'RIS'],
            ])
            ->assertStatus(201)
            ->decodeResponseJson()['data']['integration']['id'];

        $res = $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/integrations/{$id}/probe")
            ->assertOk();

        $this->assertSame('active', $res->decodeResponseJson()['data']['probe']['status']);
        $this->assertSame('configuration', $res->decodeResponseJson()['data']['probe']['check']);
        $this->assertStringContainsString('delivery is not asserted', $res->decodeResponseJson()['data']['probe']['detail']);
    }

    public function test_secret_rotation_replaces_credentials_and_is_audited(): void
    {
        $super = $this->platformUser();

        $id = (int) $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/integrations", [
                'type' => 'whatsapp',
                'name' => 'WhatsApp',
                'config' => ['phoneNumberId' => '1234'],
                'secrets' => ['accessToken' => 'old-token'],
            ])
            ->assertStatus(201)
            ->decodeResponseJson()['data']['integration']['id'];

        $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/integrations/{$id}/secrets", [
                'secrets' => ['accessToken' => 'new-token'],
            ])
            ->assertOk();

        $this->assertSame('new-token', TenantIntegration::find($id)->secrets['accessToken']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant_integration_secret_rotated', 'business_id' => $this->businessA->id]);

        // A payload with no recognised credential key is rejected outright.
        $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/integrations/{$id}/secrets", [
                'secrets' => ['notACredential' => 'x'],
            ])
            ->assertStatus(422);
    }

    public function test_cross_tenant_integration_access_is_404_and_tenant_staff_are_blocked(): void
    {
        $super = $this->platformUser();

        $betaId = (int) $this->actingAs($super)
            ->postJson("/api/v1/platform/tenants/{$this->businessB->id}/integrations", [
                'type' => 'dicom',
                'name' => 'Beta PACS',
                'config' => ['host' => '127.0.0.1', 'port' => '104', 'aeTitle' => 'BETA'],
            ])
            ->assertStatus(201)
            ->decodeResponseJson()['data']['integration']['id'];

        foreach ([
            ['patch', "/api/v1/platform/tenants/{$this->businessA->id}/integrations/{$betaId}", ['name' => 'hijack']],
            ['post', "/api/v1/platform/tenants/{$this->businessA->id}/integrations/{$betaId}/probe", []],
            ['post', "/api/v1/platform/tenants/{$this->businessA->id}/integrations/{$betaId}/secrets", ['secrets' => ['x' => 'y']]],
            ['delete', "/api/v1/platform/tenants/{$this->businessA->id}/integrations/{$betaId}", []],
        ] as [$verb, $url, $payload]) {
            $this->actingAs($super)->{$verb.'Json'}($url, $payload)->assertStatus(404);
        }

        // Tenant staff never reach the control plane at all.
        $this->actingAs($this->adminA)
            ->getJson("/api/v1/platform/tenants/{$this->businessA->id}/integrations")
            ->assertStatus(403);
        $this->actingAs($this->adminA)
            ->postJson("/api/v1/platform/tenants/{$this->businessA->id}/integrations", ['type' => 'dicom', 'name' => 'x'])
            ->assertStatus(403);

        // Beta's integration survived every attempt and stays in Beta.
        $this->assertDatabaseHas('tenant_integrations', ['id' => $betaId, 'business_id' => $this->businessB->id]);
    }

    public function test_integration_names_are_unique_per_tenant_only(): void
    {
        $super = $this->platformUser();
        $payload = ['type' => 'dicom', 'name' => 'Shared name', 'config' => ['host' => '127.0.0.1', 'port' => '104', 'aeTitle' => 'A']];

        $this->actingAs($super)->postJson("/api/v1/platform/tenants/{$this->businessA->id}/integrations", $payload)->assertStatus(201);
        $this->actingAs($super)->postJson("/api/v1/platform/tenants/{$this->businessA->id}/integrations", $payload)->assertStatus(422);
        // The same name is fine for a different tenant.
        $this->actingAs($super)->postJson("/api/v1/platform/tenants/{$this->businessB->id}/integrations", $payload)->assertStatus(201);
    }
}
