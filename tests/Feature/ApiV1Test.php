<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_ok(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('version', 'v1');
    }

    public function test_login_returns_token_for_clinic_admin(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => bcrypt('secret123'),
            'type' => 'admin',
            'lang' => 'en',
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'admin@test.local',
            'password' => 'secret123',
        ])->assertOk()->assertJsonStructure(['data' => ['token', 'token_type', 'user' => ['id', 'email']]]);
    }

    public function test_login_rejects_non_staff_users(): void
    {
        User::create([
            'name' => 'Customer',
            'email' => 'cust@test.local',
            'password' => bcrypt('secret123'),
            'type' => 'customer',
            'lang' => 'en',
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'cust@test.local',
            'password' => 'secret123',
        ])->assertStatus(403);
    }

    public function test_login_is_rate_limited(): void
    {
        RateLimiter::clear('login');
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', ['email' => 'nobody@test.local', 'password' => 'x']);
        }
        $this->postJson('/api/v1/login', ['email' => 'nobody@test.local', 'password' => 'x'])
            ->assertStatus(429);
    }

    public function test_protected_routes_require_token(): void
    {
        $this->getJson('/api/v1/appointments')->assertStatus(401);
        $this->getJson('/api/v1/dashboard')->assertStatus(401);
    }

    public function test_legacy_api_is_retired(): void
    {
        // Legacy /api/* was removed during the radiology overhaul.
        $this->postJson('/api/login', ['email' => 'x@y.z', 'password' => 'x'])
            ->assertNotFound();
    }
}
