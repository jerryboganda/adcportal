<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Location;
use App\Models\TenantIntegration;
use App\Models\UsageCounter;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Per-tenant health rollup for the observability dashboard (master-prompt §80).
 *
 * Every figure is a real count from a real table, and the verdict is DERIVED
 * from those figures with its reasons attached — an operator must never see a
 * green badge that no data supports, nor a red one they cannot explain.
 *
 * Cost: the cheap aggregates (integrations, seats, facilities, studies) are
 * computed in bulk, so the dashboard stays a fixed handful of queries whatever
 * the tenant count. The one genuinely per-tenant measurement — bytes on disk —
 * is only paid for the tenants actually returned, which the payload states.
 */
class TenantHealthService
{
    public const OK = 'ok';

    public const DEGRADED = 'degraded';

    public const CRITICAL = 'critical';

    /** Worst first. */
    private const SEVERITY = [self::CRITICAL => 3, self::DEGRADED => 2, self::OK => 1];

    /** A quota at or above this share of the plan limit is "approaching". */
    private const NEAR_LIMIT = 0.9;

    /**
     * @return array{tenants: array<int, array>, summary: array, deployments: array, provisioningStuck: array, totalTenants: int}
     */
    public static function dashboard(int $limit = 25): array
    {
        $period = now()->format('Y-m');

        $integrations = TenantIntegration::query()
            ->select(
                'business_id',
                DB::raw("sum(case when status = 'error' then 1 else 0 end) as failing"),
                DB::raw("sum(case when status = 'unconfigured' then 1 else 0 end) as unconfigured"),
                DB::raw('count(*) as total')
            )
            ->groupBy('business_id')
            ->get()
            ->keyBy('business_id');

        $seats = self::intMap(
            User::query()->where('type', '!=', 'customer')
                ->select('business_id', DB::raw('count(*) as total'))
                ->groupBy('business_id')
        );

        $facilities = self::intMap(
            Location::query()
                ->select('business_id', DB::raw('count(*) as total'))
                ->groupBy('business_id')
        );

        $studies = self::intMap(
            UsageCounter::query()->where('metric', 'studies')->where('period', $period)
                ->select('business_id', DB::raw('sum(value) as total'))
                ->groupBy('business_id')
        );

        $rows = [];
        $deployments = [];

        foreach (Business::query()->orderBy('id')->get() as $tenant) {
            $aggregate = $integrations->get($tenant->id);

            $row = self::row($tenant, [
                'failing' => (int) ($aggregate->failing ?? 0),
                'unconfigured' => (int) ($aggregate->unconfigured ?? 0),
                'integrations' => (int) ($aggregate->total ?? 0),
                'seats' => $seats[$tenant->id] ?? 0,
                'facilities' => $facilities[$tenant->id] ?? 0,
                'studies' => $studies[$tenant->id] ?? 0,
            ]);

            $rows[] = $row;

            $key = ($tenant->region ?: 'unplaced').'/'.($tenant->deployment_stamp ?: 'unplaced');
            $deployments[$key] ??= [
                'region' => $tenant->region,
                'deploymentStamp' => $tenant->deployment_stamp,
                'isolationProfile' => $tenant->isolation_profile,
                'tenants' => 0,
                'degraded' => 0,
                'critical' => 0,
            ];
            $deployments[$key]['tenants']++;
            if ($row['health'] === self::CRITICAL) {
                $deployments[$key]['critical']++;
            } elseif ($row['health'] === self::DEGRADED) {
                $deployments[$key]['degraded']++;
            }
        }

        $summary = [
            'total' => count($rows),
            'ok' => count(array_filter($rows, fn ($r) => $r['health'] === self::OK)),
            'degraded' => count(array_filter($rows, fn ($r) => $r['health'] === self::DEGRADED)),
            'critical' => count(array_filter($rows, fn ($r) => $r['health'] === self::CRITICAL)),
            'failingIntegrations' => array_sum(array_column($rows, 'failingIntegrations')),
            'quotaBreaches' => count(array_filter($rows, fn ($r) => $r['quotasBreached'] !== [])),
        ];

        // Worst first, then oldest tenant — stable and explainable.
        usort($rows, fn ($a, $b) => [self::SEVERITY[$b['health']], $a['tenantId']] <=> [self::SEVERITY[$a['health']], $b['tenantId']]);

        $shown = array_slice($rows, 0, $limit);

        // Bytes on disk are the only per-tenant measurement we cannot batch, so
        // we pay for them only for the rows we actually return.
        foreach ($shown as $index => $row) {
            $bytes = StorageMeter::bytes($row['tenantId']);
            $shown[$index]['storageBytes'] = $bytes;
            $shown[$index]['quotas']['storage'] = self::pressure(
                $bytes,
                $row['limits']['maxStorageMb'] === null ? null : $row['limits']['maxStorageMb'] * 1024 * 1024
            );
        }

        return [
            'totalTenants' => count($rows),
            'returned' => count($shown),
            'storageBasis' => 'measured for the tenants returned',
            'summary' => $summary,
            'tenants' => $shown,
            'deployments' => array_values($deployments),
            'provisioningStuck' => array_values(array_filter(
                $shown,
                fn ($r) => $r['subscriptionStatus'] === 'provisioning'
            )),
        ];
    }

