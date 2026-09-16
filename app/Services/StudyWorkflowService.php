<?php

namespace App\Services;

use App\Enums\StudyState;
use App\Events\Study\StudyAcquisitionCompleted;
use App\Events\Study\StudyCheckedIn;
use App\Events\Study\StudyRejectedToTechnologist;
use App\Events\Study\StudySentToReading;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\DoseLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/**
 * Enforces the radiology study pipeline:
 *
 *   booked → checked_in → preparing → in_progress → acquired
 *          → reading/reported → delivered      (+ cancelled / no_show)
 *
 * Every transition is guarded, timestamped, and audit-logged.
 *
 * Unit of work: the state change, its audit row, and the domain FACT (e.g.
 * "patient checked in") commit atomically — the fact is dispatched inside
 * the transaction and its projector writes the notification row in the same
 * transaction. A crash can never leave a transition without its fact, and a
 * failed fact insert rolls the transition back (fail-safe). The projector
 * must therefore never perform broker/HTTP I/O.
 */
class StudyWorkflowService
{
    public function transition(Appointment $appointment, StudyState $target, array $payload = []): Appointment
    {
        $current = $appointment->state();

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'workflow_state' => __("Cannot move a study from ':from' to ':to'.", [
                    'from' => $current->label(),
                    'to' => $target->label(),
                ]),
            ]);
        }

        $this->guard($appointment, $current, $target);

        return DB::transaction(function () use ($appointment, $current, $target, $payload) {
            if (array_key_exists('cancel_reason', $payload)) {
                $appointment->cancel_reason = $payload['cancel_reason'];
            }

            if (array_key_exists('reject_reason', $payload)) {
                $appointment->reject_reason = $payload['reject_reason'];
            }

            if (! empty($payload['performed_by_staff_id'])) {
                $appointment->performed_by_staff_id = $payload['performed_by_staff_id'];
            }

            $column = $target->timestampColumn();
            if ($column && empty($appointment->{$column})) {
                $appointment->{$column} = now();
            }

            $appointment->workflow_state = $target->value;
            $appointment->save();

            // Capture dose/contrast data atomically with acquisition completion.
            if ($target === StudyState::Acquired && ! empty($payload['dose_data'])) {
                DoseLog::updateOrCreate(
                    ['appointment_id' => $appointment->id],
                    collect($payload['dose_data'])->only([
                        'dose_value', 'dose_unit', 'dlp_value', 'kvp', 'mas',
                        'slice_count', 'series_count', 'contrast_agent',
                        'contrast_volume_ml', 'contrast_flow_rate', 'cannula_site',
                        'saline_flush_ml', 'technique_notes', 'qc_passed',
                    ])->all() + ['recorded_by' => auth()->id()]
                );

                app(\App\Services\InventoryService::class)->deductForStudy($appointment);
            }

            AuditLog::record('study_state_changed', $appointment, [
                'from' => $current->value,
                'to' => $target->value,
                'reason' => $payload['cancel_reason'] ?? $payload['reject_reason'] ?? null,
            ]);

            // Domain fact — same unit of work as the state change above.
            $this->emitFact($appointment, $target, $payload);

            return $appointment;
        });
    }

    // ==================== High-level actions ====================

    public function checkIn(Appointment $appointment): Appointment
    {
        return $this->transition($appointment, StudyState::CheckedIn);
    }

    public function markNoShow(Appointment $appointment): Appointment
    {
        return $this->transition($appointment, StudyState::NoShow);
    }

    public function cancel(Appointment $appointment, ?string $reason = null): Appointment
    {
        return $this->transition($appointment, StudyState::Cancelled, ['cancel_reason' => $reason]);
    }

    public function startPreparing(Appointment $appointment): Appointment
    {
        return $this->transition($appointment, StudyState::Preparing);
    }

    /** Technologist starts acquiring. Blocked while screening risk is unresolved. */
    public function startAcquisition(Appointment $appointment): Appointment
    {
        return $this->transition($appointment, StudyState::InProgress);
    }

    /**
     * Technologist completes acquisition; optional dose/contrast log captured atomically.
     */
    public function completeAcquisition(Appointment $appointment, ?array $doseData = null, ?int $performedByStaffId = null): Appointment
    {
        return $this->transition($appointment, StudyState::Acquired, array_filter([
            'performed_by_staff_id' => $performedByStaffId,
            'dose_data' => $doseData,
        ]));
    }

    public function sendToReading(Appointment $appointment): Appointment
    {
        return $this->transition($appointment, StudyState::Reading);
    }

    /** Radiologist rejects images back to the technologist (repeat/redo). */
    public function rejectToTechnologist(Appointment $appointment, ?string $reason = null): Appointment
    {
        return $this->transition($appointment, StudyState::Acquired, ['reject_reason' => $reason]);
    }

    /** Called by the reporting module once a report is signed. */
    public function markReported(Appointment $appointment): Appointment
    {
        $current = $appointment->state();

        // A signed ADDENDUM on an already-reported (or delivered) study must
        // not rewind the pipeline — signing is idempotent for the state.
        if (in_array($current, [StudyState::Reported, StudyState::Delivered], true)) {
            return $appointment;
        }

        return $this->transition($appointment, StudyState::Reported);
    }

    public function deliver(Appointment $appointment): Appointment
    {
        return $this->transition($appointment, StudyState::Delivered);
    }

    public function assignRadiologist(Appointment $appointment, int $radiologistId): Appointment
    {
        $appointment->forceFill(['assigned_radiologist_id' => $radiologistId])->save();
        AuditLog::record('radiologist_assigned', $appointment, ['radiologist_id' => $radiologistId]);

        return $appointment;
    }

    // ==================== Internals ====================

    /**
     * Map a successful transition to its past-tense domain fact. Dispatched
     * inside the transaction; the projector (ProjectStudyNotification)
     * persists the notification row in this same unit of work.
     *
     * Note: StudyState::Acquired is the target of BOTH completion and
     * rejection — distinguished by the payload (reject_reason present).
     */
    private function emitFact(Appointment $appointment, StudyState $target, array $payload): void
    {
        $fact = match (true) {
            $target === StudyState::CheckedIn => new StudyCheckedIn($appointment),
            $target === StudyState::Reading => new StudySentToReading($appointment),
            $target === StudyState::Acquired && array_key_exists('reject_reason', $payload)
                => new StudyRejectedToTechnologist($appointment, (string) ($payload['reject_reason'] ?? '')),
            $target === StudyState::Acquired => new StudyAcquisitionCompleted($appointment),
            default => null,
        };

        if ($fact !== null) {
            Event::dispatch($fact);
        }
    }

    private function guard(Appointment $appointment, StudyState $current, StudyState $target): void
    {
        // Screening gate: no needle-time on uncleared studies.
        if (in_array($target, [StudyState::InProgress], true)
            && $appointment->hasUnresolvedScreeningRisk()) {
            throw ValidationException::withMessages([
                'screening' => __('Safety screening has unresolved risks. Resolve or override before proceeding.'),
            ]);
        }

        // Only a signed report may finalize the study state.
        if ($target === StudyState::Reported) {
            $signed = $appointment->radiologyReports()->whereNotNull('locked_at')->exists();
            if (! $signed) {
                throw ValidationException::withMessages([
                    'report' => __('A signed report is required before this study can be marked reported.'),
                ]);
            }
        }
    }
}
