<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'currency',
        'trial_days',
        'max_users',
        'max_studies_per_month',
        'max_storage_mb',
        'max_locations',
        'features',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function businesses()
    {
        return $this->hasMany(Business::class);
    }
}
