<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Service;

class TenantIsolationTest extends ApiTestCase
{
    private function bookInTenantA(Service $service = null)
    {
        $svc = $service ?? Service::where('code', 'DX-CHEST-PA')->where('business_id', $this->businessA->id)->firstOrFail();
        $receptionist = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        return $this->actingAs($receptionist)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Alpha Patient', 'gender' => 'male'],
            'serviceId' => $svc->id,
            'date' => now()->toDateString(),
            'time' => '10:00 AM',
            'priority' => 'routine',
        ])->assertCreated()->json('data.study');
    }

    public function test_tenant_b_cannot_see_tenant_a_patients_or_studies_in_bootstrap(): void
    {
        $study = $this->bookInTenantA();

        $bootstrap = $this->actingAs($this->adminB)->getJson('/api/v1/bootstrap')->assertOk();

        $studyIds = collect($bootstrap->json('data.studies'))->pluck('id');
        $this->assertNotContains($study['id'], $studyIds);

        $patientNames = collect($bootstrap->json('data.patients'))->pluck('name');
        $this->assertNotContains('Alpha Patient', $patientNames);

        // Tenant A does see it.
        $bootstrapA = $this->actingAs($this->adminA)->getJson('/api/v1/bootstrap')->assertOk();
        $this->assertContains($study['id'], collect($bootstrapA->json('data.studies'))->pluck('id'));
    }

    public function test_tenant_b_cannot_mutate_tenant_a_studies(): void
    {
        $study = $this->bookInTenantA();
        $id = $study['id'];

        $this->actingAs($this->adminB)
            ->putJson("/api/v1/studies/{$id}", ['priority' => 'stat'])
            ->assertNotFound();

        $this->actingAs($this->adminB)
            ->postJson("/api/v1/studies/{$id}/transition", ['action' => 'checkin'])
            ->assertNotFound();

        $this->actingAs($this->adminB)
            ->getJson("/api/v1/studies/{$id}/screening")
            ->assertNotFound();
    }

    public function test_tenant_b_cannot_read_tenant_a_invoices(): void
    {
        $study = $this->bookInTenantA();
        $invoice = Invoice::where('appointment_id', $study['id'])->firstOrFail();

        $listing = $this->actingAs($this->adminB)->getJson('/api/v1/invoices')->assertOk();
        $this->assertNotContains(
            $invoice->invoice_number,
            collect($listing->json('data.invoices'))->pluck('invoiceNumber')
        );

        $this->actingAs($this->adminB)
            ->postJson("/api/v1/invoices/{$invoice->id}/payments", [
                'amount' => 100,
                'method' => 'cash',
            ])->assertNotFound();
    }

    public function test_customer_records_are_tenant_scoped(): void
    {
        $this->bookInTenantA();

        $this->assertSame(
            1,
            Customer::where('business_id', $this->businessA->id)->count()
        );
        $this->assertSame(
            0,
            Customer::where('business_id', $this->businessB->id)->count()
        );
    }
}
