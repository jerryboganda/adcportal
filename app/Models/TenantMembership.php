<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's membership in one tenant (clinic). A user may hold memberships in
 * several tenants (e.g. a radiologist reporting for two clinics) and switches
 * between them explicitly; the active membership scopes every request.
 */
class TenantMembership extends Model
{
    /** Roles from the SPA vocabulary a membership can carry. */
    public const ROLES = ['admin', 'radiologist', 'technologist', 'receptionist', 'billing'];

    protected $fillable = ['user_id', 'business_id', 'role', 'is_default', 'status'];

    protected $casts = ['is_default' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
