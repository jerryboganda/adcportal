<?php

namespace App\Console\Commands;

use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Patient appointment reminders for the modern RIS schema.
 *
 * The previous implementation was legacy scaffolding that could never work:
 * it parsed Y-m-d study dates as d-m-Y, required an exact-minute match against
 * a 5-minute schedule, crashed on a missing `defult_timezone` setting, and
 * queued a mailable whose Blade view did not exist on a host with no queue
 * worker. This version is idempotent (`reminder_sent_at` marker), window-based
 * (default next 24h, per-clinic `reminderHours`), opt-in per clinic
 * (`sendAppointmentReminders` in the clinic profile), and sends synchronously.
 */
class AppointmentReminder extends Command
{
    protected $signature = 'app:appointment-reminder {--hours= : Override the reminder window in hours}';

    protected $description = 'Email patients their upcoming study appointments (opt-in, idempotent)';

    public function handle(): int
    {
        $windowHours = (int) ($this->option('hours') ?: 24);

        foreach (Business::query()->cursor() as $business) {
            $settings = $this->clinicProfile($business->id);

            if (! ($settings['sendAppointmentReminders'] ?? false)) {
                continue;
            }

            $hours = (int) ($settings['reminderHours'] ?? $windowHours);
            if ($hours < 1) {
                $hours = $windowHours;
            }

            $now = now();
            $until = $now->copy()->addHours($hours);

            $appointments = Appointment::forClinic($business->id)
                ->whereIn('workflow_state', ['booked', 'checked_in', 'preparing'])
                ->whereNull('reminder_sent_at')
                ->get()
                ->filter(function (Appointment $appointment) use ($now, $until) {
                    $at = $this->appointmentAt($appointment);

                    return $at !== null && $at->betweenIncluded($now, $until);
                });

            foreach ($appointments as $appointment) {
                $this->remind($business, $appointment);
            }
        }

        return self::SUCCESS;
    }

    private function remind(Business $business, Appointment $appointment): void
    {
        $patientEmail = $this->patientEmail($appointment);

        if ($patientEmail === null) {
            // No deliverable address (walk-ins get placeholder addresses):
            // stamp so the command does not retry this study forever.
            $appointment->forceFill(['reminder_sent_at' => now()])->save();

            return;
        }

        $clinicProfile = $this->clinicProfile($business->id);

        $payload = [
            'clinicName' => $clinicProfile['name'] ?? $business->name,
            'clinicEmail' => $clinicProfile['email'] ?? null,
            'clinicPhone' => $clinicProfile['phone'] ?? null,
            'patientName' => $appointment->patientDisplayName(),
            'tokenNumber' => $appointment->token_number ?? '-',
            'serviceName' => $appointment->ServiceData?->name ?? 'Imaging study',
            'date' => Carbon::parse($appointment->date_sort)->format('D, j M Y'),
            'time' => \App\Http\Resources\ApiShape::time($appointment->time) ?? (string) $appointment->time,
            'roomNumber' => $appointment->room_number,
            'subject' => sprintf(
                'Appointment reminder: %s (%s) at %s',
                $appointment->ServiceData?->name ?? 'imaging study',
                $appointment->token_number ?? '',
                $clinicProfile['name'] ?? $business->name,
            ),
        ];

        try {
            Mail::to($patientEmail)->send(new AppointmentReminderMail($payload));
        } catch (\Throwable $e) {
            // Do NOT stamp: a transient SMTP failure must retry next run.
            Log::error('Appointment reminder failed', [
                'appointment_id' => $appointment->id,
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $appointment->forceFill(['reminder_sent_at' => now()])->save();

        \App\Models\AuditLog::record('appointment_reminder_sent', $appointment, [
            'summary' => sprintf(
                'Emailed appointment reminder to %s for %s (%s).',
                $patientEmail,
                $appointment->patientDisplayName(),
                $appointment->token_number,
            ),
        ], $business->id);
    }

    /** Appointment date (Y-m-d mirror) + time (H:i:s) → Carbon, or null if unparseable. */
    private function appointmentAt(Appointment $appointment): ?Carbon
    {
        try {
            return Carbon::parse($appointment->date_sort.' '.substr((string) $appointment->time, 0, 8));
        } catch (\Throwable) {
            return null;
        }
    }

    /** Deliverable patient address, or null for placeholders/missing. */
    private function patientEmail(Appointment $appointment): ?string
    {
        $email = trim((string) ($appointment->CustomerData?->email ?? $appointment->email ?? ''));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        // Walk-in registration fabricates deliverability-proof addresses on a
        // reserved domain — never mail those.
        if (str_ends_with(strtolower($email), '@patients.local')) {
            return null;
        }

        return strtolower($email);
    }

    private function clinicProfile(int $businessId): array
    {
        $blob = Setting::where('business', $businessId)->where('key', 'ris_clinic_profile')->value('value');

        return $blob ? (json_decode($blob, true) ?: []) : [];
    }
}
