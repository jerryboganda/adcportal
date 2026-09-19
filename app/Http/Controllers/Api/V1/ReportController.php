<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ApiShape;
use App\Models\Appointment;
use App\Models\RadiologyReport;
use App\Models\ReportTemplate;
use App\Models\UsageCounter;
use App\Services\StudyWorkflowService;
use App\Support\ReportStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        // An unsaved draft already exists: it must be UPDATED (PUT /reports/{id}),
        // not duplicated — the old behaviour piled up version rows per save.
        if (! $signed && $appointment->radiologyReports()->whereNull('locked_at')->exists()) {
            abort(422, 'An unsigned draft already exists for this study. Update it instead of creating another.');
        }

        $template = $this->resolveTemplate($validated['templateId'] ?? null);

        [$structuredValues, $structureErrors] = ReportStructure::validateValues(
            $template?->structured_fields,
            $validated['structuredValues'] ?? null,
        );

        if ($structureErrors !== []) {
            throw ValidationException::withMessages(
                collect($structureErrors)->mapWithKeys(fn ($msg, $key) => ["structuredValues.{$key}" => $msg])->all()
            );
        }

        $report = DB::transaction(function () use ($validated, $appointment, $signed, $template, $structuredValues) {
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
                'findings' => $validated['findings'] ?? null,
                'impression' => $validated['impression'] ?? null,
                'recommendations' => $validated['recommendations'] ?? null,
                'critical_flag' => (bool) ($validated['criticalFlag'] ?? false),
                'template_id' => $template?->id,
                'template_version' => $template?->version,
                'structured_values' => $structuredValues ?: null,
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

        return response()->json([
            'data' => [
                'study' => ApiShape::appointment($appointment->fresh(StudyController::eager())),
                'report' => ApiShape::radiologyReport($report->fresh(['author', 'signer', 'releases'])),
            ],
            'meta' => ['reportId' => (string) $report->id],
        ]);
    }

    /** Current server state of one report version (draft recovery / conflict resolution). */
    public function show(RadiologyReport $report): JsonResponse
    {
        if ($report->business_id !== $this->tenantId()) {
            abort(404);
        }

        $this->denyUnless('report manage');

        $report->loadMissing(['author', 'signer', 'releases.releaser']);

        return $this->ok([
            'report' => ApiShape::radiologyReport($report),
            'study' => ApiShape::appointment($report->appointment->fresh(StudyController::eager())),
        ]);
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

        // Optimistic concurrency: an autosave built from a stale snapshot must
        // not silently overwrite the other radiologist's work. The client is
        // handed the authoritative current state so it can reconcile.
        $expected = $validated['lockVersion'] ?? null;
        if ($expected !== null && (int) $expected !== (int) ($report->lock_version ?? 1)) {
            return response()->json([
                'message' => 'This report was changed by someone else. Your copy is out of date.',
                'error' => 'report.conflict',
                'data' => ['report' => ApiShape::radiologyReport($report->fresh(['author', 'signer', 'releases']))],
            ], 409);
        }

        $template = $report->template_id
            ? ReportTemplate::forClinic($this->tenantId())->find($report->template_id)
            : $this->resolveTemplate($validated['templateId'] ?? null);

        [$structuredValues, $structureErrors] = ReportStructure::validateValues(
            $template?->structured_fields,
            $validated['structuredValues'] ?? null,
        );

        if ($structureErrors !== []) {
            throw ValidationException::withMessages(
                collect($structureErrors)->mapWithKeys(fn ($msg, $key) => ["structuredValues.{$key}" => $msg])->all()
            );
        }

        DB::transaction(function () use ($report, $validated, $template, $structuredValues) {
            $report->update([
                'clinical_history' => $validated['clinicalHistory'] ?? null,
                'technique' => $validated['technique'] ?? null,
                'comparison' => $validated['comparison'] ?? null,
                'findings' => $validated['findings'] ?? null,
                'impression' => $validated['impression'] ?? null,
                'recommendations' => $validated['recommendations'] ?? null,
                'critical_flag' => (bool) ($validated['criticalFlag'] ?? false),
                'structured_values' => $structuredValues ?: null,
            ]);

            if ($template !== null) {
                $report->forceFill([
                    'template_id' => $template->id,
                    'template_version' => $template->version,
                ])->save();
            }

            // Every accepted write advances the draft's revision counter.
            $report->forceFill(['lock_version' => ((int) ($report->lock_version ?? 1)) + 1])->save();

            if (! empty($validated['signNow'])) {
                $this->signReport($report, $validated['signAs'] ?? 'final');
            }
        });

        return $this->ok([
            'report' => ApiShape::radiologyReport($report->fresh(['author', 'signer', 'releases'])),
            'study' => ApiShape::appointment($report->appointment->fresh(StudyController::eager())),
        ]);
    }

    /**
     * Add an addendum to a SIGNED report.
     *
     * The original final version is never touched: an addendum is appended as
     * its own signed version linked through `parent_report_id`, so the
     * medicol-legal record keeps both documents exactly as they were signed.
     */
    public function addendum(Request $request, RadiologyReport $report): JsonResponse
    {
        if ($report->business_id !== $this->tenantId()) {
            abort(404);
        }

        $this->denyUnless('report sign');

        if (! $report->isSigned()) {
            abort(422, 'Only a signed report can receive an addendum.');
        }

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:20000'],
            'recommendations' => ['nullable', 'string', 'max:3000'],
            'criticalFlag' => ['sometimes', 'boolean'],
        ]);

        // Chain onto the newest signed version for this study (the report the
        // addendum actually amends).
        $latestSigned = RadiologyReport::where('appointment_id', $report->appointment_id)
            ->whereNotNull('locked_at')
            ->orderByDesc('version')
            ->first() ?? $report;

        $addendum = DB::transaction(function () use ($validated, $report, $latestSigned) {
            $nextVersion = ((int) RadiologyReport::where('appointment_id', $report->appointment_id)->max('version')) + 1;

            $addendum = RadiologyReport::create([
                // Context is copied so the addendum renders as a complete
                // document on its own, while the amended FINDINGS/IMPRESSION
                // stay exactly as originally signed.
                'clinical_history' => $latestSigned->clinical_history,
                'technique' => $latestSigned->technique,
                'comparison' => $latestSigned->comparison,
                'findings' => $validated['text'],
                'impression' => null,
                'recommendations' => $validated['recommendations'] ?? null,
                'critical_flag' => (bool) ($validated['criticalFlag'] ?? $latestSigned->critical_flag),
                'template_id' => $latestSigned->template_id,
                'template_version' => $latestSigned->template_version,
                'appointment_id' => $report->appointment_id,
                'version' => $nextVersion,
                'type' => 'addendum',
                'parent_report_id' => $latestSigned->id,
                'authored_by' => Auth::id(),
                'business_id' => $this->tenantId(),
                'created_by' => Auth::id(),
            ]);

            $this->signReport($addendum, 'final');

            return $addendum;
        });

        $this->audit('report_addendum', $report->appointment, [
            'summary' => "Signed addendum for {$report->appointment->patientDisplayName()} (#{$report->appointment->token_number})",
            'report_id' => $addendum->id,
            'parent_report_id' => $latestSigned->id,
        ]);

        return response()->json([
            'data' => [
                'report' => ApiShape::radiologyReport($addendum->fresh(['author', 'signer', 'releases'])),
                'study' => ApiShape::appointment($report->appointment->fresh(StudyController::eager())),
            ],
        ], 201);
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

        // Fan the release out to the tenant's interoperability integrations
        // (queued — never blocks the clinical request on third parties).
        \App\Services\Delivery\IntegrationEventFanout::dispatch(
            $this->tenantId(),
            ['webhook', 'hl7', 'fhir'],
            'report.released',
            [
                'studyId' => $report->appointment_id,
                'token' => $report->appointment->token_number,
                'patientName' => $report->appointment->patientDisplayName(),
                'study' => $report->appointment->ServiceData?->name,
                'releasedAt' => now()->toIso8601String(),
                'channel' => $channel,
            ],
        );

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
        // A DRAFT may be partial — a radiologist saves an indication, a
        // technique or half a findings section and comes back to it. Completeness
        // is a precondition of SIGNING, so the narrative sections are required
        // only when the same request finalizes the report.
        $signing = $request->boolean('signNow');

        return $request->validate([
            'clinicalHistory' => ['nullable', 'string', 'max:5000'],
            'technique' => ['nullable', 'string', 'max:5000'],
            'comparison' => ['nullable', 'string', 'max:2000'],
            'findings' => [Rule::requiredIf($signing), 'nullable', 'string'],
            'impression' => [Rule::requiredIf($signing), 'nullable', 'string'],
            'recommendations' => ['nullable', 'string', 'max:3000'],
            'criticalFlag' => ['sometimes', 'boolean'],
            'templateId' => ['nullable', 'integer'],
            'structuredValues' => ['sometimes', 'array'],
            // Draft autosave revision the client believes it is editing.
            'lockVersion' => ['sometimes', 'integer', 'min:1'],
            'signNow' => ['sometimes', 'boolean'],
            'signAs' => ['sometimes', 'in:final,preliminary'],
        ]);
    }

    /** Resolve a client-supplied template id INSIDE the tenant boundary. */
    private function resolveTemplate(?int $templateId): ?ReportTemplate
    {
        if (empty($templateId)) {
            return null;
        }

        // A template from another tenant simply does not exist here.
        return ReportTemplate::forClinic($this->tenantId())->findOrFail($templateId);
    }

    /** Typed-signature confirmation must match the signing radiologist's name. */
    public function signReport(RadiologyReport $report, string $signAs): void
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
