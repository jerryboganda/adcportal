<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\TenantBranding;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Account name vs white-label brand — the two tenant names.
 *
 * - `businesses.name` is the platform ACCOUNT name (control plane, lists,
 *   audit, memberships).
 * - `tenant_brandings.app_name` is the BRAND the tenant's users see
 *   (login screen, navbar, browser title, emails).
 *
 * These are different concepts and MUST be independently settable and
 * independently observable, so "I changed the brand" can never silently mean
 * "the account got renamed" (or vice versa).
 */
class TenantIdentityAndBrandingTest extends ApiTestCase
{
    private function makePlatformUser(string $role = 'super_admin'): User
    {
        return User::create([
            'name' => "Platform {$role}",
            'email' => 'platform.'.md5(uniqid('', true)).'@test.local',
            'password' => 'R1s!T3st#2026x',
            'email_verified_at' => now(),
            'type' => $role === 'super_admin' ? 'super_admin' : 'platform_admin',
            'platform_role' => $role === 'super_admin' ? null : $role,
            'active_status' => 1,
            'lang' => 'en',
        ]);
    }

    public function test_tenant_payload_exposes_both_account_name_and_brand(): void
    {
        TenantBranding::create(['business_id' => $this->businessA->id, 'app_name' => 'Alpha Care Portal']);

        $tenant = $this->actingAs($this->makePlatformUser())
            ->getJson("/api/v1/platform/tenants/{$this->businessA->id}")
            ->assertStatus(200)
            ->json('data.tenant');

        $this->assertSame('Alpha Diagnostic Centre', $tenant['name']);
        $this->assertSame('Alpha Care Portal', $tenant['brandName']);
    }

    public function test_brand_save_does_not_touch_the_account_name(): void
    {
        $platformUser = $this->makePlatformUser();

        $this->actingAs($platformUser)
            ->confirmStepUp()
            ->putJson("/api/v1/platform/tenants/{$this->businessA->id}/branding", [
                'appName' => 'DHQ Hospital Gujranwala',
            ])
            ->assertStatus(200);

        // The whole point of the two-name model: brand changed, account not.
        $this->assertSame('Alpha Diagnostic Centre', $this->businessA->fresh()->name);
        $this->assertSame('DHQ Hospital Gujranwala', $this->businessA->fresh()->branding->app_name);
    }

    public function test_account_rename_is_independent_of_brand_and_audited(): void
    {
        TenantBranding::create(['business_id' => $this->businessA->id, 'app_name' => 'Alpha Care Portal']);
        $platformUser = $this->makePlatformUser();

        $renamed = $this->actingAs($platformUser)
            ->confirmStepUp()
            ->patchJson("/api/v1/platform/tenants/{$this->businessA->id}", [
                'name' => 'Alpha Medical Group',
            ])
            ->assertStatus(200)
            ->json('data.tenant');

        // Renaming the account leaves the brand alone.
        $this->assertSame('Alpha Medical Group', $renamed['name']);
        $this->assertSame('Alpha Care Portal', $renamed['brandName']);
        $this->assertSame('Alpha Medical Group', $this->businessA->fresh()->name);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tenant_renamed',
            'business_id' => $this->businessA->id,
        ]);
    }

    public function test_rename_is_step_up_gated(): void
    {
        $this->actingAs($this->makePlatformUser())
            ->patchJson("/api/v1/platform/tenants/{$this->businessA->id}", [
                'name' => 'No Fresh Password',
            ])
            ->assertStatus(428);

        $this->assertSame('Alpha Diagnostic Centre', $this->businessA->fresh()->name);
    }

    public function test_me_exposes_the_active_tenant_brand_and_membership_brands(): void
    {
        TenantBranding::create(['business_id' => $this->businessA->id, 'app_name' => 'Alpha Care Portal']);

        $me = $this->actingAs($this->adminA)
            ->getJson('/api/v1/me')
            ->assertStatus(200)
            ->json('data.user');

        $this->assertSame('Alpha Diagnostic Centre', $me['businessName']);
        $this->assertSame('Alpha Care Portal', $me['businessBrandName']);
        $this->assertSame(
            'Alpha Care Portal',
            collect($me['memberships'])->firstWhere('businessId', (int) $this->businessA->id)['businessBrandName']
        );
    }
}
