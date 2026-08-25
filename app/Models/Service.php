<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded;

    protected $casts = [
        'requires_screening' => 'boolean',
        'is_bookable_online' => 'boolean',
        'duration_minutes' => 'integer',
        'tat_target_hours' => 'integer',
    ];

    public function scopeForClinic($query, $businessId = null, $creatorId = null)
    {
        return $query->where('business_id', $businessId ?? getActiveBusiness())
            ->when($creatorId !== false, fn ($q) => $q->where('created_by', $creatorId ?? creatorId()));
    }

    /** Effective slot length in minutes (normalized column, legacy fallback). */
    public function slotMinutes(): int
    {
        if (! empty($this->duration_minutes) && $this->duration_minutes > 0) {
            return (int) $this->duration_minutes;
        }

        return max(1, (int) $this->duration);
    }

    public function Category()
    {
        return $this->hasOne(category::class, 'id', 'category_id');
    }

    public function modality()
    {
        return $this->belongsTo(Modality::class, 'modality_id');
    }

    public function reportTemplates()
    {
        return $this->hasMany(ReportTemplate::class);
    }

    // Service belongs to Business
    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    // Service can have many appointments (if needed)
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'service_id');
    }
}
