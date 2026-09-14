<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Platform-set per-tenant feature override — the top layer of flag resolution. */
class TenantFeatureOverride extends Model
{
    protected $fillable = ['business_id', 'feature', 'enabled', 'actor_id'];

    protected $casts = ['enabled' => 'boolean'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
