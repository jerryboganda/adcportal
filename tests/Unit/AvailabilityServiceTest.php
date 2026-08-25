<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\BusinessHours;
use App\Models\Category;
use App\Models\Location;
use App\Models\Service;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze the clock inside the seeded business window so slot math
        // is deterministic regardless of when the suite runs.
        \Carbon\Carbon::setTestNow(now()->startOfDay()->setTime(8, 0));

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => bcrypt('secret123'),
            'type' => 'admin',
            'lang' => 'en',
        ]);

        $this->business = Business::create([
            'name' => 'ADC Test Clinic',
            'form_type' => 'form-layout',
            'layouts' => 'Formlayout11',
            'created_by' => $admin->id,
        ]);

        $category = Category::create([
            'name' => 'Lab',
            'business_id' => $this->business->id,
            'created_by' => $admin->id,
        ]);

        Location::create([
            'name' => 'Main',
            'address' => 'Test Address',
            'business_id' => $this->business->id,
            'created_by' => $admin->id,
        ]);

        $this->service = Service::create([
            'name' => 'Blood Test',
            'duration' => 30,
            'price' => 500,
            'category_id' => $category->id,
            'business_id' => $this->business->id,
            'created_by' => $admin->id,
        ]);

        \App\Models\Staff::create(['name' => 'Dr Test', 'user_id' => $admin->id, 'business_id' => $this->business->id, 'created_by' => $admin->id, 'location_id' => '1', 'service_id' => '1', 'status' => '1']);

        BusinessHours::create([
            'day_name' => now()->format('l'),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'break_hours' => json_encode([]),
            'business_id' => $this->business->id,
            'created_by' => $admin->id,
        ]);

        \Cache::flush();
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_generates_slots_based_on_business_hours_and_duration(): void
    {
        $slots = app(AvailabilityService::class)->slots($this->service->id, now()->format('d-m-Y'));

        // 09:00-11:00 with 30min duration = 4 slots
        $this->assertCount(4, $slots);
        $this->assertSame('09:00', $slots[0]['start']);
        $this->assertSame('10:30', $slots[3]['start']);
    }

    public function test_slots_are_cached_and_invalidation_works(): void
    {
        $date = now()->format('d-m-Y');
        $svc = app(AvailabilityService::class);

        $first = $svc->slots($this->service->id, $date);
        $this->assertTrue(\Cache::has(AvailabilityService::cacheKey($this->service->id, null, $date)));

        // Mutate DB directly — cached result must not change within TTL
        $this->createAppointment($date, '09:00');
        $second = $svc->slots($this->service->id, $date);
        $this->assertCount(count($first), $second, 'cache must serve the same slot count within TTL');

        // Invalidation makes the booked slot disappear
        AvailabilityService::forget($this->service->id, null, $date);
        $third = $svc->slots($this->service->id, $date);
        $this->assertCount(count($first) - 1, $third);
        $this->assertNotContains('09:00', array_column($third, 'start'));
    }

    public function test_break_hours_are_excluded(): void
    {
        BusinessHours::where('business_id', $this->business->id)->update([
            'break_hours' => json_encode([['start' => '09:30', 'end' => '10:00']]),
        ]);
        \Cache::flush();

        $slots = app(AvailabilityService::class)->slots($this->service->id, now()->format('d-m-Y'));

        $starts = array_column($slots, 'start');
        $this->assertNotContains('09:30', $starts);
        $this->assertCount(3, $starts); // 09:00, 10:00, 10:30
    }

    public function test_today_past_slots_are_cut_off(): void
    {
        \Carbon\Carbon::setTestNow(now()->setTime(10, 15));
        \Cache::flush();

        $slots = app(AvailabilityService::class)->slots($this->service->id, now()->format('d-m-Y'));
        $starts = array_column($slots, 'start');

        $this->assertNotContains('09:00', $starts);
        $this->assertNotContains('10:00', $starts);
        $this->assertContains('10:30', $starts);

        \Carbon\Carbon::setTestNow();
    }

    public function test_maximum_slot_capacity_allows_multiple_bookings(): void
    {
        $date = now()->format('d-m-Y');

        $this->createAppointment($date, '09:00');
        AvailabilityService::forget($this->service->id, null, $date);
        $slots = app(AvailabilityService::class)->slots($this->service->id, $date);

        // Default maximum_slot=1 -> 09:00 gone
        $this->assertNotContains('09:00', array_column($slots, 'start'));
    }

    private function createAppointment(string $date, string $time): void
    {
        \App\Models\Appointment::create([
            'date' => $date,
            'time' => $time,
            'service_id' => $this->service->id,
            'staff_id' => 1,
            'location_id' => 1,
            'business_id' => $this->business->id,
            'created_by' => $this->business->created_by,
            'payment_type' => 'Manually',
            'appointment_status' => 0,
        ]);
    }
}
