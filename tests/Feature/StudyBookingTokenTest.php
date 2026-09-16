<?php

namespace Tests\Feature;

use App\Models\Service;

/**
 * Adapter-level regression guard for the token domain rule: booking through
 * the public API must yield INTEGER daily tokens (1, 2, 3…) — the exact bug
 * class the StudyTokenAllocator extraction eliminated (string tokens like
 * 'DX-01' were being written into an integer column, which strict-mode MySQL
 * rejects with error 1366 while SQLite silently coerces).
 */
class StudyBookingTokenTest extends ApiTestCase
{
    public function test_api_booking_returns_integer_sequential_tokens(): void
    {
        $svc = Service::where('business_id', $this->businessA->id)->firstOrFail();
        $receptionist = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        $book = fn (string $name) => $this->actingAs($receptionist)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => $name, 'gender' => 'female'],
            'serviceId' => $svc->id,
            'date' => now()->toDateString(),
            'time' => '11:30 AM',
            'priority' => 'routine',
        ])->assertCreated()->json('data.study');

        $first = $book('Token Probe One');
        $second = $book('Token Probe Two');

        $this->assertSame(1, (int) $first['tokenNumber']);
        $this->assertSame(2, (int) $second['tokenNumber']);
        $this->assertEquals($first['tokenNumber'] + 1, $second['tokenNumber']);
    }
}
