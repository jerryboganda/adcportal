<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only tenant lifecycle history (provisioning, suspension, offboarding…). */
class TenantLifecycleEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['business_id', 'event', 'from_status', 'to_status', 'actor_id', 'details', 'created_at'];

    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public static function record(Business $business, string $event, ?string $from, ?string $to, array $details = []): void
    {
        try {
            self::create([
                'business_id' => $business->id,
                'event' => $event,
                'from_status' => $from,
                'to_status' => $to,
                'actor_id' => auth()->id() ?? 0,
                'details' => $details ?: null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
