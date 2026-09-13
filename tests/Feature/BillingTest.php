<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Service;

class BillingTest extends ApiTestCase
{
    private function bookStudy()
    {
        $svc = Service::where('code', 'US-ABD-PEL')->where('business_id', $this->businessA->id)->firstOrFail();
        $receptionist = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        return $this->actingAs($receptionist)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Billing Test Patient', 'gender' => 'female'],
            'serviceId' => $svc->id,
            'date' => now()->toDateString(),
            'time' => '11:00 AM',
            'priority' => 'routine',
        ]);

        if ($response->status() !== 201) {
            $this->fail('BOOKING FAILED ['.$response->status().']: '.substr($response->getContent(), 0, 1600));
        }

        return $response->assertCreated()->json('data');
    }

    public function test_payments_update_status_and_balance(): void
    {
        ['study' => $study, 'invoice' => $invoice] = $this->bookStudy();
        $billing = $this->makeStaff($this->businessA, $this->adminA, 'billing');

        // Partial payment
        $response = $this->actingAs($billing)
            ->postJson("/api/v1/invoices/{$invoice['id']}/payments", [
                'amount' => 1000,
                'method' => 'cash',
                'reference' => 'RCPT-1',
            ])->assertOk();

        $this->assertSame('partial', $response->json('invoice.status'));
        $this->assertEquals(1000, $response->json('invoice.paidTotal'));
        $this->assertEquals(2500, $response->json('invoice.balanceDue'));

        // Settle the rest
        $response = $this->actingAs($billing)
            ->postJson("/api/v1/invoices/{$invoice['id']}/payments", [
                'amount' => 2500,
                'method' => 'card',
                'reference' => 'POS-1',
            ])->assertOk();

        $this->assertSame('paid', $response->json('invoice.status'));
        $this->assertEquals(0, $response->json('invoice.balanceDue'));

        // Overpayment is rejected
        $this->actingAs($billing)
            ->postJson("/api/v1/invoices/{$invoice['id']}/payments", [
                'amount' => 500,
                'method' => 'cash',
            ])->assertStatus(422);
    }

    public function test_void_rules(): void
    {
        ['invoice' => $invoice] = $this->bookStudy();
        $billing = $this->makeStaff($this->businessA, $this->adminA, 'billing');

        // With payments → refused
        $this->actingAs($billing)
            ->postJson("/api/v1/invoices/{$invoice['id']}/payments", ['amount' => 500, 'method' => 'cash'])
            ->assertOk();

        $this->actingAs($billing)
            ->postJson("/api/v1/invoices/{$invoice['id']}/void", ['reason' => 'duplicate'])
            ->assertStatus(422);

        // Without payments → voided
        $fresh = Invoice::where('appointment_id', $invoice['appointmentId'])->first();
        $fresh->payments()->delete();
        $fresh->recalculateFromPayments();

        $this->actingAs($billing)
            ->postJson("/api/v1/invoices/{$invoice['id']}/void", ['reason' => 'test'])
            ->assertOk()
            ->assertJsonPath('invoice.status', 'void');
    }

    public function test_invoice_numbering_is_sequential_per_tenant(): void
    {
        $first = $this->bookStudy();
        $second = $this->bookStudy();

        $firstSeq = (int) substr($first['invoice']['invoiceNumber'], -5);
        $secondSeq = (int) substr($second['invoice']['invoiceNumber'], -5);

        $this->assertSame($firstSeq + 1, $secondSeq);
    }
}
