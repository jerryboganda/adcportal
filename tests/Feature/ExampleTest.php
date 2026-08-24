<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_single_clinic_business_routes_generate_valid_urls(): void
    {
        $businessId = getActiveBusiness();

        $this->assertSame(url('/business'), route('business.index'));
        $this->assertSame(url('/business/create'), route('business.create'));
        $this->assertSame(url('/clinic/manage'), route('business.manage'));
        $this->assertSame(url('/clinic/manage/' . $businessId), route('business.manage', $businessId));
        $this->assertSame(url('/clinic/edit/' . $businessId), route('business.edit', $businessId));
        $this->assertStringNotContainsString('?', route('business.manage', 123));
    }
}
