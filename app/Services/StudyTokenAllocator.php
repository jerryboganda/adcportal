<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Support\Facades\DB;

/**
 * Domain service: allocation of daily radiology token numbers.
 *
 * Single source of truth for the "token" rule of the study context — the
 * booking adapters (StudyController, BookingService) delegate here, so the
 * invariant "one tenant, one day, one sequence" can never drift between
 * implementations.
 *
 * Tokens are per-tenant, per-day integers. Allocation locks the day's
 * counter row (`study_token_counters`, unique on business_id + token_date)
 * and increments it, so two receptionists booking at the same instant
 * serialize instead of colliding. The counter is the lock target
 * deliberately: PostgreSQL rejects `FOR UPDATE` on an aggregate query
 * (`SQLSTATE[0A000] FOR UPDATE is not allowed with aggregate functions`),
 * which is what made the previous `SELECT MAX(token_number) … FOR UPDATE`
 * version throw a 500 on production while SQLite-based CI stayed green.
 *
 * Stores into the integer appointments.token_number column — never strings.
 */
class StudyTokenAllocator
{
    public const COUNTERS_TABLE = 'study_token_counters';

    /** How often we retry when a concurrent inserter aborts mid-flight. */
    private const INSERT_ATTEMPTS = 3;

    /**
     * Allocate the next token for a tenant+day inside the caller's
     * transaction. Must be called within an open DB transaction.
     */
    public static function next(int $tenantId, string $date): int
    {
        $day = substr($date, 0, 10);

        // Floor from the study table itself: tokens issued for this tenant+day
        // through ANY path (seeded history, imports, legacy writes) must never
        // be handed out twice, even if the counter row was never created.
        $issuedMax = (int) Appointment::query()
            ->where('business_id', $tenantId)
            ->whereDate('date_sort', $day)
            ->whereNotNull('token_number')
            ->max('token_number');

        for ($attempt = 1; $attempt <= self::INSERT_ATTEMPTS; $attempt++) {
            // Atomic insert-if-absent (unique business_id + token_date). If a
            // concurrent transaction is inserting the same row we wait on the
            // unique index instead of raising.
            DB::table(self::COUNTERS_TABLE)->insertOrIgnore([
                'business_id' => $tenantId,
                'token_date' => $day,
                'last_token' => $issuedMax,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Row lock: concurrent allocators queue here, one at a time.
            $counter = DB::table(self::COUNTERS_TABLE)
                ->where('business_id', $tenantId)
                ->where('token_date', $day)
                ->lockForUpdate()
                ->first();

            if ($counter === null) {
                // A competing inserter rolled back after we ignored our own
                // insert (ON CONFLICT DO NOTHING does not retry); try again.
                continue;
            }

            $next = max((int) $counter->last_token, $issuedMax) + 1;

            DB::table(self::COUNTERS_TABLE)
                ->where('id', $counter->id)
                ->update(['last_token' => $next, 'updated_at' => now()]);

            return $next;
        }

        // Refusing to invent a token is correct here: a booking without a
        // queue token cannot be served, and the caller's transaction rolls
        // back rather than persisting a half-identified study.
        throw new \RuntimeException(
            'Unable to allocate a study token for tenant '.$tenantId.' on '.$day.'.'
        );
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
