<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Service;
use App\Models\Setting;
use App\Models\TenantBranding;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Live Queue TV: staff console feed, public kiosk feed and display-link
 * settings. Covers the workflow rewrite contract — a called patient leaves
 * "next in line" and appears in "now serving" on every surface, the queue
 * is today-only, tenant-isolated, minimal-PHI, and `queue view` is finally
 * enforced server-side.
 */
class QueueDisplayTest extends ApiTestCase
{
    private function service($business = null): Service
    {
        return Service::where('code', 'DX-CHEST-PA')
            ->where('business_id', ($business ?? $this->businessA)->id)
            ->firstOrFail();
    }

    private function book(array $overrides = [], $business = null): array
    {
        $business = $business ?? $this->businessA;
        $receptionist = $this->makeStaff($business, $this->adminFor($business), 'receptionist');

        $payload = array_merge([
            'newPatient' => [
                'name' => 'Queue Patient '.uniqid(),
                'phone' => '+44 7843 985126',
                'email' => 'queue.'.uniqid().'@example.com',
                'age' => 30,
                'gender' => 'male',
            ],
            'serviceId' => $this->service($business)->id,
            'date' => now()->toDateString(),
            'time' => '9:30 AM',
            'priority' => 'routine',
        ], $overrides);

        return $this->actingAs($receptionist)
            ->postJson('/api/v1/studies', $payload)
            ->assertCreated()
            ->json('data.study');
    }

    private function adminFor($business): User
    {
        return $business->id === $this->businessA->id ? $this->adminA : $this->adminB;
    }

    private function staffDisplay($user)
    {
        return $this->actingAs($user)->getJson('/api/v1/queue/display');
    }

    private function generateKey($business): string
    {
        $admin = $this->adminFor($business);
        $this->actingAs($admin)
            ->putJson('/api/v1/queue/display/settings', ['announcement' => 'Bring your token slip.'])
            ->assertOk();

        return Setting::where('key', 'queue_display_key')
            ->where('business', $business->id)
            ->value('value');
    }

    // ==================== staff feed ====================

    public function test_staff_display_requires_authentication(): void
    {
        $this->getJson('/api/v1/queue/display')->assertStatus(401);
    }

    public function test_staff_display_enforces_queue_view_server_side(): void
    {
        // A session with NO roles: the SPA would hide the tab, but the server
        // must refuse independently.
        $outsider = User::create([
            'name' => 'No Role Staff',
            'email' => 'norole.'.uniqid().'@test.local',
            'password' => 'R1s!T3st#2026x',
            'email_verified_at' => now(),
            'type' => 'staff',
            'active_status' => 1,
            'business_id' => $this->businessA->id,
            'created_by' => $this->businessA->id,
        ]);

        $this->staffDisplay($outsider)->assertStatus(403);
        $this->staffDisplay($this->adminA)->assertOk();
    }

    public function test_call_moves_patient_from_waiting_to_serving(): void
    {
        $study = $this->book();
        $admin = $this->adminA;

        // Before the call: only in "next in line".
        $before = $this->staffDisplay($admin)->assertOk()->json('data');
        $this->assertTrue(collect($before['upNext'])->contains(fn ($e) => $e['id'] === $study['id']));
        $this->assertFalse(collect($before['nowServing'])->contains(fn ($e) => $e['id'] === $study['id']));

        $caller = $this->makeStaff($this->businessA, $admin, 'receptionist');
        $this->actingAs($caller)
            ->postJson("/api/v1/studies/{$study['id']}/transition", ['action' => 'call'])
            ->assertOk();

        // After the call: serving, not waiting — on the SAME feed every TV polls.
        $after = $this->staffDisplay($admin)->assertOk()->json('data');
        $this->assertTrue(collect($after['nowServing'])->contains(fn ($e) => $e['id'] === $study['id']));
        $this->assertFalse(collect($after['upNext'])->contains(fn ($e) => $e['id'] === $study['id']));
        $this->assertSame(1, $after['stats']['serving']);
        $this->assertNotNull(collect($after['nowServing'])->first(fn ($e) => $e['id'] === $study['id'])['calledAt']);

        $this->assertTrue(
            AuditLog::where('action', 'queue_patient_called')
                ->where('business_id', $this->businessA->id)
                ->exists()
        );
    }

