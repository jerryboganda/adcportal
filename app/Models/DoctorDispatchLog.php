<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorDispatchLog extends Model
{
    protected $fillable = [
        'appointment_id',
        'token_number',
        'patient_name',
        'referrer_id',
        'referrer_name',
        'study_name',
        'channel',
        'recipient_contact',
        'status',
        'sent_by',
        'business_id',
    ];
}
