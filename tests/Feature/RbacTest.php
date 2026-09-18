<?php

namespace Tests\Feature;

use App\Models\Service;

class RbacTest extends ApiTestCase
{    private function bookStudy()
    {
        $svc = Service::where('code', 'DX-CHEST-PA')->where('business_id', $this->businessA->id)->firstOrFail();
        $receptionist = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        $response = $this->actingAs($receptionist)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'RBAC Patient', 'gender' => 'male'],
            'serviceId' => $svc->id,
            'date' => now()->toDateString(),
            'time' => '12:00 PM',
            'priority' => 'routine',
        ]);

        if ($response->status() !== 201) {
            \Log::error('BOOKING-RESPONSE: '.substr($response->getContent(), 0, 2500));
        }

        return $response->assertCreated()->json('data');
    }

    public function test_guest_gets_unauthenticated(): void
    {
        $this->getJson('/api/v1/bootstrap')->assertUnauthorized();
        $this->getJson('/api/v1/studies')->assertUnauthorized();
    }

    public function test_receptionist_cannot_sign_or_create_reports(): void
    {
        ['study' => $study] = $this->bookStudy();

        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'receptionist'))
            ->postJson("/api/v1/studies/{$study['id']}/reports", [
                'findings' => 'x',
                'impression' => 'y',
            ])->assertForbidden();
    }

    public function test_technician_cannot_manage_staff_or_create_invoices(): void
    {
        ['study' => $study] = $this->bookStudy();
        $tech = $this->makeStaff($this->businessA, $this->adminA, 'technologist');

        $this->actingAs($tech)->getJson('/api/v1/staff')->assertForbidden();
        $this->actingAs($tech)->postJson('/api/v1/staff', [
            'name' => 'X', 'email' => 'x@test.local', 'password' => 'Secret#123', 'role' => 'receptionist',
        ])->assertForbidden();

        $this->actingAs($tech)
            ->postJson("/api/v1/studies/{$study['id']}/invoices", [
                'items' => [['description' => 'extra', 'quantity' => 1, 'unitPrice' => 100]],
            ])->assertForbidden();
    }

    public function test_billing_clerk_cannot_book_studies(): void
    {
        $svc = Service::where('code', 'DX-CHEST-PA')->where('business_id', $this->businessA->id)->firstOrFail();
        $billing = $this->makeStaff($this->businessA, $this->adminA, 'billing');

        $this->actingAs($billing)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Nope'],
            'serviceId' => $svc->id,
            'date' => now()->toDateString(),
            'time' => '1:00 PM',
            'priority' => 'routine',
        ])->assertForbidden();
    }

    public function test_tenant_staff_cannot_reach_other_tenants_via_user_endpoint(): void
    {
        // A staff user of tenant B must not appear in tenant A's staff list.
        $other = $this->makeStaff($this->businessB, $this->adminB, 'receptionist');

        $listing = $this->actingAs($this->adminA)
            ->getJson('/api/v1/staff')->assertOk();

        $this->assertNotContains($other->id, collect($listing->json('data.staff'))->pluck('id'));
    }

    // ==================== radiologists must not book ====================

    public function test_radiologist_cannot_book_studies(): void
    {
        $svc = Service::where('code', 'DX-CHEST-PA')->where('business_id', $this->businessA->id)->firstOrFail();
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $this->actingAs($radiologist)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Should Not Exist', 'gender' => 'male'],
            'serviceId' => $svc->id,
            'date' => now()->toDateString(),
            'time' => '11:00 AM',
            'priority' => 'routine',
        ])->assertForbidden();

        // No study may exist for that patient — the 403 is a hard stop.
        $this->assertDatabaseMissing('customers', ['name' => 'Should Not Exist']);
    }

    public function test_radiologist_cannot_book_with_crafted_cross_tenant_payload(): void
    {
        // A malicious radiologist cannot bypass the wall with another
        // tenant's ids: the permission check fires before any resolution.
        $foreignService = Service::where('code', 'DX-CHEST-PA')->where('business_id', $this->businessB->id)->firstOrFail();
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $this->actingAs($radiologist)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Crafted Payload'],
            'serviceId' => $foreignService->id,
            'roomId' => 999999,
            'date' => now()->toDateString(),
            'time' => '11:00 AM',
            'priority' => 'stat',
        ])->assertForbidden();
    }

    public function test_radiologist_cannot_manage_booking_configuration(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        // Imaging suites.
        $this->actingAs($radiologist)->postJson('/api/v1/rooms', [
            'name' => 'Rogue Suite',
            'modalityId' => \App\Models\Modality::where('code', 'CT')->where('business_id', $this->businessA->id)->firstOrFail()->id,
        ])->assertForbidden();

        // Payment methods.
        $this->actingAs($radiologist)->postJson('/api/v1/payment-methods', [
            'code' => 'rogue', 'name' => 'Rogue Method',
        ])->assertForbidden();

        // Booking-time money capture is also `invoice payment`-gated.
        $svc = Service::where('code', 'DX-CHEST-PA')->where('business_id', $this->businessA->id)->firstOrFail();
        $this->actingAs($radiologist)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Radiologist Payment Bypass'],
            'serviceId' => $svc->id,
            'date' => now()->toDateString(),
            'time' => '11:00 AM',
            'priority' => 'routine',
            'payment' => [
                'status' => 'paid',
                'amountPaid' => 10,
                'method' => 1,
            ],
        ])->assertForbidden();
    }

    public function test_admin_can_book_studies(): void
    {
        // The tenant admin is an authorized booking actor, same as reception.
        $svc = Service::where('code', 'DX-CHEST-PA')->where('business_id', $this->businessA->id)->firstOrFail();

        $this->actingAs($this->adminA)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Admin Booked Patient', 'gender' => 'female'],
            'serviceId' => $svc->id,
            'date' => now()->toDateString(),
            'time' => '2:00 PM',
            'priority' => 'urgent',
        ])->assertCreated();
    }
}
