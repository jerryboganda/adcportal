<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RadiologyPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('type', 'admin')->first();

        // Give admin the full seeded permission set (seeder attaches automatically).
        $this->actingAs($this->admin);
    }

    public function test_radiology_pages_render_for_admin(): void
    {
        $routes = [
            'study.checkin',
            'study.technologist',
            'reports.worklist',
            'modality.index',
            'room.index',
            'screening-forms.index',
            'report-templates.index',
            'invoices.index',
            'appointment.index',
            'portal.studies',
        ];

        foreach ($routes as $name) {
            $response = $this->get(route($name));
            $this->assertTrue(
                $response->status() === 200,
                "Route {$name} returned {$response->status()} instead of 200."
            );
        }
    }

    public function test_queue_board_requires_key(): void
    {
        $key = company_setting('queue_board_key');

        $this->get('/display/queue-board')->assertNotFound();
        $this->get('/display/queue-board?key=wrong')->assertNotFound();
        $this->get('/display/queue-board?key='.$key)->assertOk();
    }
}
