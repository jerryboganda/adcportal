<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RisNotificationTemplate extends Model
{
    protected $table = 'ris_notification_templates';

    protected $fillable = [
        'name',
        'category',
        'channel',
        'subject',
        'template_body',
        'enabled',
        'business_id',
    ];

    protected $casts = ['enabled' => 'boolean'];
}
