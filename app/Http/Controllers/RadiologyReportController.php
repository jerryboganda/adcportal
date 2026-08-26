<?php

namespace App\Http\Controllers;

use App\Enums\StudyState;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\RadiologyReport;
use App\Models\ReportRelease;
use App\Models\ReportTemplate;
use App\Services\StudyWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class RadiologyReportController extends Controller
{
    public function __construct(private StudyWorkflowService $workflow)
    {
    }

    // ==================== Reading worklist ====================

    public function worklist(Request $request)
    {
        if (! Auth::user()->isAbleTo('report manage')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $query = Appointment::forClinic()
            ->whereIn('workflow_state', [StudyState::Acquired->value, StudyState::Reading->value, StudyState::Reported->value])
            ->when($request->boolean('mine'), fn ($q) => $q->where('assigned_radiologist_id', Auth::id()))
            ->when($request->filled('modality'), fn ($q) => $q
                ->whereHas('ServiceData', fn ($s) => $s->where('modality_id', $request->modality)))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->priority))
            ->with(array_merge(StudyWorkflowController::eagerForWorklist(), ['radiologyReports']))
            // Portable priority sort (works on both MySQL and SQLite).
            ->orderByRaw("CASE priority WHEN 'stat' THEN 0 WHEN 'urgent' THEN 1 ELSE 2 END")
            ->orderBy('acquired_at');

        $modalities = \App\Models\Modality::forClinic()->orderBy('name')->get();

        $studies = $query->paginate(25)->withQueryString();

        return view('reports.worklist', compact('studies', 'modalities'));
    }

    // ==================== Editor ====================

    public function create(Appointment $appointment, Request $request)
    {
        if (! Auth::user()->isAbleTo('report create')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $template = ReportTemplate::resolveFor($appointment);
        $previous = $appointment->radiologyReports()->first();

        $prefill = [
            'clinical_history' => $template?->clinical_history ?? '',
            'technique' => $template?->technique ?? '',
            'findings' => $template?->findings ?? '',
            'impression' => $template?->impression ?? '',
            'recommendations' => $template?->recommendations ?? '',
        ];

        return view('reports.editor', compact('appointment', 'template', 'prefill', 'previous'));
    }

    public function store(Request $request, Appointment $appointment)
    {
        if (! Auth::user()->isAbleTo('report create')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validated = $this->validateReport($request);

        DB::transaction(function () use ($validated, $request, $appointment) {
            $nextVersion = ((int) $appointment->radiologyReports()->max('version')) + 1;
            $parent = $appointment->radiologyReports()->first();

            // Assign to the authoring radiologist when unassigned.
            if (empty($appointment->assigned_radiologist_id)) {
                $appointment->forceFill(['assigned_radiologist_id' => Auth::id()])->save();
            }
            if ($appointment->workflow_state === StudyState::Acquired->value) {
                $appointment->forceFill(['workflow_state' => StudyState::Reading->value])->save();
            }

            $report = RadiologyReport::create([
                ...$validated,
                'appointment_id' => $appointment->id,
                'version' => $nextVersion,
                'type' => $parent ? 'addendum' : 'draft',
                'parent_report_id' => $parent?->id,
                'template_id' => $request->input('template_id') ?: null,
                'authored_by' => Auth::id(),
                'business_id' => getActiveBusiness(),
                'created_by' => creatorId(),
            ]);

            if ($request->boolean('sign_now')) {
                $this->signReport($report, $request);
            }
        });

        AuditLog::record('report_saved', $appointment);

        return redirect()->route('reports.worklist')->with('success', __('Report saved.'));
    }

    public function edit(RadiologyReport $report)
    {
        if (! Auth::user()->isAbleTo('report edit') || $report->isSigned()) {
            if ($report->isSigned()) {
                return redirect()->back()->with('error', __('Signed reports are immutable. Create an addendum instead.'));
            }

            return redirect()->back()->with('error', __('Permission denied.'));
        }

        return view('reports.editor', [
            'appointment' => $report->appointment,
            'report' => $report,
            'template' => null,
            'prefill' => [],
            'previous' => null,
        ]);
    }

    public function update(Request $request, RadiologyReport $report)
    {
        if ($report->isSigned()) {
            return redirect()->back()->with('error', __('Signed reports are immutable.'));
        }
        if (! Auth::user()->isAbleTo('report edit')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $report->update($this->validateReport($request));

        if ($request->boolean('sign_now')) {
            $this->signReport($report, $request);
        }

        return redirect()->route('reports.worklist')->with('success', __('Report updated.'));
    }

    // ==================== Sign-off & delivery ====================

    public function sign(Request $request, RadiologyReport $report)
    {
        if (! Auth::user()->isAbleTo('report sign')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        try {
            DB::transaction(function () use ($report, $request) {
                $this->signReport($report, $request);
            });
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('Report signed as :type.', ['type' => ucfirst($report->fresh()->type)]));
    }

    public function downloadPdf(RadiologyReport $report)
    {
        if (! Auth::user()->isAbleTo('report manage')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $path = $report->storePdf();

        return \Storage::disk('public')->download($path, basename($path));
    }

    public function release(Request $request, RadiologyReport $report)
    {
        if (! Auth::user()->isAbleTo('report release')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        if (! $report->isSigned()) {
            return redirect()->back()->with('error', __('Only signed reports can be released.'));
        }

        $channel = $request->input('channel', 'hand');

        if ($channel === 'email') {
            $recipient = $request->input('recipient_email')
                ?? $report->appointment->ReferrerData?->email
                ?? $report->appointment->email
                ?? $report->appointment->CustomerData?->customer?->email;

            if (empty($recipient)) {
                return redirect()->back()->with('error', __('No recipient email available. Enter one manually.'));
            }

            $path = $report->storePdf();
            try {
                Mail::raw(
                    __('Please find attached the imaging report for :patient.', ['patient' => $report->appointment->patientDisplayName()]),
                    fn ($message) => $message->to($recipient)->subject(__('Imaging Report :ref', ['ref' => $report->appointment->id]))
                        ->attachFromStorageDisk('public', $path)
                );
            } catch (\Throwable $e) {
                report($e);

                return redirect()->back()->with('error', __('Mail send failed: check SMTP settings.'));
            }

            $recipientEmail = $recipient;
        } else {
            $recipientEmail = null;
        }

        ReportRelease::create([
            'report_id' => $report->id,
            'channel' => $channel,
            'recipient_email' => $recipientEmail,
            'released_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', __('Report released (:channel).', ['channel' => $channel]));
    }

    // ==================== Internals ====================

    private function validateReport(Request $request): array
    {
        return $request->validate([
            'clinical_history' => 'nullable|string|max:5000',
            'technique' => 'nullable|string|max:5000',
            'comparison' => 'nullable|string|max:2000',
            'findings' => 'required|string',
            'impression' => 'required|string',
            'recommendations' => 'nullable|string|max:3000',
            'critical_flag' => 'sometimes|boolean',
            'template_id' => 'nullable|integer',
            'sign_now' => 'sometimes|boolean',
            'signature_confirm' => 'nullable|string|max:255',
        ]);
    }

    private function signReport(RadiologyReport $report, Request $request): void
    {
        if ($report->isSigned()) {
            throw new \InvalidArgumentException(__('Report is already signed.'));
        }
        if (! Auth::user()->isAbleTo('report sign')) {
            throw new \InvalidArgumentException(__('You are not allowed to sign reports.'));
        }

        // Typed-signature confirmation must match the signing radiologist.
        $confirmed = trim((string) $request->input('signature_confirm'));
        if (mb_strtolower($confirmed) !== mb_strtolower(trim(Auth::user()->name))) {
            throw new \InvalidArgumentException(__('Your typed name does not match your account name — signature not applied.'));
        }

        $type = $report->type === 'addendum' ? 'addendum' : ($request->input('sign_as') === 'preliminary' ? 'preliminary' : 'final');

        $report->forceFill([
            'type' => $type,
            'signed_by' => Auth::id(),
            'signed_at' => now(),
            'locked_at' => now(),
            'critical_flag' => (bool) $request->boolean('critical_flag'),
        ])->save();

        // Final/addendum signature finalizes the study pipeline.
        if (in_array($type, ['final', 'addendum'], true)) {
            $this->workflow->markReported($report->appointment);
        }

        AuditLog::record('report_signed', $report->appointment, ['report_id' => $report->id, 'type' => $type]);
    }
}
