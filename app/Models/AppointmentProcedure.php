<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentProcedure extends Model
{
    protected $fillable = [
        'appointment_id', 'service_id', 'description', 'quantity', 'unit_price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function serviceData()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
