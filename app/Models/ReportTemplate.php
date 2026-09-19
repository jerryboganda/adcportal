<?php

namespace App\Models;

use App\Support\ReportTemplateResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'code', 'service_id', 'modality_id', 'body_region', 'age_group',
        'sex', 'contrast', 'clinical_history', 'technique',
        'findings', 'impression', 'recommendations', 'structured_fields',
        'is_default', 'scope', 'is_archived', 'version',
        'business_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_archived' => 'boolean',
        'version' => 'integer',
        'structured_fields' => 'array',
    ];

    public function scopeForClinic($query, $businessId = null, $creatorId = null)
    {
        // Tenant boundary is business_id ONLY. Creator scoping must be explicit.
        return $query->where('business_id', $businessId ?? getActiveBusiness())
            ->when($creatorId !== null && $creatorId !== false, fn ($q) => $q->where('created_by', $creatorId));
    }

    /** Archived templates stay for historical traceability but never resolve. */
    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    /** Templates this user may use: tenant-wide plus their own personal ones. */
    public function scopeUsableBy($query, ?int $userId)
    {
        return $query->where(fn ($q) => $q->where('scope', 'tenant')->orWhere('created_by', $userId ?? 0));
    }

    public function serviceData()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function modality()
    {
        return $this->belongsTo(Modality::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Best template for a study's clinical context. Delegates to the
     * deterministic resolver — kept here so existing callers keep working.
     *
     * @return array{template: ?ReportTemplate, tier: string, ageGroup: ?string, candidates: int, explanation: string}
     */
    public static function resolveFor(Appointment $appointment, ?int $userId = null): array
    {
        return ReportTemplateResolver::forAppointment($appointment, $userId);
    }
}
