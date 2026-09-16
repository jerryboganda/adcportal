<?php

namespace App\Events\Study;

use App\Models\Appointment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Domain fact: the radiologist rejected the study back to the technologist. */
class StudyRejectedToTechnologist
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $reason,
    ) {}
}
