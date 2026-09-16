<?php

namespace App\Listeners;

use App\Events\Study\StudyAcquisitionCompleted;
use App\Events\Study\StudyBooked;
use App\Events\Study\StudyCheckedIn;
use App\Events\Study\StudyRejectedToTechnologist;
use App\Events\Study\StudySentToReading;
use App\Http\Resources\ApiShape;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Projects study domain facts into the clinical notification center.
 *
 * Events are dispatched synchronously INSIDE the workflow unit of work, so
 * the notification row commits (or rolls back) atomically with the state
 * change — the previous controller-side writes lost the fact if the process
 * died between commit and notify.
 *
 * Payload strings are byte-identical to the legacy controller writes so the
 * SPA notification center is unchanged. Because this runs inside the open
 * transaction it must NEVER perform broker/HTTP I/O — a failed insert rolls
 * back the whole transition (fail-safe).
 */
class ProjectStudyNotification
{
    public function handle(
        StudyBooked|StudyCheckedIn|StudyAcquisitionCompleted|StudySentToReading|StudyRejectedToTechnologist $event,
    ): void {
        $match = $event::class;

        if ($match === StudyBooked::class) {
            /** @var StudyBooked $event */
            $a = $event->appointment;

            if ($a->priority === 'stat') {
                NotificationService::push([
                    'title' => "🚨 STAT Booking Created (#{$a->token_number})",
                    'message' => "Emergency priority study scheduled for {$a->patientDisplayName()} ({$event->serviceName}). Modality: {$event->modalityName}.",
                    'category' => 'stat',
                    'priority' => 'critical',
                    'appointment_id' => $a->id,
                    'token_number' => $a->token_number,
                    'patient_name' => $a->patientDisplayName(),
                    'target_tab' => 'technologist',
                    'action_label' => 'Open Tech Worklist',
                ]);
            } else {
                NotificationService::push([
                    'title' => "New Appointment Booked (#{$a->token_number})",
                    'message' => $a->patientDisplayName().' registered for '.$event->serviceName.' at '.ApiShape::time($a->time).'.',
                    'category' => 'workflow',
                    'priority' => 'low',
                    'appointment_id' => $a->id,
                    'token_number' => $a->token_number,
                    'patient_name' => $a->patientDisplayName(),
                    'target_tab' => 'checkin',
                    'action_label' => 'View Reception Desk',
                ]);
            }

            return;
        }

        if ($match === StudyCheckedIn::class) {
            /** @var StudyCheckedIn $event */
            $a = $event->appointment;

            NotificationService::push([
                'title' => "Patient Checked In (#{$a->token_number})",
                'message' => "{$a->patientDisplayName()} has arrived at the reception desk. Token {$a->token_number} is now ready for preparation in {$a->ServiceData?->modality?->name}.",
                'category' => 'workflow',
                'priority' => $a->priority === 'stat' ? 'critical' : 'medium',
                'appointment_id' => $a->id,
                'token_number' => $a->token_number,
                'patient_name' => $a->patientDisplayName(),
                'target_tab' => 'technologist',
                'action_label' => 'View Worklist',
            ]);

            return;
        }

        if ($match === StudyAcquisitionCompleted::class) {
            /** @var StudyAcquisitionCompleted $event */
            $a = $event->appointment;

            NotificationService::push([
                'title' => "Acquisition Complete (#{$a->token_number})",
                'message' => ($a->ServiceData?->modality?->code ?? 'Imaging')." imaging completed for {$a->patientDisplayName()}. Study ready for reporting.",
                'category' => 'workflow',
                'priority' => $a->priority === 'stat' ? 'high' : 'medium',
                'appointment_id' => $a->id,
                'token_number' => $a->token_number,
                'patient_name' => $a->patientDisplayName(),
                'target_tab' => 'reporting',
                'action_label' => 'Open Diagnostic Report',
            ]);

            return;
        }

        if ($match === StudySentToReading::class) {
            /** @var StudySentToReading $event */
            $a = $event->appointment;

            NotificationService::push([
                'title' => "Study Sent to Reading (#{$a->token_number})",
                'message' => ($a->ServiceData?->modality?->code ?? 'Imaging')." study for {$a->patientDisplayName()} passed technologist QC and is ready for interpretation.",
                'category' => 'workflow',
                'priority' => $a->priority === 'stat' ? 'high' : 'medium',
                'appointment_id' => $a->id,
                'token_number' => $a->token_number,
                'patient_name' => $a->patientDisplayName(),
                'target_tab' => 'reporting',
                'action_label' => 'Open Report Worklist',
            ]);

            return;
        }

        if ($match === StudyRejectedToTechnologist::class) {
            /** @var StudyRejectedToTechnologist $event */
            $a = $event->appointment;

            NotificationService::push([
                'title' => "Quality Rejection Alert (#{$a->token_number})",
                'message' => \Illuminate\Support\Facades\Auth::user()?->name." rejected study #{$a->token_number} back for repeat scan/technologist review: \"{$event->reason}\"",
                'category' => 'stat',
                'priority' => 'high',
                'appointment_id' => $a->id,
                'token_number' => $a->token_number,
                'patient_name' => $a->patientDisplayName(),
                'target_tab' => 'technologist',
                'action_label' => 'View Study in Worklist',
            ]);
        }
    }
}
