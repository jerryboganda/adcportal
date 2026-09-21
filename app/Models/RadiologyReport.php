<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RadiologyReport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'appointment_id', 'version', 'type', 'parent_report_id',
        'clinical_history', 'technique', 'comparison', 'findings',
        'impression', 'recommendations',
        'critical_flag', 'critical_acked_at', 'critical_acked_by',
        'template_id', 'template_version', 'structured_values',
        'authored_by', 'signed_by', 'signed_at', 'locked_at',
        'pdf_path', 'business_id', 'created_by',
    ];

    protected $casts = [
        'critical_flag' => 'boolean',
        'critical_acked_at' => 'datetime',
        'signed_at' => 'datetime',
        'locked_at' => 'datetime',
        'structured_values' => 'array',
        'version' => 'integer',
        'template_version' => 'integer',
        'lock_version' => 'integer',
    ];

    public function scopeForClinic($query, $businessId = null, $creatorId = null)
    {
        return $query->where('business_id', $businessId ?? getActiveBusiness());
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'authored_by');
    }

    public function signer()
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function releases()
    {
        return $this->hasMany(ReportRelease::class, 'report_id');
    }

    public function template()
    {
        return $this->belongsTo(ReportTemplate::class, 'template_id');
    }

    /** Critical-result communications recorded against this report version. */
    public function criticalFindingLogs()
    {
        return $this->hasMany(CriticalFindingLog::class, 'report_id');
    }

    public function parentReport()
    {
        return $this->belongsTo(RadiologyReport::class, 'parent_report_id');
    }

    public function isSigned(): bool
    {
        return $this->locked_at !== null;
    }

    public function isFinal(): bool
    {
        return $this->type === 'final' && $this->isSigned();
    }

    /** Immutable once signed — addenda create new versions instead. */
    public function lock(): static
    {
        $this->forceFill(['locked_at' => now()])->save();

        return $this;
    }

    /** Authoring label used by worklists and the report history panel. */
    public function statusLabel(): string
    {
        return match (true) {
            $this->type === 'addendum' => 'Addendum',
            $this->type === 'final' => 'Final',
            $this->type === 'preliminary' => 'Preliminary',
            default => 'Draft',
        };
    }

    /** One-line preview for the report history/search list. */
    public function summary(int $length = 140): string
    {
        $text = trim((string) ($this->impression ?: $this->findings ?: $this->clinical_history ?: ''));
        $text = preg_replace('/\s+/', ' ', $text) ?? '';

        return \Illuminate\Support\Str::limit($text, $length);
    }

    /**
     * The report PDF, rendered by the shared print pipeline.
     *
     * `storePdf()` keeps its original path semantics (the patient portal serves
     * `pdf_path` directly), but the bytes now come from the ONE print document
     * model and layout. Which engine painted them — headless Chromium when the
     * deployment has it, DomPDF otherwise — is recorded on the archive row
     * rather than assumed.
     */
    public function renderPdf(): string
    {
        $service = app(\App\Services\Print\PrintPdfService::class);
        $document = $service->documentFor('report', $this);
        $rendered = $service->render($document);

        return $rendered['bytes'];
    }

    public function storePdf(): string
    {
        // A signed report is frozen: the stored file IS the document of record
        // and is never regenerated from today's branding.
        if ($this->pdf_path && \Storage::disk('public')->exists($this->pdf_path)) {
            return $this->pdf_path;
        }

        $path = "radiology_reports/{$this->appointment_id}/report_v{$this->version}_{$this->id}.pdf";
        $service = app(\App\Services\Print\PrintPdfService::class);
        $document = $service->documentFor('report', $this);
        $rendered = $service->render($document);

        \Storage::disk('public')->put($path, $rendered['bytes']);

        $this->forceFill(['pdf_path' => $path])->save();

        $service->store((int) $this->business_id, $document, [
            'finalized' => $this->isSigned(),
            'force' => true,
            'path' => $path,
            'rendered' => $rendered,
        ]);

        return $path;
    }
}
