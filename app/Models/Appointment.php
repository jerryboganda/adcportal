<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'location_id',
        'service_id',
        'staff_id',
        'name',
        'email',
        'contact',
        'date',
        'time',
        'notes',
        'referrer_id',
        'referred_by',
        'payment_type',
        'appointment_status',
        'attachment',
        'custom_field',
        'business_id',
        'created_by'
    ];


    public static function appointmentNumberFormat($number, $Id = null, $businessId = null)
    {
        $company_settings = getCompanyAllSetting($Id, $businessId);
        $data = !empty($company_settings['appointment_prefix']) ? $company_settings['appointment_prefix'] : '#APP0000';

        return $data . sprintf("%01d", $number);
    }

    // this function is created for query optimization
    public static function appointmentNumberWithFormat($number, $company_settings)
    {
        $data = !empty($company_settings['appointment_prefix']) ? $company_settings['appointment_prefix'] : '#APP0000';
        return $data . sprintf("%01d", $number);
    }

    public function CustomerData()
    {
        return $this->hasOne(Customer::class, 'user_id', 'customer_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'customer_id', 'id');
    }

    public function StaffData()
    {
        return $this->hasOne(Staff::class, 'user_id', 'staff_id');
    }

    public function ServiceData()
    {
        return $this->hasOne(Service::class, 'id', 'service_id');
    }

    public function LocationData()
    {
        return $this->hasOne(Location::class, 'id', 'location_id');
    }

    public function ReferrerData()
    {
        return $this->belongsTo(Referrer::class, 'referrer_id', 'id');
    }

    public function StatusData()
    {
        return $this->hasOne(CustomStatus::class, 'id', 'appointment_status');
    }

    public function payment()
    {
        return $this->hasOne(AppointmentPayment::class, 'appointment_id', 'id');
    }

    public function paymentsInfo()
    {
        return $this->hasMany(AppointmentPayment::class, 'appointment_id', 'id');
    }

    public function payments($id)
    {
        return AppointmentPayment::whereRaw("FIND_IN_SET($id, appointment_ids)")->first();
    }

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id', 'id');
    }

    public function reports()
    {
        return $this->hasMany(AppointmentReport::class, 'appointment_id', 'id');
    }

    public static function ColorCode()
    {
        $Color = [];
        $Color = [
            '#21c9b0',
            '#f04c43',
            '#fa9c30',
            '#a969ba',
            '#0080b6',
            '#27a93dc9',
            '#df3e9d',
            '#5c6bc0',
            '#f6c436'
        ];

        return $Color;
    }
}
