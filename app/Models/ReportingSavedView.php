<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A radiologist's named worklist filter set ("My STAT CT list").
 *
 * Holds filter values only — never report content or patient identifiers — and
 * is private to the user who saved it, within the clinic it was saved in.
 */
class ReportingSavedView extends Model
{
    protected $table = 'reporting_saved_views';

    protected $fillable = ['business_id', 'user_id', 'name', 'filters'];

    protected $casts = [
        'filters' => 'array',
    ];

    public function scopeForClinic($query, $businessId = null)
    {
        return $query->where('business_id', $businessId ?? getActiveBusiness());
    }
}
