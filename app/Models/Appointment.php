<?php

namespace App\Models;

use App\Enums\StudyState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    /** Clinic-scoped query scope: every tenant query should pass through here. */
    public function scopeForClinic($query, $businessId = null, $creatorId = null)
    {
        return $query->where('business_id', $businessId ?? getActiveBusiness())
            ->where('created_by', $creatorId ?? creatorId());
    }

    /** Eager-load map for list/dashboard rendering (kills N+1). */
    public static $eager = ['CustomerData.customer', 'StaffData.user', 'ServiceData', 'LocationData', 'StatusData'];

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
        'created_by',
        // Radiology study lifecycle
        'workflow_state',
        'priority',
        'assigned_radiologist_id',
        'performed_by_staff_id',
        'screening_required',
        'screening_cleared',
        'cancel_reason',
        'checked_in_at',
        'preparing_at',
        'in_progress_at',
        'acquired_at',
        'reported_at',
        'delivered_at',
    ];

    protected $casts = [
        'screening_required' => 'boolean',
        'screening_cleared' => 'boolean',
        'checked_in_at' => 'datetime',
        'preparing_at' => 'datetime',
        'in_progress_at' => 'datetime',
        'acquired_at' => 'datetime',
        'reported_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    protected static function booted()
    {
        // Keep the indexed, sortable mirror of `date` in sync on every write.
        static::saving(function (Appointment $appointment) {
            if (! empty($appointment->date)) {
                foreach (['d-m-Y', 'Y-m-d', 'd/m/Y'] as $fmt) {
                    try {
                        $appointment->date_sort = \Carbon\Carbon::createFromFormat($fmt, $appointment->date)->format('Y-m-d');
                        break;
                    } catch (Throwable $e) {
                        continue;
                    }
                }
            }

            if (empty($appointment->workflow_state)) {
                $appointment->workflow_state = StudyState::Booked->value;
            }
        });
    }


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

    // ==================== Radiology study relations ====================

    public function state(): StudyState
    {
        return StudyState::fromAppointment($this);
    }

    /** Display name for worklists (registered patients + walk-in guests). */
    public function patientDisplayName(): string
    {
        return $this->CustomerData?->name
            ?? $this->CustomerData?->customer?->name
            ?? $this->name
            ?? __('Guest');
    }

    public function patientContact(): ?string
    {
        return $this->CustomerData?->customer?->mobile_no
            ?? $this->contact;
    }

    public function procedures()
    {
        return $this->hasMany(AppointmentProcedure::class);
    }

    public function assignedRadiologist()
    {
        return $this->belongsTo(User::class, 'assigned_radiologist_id');
    }

    public function performedBy()
    {
        return $this->belongsTo(Staff::class, 'performed_by_staff_id', 'user_id');
    }

    public function screeningAnswers()
    {
        return $this->hasMany(StudyScreeningAnswer::class);
    }

    public function doseLog()
    {
        return $this->hasOne(DoseLog::class);
    }

    /** Structured radiology report versions (newest first). */
    public function radiologyReports()
    {
        return $this->hasMany(RadiologyReport::class)->orderByDesc('version');
    }

    public function latestReport()
    {
        return $this->hasOne(RadiologyReport::class)->orderByDesc('version')->limit(1);
    }

    public function hasUnresolvedScreeningRisk(): bool
    {
        if (! $this->screening_required) {
            return false;
        }

        return ! $this->screening_cleared
            || $this->screeningAnswers()
                ->where('is_risk', true)
                ->whereNull('override_reason')
                ->exists();
    }

    /** Total TAT so far from acquisition (or check-in) to now/report. */
    public function turnaroundHours(): ?float
    {
        $from = $this->acquired_at ?? $this->checked_in_at;

        if (! $from) {
            return null;
        }

        $to = $this->reported_at ?? now();

        return round($from->diffInHours($to), 1);
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
