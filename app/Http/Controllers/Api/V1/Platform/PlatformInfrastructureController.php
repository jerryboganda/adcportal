<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Resources\ApiShape;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\TenantLifecycleEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Deployment topology of the control plane: which region / stamp / isolation
 * profile a tenant's data lives on, and how the tenant population is spread
 * across them. Everything here is operator metadata — no clinical payload.
 *
 * The catalog is config-owned (config/ris.php) so a tenant can never be pinned
 * to a region or stamp the operator has not declared, and moving a tenant
 * between placements is an explicit, audited action.
 */
class PlatformInfrastructureController extends PlatformController
{
    /** Placement catalog + how tenants are distributed across it. */
    public function index(): JsonResponse
    {
        $this->denyUnlessCapability('tenants.view');

        $regions = collect(config('ris.regions', []))->map(fn ($meta, $key) => [
            'key' => (string) $key,
            'label' => $meta['label'] ?? (string) $key,
            'storage' => $meta['storage'] ?? (string) $key,
        ])->values()->all();

        $stamps = collect(config('ris.deployment_stamps', []))->map(fn ($meta, $key) => [
            'key' => (string) $key,
            'label' => $meta['label'] ?? (string) $key,
        ])->values()->all();

        $profiles = collect(config('ris.isolation_profiles', []))->map(fn ($label, $key) => [
            'key' => (string) $key,
            'label' => (string) $label,
        ])->values()->all();

        $by = fn (string $column) => Business::query()
            ->selectRaw("COALESCE({$column}, 'unassigned') as placement, count(*) as tenants")
            ->groupBy('placement')
            ->orderByDesc('tenants')
            ->get()
            ->map(fn ($row) => ['key' => (string) $row->placement, 'tenants' => (int) $row->tenants])
            ->all();

        return $this->ok([
            'catalog' => [
                'regions' => $regions,
                'deploymentStamps' => $stamps,
                'isolationProfiles' => $profiles,
            ],
            'placement' => [
                'regions' => $by('region'),
                'deploymentStamps' => $by('deployment_stamp'),
                'isolationProfiles' => $by('isolation_profile'),
            ],
            'unplacedTenants' => Business::query()->whereNull('isolation_profile')->count(),
        ]);
    }

    /** Re-place one tenant: region, stamp, isolation profile, cluster, storage region. */
    public function updateDeployment(Request $request, Business $tenant): JsonResponse
    {
        $this->denyUnlessCapability('infrastructure.manage');

        $validated = $request->validate([
            'region' => ['required', Rule::in(array_keys(config('ris.regions', [])))],
            'deploymentStamp' => ['required', Rule::in(array_keys(config('ris.deployment_stamps', [])))],
            'isolationProfile' => ['required', Rule::in(array_keys(config('ris.isolation_profiles', [])))],
            'databaseCluster' => ['nullable', 'string', 'max:64'],
            'storageRegion' => ['nullable', 'string', 'max:64'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $before = [
            'region' => $tenant->region,
            'deploymentStamp' => $tenant->deployment_stamp,
            'isolationProfile' => $tenant->isolation_profile,
            'databaseCluster' => $tenant->database_cluster,
            'storageRegion' => $tenant->storage_region,
        ];

        $tenant->update([
            'region' => $validated['region'],
            'deployment_stamp' => $validated['deploymentStamp'],
            'isolation_profile' => $validated['isolationProfile'],
            'database_cluster' => $validated['databaseCluster'] ?? $tenant->database_cluster ?? 'primary',
            'storage_region' => $validated['storageRegion'] ?? $validated['region'],
        ]);

        $after = [
            'region' => $tenant->region,
            'deploymentStamp' => $tenant->deployment_stamp,
            'isolationProfile' => $tenant->isolation_profile,
            'databaseCluster' => $tenant->database_cluster,
            'storageRegion' => $tenant->storage_region,
        ];

        TenantLifecycleEvent::record($tenant, 'deployment_updated', $tenant->subscription_status, $tenant->subscription_status, [
            'summary' => 'Deployment placement changed: '.json_encode($after).'.',
            'from' => $before,
            'to' => $after,
        ]);

        AuditLog::record('tenant_deployment_updated', $tenant, [
            'summary' => "Deployment placement for {$tenant->name} changed to region={$after['region']}, "
                ."stamp={$after['deploymentStamp']}, isolation={$after['isolationProfile']}."
                .($validated['reason'] ?? '' ? " Reason: {$validated['reason']}." : '')
                .' Acting platform user: '.$this->actor()->email.'.',
        ], $tenant->id);

        return $this->ok(['tenant' => ApiShape::tenant($tenant->fresh('plan'))]);
    }
}
