<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;

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

    public function renderPdf(): string
    {
        $pdf = PdfFacade::loadView('reports.pdf', ['report' => $this->loadMissing(['appointment.ServiceData', 'author'])])
            ->setPaper('a4');

        return $pdf->output();
    }

    public function storePdf(): string
    {
        if ($this->pdf_path && \Storage::disk('public')->exists($this->pdf_path)) {
            return $this->pdf_path;
        }

        $path = "radiology_reports/{$this->appointment_id}/report_v{$this->version}_{$this->id}.pdf";
        \Storage::disk('public')->put($path, $this->renderPdf());

        $this->forceFill(['pdf_path' => $path])->save();

        return $path;
    }
}
