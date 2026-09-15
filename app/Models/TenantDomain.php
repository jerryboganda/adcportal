<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registry of hosts that resolve to a tenant for presentation purposes
 * (login branding, app name, report header). A host match is NEVER an
 * authorization decision — see TenantBrandingService.
 */
class TenantDomain extends Model
{
    protected $table = 'tenant_domains';

    protected $fillable = ['business_id', 'host', 'is_primary', 'verified_at', 'created_by'];

    protected $casts = [
        'is_primary' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /** Canonical host form: lowercase, no scheme, no port, no trailing dot. */
    public static function normalizeHost(?string $host): ?string
    {
        if ($host === null) {
            return null;
        }

        $host = strtolower(trim($host));
        $host = preg_replace('#^https?://#', '', $host) ?? $host;
        $host = explode('/', $host)[0];
        $host = explode(':', $host)[0];
        $host = rtrim($host, '.');

        return $host === '' ? null : $host;
    }
}
