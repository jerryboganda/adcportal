<?php

namespace App\Services;

use App\Models\Business;
use App\Models\TenantFeatureOverride;

/**
 * Entitlement reconciliation (master-prompt §81).
 *
 * Effective features resolve in three layers — platform default → plan
 * `features` → tenant override — and the stored overrides drift from the
 * catalog over time: a deploy can rename or remove a feature, and an override
 * can be stored that merely restates what the plan already says. That drift is
 * invisible in the UI, because the resolver simply ignores unknown keys, which
 * is exactly why it needs an explicit report.
 *
 * `apply()` only ever prunes overrides for features the catalog no longer
 * declares: those are inert today and would silently come back to life if a
 * future deploy reintroduced the key. Overrides that contradict the plan are
 * reported, never deleted — they are a deliberate commercial decision, not a
 * defect.
 */
class EntitlementReconciler
{
    /** Drift report; changes nothing. */
    public static function report(Business $tenant): array
    {
        $catalog = array_keys((array) config('ris.features', []));
        $planFeatures = (array) ($tenant->plan?->features ?? []);

        $rows = [];
        $stale = [];
        $redundant = [];
        $contradicting = [];

        $overrides = TenantFeatureOverride::query()
            ->where('business_id', $tenant->id)
            ->orderBy('feature')
            ->get();

        foreach ($overrides as $override) {
            $feature = (string) $override->feature;
            $enabled = (bool) $override->enabled;

            $inCatalog = in_array($feature, $catalog, true);
            $planValue = array_key_exists($feature, $planFeatures) ? (bool) $planFeatures[$feature] : null;
            $platformDefault = $inCatalog ? (bool) (config('ris.features')[$feature] ?? false) : null;

            if (! $inCatalog) {
                $state = 'stale';
                $stale[] = $feature;
            } elseif ($planValue === null) {
                // Not a drift: the plan is silent, so the override is the only
                // statement of intent. Reported for completeness.
                $state = 'override-without-plan';
            } elseif ($planValue === $enabled) {
                $state = 'redundant';
                $redundant[] = $feature;
            } else {
                $state = 'overrides-plan';
                $contradicting[] = $feature;
            }

            $rows[] = [
                'feature' => $feature,
                'override' => $enabled,
                'plan' => $planValue,
                'platformDefault' => $platformDefault,
                'effective' => FeatureResolver::enabled($tenant, $feature),
                'state' => $state,
            ];
        }

        return [
            'catalog' => $catalog,
            'planFeatures' => $planFeatures,
            'overrides' => $rows,
            'stale' => $stale,
            'redundant' => $redundant,
            'contradicting' => $contradicting,
            'effective' => FeatureResolver::features($tenant),
            'pruned' => [],
            'driftDetected' => $stale !== [] || $redundant !== [],
        ];
    }

    /** Report, then prune the inert overrides for features outside the catalog. */
    public static function apply(Business $tenant): array
    {
        $report = self::report($tenant);

        if ($report['stale'] !== []) {
            TenantFeatureOverride::query()
                ->where('business_id', $tenant->id)
                ->whereIn('feature', $report['stale'])
                ->delete();
        }

        $report['pruned'] = $report['stale'];
        $report['stale'] = [];
        $report['overrides'] = array_values(array_filter(
            $report['overrides'],
            fn (array $row) => $row['state'] !== 'stale'
        ));
        $report['effective'] = FeatureResolver::features($tenant);
        $report['driftDetected'] = $report['redundant'] !== [];

        return $report;
    }
}
