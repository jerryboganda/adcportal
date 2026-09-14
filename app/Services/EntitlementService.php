<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Location;
use App\Models\UsageCounter;
use App\Models\User;

/**
 * Server-side entitlement/quota enforcement. Limits come from the tenant's
 * plan (null = unlimited); usage comes from real persisted data — live counts
 * for people/places, transactional counters for studies, computed bytes for
 * storage. Frontend displays are a mirror, never the enforcement point.
 */
class EntitlementService
{
    public static function limits(Business $tenant): array
    {
        $plan = $tenant->plan;

        return [
            'maxUsers' => $plan?->max_users ? (int) $plan->max_users : null,
            'maxStudiesPerMonth' => $plan?->max_studies_per_month ? (int) $plan->max_studies_per_month : null,
            'maxStorageMb' => $plan?->max_storage_mb ? (int) $plan->max_storage_mb : null,
            'maxLocations' => $plan?->max_locations ? (int) $plan->max_locations : null,
        ];
    }

    public static function usage(Business $tenant): array
    {
        return [
            'users' => User::where('business_id', $tenant->id)->where('type', '!=', 'customer')->count(),
            'studiesThisMonth' => UsageCounter::totalFor($tenant->id, 'studies', now()->format('Y-m')),
            'storageBytes' => StorageMeter::bytes($tenant->id),
            'locations' => Location::where('business_id', $tenant->id)->count(),
        ];
    }

    /** entitlement key: users | studies | storage | locations */
    public static function withinLimit(Business $tenant, string $key): bool
    {
        $limits = self::limits($tenant);
        $usage = self::usage($tenant);

        return match ($key) {
            'users' => $limits['maxUsers'] === null || $usage['users'] < $limits['maxUsers'],
            'studies' => $limits['maxStudiesPerMonth'] === null || $usage['studiesThisMonth'] < $limits['maxStudiesPerMonth'],
            'storage' => $limits['maxStorageMb'] === null || $usage['storageBytes'] <= $limits['maxStorageMb'] * 1024 * 1024,
            'locations' => $limits['maxLocations'] === null || $usage['locations'] < $limits['maxLocations'],
            default => true,
        };
    }

    private const LIMIT_KEY = [
        'users' => 'maxUsers',
        'studies' => 'maxStudiesPerMonth',
        'storage' => 'maxStorageMb',
        'locations' => 'maxLocations',
    ];

    public static function enforce(Business $tenant, string $key, string $label): void
    {
        if (! self::withinLimit($tenant, $key)) {
            abort(response()->json([
                'message' => "Your plan's {$label} limit has been reached. Upgrade the subscription to continue.",
                'error' => 'quota_exceeded',
                'quota' => $key,
                'limit' => self::limits($tenant)[self::LIMIT_KEY[$key]] ?? null,
            ], 403));
        }
    }

    /** Bundle sent to the SPA (mirrored entitlements — enforcement is server-side). */
    public static function payload(Business $tenant): array
    {
        return [
            'plan' => $tenant->plan ? \App\Http\Resources\ApiShape::plan($tenant->plan) : null,
            'subscriptionStatus' => $tenant->subscription_status,
            'trialEndsAt' => $tenant->trial_ends_at?->toIso8601String(),
            'subscriptionEndsAt' => $tenant->subscription_ends_at?->toIso8601String(),
            'limits' => self::limits($tenant),
            'usage' => self::usage($tenant),
            'features' => FeatureResolver::features($tenant),
        ];
    }
}
