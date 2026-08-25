<?php

namespace Tests\Feature;

use App\Models\BusinessHours;
use App\Models\Business;
use App\Models\Category;
use App\Models\Location;
use App\Models\Service;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Business $business;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
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
            'created_by' => $this->admin->id,
        ]);

        $category = Category::create([
            'name' => 'Lab',
            'business_id' => $this->business->id,
            'created_by' => $this->admin->id,
        ]);

        Location::create(['name' => 'Main', 'address' => 'Test Address', 'business_id' => $this->business->id, 'created_by' => $this->admin->id]);

        $this->service = Service::create([
            'name' => 'Consultation',
            'duration' => 30,
            'price' => 1000,
            'category_id' => $category->id,
            'business_id' => $this->business->id,
            'created_by' => $this->admin->id,
        ]);

        \App\Models\Staff::create(['name' => 'Dr Test', 'user_id' => $this->admin->id, 'business_id' => $this->business->id, 'created_by' => $this->admin->id, 'location_id' => '1', 'service_id' => '1', 'status' => '1']);

        BusinessHours::create([
            'day_name' => now()->addDay()->format('l'),
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'break_hours' => json_encode([]),
            'business_id' => $this->business->id,
            'created_by' => $this->admin->id,
        ]);

        Cache::flush();
    }

    private function bookingPayload(array $overrides = []): array
    {
        return array_merge([
            'business_id' => $this->business->id,
            'service' => $this->service->id,
            'staff' => 1,
            'location' => 1,
            'appointment_date' => now()->addDay()->format('d-m-Y'),
            'duration' => '09:00',
            'type' => 'guest-user',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'contact' => '+923001234567',
            'payment' => 'Manually',
        ], $overrides);
    }

    public function test_guest_booking_creates_appointment_and_payment(): void
    {
        $response = $this->post('/booking', $this->bookingPayload());

        $response->assertStatus(200)->assertJson(['status' => 'success']);

        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('appointment_payments', 1);

        $appointment = \App\Models\Appointment::first();
        $this->assertSame($this->business->id, $appointment->business_id);
        $this->assertSame('09:00', $appointment->time);
        $this->assertNotNull($appointment->date_sort, 'date_sort mirror must be auto-filled');
        $this->assertSame(now()->addDay()->format('Y-m-d'), (string) $appointment->date_sort);

        // Audit trail entry written
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'subject_type' => 'Appointment',
            'subject_id' => $appointment->id,
        ]);
    }

    public function test_double_booking_same_slot_is_rejected(): void
    {
        $payload = $this->bookingPayload();

        $first = $this->post('/booking', $payload);
        $first->assertJson(['status' => 'success']);

        AvailabilityService::forget($this->service->id, 1, $payload['appointment_date']);

        $second = $this->post('/booking', $payload);
        $second->assertJson(['status' => 'failed']);

        $this->assertDatabaseCount('appointments', 1, );
    }

    public function test_validation_rejects_bad_contact_and_missing_fields(): void
    {
        // missing required fields (assert the redirect-back behaviour for web)
        $this->from('/booking')->post('/booking', [])->assertSessionHasErrors([
            'business_id', 'service', 'appointment_date', 'duration', 'type', 'email', 'contact', 'payment',
        ]);

        // bad contact format
        $this->post('/booking', $this->bookingPayload(['contact' => '03001234567']))
            ->assertSessionHasErrors(['contact']);

        // bad date format
        $this->post('/booking', $this->bookingPayload(['appointment_date' => '2026-08-25']))
            ->assertSessionHasErrors(['appointment_date']);
    }

    public function test_existing_user_with_wrong_password_is_rejected(): void
    {
        $user = User::create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => bcrypt('rightpass'),
            'type' => 'customer',
            'lang' => 'en',
            'business_id' => $this->business->id,
            'created_by' => $this->admin->id,
        ]);

        $this->post('/booking', $this->bookingPayload([
            'type' => 'existing-user',
            'email' => 'jane@example.com',
            'password' => 'wrongpass',
        ]))->assertJson(['status' => 'error']);

        $this->assertDatabaseCount('appointments', 0);
    }
}
