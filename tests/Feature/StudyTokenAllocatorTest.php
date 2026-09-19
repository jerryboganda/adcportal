<?php

namespace Tests\Feature;

use App\Enums\StudyState;
use App\Models\Appointment;
use App\Services\StudyTokenAllocator;
use Illuminate\Support\Facades\DB;

/**
 * The token domain rule, exercised through BOTH adapters (API booking and
 * the allocator service directly): integer tokens, one sequence per
 * tenant+day, race-free, never leaking across tenants.
 */
class StudyTokenAllocatorTest extends ApiTestCase
{
    private function bookStudy(int $businessId, int $serviceId, int $customerUserId, string $date, string $priority = 'routine'): Appointment
    {
        return Appointment::create([
            'customer_id' => $customerUserId,
            'name' => 'Token Test Patient',
            'service_id' => $serviceId,
            'date' => $date,
            'time' => '10:00:00',
            'priority' => $priority,
            'workflow_state' => StudyState::Booked->value,
            'screening_required' => false,
            'screening_cleared' => true,
            'room_number' => 'Room 1',
            'business_id' => $businessId,
            // A REAL user id: appointments.created_by is an enforced foreign
            // key on PostgreSQL, and Postgres sequences are not rolled back
            // with the test transaction, so a hardcoded `1` only exists in the
            // first test of a run (SQLite restarts its autoincrement).
            'created_by' => $customerUserId,
        ]);
    }

    public function test_tokens_are_sequential_integers_per_day(): void
    {
        $date = now()->toDateString();
        $svc = \App\Models\Service::forClinic($this->businessA->id)->first();
        $owner = $this->adminA->id;

        $a = $this->bookStudy($this->businessA->id, $svc->id, $owner, $date);
        $b = $this->bookStudy($this->businessA->id, $svc->id, $owner, $date);
        $c = $this->bookStudy($this->businessA->id, $svc->id, $owner, $date);

        StudyTokenAllocator::assignTo($a, $date);
        StudyTokenAllocator::assignTo($b, $date);
        StudyTokenAllocator::assignTo($c, $date);

        $this->assertSame(1, $a->fresh()->token_number);
        $this->assertSame(2, $b->fresh()->token_number);
        $this->assertSame(3, $c->fresh()->token_number);
        $this->assertIsInt($a->fresh()->token_number);
    }

    public function test_token_sequence_restarts_per_day(): void
    {
        $svc = \App\Models\Service::forClinic($this->businessA->id)->first();
        $owner = $this->adminA->id;

        $yesterday = $this->bookStudy($this->businessA->id, $svc->id, $owner, now()->subDay()->toDateString());
        $today = $this->bookStudy($this->businessA->id, $svc->id, $owner, now()->toDateString());

        StudyTokenAllocator::assignTo($yesterday, now()->subDay()->toDateString());
        StudyTokenAllocator::assignTo($today, now()->toDateString());

        $this->assertSame(1, $yesterday->fresh()->token_number);
        $this->assertSame(1, $today->fresh()->token_number);
    }

    public function test_tokens_never_leak_across_tenants(): void
    {
        $date = now()->toDateString();
        $svcA = \App\Models\Service::forClinic($this->businessA->id)->first();
        $svcB = \App\Models\Service::forClinic($this->businessB->id)->first();

        $a1 = $this->bookStudy($this->businessA->id, $svcA->id, $this->adminA->id, $date);
        $a2 = $this->bookStudy($this->businessA->id, $svcA->id, $this->adminA->id, $date);
        $b1 = $this->bookStudy($this->businessB->id, $svcB->id, $this->adminB->id, $date);

        StudyTokenAllocator::assignTo($a1, $date);
        StudyTokenAllocator::assignTo($a2, $date);
        StudyTokenAllocator::assignTo($b1, $date);

        $this->assertSame(1, $a1->fresh()->token_number);
        $this->assertSame(2, $a2->fresh()->token_number);
        $this->assertSame(1, $b1->fresh()->token_number); // tenant B has its own sequence
    }

    public function test_works_inside_a_caller_transaction(): void
    {
        $date = now()->toDateString();
        $svc = \App\Models\Service::forClinic($this->businessA->id)->first();

        $token = DB::transaction(function () use ($date, $svc) {
            $apt = $this->bookStudy($this->businessA->id, $svc->id, $this->adminA->id, $date);

            return StudyTokenAllocator::assignTo($apt, $date);
        });

        $this->assertSame(1, $token);
    }

    public function test_counter_continues_after_tokens_written_outside_the_allocator(): void
    {
        $date = now()->toDateString();
        $svc = \App\Models\Service::forClinic($this->businessA->id)->first();

        // Seeded history / imports can write tokens without the allocator; the
        // next allocation must continue past them instead of re-issuing 1.
        // (token_number is deliberately NOT mass-assignable — only the
        // allocator mints tokens — so history is written explicitly.)
        $imported = $this->bookStudy($this->businessA->id, $svc->id, $this->adminA->id, $date);
        $imported->forceFill(['token_number' => 7])->save();

        $fresh = $this->bookStudy($this->businessA->id, $svc->id, $this->adminA->id, $date);

        $this->assertSame(8, StudyTokenAllocator::assignTo($fresh, $date));
    }

    public function test_many_allocations_never_repeat_a_token(): void
    {
        $date = now()->toDateString();
        $svc = \App\Models\Service::forClinic($this->businessA->id)->first();
        $allocated = [];

        foreach (range(1, 25) as $ignored) {
            $apt = $this->bookStudy($this->businessA->id, $svc->id, $this->adminA->id, $date);
            $allocated[] = StudyTokenAllocator::assignTo($apt, $date);
        }

        $this->assertSame(range(1, 25), $allocated);
        $this->assertSame(25, count(array_unique($allocated)));

        // The counter row is the day's high-water mark, per tenant.
        $this->assertSame(25, (int) DB::table(StudyTokenAllocator::COUNTERS_TABLE)
            ->where('business_id', $this->businessA->id)
            ->where('token_date', $date)
            ->value('last_token'));
    }
}
