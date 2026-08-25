<?php

namespace App\Enums;

use App\Models\Appointment;

enum StudyState: string
{
    case Booked = 'booked';
    case CheckedIn = 'checked_in';
    case Preparing = 'preparing';
    case InProgress = 'in_progress';
    case Acquired = 'acquired';
    case Reading = 'reading';
    case Reported = 'reported';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /** Allowed forward/sideways transitions of the radiology pipeline. */
    public function transitions(): array
    {
        return match ($this) {
            self::Booked => [self::CheckedIn, self::Cancelled, self::NoShow],
            self::CheckedIn => [self::Preparing, self::InProgress, self::Cancelled],
            self::Preparing => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Acquired, self::Cancelled],
            self::Acquired => [self::Reading, self::Reported],
            self::Reading => [self::Reported, self::Acquired], // reject back to tech
            self::Reported => [self::Delivered],
            // Addenda are handled by the reporting module, not the pipeline.
            self::Delivered, self::Cancelled, self::NoShow => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->transitions(), true);
    }

    /** Timestamp column stamped when entering this state. */
    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::CheckedIn => 'checked_in_at',
            self::Preparing => 'preparing_at',
            self::InProgress => 'in_progress_at',
            self::Acquired => 'acquired_at',
            self::Reported => 'reported_at',
            self::Delivered => 'delivered_at',
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Booked => __('Booked'),
            self::CheckedIn => __('Checked In'),
            self::Preparing => __('Preparing'),
            self::InProgress => __('In Progress'),
            self::Acquired => __('Awaiting Read'),
            self::Reading => __('Being Read'),
            self::Reported => __('Report Finalized'),
            self::Delivered => __('Delivered'),
            self::Cancelled => __('Cancelled'),
            self::NoShow => __('No Show'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Booked => 'bg-label-secondary',
            self::CheckedIn => 'bg-label-info',
            self::Preparing => 'bg-label-primary',
            self::InProgress => 'bg-label-warning',
            self::Acquired => 'bg-label-purple',
            self::Reading => 'bg-label-orange',
            self::Reported => 'bg-label-success',
            self::Delivered => 'bg-label-green',
            self::Cancelled => 'bg-label-danger',
            self::NoShow => 'bg-label-dark',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->all();
    }

    /** Hydrate from an appointment row. */
    public static function fromAppointment(Appointment $appointment): self
    {
        return self::from($appointment->workflow_state ?? self::Booked->value);
    }
}
