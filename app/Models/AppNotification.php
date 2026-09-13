<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $table = 'ris_app_notifications';

    protected $fillable = [
        'title',
        'message',
        'category',
        'priority',
        'appointment_id',
        'token_number',
        'patient_name',
        'target_tab',
        'action_label',
        'is_read',
        'business_id',
    ];

    protected $casts = ['is_read' => 'boolean'];
}

