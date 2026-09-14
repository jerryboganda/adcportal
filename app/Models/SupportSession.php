<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Break-glass support session: time-boxed, reason-mandated, audited grant that
 * lets one named platform user operate inside one named tenant. There is never
 * a silent standing cross-tenant grant for platform staff.
 */
class SupportSession extends Model
{
    protected $fillable = ['platform_user_id', 'business_id', 'reason', 'started_at', 'expires_at', 'ended_at', 'created_ip'];

    protected $casts = ['started_at' => 'datetime', 'expires_at' => 'datetime', 'ended_at' => 'datetime'];

    public function platformUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'platform_user_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function isActive(): bool
    {
        return $this->ended_at === null && $this->expires_at->isFuture();
    }
}
