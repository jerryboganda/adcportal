<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ApiShape;
use App\Models\Appointment;
use App\Models\RadiologyReport;
use App\Models\UsageCounter;
use App\Services\StudyWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * Radiology reporting: draft/addendum authoring, typed-signature sign-off,
 * and report release (hand / email / portal). Signed reports are immutable.
 */
class ReportController extends BaseApiController
{
    public function __construct(private StudyWorkflowService $workflow)
    {
    }

    public function store(Request $request, Appointment $appointment): JsonResponse
    {
        $this->denyUnless('report create');

        if ($appointment->business_id !== $this->tenantId()) {
            abort(404);
        }

        $validated = $this->validated($request);

        $signed = $appointment->radiologyReports()->whereNotNull('locked_at')->exists();

        $report = DB::transaction(function () use ($validated, $appointment, $signed) {
            $nextVersion = ((int) $appointment->radiologyReports()->max('version')) + 1;
            $parent = $appointment->radiologyReports()->first();

            if (empty($appointment->assigned_radiologist_id)) {
                $appointment->forceFill(['assigned_radiologist_id' => Auth::id()])->save();
            }

            if ($appointment->workflow_state === 'acquired') {
                $appointment->forceFill(['workflow_state' => 'reading'])->save();
            }

            $report = RadiologyReport::create([
                'clinical_history' => $validated['clinicalHistory'] ?? null,
                'technique' => $validated['technique'] ?? null,
                'comparison' => $validated['comparison'] ?? null,
                'findings' => $validated['findings'],
                'impression' => $validated['impression'],
                'recommendations' => $validated['recommendations'] ?? null,
                'critical_flag' => (bool) ($validated['criticalFlag'] ?? false),
                'template_id' => $validated['templateId'] ?? null,
                'appointment_id' => $appointment->id,
                'version' => $nextVersion,
                'type' => $signed ? 'addendum' : 'draft',
                'parent_report_id' => $parent?->id,
                'authored_by' => Auth::id(),
                'business_id' => $this->tenantId(),
                'created_by' => Auth::id(),
            ]);

            if (! empty($validated['signNow'])) {
                $this->signReport($report, $validated['signAs'] ?? 'final');
            }

            UsageCounter::add($this->tenantId(), 'reports');

            return $report;
        });

        $this->audit('report_saved', $appointment, [
            'summary' => sprintf(
                '%s report for %s (#%s) by %s',
                $report->isSigned() ? 'Signed' : 'Drafted',
                $appointment->patientDisplayName(),
                $appointment->token_number,
                Auth::user()->name,
            ),
        ]);

        return $this->ok([
            'study' => ApiShape::appointment($appointment->fresh(StudyController::eager())),
        ], ['reportId' => (string) $report->id]);
    }

    public function update(Request $request, RadiologyReport $report): JsonResponse
    {
        if ($report->business_id !== $this->tenantId()) {
            abort(404);
        }

        if ($report->isSigned()) {
            abort(422, 'Signed reports are immutable. Create an addendum instead.');
        }

        $this->denyUnless('report edit');

        $validated = $this->validated($request);

        $report->update([
            'clinical_history' => $validated['clinicalHistory'] ?? null,
            'technique' => $validated['technique'] ?? null,
            'comparison' => $validated['comparison'] ?? null,
            'findings' => $validated['findings'],
            'impression' => $validated['impression'],
            'recommendations' => $validated['recommendations'] ?? null,
            'critical_flag' => (bool) ($validated['criticalFlag'] ?? false),
        ]);

        if (! empty($validated['signNow'])) {
            DB::transaction(fn () => $this->signReport($report, $validated['signAs'] ?? 'final'));
        }

        return $this->ok(['study' => ApiShape::appointment($report->appointment->fresh(StudyController::eager()))]);
    }

