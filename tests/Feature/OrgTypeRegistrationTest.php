<?php

namespace Tests\Feature;

use App\Http\Resources\ApiShape;
use App\Models\Business;
use App\Services\TenantLifecycleService;

/**
 * Registration organization flavor: the public signup accepts a
 * clinic|hospital discriminator. Both flavors provision the identical
 * portal/permission set today — only the stored label differs.
 */
class OrgTypeRegistrationTest extends ApiTestCase
{
    public function test_registration_defaults_to_clinic_when_org_type_omitted(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'clinic_name' => 'Default Flavor Centre',
            'name' => 'Dr. Default',
            'email' => 'default-flavor@test.local',
            'password' => 'Str0ng!Passphrase',
        ])->assertCreated();

        $tenant = Business::where('name', 'Default Flavor Centre')->firstOrFail();
        $this->assertSame('clinic', $tenant->org_type);
        $this->assertSame('trialing', $response->json('data.user.subscriptionStatus'));
    }

    public function test_hospital_registration_stores_org_type_and_provisions_identically(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'org_type' => 'hospital',
            'clinic_name' => 'General Hospital Radiology',
            'name' => 'Dr. Hospital',
            'email' => 'hospital-reg@test.local',
            'password' => 'Str0ng!Passphrase',
        ])->assertCreated();

        $tenant = Business::where('name', 'General Hospital Radiology')->firstOrFail();
        $this->assertSame('hospital', $tenant->org_type);
        $this->assertSame('admin', $response->json('data.user.role'));

        // Same portal: the new hospital admin gets the standard seeded masters.
        $bootstrap = $this->getJson('/api/v1/bootstrap')->assertOk();
        $this->assertCount(5, $bootstrap->json('data.modalities'));
    }

    public function test_explicit_clinic_registration_is_accepted(): void
    {
        $this->postJson('/api/v1/register', [
            'org_type' => 'clinic',
            'clinic_name' => 'Explicit Clinic',
            'name' => 'Dr. Explicit',
            'email' => 'explicit@test.local',
            'password' => 'Str0ng!Passphrase',
        ])->assertCreated();

        $this->assertSame(
            'clinic',
            Business::where('name', 'Explicit Clinic')->value('org_type')
        );
    }

    public function test_invalid_org_type_is_rejected(): void
    {
        $this->postJson('/api/v1/register', [
            'org_type' => 'laboratory',
            'clinic_name' => 'Bad Flavor Centre',
            'name' => 'Dr. Bad',
            'email' => 'bad-flavor@test.local',
            'password' => 'Str0ng!Passphrase',
        ])->assertStatus(422)->assertJsonValidationErrors(['org_type']);

        $this->assertFalse(Business::where('name', 'Bad Flavor Centre')->exists());
    }

    public function test_api_shape_exposes_org_type(): void
    {
        $result = app(TenantLifecycleService::class)->provision('Shape Check Clinic', [
            'name' => 'Dr. Shape',
            'email' => 'shape@test.local',
            'password' => 'Str0ng!Passphrase',
        ]);

        $shape = ApiShape::tenant($result['business']->refresh());
        $this->assertSame('clinic', $shape['orgType']);
    }

    public function test_service_provisioning_defaults_to_clinic(): void
    {
        // Platform console path passes no flavor — must stay 'clinic'.
        $result = app(TenantLifecycleService::class)->provision('Service Default Clinic', [
            'name' => 'Dr. Service',
            'email' => 'service-default@test.local',
            'password' => 'Str0ng!Passphrase',
        ]);

        $this->assertSame('clinic', $result['business']->org_type);
    }

    public function test_service_provisioning_accepts_hospital_flavor(): void
    {
        $result = app(TenantLifecycleService::class)->provision('Service Hospital', [
            'name' => 'Dr. Service H',
            'email' => 'service-hospital@test.local',
            'password' => 'Str0ng!Passphrase',
        ], orgType: 'hospital');

        $this->assertSame('hospital', $result['business']->org_type);
    }
}
