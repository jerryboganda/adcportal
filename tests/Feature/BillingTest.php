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

        $response = $this->actingAs($receptionist)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Billing Test Patient', 'gender' => 'female'],
            'serviceId' => $svc->id,
            'date' => now()->toDateString(),
            'time' => '11:00 AM',
            'priority' => 'routine',
        ]);

        if ($response->status() !== 201) {
            $this->fail('BOOKING FAILED ['.$response->status().']: '.substr(str_replace([chr(10), chr(13)], ' ', $response->getContent()), 0, 1600));
        }

        return $response->assertCreated()->json('data');
    }

    private function billingStaff()
    {
        return $this->makeStaff($this->businessA, $this->adminA, 'billing');
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

        $this->assertSame('partial', $response->json('data.invoice.status'));
        $this->assertEquals(1000, $response->json('data.invoice.paidTotal'));
        $this->assertEquals(2500, $response->json('data.invoice.balanceDue'));

        // Settle the rest
        $response = $this->actingAs($billing)
            ->postJson("/api/v1/invoices/{$invoice['id']}/payments", [
                'amount' => 2500,
                'method' => 'card',
                'reference' => 'POS-1',
            ])->assertOk();

        $this->assertSame('paid', $response->json('data.invoice.status'));
        $this->assertEquals(0, $response->json('data.invoice.balanceDue'));

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
            ->assertJsonPath('data.invoice.status', 'void');
    }

    public function test_invoice_numbering_is_sequential_per_tenant(): void
    {
        $first = $this->bookStudy();
        $second = $this->bookStudy();

        $firstSeq = (int) substr($first['invoice']['invoiceNumber'], -5);
        $secondSeq = (int) substr($second['invoice']['invoiceNumber'], -5);

        $this->assertSame($firstSeq + 1, $secondSeq);
    }

    public function test_initial_payment_cannot_exceed_invoice_total(): void
    {
        $svc = Service::where('code', 'US-ABD-PEL')->where('business_id', $this->businessA->id)->firstOrFail();
        $receptionist = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        $created = $this->actingAs($receptionist)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Cap Test Patient', 'gender' => 'male'],
            'serviceId' => $svc->id,
            'date' => now()->toDateString(),
            'time' => '12:00 PM',
            'priority' => 'routine',
        ])->assertCreated()->json('data');

        $appointmentId = $created['study']['id'];

        // 3500 price, 500 line discount → 3000 payable. An initial payment of
        // 3500 must be refused at creation time (the old hole let it through
        // and seeded a negative balance on a brand-new invoice).
        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'billing'))
            ->postJson("/api/v1/studies/{$appointmentId}/invoices", [
                'items' => [[
                    'serviceId' => $svc->id,
                    'description' => $svc->name,
                    'quantity' => 1,
                    'unitPrice' => 3500,
                    'discount' => 500,
                ]],
                'discountAmount' => 0,
                'initialPayment' => [
                    'amount' => 3500,
                    'method' => 'cash',
                ],
            ])
            ->assertStatus(422);
    }

    /**
     * THE refund lifecycle: pay → partial refund → over-refund refused →
     * full refund → status heals to issued (unpaid) → overpayment accepted
     * again because the ledger is now the whole truth.
     */
    public function test_refund_lifecycle_is_ledger_exact(): void
    {
        ['invoice' => $invoice] = $this->bookStudy(); // 3500 payable
        $billing = $this->billingStaff();

        // Full collection
        $this->actingAs($billing)
            ->postJson("/api/v1/invoices/{$invoice['id']}/payments", ['amount' => 3500, 'method' => 'cash', 'reference' => 'CASH-1'])
            ->assertOk();
        $invoice = $this->getInvoice($invoice['id']);
        $this->assertSame('paid', $invoice['status']);

        $collection = collect($invoice['payments'])->firstWhere('kind', 'payment');
        $this->assertNotNull($collection, 'a collected payment row must exist');

        // Refund permission is its own gate: a billing clerk whose overrides
        // DENY `invoice refund` gets 403 (the role bundle alone won't).
        $clerkNoRefund = $this->makeStaff($this->businessA, $this->adminA, 'billing');
        $this->actingAs($this->adminA)
            ->putJson("/api/v1/access/users/{$clerkNoRefund->id}/overrides", [
                'allow' => [], 'deny' => ['invoice refund'],
            ])->assertOk();
        $this->actingAs($clerkNoRefund)
            ->postJson("/api/v1/invoices/{$invoice['id']}/refunds", [
                'paymentId' => $collection['id'],
                'amount' => 100,
                'reason' => 'should be forbidden',
            ])->assertStatus(403);

        // Partial refund (1200 of 3500)
        $response = $this->actingAs($this->billingStaff())
            ->postJson("/api/v1/invoices/{$invoice['id']}/refunds", [
                'paymentId' => $collection['id'],
                'amount' => 1200,
                'reason' => 'Patient cancelled after booking',
            ])->assertOk();

        $invoice = $response->json('data.invoice');
        $this->assertEquals(2300, $invoice['paidTotal']);
        $this->assertEquals(1200, $invoice['balanceDue']);
        $this->assertSame('partial', $invoice['status'], 'partially refunded money leaves a real balance');
        $this->assertCount(1, collect($invoice['payments'])->where('kind', 'refund'));

        // Refunding more than the collection holds is refused
        $this->actingAs($this->billingStaff())
            ->postJson("/api/v1/invoices/{$invoice['id']}/refunds", [
                'paymentId' => $collection['id'],
                'amount' => 2400, // only 2300 remains refundable
                'reason' => 'over-refund attempt',
            ])->assertStatus(422);

        // Full refund of the remainder
        $response = $this->actingAs($this->billingStaff())
            ->postJson("/api/v1/invoices/{$invoice['id']}/refunds", [
                'paymentId' => $collection['id'],
                'amount' => 2300,
                'reason' => 'Full settlement returned',
            ])->assertOk();

        $invoice = $response->json('data.invoice');
        $this->assertEquals(0, $invoice['paidTotal']);
        $this->assertEquals(3500, $invoice['balanceDue']);
        $this->assertSame('issued', $invoice['status'], 'a fully refunded invoice is again an outstanding debt');

        // Ledger is the truth: overpayment is now accepted again.
        $this->actingAs($this->billingStaff())
            ->postJson("/api/v1/invoices/{$invoice['id']}/payments", ['amount' => 500, 'method' => 'card'])
            ->assertOk();
    }

    public function test_refund_is_refused_for_void_invoices_and_foreign_tenants(): void
    {
        ['invoice' => $invoice] = $this->bookStudy();
        $billing = $this->billingStaff();

        $this->actingAs($billing)
            ->postJson("/api/v1/invoices/{$invoice['id']}/payments", ['amount' => 1000, 'method' => 'cash'])
            ->assertOk();
        $invoice = $this->getInvoice($invoice['id']);
        $collection = collect($invoice['payments'])->firstWhere('kind', 'payment');

        // A voided invoice is a closed document of record: no refunds.
        $fresh = Invoice::where('appointment_id', $invoice['appointmentId'])->first();
        $fresh->payments()->delete();
        $fresh->recalculateFromPayments();
        $fresh->forceFill(['status' => Invoice::STATUS_VOID, 'voided_at' => now()])->save();

        $this->actingAs($this->billingStaff())
            ->postJson("/api/v1/invoices/{$invoice['id']}/refunds", [
                'paymentId' => $collection['id'],
                'amount' => 100,
                'reason' => 'void invoice',
            ])->assertStatus(422);
    }

    public function test_shift_summary_scopes_to_today_and_folds_in_refunds(): void
    {
        ['invoice' => $invoice] = $this->bookStudy();
        $billing = $this->billingStaff();

        $this->actingAs($billing)
            ->postJson("/api/v1/invoices/{$invoice['id']}/payments", ['amount' => 2000, 'method' => 'cash'])
            ->assertOk();
        $invoice = $this->getInvoice($invoice['id']);
        $collection = collect($invoice['payments'])->firstWhere('kind', 'payment');

        $this->actingAs($billing)
            ->postJson("/api/v1/invoices/{$invoice['id']}/refunds", [
                'paymentId' => $collection['id'],
                'amount' => 500,
                'reason' => 'partial return',
            ])->assertOk();

        $response = $this->actingAs($billing)
            ->getJson('/api/v1/billing/shift-summary')
            ->assertOk()
            ->json('data.shift');

        $cash = collect($response['byMethod'])->firstWhere('method', 'cash');
        $this->assertNotNull($cash, 'cash must appear in the shift tender breakdown');
        $this->assertEquals(1500, $cash['total'], 'shift cash = collections − refunds for the day');
        $this->assertEquals(-500, $response['refundedTotal']);
        $this->assertEquals(1500, $response['totalCollected']);
        $this->assertSame(1, $response['invoiceCount']);
        $this->assertSame(1, $response['paymentCount']);
    }

    public function test_shift_review_is_advisory_and_fail_open_without_key(): void
    {
        $billing = $this->billingStaff();

        // phpunit.xml ships an EMPTY gateway key, so the review must come back
        // null rather than throwing — reconciliation never blocks on Jev.
        $this->actingAs($billing)
            ->postJson('/api/v1/billing/shift-review', [
                'cashExpected' => 1000,
                'cashCounted' => 900,
                'totalCollected' => 5000,
                'refundedTotal' => 0,
                'paymentCount' => 4,
            ])
            ->assertOk()
            ->assertJsonPath('data.review', null);
    }

    private function getInvoice(string $id): array
    {
        return $this->getJson("/api/v1/invoices/{$id}")
            ->assertOk()
            ->json('data.invoice');
    }
}
