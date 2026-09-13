<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoseLog extends Model
{
    protected $fillable = [
        'appointment_id', 'dose_value', 'dose_unit', 'dlp_value', 'kvp', 'mas',
        'slice_count', 'series_count', 'contrast_agent', 'contrast_volume_ml',
        'contrast_flow_rate', 'cannula_site', 'saline_flush_ml',
        'technique_notes', 'qc_passed', 'recorded_by',
    ];

    protected $casts = [
        'dose_value' => 'decimal:3',
        'dlp_value' => 'decimal:3',
        'kvp' => 'decimal:2',
        'mas' => 'decimal:2',
        'contrast_volume_ml' => 'decimal:2',
        'saline_flush_ml' => 'decimal:2',
        'qc_passed' => 'boolean',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
