<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reusable reporting snippet ("macro").
 *
 * Clinical text belongs in tenant-managed data, not in a UI component: a
 * radiologist or clinic admin can curate, version and retire macros without a
 * deploy, and every tenant's library stays isolated.
 */
class ReportMacro extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'shortcut', 'modality_id', 'service_id',
        'findings', 'impression', 'recommendations',
        'scope', 'is_archived', 'usage_count',
        'business_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'usage_count' => 'integer',
    ];

    public function scopeForClinic($query, $businessId = null)
    {
        return $query->where('business_id', $businessId ?? getActiveBusiness());
    }

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeUsableBy($query, ?int $userId)
    {
        return $query->where(fn ($q) => $q->where('scope', 'tenant')->orWhere('created_by', $userId ?? 0));
    }

    public function modality()
    {
        return $this->belongsTo(Modality::class);
    }

    public function serviceData()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
