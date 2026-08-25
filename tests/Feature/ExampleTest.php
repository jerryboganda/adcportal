<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => bcrypt('secret123'),
            'type' => 'admin',
            'lang' => 'en',
        ]);

        Business::create([
            'name' => 'ADC Test Clinic',
            'form_type' => 'form-layout',
            'layouts' => 'Formlayout11',
            'created_by' => $admin->id,
        ]);

        foreach (['title_text' => 'ADC Test Clinic', 'footer_text' => 'Copyright © ADC', 'landing_page' => 'off'] as $key => $value) {
            Setting::create(['key' => $key, 'value' => $value, 'business' => 0, 'created_by' => $admin->id]);
        }
    }

    public function test_login_page_loads(): void
    {
        $this->get('/login')->assertStatus(200);
    }


    public function test_single_clinic_business_routes_generate_valid_urls(): void
    {
        $business = Business::first();

        $this->assertNotNull($business);
        $this->assertIsString(route('appointments.form', $business->slug));
    }
}