    /** One tenant's health row: real numbers plus an explained verdict. */
    private static function row(Business $tenant, array $counts): array
    {
        $limits = EntitlementService::limits($tenant);

        $quotas = [
            'users' => self::pressure($counts['seats'], $limits['maxUsers']),
            'studies' => self::pressure($counts['studies'], $limits['maxStudiesPerMonth']),
            'locations' => self::pressure($counts['facilities'], $limits['maxLocations']),
            // Storage is filled in later, for the returned rows only.
            'storage' => self::pressure(null, $limits['maxStorageMb'] === null ? null : $limits['maxStorageMb'] * 1024 * 1024),
        ];

        $breached = array_keys(array_filter($quotas, fn ($q) => $q['exceeded']));
        $near = array_keys(array_filter($quotas, fn ($q) => $q['nearLimit']));

        $reasons = [];
        $critical = false;
        $degraded = false;

        if ($counts['failing'] > 0) {
            $reasons[] = "{$counts['failing']} integration(s) failing their health check.";
            $critical = true;
        }

        if ($breached !== []) {
            $reasons[] = 'Quota exceeded: '.implode(', ', $breached).'.';
            $critical = true;
        }

        if ($counts['unconfigured'] > 0) {
            $reasons[] = "{$counts['unconfigured']} integration(s) not fully configured.";
            $degraded = true;
        }

        if ($near !== []) {
            $reasons[] = 'Approaching quota: '.implode(', ', $near).'.';
            $degraded = true;
        }

        if (! in_array($tenant->subscription_status, ['active', 'trialing'], true)) {
            $reasons[] = "Subscription status is '{$tenant->subscription_status}'.";
            $degraded = true;
        }

        if (self::trialExpiringSoon($tenant)) {
            $reasons[] = 'Trial ends within 3 days.';
            $degraded = true;
        }

        $health = $critical ? self::CRITICAL : ($degraded ? self::DEGRADED : self::OK);

        return [
            'tenantId' => (int) $tenant->id,
            'name' => $tenant->name,
            'tenantCode' => $tenant->tenant_code,
            'subscriptionStatus' => $tenant->subscription_status,
            'trialEndsAt' => $tenant->trial_ends_at?->toIso8601String(),
            'subscriptionEndsAt' => $tenant->subscription_ends_at?->toIso8601String(),
            'region' => $tenant->region,
            'deploymentStamp' => $tenant->deployment_stamp,
            'isolationProfile' => $tenant->isolation_profile,
            'health' => $health,
            'reasons' => $reasons,
            'integrations' => $counts['integrations'],
            'failingIntegrations' => $counts['failing'],
            'unconfiguredIntegrations' => $counts['unconfigured'],
            'usage' => [
                'users' => $counts['seats'],
                'facilities' => $counts['facilities'],
                'studiesThisMonth' => $counts['studies'],
            ],
            'limits' => $limits,
            'quotas' => $quotas,
            'quotasBreached' => $breached,
            'quotasApproaching' => $near,
            // Filled in for returned rows only; null means "not measured here".
            'storageBytes' => null,
        ];
    }

    /**
     * @return array{used: int|null, limit: int|null, percent: float|null, nearLimit: bool, exceeded: bool}
     */
    private static function pressure(?int $used, ?int $limit): array
    {
        // No declared limit → unlimited: nothing to pressure.
        if ($limit === null || $limit <= 0) {
            return ['used' => $used, 'limit' => null, 'percent' => null, 'nearLimit' => false, 'exceeded' => false];
        }

        // Not measured yet (storage outside the returned page): stay silent
        // rather than report a comfortable zero we have not actually verified.
        if ($used === null) {
            return ['used' => null, 'limit' => $limit, 'percent' => null, 'nearLimit' => false, 'exceeded' => false];
        }

        $percent = round($used / $limit * 100, 1);

        return [
            'used' => $used,
            'limit' => $limit,
            'percent' => $percent,
            'nearLimit' => $percent >= self::NEAR_LIMIT * 100 && $used <= $limit,
            'exceeded' => $used > $limit,
        ];
    }

    private static function trialExpiringSoon(Business $tenant): bool
    {
        return $tenant->subscription_status === 'trialing'
            && $tenant->trial_ends_at !== null
            && $tenant->trial_ends_at->isBetween(now(), now()->addDays(3));
    }

    /** @return array<int, int> */
    private static function intMap($query): array
    {
        return $query->pluck('total', 'business_id')
            ->mapWithKeys(fn ($value, $key) => [(int) $key => (int) $value])
            ->all();
    }
}
