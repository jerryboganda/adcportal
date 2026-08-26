<?php

namespace App\Http\Controllers;

use App\Enums\StudyState;
use App\Models\Appointment;
use App\Models\Modality;
use App\Models\Service;
use App\Services\StudyWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudyWorkflowController extends Controller
{
    public function __construct(private StudyWorkflowService $workflow)
    {
    }

    // ==================== Reception check-in desk ====================

    public function checkinBoard(Request $request)
    {
        if (! Auth::user()->isAbleTo('study checkin')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $businessId = getActiveBusiness();
        $today = now()->format('Y-m-d');

        $studies = Appointment::forClinic($businessId)
            ->whereIn('workflow_state', [StudyState::Booked->value, StudyState::CheckedIn->value])
            ->when($request->filled('date'), fn ($q) => $q->whereDate('date_sort', $request->date))
            ->when(! $request->filled('date'), fn ($q) => $q->whereDate('date_sort', $today))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($request->q)) . '%';
                $q->where(function ($w) use ($term) {
                    $w->where('name', 'like', $term)
                        ->orWhere('token_number', 'like', $term)
                        ->orWhereHas('CustomerData', fn ($c) => $c
                            ->where('name', 'like', $term)
                            ->orWhere('mrn', 'like', $term)
                            ->orWhere('email', 'like', $term));
                });
            })
            ->with(self::eagerForWorklist())
            ->orderBy('time')
            ->get();

        return view('study.checkin', compact('studies'));
    }

    public function checkIn(Request $request, Appointment $appointment)
    {
        if (! Auth::user()->isAbleTo('study checkin')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        try {
            $this->workflow->checkIn($appointment);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->with('error', $e->validator->errors()->first());
        }

        return redirect()->back()->with('success', __('Patient checked in — :name', ['name' => $appointment->patientDisplayName()]));
    }

    public function markNoShow(Request $request, Appointment $appointment)
    {
        if (! Auth::user()->isAbleTo('study checkin')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $this->workflow->markNoShow($appointment);

        return redirect()->back()->with('success', __('Marked as no-show.'));
    }

    // ==================== State transitions (shared) ====================

    public function transition(Request $request, Appointment $appointment)
    {
        if (! Auth::user()->isAbleTo('appointment manage')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $action = $request->input('action');
        $map = [
            'prepare' => 'startPreparing',
            'start' => 'startAcquisition',
            'complete' => 'completeAcquisition',
            'to_reading' => 'sendToReading',
            'reject' => 'rejectToTechnologist',
            'deliver' => 'deliver',
            'cancel' => 'cancel',
        ];

        if (! isset($map[$action])) {
            return redirect()->back()->with('error', __('Unknown workflow action.'));
        }

        try {
            switch ($action) {
                case 'complete':
                    $this->workflow->completeAcquisition(
                        $appointment,
                        $request->only(['dose_value', 'dose_unit', 'contrast_agent', 'contrast_volume_ml', 'technique_notes']),
                        Auth::id()
                    );
                    break;

                case 'cancel':
                case 'reject':
                    $reason = trim((string) $request->input('reason'));
                    if ($reason === '') {
                        return redirect()->back()->with('error', __('A reason is required.'));
                    }
                    $payload[$action === 'cancel' ? 'cancel_reason' : 'reject_reason'] = $reason;
                    // fall through to the plain call below via method name
                    // no break — handled by explicit calls
                    if ($action === 'cancel') {
                        $this->workflow->cancel($appointment, $reason);
                    } else {
                        $this->workflow->rejectToTechnologist($appointment, $reason);
                    }
                    break;

                default:
                    $this->workflow->{$map[$action]}($appointment);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->with('error', $e->validator->errors()->first());
        }

        return redirect()->back()->with('success', __('Study updated.'));
    }

    // ==================== Technologist worklist ====================

    public function technologistBoard(Request $request)
    {
        if (! Auth::user()->isAbleTo('study acquire')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $query = Appointment::forClinic()
            ->whereIn('workflow_state', [
                StudyState::CheckedIn->value,
                StudyState::Preparing->value,
                StudyState::InProgress->value,
                StudyState::Acquired->value,
            ])
            ->when($request->filled('modality'), fn ($q) => $q
                ->whereHas('ServiceData', fn ($s) => $s->where('modality_id', $request->modality)))
            ->with(array_merge(self::eagerForWorklist(), ['screeningAnswers.question', 'doseLog']));

        if (! $request->boolean('all')) {
            $query->whereDate('date_sort', today());
        }

        $studies = $query
            // Portable STAT-first prioritisation (MySQL & SQLite safe).
            ->orderByRaw("CASE priority WHEN 'stat' THEN 0 WHEN 'urgent' THEN 1 ELSE 2 END")
            ->orderBy('date_sort')->orderBy('time')->get()
            ->groupBy(fn ($a) => optional($a->ServiceData)->modality_id ?? 0);

        $modalities = Modality::forClinic()->orderBy('name')->get();

        return view('study.technologist', compact('studies', 'modalities'));
    }

    // ==================== Live queue board (TV display) ====================

    public function queueBoard(Request $request)
    {
        // Public display endpoint — gated by a configured access key.
        $expected = company_setting('queue_board_key');
        if (empty($expected) || ! hash_equals((string) $expected, (string) $request->query('key'))) {
            abort(404);
        }

        $nowServing = Appointment::forClinic()
            ->where('workflow_state', StudyState::InProgress->value)
            ->whereDate('date_sort', today())
            ->with(self::eagerForWorklist())
            ->get();

        $waiting = Appointment::forClinic()
            ->whereIn('workflow_state', [StudyState::CheckedIn->value, StudyState::Preparing->value])
            ->whereDate('date_sort', today())
            ->with(self::eagerForWorklist())
            ->orderBy('checked_in_at')
            ->get();

        return view('study.queue-board', compact('nowServing', 'waiting'))
            ->with('refresh', (int) ($request->query('refresh', 30)));
    }

    // ==================== Helpers ====================

    public static function eagerForWorklist(): array
    {
        return ['CustomerData.customer', 'ServiceData.modality', 'StaffData.user'];
    }
}
