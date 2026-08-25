<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'modality_id', 'location_id', 'capacity_per_slot',
        'description', 'is_active', 'business_id', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity_per_slot' => 'integer',
    ];

    public function scopeForClinic($query, $businessId = null, $creatorId = null)
    {
        return $query->where('business_id', $businessId ?? getActiveBusiness())
            ->when($creatorId !== false, fn ($q) => $q->where('created_by', $creatorId ?? creatorId()));
    }

    public function modality()
    {
        return $this->belongsTo(Modality::class);
    }

    public function locationData()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function downtimes()
    {
        return $this->hasMany(EquipmentDowntime::class);
    }

    /** @param string $date Y-m-d */
    public function hasDowntimeOn(string $date): bool
    {
        return $this->downtimes()
            ->whereDate('starts_at', '<=', $date)
            ->whereDate('ends_at', '>=', $date)
            ->exists();
    }
}
