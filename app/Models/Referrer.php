<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Referrer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'specialty',
        'clinic',
        'phone',
        'email',
        'is_active',
        'business_id',
        'created_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'referrer_id', 'id');
    }

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id', 'id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForBusiness($query, $businessId = null)
    {
        return $query->where('business_id', $businessId ?? getActiveBusiness());
    }
}
