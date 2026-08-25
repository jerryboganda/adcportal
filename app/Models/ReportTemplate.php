<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'service_id', 'modality_id', 'clinical_history', 'technique',
        'findings', 'impression', 'recommendations', 'is_default',
        'business_id', 'created_by',
    ];

    protected $casts = ['is_default' => 'boolean'];

    public function scopeForClinic($query, $businessId = null, $creatorId = null)
    {
        return $query->where('business_id', $businessId ?? getActiveBusiness())
            ->when($creatorId !== false, fn ($q) => $q->where('created_by', $creatorId ?? creatorId()));
    }

    public function serviceData()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function modality()
    {
        return $this->belongsTo(Modality::class);
    }

    /** Resolve the best template for a study: procedure-specific > modality > global default. */
    public static function resolveFor(Appointment $appointment): ?self
    {
        $base = static::forClinic();

        return (clone $base)->where('service_id', $appointment->service_id)->first()
            ?? (clone $base)
                ->whereHas('serviceData', fn ($q) => $q->where('modality_id', optional($appointment->ServiceData)->modality_id))
                ->first()
            ?? (clone $base)->where('is_default', true)->first();
    }
}
