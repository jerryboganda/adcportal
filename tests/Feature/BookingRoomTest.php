<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Modality;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;

/**
 * Imaging suites (rooms) as tenant configuration: explicit selection at
 * booking, modality/activity/tenant validation, auto-assignment, and the
 * admin CRUD surface — replacing the old hard-coded 'Room 1' fallback.
 */
class BookingRoomTest extends ApiTestCase
{
    private function ctService(): Service
    {
        return Service::where('code', 'CT-BRAIN-NC')->where('business_id', $this->businessA->id)->firstOrFail();
    }

    private function ctModality(): Modality
    {
        return Modality::where('code', 'CT')->where('business_id', $this->businessA->id)->firstOrFail();
    }

    private function makeRoom(int $businessId, int $modalityId, array $attrs = []): Room
    {
        return Room::create([
            'name' => $attrs['name'] ?? 'CT Suite 1',
            'modality_id' => $modalityId,
            'is_active' => $attrs['is_active'] ?? true,
            'capacity_per_slot' => $attrs['capacity_per_slot'] ?? 1,
            'description' => $attrs['description'] ?? null,
            'business_id' => $businessId,
            'created_by' => $businessId,
        ]);
    }

    private function receptionist(): User
    {
        return $this->makeStaff($this->businessA, $this->adminA, 'receptionist');
    }

    private function book(User $actor, Service $svc, array $extra = [])
    {
        return $this->actingAs($actor)->postJson('/api/v1/studies', array_merge([
            'newPatient' => ['name' => 'Suite Walk-in', 'gender' => 'female'],
            'serviceId' => $svc->id,
            'date' => now()->toDateString(),
            'time' => '09:15 AM',
            'priority' => 'routine',
        ], $extra));
    }

    public function test_explicit_suite_is_persisted_on_the_study(): void
    {
        $room = $this->makeRoom($this->businessA->id, $this->ctModality()->id, ['name' => 'CT Suite 1']);
        $svc = $this->ctService();

        $study = $this->book($this->receptionist(), $svc, ['roomId' => $room->id])
            ->assertCreated()->json('data.study');

        $this->assertEquals($room->id, $study['roomId']);
        $this->assertSame('CT Suite 1', $study['roomNumber']);

        $appointment = Appointment::findOrFail($study['id']);
        $this->assertSame($room->id, $appointment->room_id);
        $this->assertSame('CT Suite 1', $appointment->room_number);
    }

    public function test_suite_of_another_modality_is_rejected(): void
    {
        $dxModality = Modality::where('code', 'DX')->where('business_id', $this->businessA->id)->firstOrFail();
        $dxRoom = $this->makeRoom($this->businessA->id, $dxModality->id, ['name' => 'X-Ray Room 1']);

        $this->book($this->receptionist(), $this->ctService(), ['roomId' => $dxRoom->id])
            ->assertStatus(422);
    }

    public function test_inactive_suite_is_rejected(): void
    {
        $room = $this->makeRoom($this->businessA->id, $this->ctModality()->id, ['is_active' => false]);

        $this->book($this->receptionist(), $this->ctService(), ['roomId' => $room->id])
            ->assertStatus(422);
    }

    public function test_other_tenants_suite_is_rejected(): void
    {
        $bModality = Modality::where('code', 'CT')->where('business_id', $this->businessB->id)->firstOrFail();
        $foreignRoom = $this->makeRoom($this->businessB->id, $bModality->id, ['name' => 'Beta CT Suite']);

        $this->book($this->receptionist(), $this->ctService(), ['roomId' => $foreignRoom->id])
            ->assertNotFound();
    }

    public function test_booking_without_any_suite_leaves_room_empty_not_fake(): void
    {
        // No rooms exist in a freshly provisioned tenant. The study must NOT
        // invent a room (the old hard-coded 'Room 1' is gone).
        $study = $this->book($this->receptionist(), $this->ctService())
            ->assertCreated()->json('data.study');

        $this->assertNull($study['roomId']);
        $this->assertSame('', $study['roomNumber']);
    }

    public function test_suite_is_auto_assigned_when_omitted_and_available(): void
    {
        $this->makeRoom($this->businessA->id, $this->ctModality()->id, ['name' => 'Auto CT Suite']);

        $study = $this->book($this->receptionist(), $this->ctService())
            ->assertCreated()->json('data.study');

        $this->assertSame('Auto CT Suite', $study['roomNumber']);
        $this->assertNotNull($study['roomId']);
    }

    public function test_admin_can_manage_suites_via_api(): void
    {
        $modality = $this->ctModality();

        // Create
        $created = $this->actingAs($this->adminA)->postJson('/api/v1/rooms', [
            'name' => 'CT Suite 2',
            'modalityId' => $modality->id,
            'capacityPerSlot' => 2,
            'description' => 'Second scanner',
            'isActive' => true,
        ])->assertCreated()->json('data.room');
        $this->assertSame('CT Suite 2', $created['name']);

        // Update
        $updated = $this->actingAs($this->adminA)->putJson("/api/v1/rooms/{$created['id']}", [
            'name' => 'CT Suite 2 (Renovated)',
            'modalityId' => $modality->id,
            'isActive' => false,
        ])->assertOk()->json('data.room');
        $this->assertFalse($updated['isActive']);

        // Tenant scoping: another modality's id from tenant B must fail validation.
        $foreignModality = Modality::where('code', 'CT')->where('business_id', $this->businessB->id)->firstOrFail();
        $this->actingAs($this->adminA)->postJson('/api/v1/rooms', [
            'name' => 'Smuggled Suite',
            'modalityId' => $foreignModality->id,
        ])->assertStatus(422);

        // Delete guard: referenced suites refuse deletion.
        $room = Room::findOrFail($created['id']);
        Appointment::create([
            'customer_id' => 0,
            'name' => 'Guard Patient',
            'service_id' => $this->ctService()->id,
            'room_id' => $room->id,
            'room_number' => $room->name,
            'date' => now()->toDateString(),
            'time' => '10:00:00',
            'business_id' => $this->businessA->id,
            'created_by' => $this->adminA->id,
        ]);
        $this->actingAs($this->adminA)
            ->deleteJson("/api/v1/rooms/{$created['id']}")
            ->assertStatus(422);
    }

    public function test_receptionist_cannot_manage_suites(): void
    {
        // Receptionists book; they do not reconfigure the facility.
        $this->actingAs($this->receptionist())
            ->postJson('/api/v1/rooms', ['name' => 'Nope', 'modalityId' => $this->ctModality()->id])
            ->assertForbidden();
    }
}
