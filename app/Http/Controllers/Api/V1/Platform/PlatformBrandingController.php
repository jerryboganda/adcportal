<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\TenantBranding;
use App\Models\TenantDomain;
use App\Services\FeatureResolver;
use App\Services\TenantBrandingService;
use App\Services\TenantDomainVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * White-labeling + custom domains from the control plane (master-prompt
 * §37/§38). Branding is presentation-only; a custom domain is registered and
 * must be DNS-verified before it is allowed to serve a tenant's brand, and it
 * never becomes an authorization input.
 */
class PlatformBrandingController extends PlatformController
{
    /** Bare hostname: labels of a-z0-9 with inner hyphens, at least two labels. */
    private const HOST_REGEX = '/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/';

    public function index(Business $tenant): JsonResponse
    {
        $this->denyUnlessCapability('tenants.view');

        return $this->ok($this->payload($tenant));
    }

    /** Upsert the tenant's white-label overrides. */
    public function update(Request $request, Business $tenant): JsonResponse
    {
        $this->denyUnlessCapability('tenants.manage');
        $this->denyUnlessFeature($tenant, 'branding');

        $validated = $request->validate([
            'appName' => ['sometimes', 'nullable', 'string', 'max:120'],
            'primaryColor' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'accentColor' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'logoUrl' => ['sometimes', 'nullable', 'url', 'max:1024'],
            'faviconUrl' => ['sometimes', 'nullable', 'url', 'max:1024'],
            'loginMessage' => ['sometimes', 'nullable', 'string', 'max:500'],
            'reportHeader' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'reportFooter' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'emailFromName' => ['sometimes', 'nullable', 'string', 'max:120'],
            'emailFromAddress' => ['sometimes', 'nullable', 'email', 'max:255'],
            'supportEmail' => ['sometimes', 'nullable', 'email', 'max:255'],
            'supportPhone' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $map = [
            'appName' => 'app_name',
            'primaryColor' => 'primary_color',
            'accentColor' => 'accent_color',
            'logoUrl' => 'logo_url',
            'faviconUrl' => 'favicon_url',
            'loginMessage' => 'login_message',
            'reportHeader' => 'report_header',
            'reportFooter' => 'report_footer',
            'emailFromName' => 'email_from_name',
            'emailFromAddress' => 'email_from_address',
            'supportEmail' => 'support_email',
            'supportPhone' => 'support_phone',
        ];

        $updates = [];
        foreach ($map as $input => $column) {
            if (array_key_exists($input, $validated)) {
                $updates[$column] = $validated[$input];
            }
        }

        TenantBranding::updateOrCreate(
            ['business_id' => $tenant->id],
            [...$updates, 'updated_by' => $this->actor()->id]
        );

        AuditLog::record('tenant_branding_updated', $tenant, [
            'summary' => "White-label branding updated for {$tenant->name} (".implode(', ', array_keys($updates)).').'
                .' Acting platform user: '.$this->actor()->email.'.',
        ], $tenant->id);

        return $this->ok($this->payload($tenant));
    }

    /** Register a custom host for the tenant (unverified until DNS proves ownership). */
    public function storeDomain(Request $request, Business $tenant): JsonResponse
    {
        $this->denyUnlessCapability('tenants.manage');
        $this->denyUnlessFeature($tenant, 'custom_domains');

        $validated = $request->validate([
            'host' => ['required', 'string', 'max:253'],
            'isPrimary' => ['sometimes', 'boolean'],
        ]);

        $host = TenantDomain::normalizeHost($validated['host']);

        abort_unless($host && preg_match(self::HOST_REGEX, $host) === 1, 422, 'Enter a bare hostname such as ris.hospital.com (no scheme, port or path).');
        abort_if(TenantDomain::where('host', $host)->exists(), 422, 'That host is already registered.');

        $makePrimary = (bool) ($validated['isPrimary'] ?? false);

        $domain = \Illuminate\Support\Facades\DB::transaction(function () use ($tenant, $host, $makePrimary) {
            if ($makePrimary) {
                TenantDomain::where('business_id', $tenant->id)->update(['is_primary' => false]);
            }

            return TenantDomain::create([
                'business_id' => $tenant->id,
                'host' => $host,
                'is_primary' => $makePrimary || ! TenantDomain::where('business_id', $tenant->id)->exists(),
                'created_by' => $this->actor()->id,
            ]);
        });

        AuditLog::record('tenant_domain_added', $tenant, [
            'summary' => "Custom domain {$domain->host} registered for {$tenant->name} (unverified)."
                .' Acting platform user: '.$this->actor()->email.'.',
        ], $tenant->id);

        return response()->json([
            'data' => [
                ...$this->payload($tenant),
                'verification' => [
                    'record' => TenantDomainVerifier::RECORD_PREFIX.'.'.$domain->host,
                    'type' => 'TXT',
                    'value' => app(TenantDomainVerifier::class)->expectedValue($domain),
                ],
            ],
        ], 201);
    }

    /** Prove ownership through a live DNS TXT lookup, then let the host serve branding. */
    public function verifyDomain(Business $tenant, TenantDomain $domain): JsonResponse
    {
        $this->denyUnlessCapability('tenants.manage');
        abort_unless($domain->business_id === $tenant->id, 404);

        $result = app(TenantDomainVerifier::class)->verify($domain);

        if ($result['verified']) {
            $domain->forceFill(['verified_at' => now()])->save();
        } else {
            $domain->forceFill(['verified_at' => null])->save();
        }

        AuditLog::record('tenant_domain_verified', $tenant, [
            'summary' => "DNS verification for {$domain->host} (tenant {$tenant->name}): "
                .($result['verified'] ? 'verified' : 'failed').'.'
                .' Acting platform user: '.$this->actor()->email.'.',
        ], $tenant->id);

        return $this->ok([
            ...$this->payload($tenant),
            'verification' => [
                'record' => TenantDomainVerifier::RECORD_PREFIX.'.'.$domain->host,
                'type' => 'TXT',
                'value' => $result['expected'],
                'found' => $result['found'],
                'verified' => $result['verified'],
            ],
        ]);
    }

    public function makePrimary(Business $tenant, TenantDomain $domain): JsonResponse
    {
        $this->denyUnlessCapability('tenants.manage');
        abort_unless($domain->business_id === $tenant->id, 404);

        \Illuminate\Support\Facades\DB::transaction(function () use ($tenant, $domain) {
            TenantDomain::where('business_id', $tenant->id)->update(['is_primary' => false]);
            $domain->forceFill(['is_primary' => true])->save();
        });

        AuditLog::record('tenant_domain_updated', $tenant, [
            'summary' => "Primary domain for {$tenant->name} set to {$domain->host}."
                .' Acting platform user: '.$this->actor()->email.'.',
        ], $tenant->id);

        return $this->ok($this->payload($tenant));
    }

    public function destroyDomain(Business $tenant, TenantDomain $domain): JsonResponse
    {
        $this->denyUnlessCapability('tenants.manage');
        abort_unless($domain->business_id === $tenant->id, 404);

        $host = $domain->host;
        $wasPrimary = $domain->is_primary;
        $domain->delete();

        if ($wasPrimary) {
            $next = TenantDomain::where('business_id', $tenant->id)->orderBy('host')->first();
            $next?->forceFill(['is_primary' => true])->save();
        }

        AuditLog::record('tenant_domain_removed', $tenant, [
            'summary' => "Custom domain {$host} removed from {$tenant->name}."
                .' Acting platform user: '.$this->actor()->email.'.',
        ], $tenant->id);

        return $this->ok($this->payload($tenant));
    }

    // ==================== internals ====================

    private function payload(Business $tenant): array
    {
        $domains = TenantBrandingService::domains($tenant);

        return [
            'branding' => TenantBrandingService::forTenant($tenant),
            'brandingOverridden' => TenantBranding::where('business_id', $tenant->id)->exists(),
            'domains' => $domains,
            'entitlements' => [
                'branding' => FeatureResolver::enabled($tenant, 'branding'),
                'customDomains' => FeatureResolver::enabled($tenant, 'custom_domains'),
            ],
            'verificationRecord' => TenantDomainVerifier::RECORD_PREFIX.'.<host>',
        ];
    }

    private function denyUnlessFeature(Business $tenant, string $feature): void
    {
        abort_unless(
            FeatureResolver::enabled($tenant, $feature),
            403,
            "The [{$feature}] entitlement is not enabled for this tenant."
        );
    }
}
