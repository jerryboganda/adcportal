<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Support\Facades\DB;

/**
 * Domain service: allocation of daily radiology token numbers.
 *
 * Single source of truth for the "token" rule of the study context — both
 * the API booking adapter (StudyController) and the legacy booking adapter
 * (AppointmentController) delegate here, so the invariant "one tenant, one
 * day, one sequence" can never drift between two implementations again.
 *
 * Tokens are per-tenant, per-day integers allocated under a pessimistic
 * lock (SELECT ... FOR UPDATE on the day's max token) to make concurrent
 * bookings race-free. Stored in the integer appointments.token_number
 * column — never strings.
 */
class StudyTokenAllocator
{
    /**
     * Allocate the next token for a tenant+day inside the caller's
     * transaction. Must be called within an open DB transaction.
     */
    public static function next(int $tenantId, string $date): int
    {
        $max = Appointment::query()
            ->where('business_id', $tenantId)
            ->whereDate('date_sort', $date)
            ->whereNotNull('token_number')
            ->lockForUpdate()
            ->max('token_number');

        return (int) $max + 1;
    }

    /**
     * Convenience: allocate + persist atomically for a freshly booked study.
     * Opens its own transaction when none is already running.
     */
    public static function assignTo(Appointment $appointment, string $date): int
    {
        return DB::transaction(function () use ($appointment, $date) {
            $token = self::next((int) $appointment->business_id, $date);

            $appointment->forceFill(['token_number' => $token])->save();

            return $token;
        });
    }
}
