<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Critical-result communication record.
 *
 * Deliberately SEPARATE from the report body: "the radiologist telephoned Dr
 * X at 14:32 and the finding was read back" is a medico-legal communication
 * event with its own actor, recipient, channel and timestamp. Folding it into
 * the impression text loses all of that structure and its auditability.
 */
class CriticalFindingLog extends Model
{
    protected $fillable = [
        'appointment_id', 'report_id', 'summary',
        'notified_to', 'notified_role', 'contact', 'method',
        'read_back_verified', 'advice_given', 'communicated_at',
        'communicated_by', 'business_id', 'created_by',
    ];

    protected $casts = [
        'read_back_verified' => 'boolean',
        'communicated_at' => 'datetime',
    ];

    public function scopeForClinic($query, $businessId = null)
    {
        return $query->where('business_id', $businessId ?? getActiveBusiness());
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function report()
    {
        return $this->belongsTo(RadiologyReport::class, 'report_id');
    }

    public function communicator()
    {
        return $this->belongsTo(User::class, 'communicated_by');
    }
}
