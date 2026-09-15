<?php

namespace App\Services;

use App\Models\Business;
use App\Models\TenantBranding;
use App\Models\TenantDomain;

/**
 * White-label resolution (master-prompt §37/§38).
 *
 * Two independent lookups, deliberately separated:
 *
 *  1. brandingFor(Business)  — after the server resolved the tenant from the
 *     authenticated principal. Used by the tenant application shell.
 *  2. brandingForHost(host)  — public, unauthenticated, presentation ONLY.
 *     It exists so the login screen can show a customer's brand before anyone
 *     signs in. A matching host is NOT proof of identity and never selects a
 *     tenant for data access: the host only chooses which logo/name/colour the
 *     login page paints. Authorization always re-derives the tenant from the
 *     session (getActiveBusiness()).
 */
class TenantBrandingService
{
    /** Platform defaults used when a tenant has no branding row / no host match. */
    public static function defaults(): array
    {
        return [
            'appName' => config('ris.platform_brand.app_name'),
            'primaryColor' => config('ris.platform_brand.primary_color'),
            'accentColor' => config('ris.platform_brand.accent_color'),
            'logoUrl' => config('ris.platform_brand.logo_url'),
            'faviconUrl' => config('ris.platform_brand.favicon_url'),
            'loginMessage' => null,
            'reportHeader' => null,
            'reportFooter' => null,
            'emailFromName' => config('ris.platform_brand.app_name'),
            'emailFromAddress' => config('ris.platform_brand.email_from_address'),
            'supportEmail' => null,
            'supportPhone' => null,
        ];
    }

    public static function shape(?TenantBranding $branding): array
    {
        $defaults = self::defaults();

        if (! $branding) {
            return $defaults;
        }

        $pick = fn ($value, $fallback) => ($value === null || $value === '') ? $fallback : $value;

        return [
            'appName' => $pick($branding->app_name, $defaults['appName']),
            'primaryColor' => $pick($branding->primary_color, $defaults['primaryColor']),
            'accentColor' => $pick($branding->accent_color, $defaults['accentColor']),
            'logoUrl' => $pick($branding->logo_url, $defaults['logoUrl']),
            'faviconUrl' => $pick($branding->favicon_url, $defaults['faviconUrl']),
            'loginMessage' => $branding->login_message,
            'reportHeader' => $branding->report_header,
            'reportFooter' => $branding->report_footer,
            'emailFromName' => $pick($branding->email_from_name, $defaults['emailFromName']),
            'emailFromAddress' => $pick($branding->email_from_address, $defaults['emailFromAddress']),
            'supportEmail' => $branding->support_email,
            'supportPhone' => $branding->support_phone,
        ];
    }

    /** Effective branding for a resolved tenant (tenant plane / control plane). */
    public static function forTenant(Business $tenant): array
    {
        return self::shape(TenantBranding::where('business_id', $tenant->id)->first());
    }

    /**
     * Public presentation lookup by host. Returns platform defaults for an
     * unknown OR unverified host — the caller must never treat the result as
     * identity. Only domains the operator has proven control of (DNS TXT
     * verification) are allowed to serve a tenant's brand.
     */
    public static function forHost(?string $host): array
    {
        $normalized = TenantDomain::normalizeHost($host);

        $unmatched = ['tenantId' => null, 'tenantCode' => null, 'matched' => false, 'verified' => false];

        if (! $normalized) {
            return $unmatched + self::defaults();
        }

        $domain = TenantDomain::where('host', $normalized)->whereNotNull('verified_at')->first();

        if (! $domain) {
            return $unmatched + self::defaults();
        }

        $tenant = Business::find($domain->business_id);

        if (! $tenant) {
            return $unmatched + self::defaults();
        }

        return [
            'tenantId' => (string) $tenant->id,
            'tenantCode' => $tenant->tenant_code,
            'matched' => true,
            'verified' => true,
        ] + self::forTenant($tenant);
    }

    /** Domain registry of one tenant (control-plane view). */
    public static function domains(Business $tenant): array
    {
        return TenantDomain::where('business_id', $tenant->id)
            ->orderByDesc('is_primary')
            ->orderBy('host')
            ->get()
            ->map(fn (TenantDomain $d) => [
                'id' => (string) $d->id,
                'host' => $d->host,
                'isPrimary' => (bool) $d->is_primary,
                'verifiedAt' => $d->verified_at?->toIso8601String(),
                'createdAt' => $d->created_at?->toIso8601String(),
            ])->all();
    }
}