    public function test_call_is_rejected_once_the_study_left_the_queue(): void
    {
        $study = $this->book();
        $tech = $this->makeStaff($this->businessA, $this->adminA, 'technologist');
        $id = $study['id'];

        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/transition", ['action' => 'checkin'])->assertOk();
        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/transition", ['action' => 'prepare'])->assertOk();
        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/transition", ['action' => 'start'])->assertOk();
        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/transition", [
            'action' => 'complete',
            'dose' => ['doseValue' => 0.15, 'doseUnit' => 'dGy*cm² (DAP)'],
        ])->assertOk();

        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'receptionist'))
            ->postJson("/api/v1/studies/{$id}/transition", ['action' => 'call'])
            ->assertStatus(422);
    }

    public function test_no_show_is_allowed_from_checked_in(): void
    {
        $study = $this->book();
        $id = $study['id'];
        $staff = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        $this->actingAs($staff)->postJson("/api/v1/studies/{$id}/transition", ['action' => 'checkin'])->assertOk();
        $this->actingAs($staff)->postJson("/api/v1/studies/{$id}/transition", ['action' => 'call'])->assertOk();

        // Called twice, patient never appeared → clear them from the board.
        $this->actingAs($staff)->postJson("/api/v1/studies/{$id}/transition", ['action' => 'no_show'])->assertOk();
        $this->assertSame('no_show', Appointment::find($id)->workflow_state);

        $payload = $this->staffDisplay($this->adminA)->assertOk()->json('data');
        $this->assertSame(1, $payload['stats']['noShow']);
    }

    // ==================== public kiosk feed ====================

    public function test_public_display_requires_a_valid_key(): void
    {
        $this->getJson('/api/v1/public/queue-display')->assertStatus(404);
        $this->getJson('/api/v1/public/queue-display?key=not-a-real-key')->assertStatus(404);
    }

    public function test_public_display_serves_minimal_phi_for_today_only(): void
    {
        $study = $this->book(['priority' => 'stat']);
        $key = $this->generateKey($this->businessA);

        // Yesterday's lingering check-in must never reach the board.
        Appointment::create([
            'customer_id' => (int) $study['patientId'],
            'name' => 'Yesterday Patient',
            'service_id' => $this->service()->id,
            'date' => Carbon::yesterday()->toDateString(),
            'time' => '9:00 AM',
            'priority' => 'routine',
            'workflow_state' => 'checked_in',
            'checked_in_at' => Carbon::yesterday(),
            'business_id' => $this->businessA->id,
        ]);

        $payload = $this->getJson("/api/v1/public/queue-display?key={$key}")
            ->assertOk()
            ->json('data');

        $this->assertSame($this->businessA->name, $payload['businessName']);
        $this->assertSame('Bring your token slip.', $payload['announcement']);

        $tokens = collect($payload['upNext'])->pluck('token');
        $this->assertTrue($tokens->contains($study['tokenNumber']));
        $this->assertFalse(
            collect($payload['upNext'])->concat($payload['nowServing'])->contains(fn ($e) => $e['patientName'] === 'Yesterday Patient')
        );

        // Minimal PHI: no MRN/contact/DOB/financial fields anywhere on the wire.
        $entry = collect($payload['upNext'])->first(fn ($e) => $e['id'] === $study['id']);
        foreach (['patient', 'contact', 'phone', 'email', 'age', 'gender', 'mrn', 'notes', 'service', 'doseLog', 'report'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $entry);
        }
        $this->assertSame('stat', $entry['priority']);
    }

    public function test_public_display_is_tenant_isolated(): void
    {
        // Token numbers are per-tenant DAILY sequences — both tenants start
        // at 1 today — so isolation must be asserted on unique patient names.
        $studyA = $this->book([], $this->businessA);
        $studyB = $this->book([], $this->businessB);
        $keyA = $this->generateKey($this->businessA);

        $payload = $this->getJson("/api/v1/public/queue-display?key={$keyA}")
            ->assertOk()
            ->json('data');

        $names = collect($payload['upNext'])->pluck('patientName');
        $this->assertTrue($names->contains($studyA['patient']['name']));
        $this->assertFalse($names->contains($studyB['patient']['name']));
    }

    public function test_public_display_goes_dark_when_the_tenant_is_not_subscribable(): void
    {
        $key = $this->generateKey($this->businessA);

        Business::where('id', $this->businessA->id)->update(['subscription_status' => 'suspended']);
        $this->getJson("/api/v1/public/queue-display?key={$key}")->assertStatus(404);

        Business::where('id', $this->businessA->id)->update(['subscription_status' => 'active']);
        $this->getJson("/api/v1/public/queue-display?key={$key}")->assertOk();
    }

    // ==================== display settings ====================

    public function test_display_settings_require_setting_manage_permission(): void
    {
        $receptionist = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        $this->actingAs($receptionist)->getJson('/api/v1/queue/display/settings')->assertStatus(403);
        $this->actingAs($receptionist)->putJson('/api/v1/queue/display/settings', ['announcement' => 'x'])->assertStatus(403);
        $this->actingAs($this->adminA)->getJson('/api/v1/queue/display/settings')->assertOk();
    }

    public function test_regenerating_the_display_link_kills_the_old_one(): void
    {
        $oldKey = $this->generateKey($this->businessA);
        $this->getJson("/api/v1/public/queue-display?key={$oldKey}")->assertOk();

        $saved = $this->actingAs($this->adminA)
            ->putJson('/api/v1/queue/display/settings', ['regenerateKey' => true])
            ->assertOk()
            ->json('data');

        $this->assertNotSame($oldKey, $saved['displayKey']);
        $this->getJson("/api/v1/public/queue-display?key={$oldKey}")->assertStatus(404);
        $this->getJson("/api/v1/public/queue-display?key={$saved['displayKey']}")->assertOk();
    }

    // ==================== identity ====================

    public function test_display_name_follows_the_same_brand_chain_as_the_topbar(): void
    {
        $this->book();
        TenantBranding::updateOrCreate(
            ['business_id' => $this->businessA->id],
            ['app_name' => 'DHQ Test Hospital']
        );

        $payload = $this->staffDisplay($this->adminA)->assertOk()->json('data');
        $this->assertSame('DHQ Test Hospital', $payload['businessName']);
    }
}
