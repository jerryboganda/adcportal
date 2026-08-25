<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Modality extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'code', 'description', 'color', 'buffer_minutes',
        'is_active', 'business_id', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'buffer_minutes' => 'integer',
    ];

    public function scopeForClinic($query, $businessId = null, $creatorId = null)
    {
        return $query->where('business_id', $businessId ?? getActiveBusiness())
            ->when($creatorId !== false, fn ($q) => $q->where('created_by', $creatorId ?? creatorId()));
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function procedures()
    {
        return $this->hasMany(Service::class, 'modality_id');
    }

    public function screeningForms()
    {
        return $this->hasMany(ScreeningForm::class);
    }
}
