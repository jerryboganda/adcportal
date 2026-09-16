<?php

namespace App\Events\Study;

use App\Models\Appointment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Domain fact: a study was booked (STAT or routine).
 *
 * Dispatched INSIDE the booking transaction so the notification row commits
 * atomically with the study — a crash after commit can no longer lose the
 * clinical fact. Listeners must therefore never perform broker/HTTP I/O.
 */
class StudyBooked
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $serviceName,
        public readonly ?string $modalityName,
    ) {}
}
