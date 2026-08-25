<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoseLog extends Model
{
    protected $fillable = [
        'appointment_id', 'dose_value', 'dose_unit', 'contrast_agent',
        'contrast_volume_ml', 'technique_notes', 'recorded_by',
    ];

    protected $casts = [
        'dose_value' => 'decimal:3',
        'contrast_volume_ml' => 'decimal:2',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
