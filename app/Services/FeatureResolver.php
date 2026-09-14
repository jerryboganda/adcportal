<?php

namespace App\Services;

use App\Models\Business;
use App\Models\TenantFeatureOverride;

/**
 * Deterministic feature-flag resolution for a tenant:
 *   platform default (config/ris.php) → plan `features` JSON → tenant override.
 * The server enforces the result on the API; the SPA only mirrors it.
 */
class FeatureResolver
{
    public static function features(Business $tenant): array
    {
        $features = array_map(fn ($v) => (bool) $v, (array) config('ris.features', []));

        $planFeatures = $tenant->plan?->features ?? [];
        foreach ((array) $planFeatures as $feature => $value) {
            if (array_key_exists($feature, $features)) {
                $features[$feature] = (bool) $value;
            }
        }

        foreach (TenantFeatureOverride::where('business_id', $tenant->id)->get() as $override) {
            if (array_key_exists($override->feature, $features)) {
                $features[$override->feature] = $override->enabled;
            }
        }

        return $features;
    }

    public static function enabled(Business $tenant, string $feature): bool
    {
        return (bool) (self::features($tenant)[$feature] ?? false);
    }
}
