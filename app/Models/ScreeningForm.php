<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScreeningForm extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'modality_id', 'is_active',
        'business_id', 'created_by',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeForClinic($query, $businessId = null, $creatorId = null)
    {
        return $query->where('business_id', $businessId ?? getActiveBusiness())
            ->when($creatorId !== false, fn ($q) => $q->where('created_by', $creatorId ?? creatorId()));
    }

    public function questions()
    {
        return $this->hasMany(ScreeningQuestion::class)->orderBy('sort_order');
    }

    public function modality()
    {
        return $this->belongsTo(Modality::class);
    }
}
