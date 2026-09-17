<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One delivery attempt through one tenant integration. Tenant-scoped,
 * audit-friendly (who triggered it), and free of secret material — `meta`
 * carries only provider message ids / ack codes / HTTP statuses.
 */
class IntegrationDelivery extends Model
{
    protected $fillable = [
        'business_id',
        'tenant_integration_id',
        'channel',
        'event',
        'status',
        'target',
        'detail',
        'latency_ms',
        'meta',
        'triggered_by',
    ];

    protected $casts = [
        'meta' => 'array',
        'latency_ms' => 'integer',
    ];
}
