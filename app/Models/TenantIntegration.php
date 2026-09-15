<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One tenant-owned healthcare/operational integration.
 *
 * `secrets` is cast to `encrypted:array`: the payload is encrypted at rest with
 * the application key and is never serialized to the API — the controller only
 * ever emits which secret keys are present, plus a mask.
 */
class TenantIntegration extends Model
{
    protected $table = 'tenant_integrations';

    protected $fillable = [
        'business_id',
        'location_id',
        'type',
        'name',
        'config',
        'secrets',
        'status',
        'last_checked_at',
        'last_error',
        'created_by',
    ];

    protected $casts = [
        'config' => 'array',
        'secrets' => 'encrypted:array',
        'last_checked_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
