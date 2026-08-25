<?php

namespace App\Services;

use App\Enums\StudyState;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\DoseLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enforces the radiology study pipeline:
 *
 *   booked → checked_in → preparing → in_progress → acquired
 *          → reading/reported → delivered      (+ cancelled / no_show)
 *
 * Every transition is guarded, timestamped, and audit-logged.
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
                        'dose_value', 'dose_unit', 'contrast_agent',
                        'contrast_volume_ml', 'technique_notes',
                    ])->all() + ['recorded_by' => auth()->id()]
                );
            }

            AuditLog::record('study_state_changed', $appointment, [
                'from' => $current->value,
                'to' => $target->value,
                'reason' => $payload['cancel_reason'] ?? $payload['reject_reason'] ?? null,
            ]);

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
