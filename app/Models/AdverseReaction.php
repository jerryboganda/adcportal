<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdverseReaction extends Model
{
    protected $table = 'ris_adverse_reactions';

    protected $fillable = [
        'appointment_id',
        'token_number',
        'patient_name',
        'modality',
        'contrast_agent',
        'batch_number',
        'administered_volume',
        'severity',
        'symptoms',
        'treatment_given',
        'outcome',
        'reported_by',
        'supervising_doctor',
        'notes',
        'business_id',
    ];

    protected $casts = ['symptoms' => 'array'];
}
