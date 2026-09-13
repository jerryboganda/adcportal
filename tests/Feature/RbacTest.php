<?php

namespace Tests\Feature;

use App\Models\Service;

class RbacTest extends ApiTestCase
{
    private function bookStudy()
    {
        $svc = Service::where('code', 'DX-CHEST-PA')->where('business_id', $this->businessA->id)->firstOrFail();
        $receptionist = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        return $this->actingAs($receptionist)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'RBAC Patient', 'gender' => 'male'],
            'serviceId' => $svc->id,
            'date' => now()->toDateString(),
            'time' => '12:00 PM',
            'priority' => 'routine',
        ])->assertCreated()->json('data');
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

        $listing = $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'receptionist') ?: $this->adminA)
            ->getJson('/api/v1/staff')->assertOk();

        $this->assertNotContains($other->id, collect($listing->json('data.staff'))->pluck('id'));
    }
}