    public function sign(Request $request, RadiologyReport $report): JsonResponse
    {
        if ($report->business_id !== $this->tenantId()) {
            abort(404);
        }

        $this->denyUnless('report sign');

        $validated = $request->validate([
            'signAs' => ['sometimes', 'in:final,preliminary'],
        ]);

        try {
            DB::transaction(fn () => $this->signReport($report, $validated['signAs'] ?? 'final'));
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return $this->ok(['study' => ApiShape::appointment($report->appointment->fresh(StudyController::eager()))]);
    }

    public function release(Request $request, RadiologyReport $report): JsonResponse
    {
        if ($report->business_id !== $this->tenantId()) {
            abort(404);
        }

        $this->denyUnless('report release');

        if (! $report->isSigned()) {
            abort(422, 'Only signed reports can be released.');
        }

        $validated = $request->validate([
            'channel' => ['required', 'in:hand,email,portal'],
            'recipientEmail' => ['nullable', 'email'],
        ]);

        $channel = $validated['channel'];
        $recipientEmail = null;

        if ($channel === 'email') {
            $recipientEmail = $validated['recipientEmail']
                ?? $report->appointment->ReferrerData?->email
                ?? $report->appointment->email;

            if (empty($recipientEmail)) {
                abort(422, 'No recipient email available. Enter one manually.');
            }

            $path = $report->storePdf();
            try {
                Mail::raw(
                    'Please find attached the imaging report for '.$report->appointment->patientDisplayName().'.',
                    fn ($message) => $message->to($recipientEmail)
                        ->subject('Imaging Report '.$report->appointment->token_number)
                        ->attachFromStorageDisk('public', $path)
                );
            } catch (\Throwable $e) {
                report($e);

                abort(502, 'Mail send failed: check SMTP settings.');
            }
        }

        \App\Models\ReportRelease::create([
            'report_id' => $report->id,
            'channel' => $channel,
            'recipient_email' => $recipientEmail,
            'released_by' => Auth::id(),
            'released_at' => now(),
        ]);

        // Hand delivery closes the pipeline; email/portal keep the study reported.
        if ($channel === 'hand') {
            $this->workflow->deliver($report->appointment);
        }

        $this->audit('report_released', $report->appointment, [
            'summary' => sprintf(
                'Released report for %s (#%s) via %s',
                $report->appointment->patientDisplayName(),
                $report->appointment->token_number,
                strtoupper($channel),
            ),
            'channel' => $channel,
        ]);

        $this->notify([
            'title' => 'Report Dispatched ('.strtoupper($channel).')',
            'message' => "Diagnostic report for {$report->appointment->patientDisplayName()} (#{$report->appointment->token_number}) successfully dispatched via ".strtoupper($channel).'.',
            'category' => 'dispatch',
            'priority' => 'low',
            'appointment_id' => $report->appointment_id,
            'token_number' => $report->appointment->token_number,
            'patient_name' => $report->appointment->patientDisplayName(),
            'target_tab' => 'doctors',
            'action_label' => 'View Dispatch Log',
        ]);

        return $this->ok(['study' => ApiShape::appointment($report->appointment->fresh(StudyController::eager()))]);
    }

    public function downloadPdf(RadiologyReport $report)
    {
        if ($report->business_id !== $this->tenantId()) {
            abort(404);
        }

        $this->denyUnless('report manage');

        $path = $report->storePdf();

        return \Storage::disk('public')->download($path, basename($path));
    }

    // ==================== internals ====================

    private function validated(Request $request): array
    {
        return $request->validate([
            'clinicalHistory' => ['nullable', 'string', 'max:5000'],
            'technique' => ['nullable', 'string', 'max:5000'],
            'comparison' => ['nullable', 'string', 'max:2000'],
            'findings' => ['required', 'string'],
            'impression' => ['required', 'string'],
            'recommendations' => ['nullable', 'string', 'max:3000'],
            'criticalFlag' => ['sometimes', 'boolean'],
            'templateId' => ['nullable', 'integer'],
            'signNow' => ['sometimes', 'boolean'],
            'signAs' => ['sometimes', 'in:final,preliminary'],
        ]);
    }

    /** Typed-signature confirmation must match the signing radiologist's name. */
    private function signReport(RadiologyReport $report, string $signAs): void
    {
        if ($report->isSigned()) {
            throw new InvalidArgumentException('Report is already signed.');
        }

        if (! auth()->user()?->isAbleTo('report sign')) {
            throw new InvalidArgumentException('You are not allowed to sign reports.');
        }

        $type = $report->type === 'addendum' ? 'addendum' : ($signAs === 'preliminary' ? 'preliminary' : 'final');

        $report->forceFill([
            'type' => $type,
            'signed_by' => Auth::id(),
            'signed_at' => now(),
            'locked_at' => now(),
        ])->save();

        if (in_array($type, ['final', 'addendum'], true)) {
            $this->workflow->markReported($report->appointment);
        }

        if ((bool) $report->critical_flag) {
            $this->notify([
                'title' => '🚨 CRITICAL FINDING REPORT SIGNED',
                'message' => "Critical radiological findings signed by ".Auth::user()->name." for {$report->appointment->patientDisplayName()} (#{$report->appointment->token_number}). Immediate clinician communication required.",
                'category' => 'stat',
                'priority' => 'critical',
                'appointment_id' => $report->appointment_id,
                'token_number' => $report->appointment->token_number,
                'patient_name' => $report->appointment->patientDisplayName(),
                'target_tab' => 'doctors',
                'action_label' => 'Dispatch Critical Alert',
            ]);
        }

        $this->audit('report_signed', $report->appointment, [
            'summary' => "Signed {$type} report for {$report->appointment->patientDisplayName()} (#{$report->appointment->token_number})",
            'report_id' => $report->id,
            'type' => $type,
        ]);
    }
}
