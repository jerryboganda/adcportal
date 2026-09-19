<?php

namespace Tests\Feature;

use App\Enums\StudyState;
use App\Models\Appointment;
use App\Models\Service;
use App\Services\StudyTokenAllocator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;

/**
 * Race behaviour of the daily token sequence: two receptionists booking the
 * same tenant and day at the same instant must serialize on the day's counter
 * row and never share a token.
 *
 * PostgreSQL-only by nature. SQLite ignores row locks entirely — which is
 * exactly why the previous `SELECT MAX(token_number) … FOR UPDATE` allocator
 * passed CI while throwing a 500 on every production booking.
 */
class StudyTokenConcurrencyTest extends ApiTestCase
{
    /**
     * This proof needs REAL transactions on two connections, not the
     * savepoints RefreshDatabase would otherwise wrap the test in.
     */
    protected function connectionsToTransact(): array
    {
        return [];
    }

    /**
     * Migrate unconditionally, on THIS test's connection. RefreshDatabase's
     * one-shot static assumes a shared in-memory handle; this class needs its
     * own schema because its rows are committed (a second connection must be
     * able to see them).
     */
    protected function refreshTestDatabase(): void
    {
        $this->artisan('migrate:fresh');

        $this->app[Kernel::class]->setArtisan(null);
    }

    protected function tearDown(): void
    {
        // This class commits (it must, so a second connection can observe the
        // counter) — force the next test class to rebuild the schema so the
        // committed fixture can never leak into another suite.
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    public function test_concurrent_allocations_serialize_on_the_daily_counter(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Row-lock interleaving is only observable on PostgreSQL.');
        }

        $service = Service::where('business_id', $this->businessA->id)->firstOrFail();
        $today = now()->toDateString();

        $newAppointment = fn () => Appointment::create([
            'customer_id' => $this->adminA->id,
            'name' => 'Concurrent Token Probe',
            'service_id' => $service->id,
            'date' => $today,
            'time' => '10:00:00',
            'priority' => 'routine',
            'workflow_state' => StudyState::Booked->value,
            'screening_required' => false,
            'screening_cleared' => true,
            'business_id' => $this->businessA->id,
            'created_by' => $this->adminA->id,
        ]);

        $default = DB::getDefaultConnection();
        config(['database.connections.token_race' => config("database.connections.{$default}")]);
        $racer = DB::connection('token_race');
        $racer->statement("set lock_timeout = '250ms'");

        // Receptionist A opens a booking and takes the day's token (holding the
        // counter row lock until the transaction commits).
        DB::beginTransaction();
        $tokenA = StudyTokenAllocator::assignTo($newAppointment(), $today);

        // Receptionist B, on an independent connection, tries the SAME tenant
        // and day while A is still uncommitted.
        $racer->beginTransaction();
        $blocked = null;

        try {
            DB::setDefaultConnection('token_race');

            try {
                StudyTokenAllocator::next($this->businessA->id, $today);
            } catch (QueryException $e) {
                $blocked = $e;
            }
        } finally {
            DB::setDefaultConnection($default);
            $racer->rollBack();
        }

        $this->assertNotNull($blocked, 'A concurrent allocation must wait for the day counter lock, never proceed in parallel.');
        $this->assertMatchesRegularExpression('/lock timeout/i', $blocked->getMessage());

        // A commits; B retries and receives the NEXT token — never A's.
        DB::commit();

        DB::setDefaultConnection('token_race');

        try {
            $racer->beginTransaction();
            $tokenB = StudyTokenAllocator::next($this->businessA->id, $today);
            $racer->commit();
        } finally {
            DB::setDefaultConnection($default);
        }

        $this->assertSame(1, $tokenA);
        $this->assertSame(2, $tokenB);
        $this->assertSame(1, Appointment::where('business_id', $this->businessA->id)->whereNotNull('token_number')->count());
    }
}
