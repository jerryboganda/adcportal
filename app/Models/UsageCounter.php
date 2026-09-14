<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-tenant usage meter (studies, reports, …) aggregated by billing period.
 * Incremented inside the same transaction as the domain event so counters
 * cannot drift from the data they meter. `storage` is computed, not counted.
 */
class UsageCounter extends Model
{
    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $fillable = ['business_id', 'metric', 'period', 'value', 'updated_at'];

    protected $casts = ['updated_at' => 'datetime'];

    /** Atomic increment: exactly one row per (tenant, metric, period).
     *
     * Named `add` — `increment` collides with Eloquent's non-static
     * Model::increment() and fatals the class at load time.
     */
    public static function add(int $businessId, string $metric, int $by = 1, ?string $period = null): void
    {
        try {
            $period ??= now()->format('Y-m');

            $existing = static::query()
                ->where('business_id', $businessId)
                ->where('metric', $metric)
                ->where('period', $period)
                ->first();

            if ($existing) {
                static::query()
                    ->where('business_id', $businessId)
                    ->where('metric', $metric)
                    ->where('period', $period)
                    ->update(['value' => $existing->value + $by, 'updated_at' => now()]);

                return;
            }

            static::create([
                'business_id' => $businessId,
                'metric' => $metric,
                'period' => $period,
                'value' => $by,
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Metering must never break the clinical transaction it observes.
            report($e);
        }
    }

    /** Sum of a metric across all periods (or one period) for a tenant. */
    public static function totalFor(int $businessId, string $metric, ?string $period = null): int
    {
        return (int) static::query()
            ->where('business_id', $businessId)
            ->where('metric', $metric)
            ->when($period, fn ($q) => $q->where('period', $period))
            ->sum('value');
    }
}
