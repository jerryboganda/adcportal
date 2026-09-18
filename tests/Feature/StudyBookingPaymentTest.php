<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PaymentMethod;
use App\Models\Service;
use App\Models\User;
use App\Services\TenantAuthorizer;

/**
 * Booking-time financials: payment status/method/amount validation and the
 * booking cash discount — all settled server-side against the auto-issued
 * booking invoice, with the tenant's OWN configured payment methods.
 */
class StudyBookingPaymentTest extends ApiTestCase
{
    private function ctService(): Service
    {
        return Service::where('code', 'CT-BRAIN-NC')->where('business_id', $this->businessA->id)->firstOrFail();
    }

    private function receptionist(): User
    {
        return $this->makeStaff($this->businessA, $this->adminA, 'receptionist');
    }

    private function cashMethod(int $businessId): PaymentMethod
    {
        return PaymentMethod::forClinic($businessId)->where('code', 'cash')->firstOrFail();
    }

    private function book(User $actor, Service $svc, array $payment = [], ?float $discount = null)
    {
        $payload = [
            'newPatient' => ['name' => 'Financial Walk-in', 'gender' => 'male'],
            'serviceId' => $svc->id,
            'date' => now()->toDateString(),
            'time' => '10:30 AM',
            'priority' => 'routine',
        ];
        if ($payment !== []) {
            $payload['payment'] = $payment;
        }
        if ($discount !== null) {
            $payload['discount'] = ['amount' => $discount];
        }

        return $this->actingAs($actor)->postJson('/api/v1/studies', $payload);
    }

    public function test_unpaid_booking_issues_invoice_without_payments(): void
    {
        $res = $this->book($this->receptionist(), $this->ctService())->assertCreated();

        $invoice = $res->json('data.invoice');
        $this->assertSame('issued', $invoice['status']);
        $this->assertEquals(0, $invoice['paidTotal']);
        $this->assertEquals($invoice['total'], $invoice['balanceDue']);
        $this->assertSame([], $invoice['payments']);
    }

    public function test_paid_booking_records_payment_and_closes_invoice(): void
    {
        $svc = $this->ctService();
        $method = $this->cashMethod($this->businessA->id);

        $res = $this->book($this->receptionist(), $svc, [
            'status' => 'paid',
            'amountPaid' => (float) $svc->price,
            'method' => (string) $method->id,
            'reference' => 'RCP-E2E-1',
        ])->assertCreated();

        $invoice = $res->json('data.invoice');
        $this->assertSame('paid', $invoice['status']);
        $this->assertEquals((float) $svc->price, $invoice['paidTotal']);
        $this->assertEquals(0, $invoice['balanceDue']);
        $this->assertCount(1, $invoice['payments']);
        $this->assertSame('cash', $invoice['payments'][0]['method']);

        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoice['id'],
            'method' => 'cash',
            'reference' => 'RCP-E2E-1',
            'business_id' => $this->businessA->id,
        ]);
    }

    public function test_partial_payment_sets_partial_status_and_balance(): void
    {
        $svc = $this->ctService();
        $method = $this->cashMethod($this->businessA->id);

        $invoice = $this->book($this->receptionist(), $svc, [
            'status' => 'partial',
            'amountPaid' => 3000,
            'method' => (string) $method->id,
        ])->assertCreated()->json('data.invoice');

        $this->assertSame('partial', $invoice['status']);
        $this->assertEquals(3000.0, $invoice['paidTotal']);
        $this->assertEquals((float) $svc->price - 3000, $invoice['balanceDue']);
    }

    public function test_booking_discount_reduces_payable_server_side(): void
    {
        $svc = $this->ctService();
        $method = $this->cashMethod($this->businessA->id);

        // Discounting needs `invoice edit` — an admin-level approval (the
        // receptionist variant is covered by the 403 test below).
        $invoice = $this->book($this->adminA, $svc, [
            'status' => 'paid',
            'amountPaid' => (float) $svc->price - 500,
            'method' => (string) $method->id,
        ], 500)->assertCreated()->json('data.invoice');

        $this->assertEquals(500.0, $invoice['manualDiscount']);
        $this->assertEquals((float) $svc->price - 500, $invoice['total']);
        $this->assertSame('paid', $invoice['status']);
    }

    public function test_discount_requires_invoice_edit_permission(): void
    {
        // Receptionists hold invoice create + payment but NOT invoice edit.
        $before = Invoice::count();
        $this->book($this->receptionist(), $this->ctService(), [], 100)->assertForbidden();
        $this->assertSame($before, Invoice::count(), 'a denied booking must not leave an invoice behind');
    }

    public function test_payment_requires_invoice_payment_permission(): void
    {
        // Custom role holding ONLY `appointment create`: booking is allowed,
        // money capture inside the booking is not.
        $role = \App\Models\Role::create([
            'name' => 'front-desk-booker',
            'guard_name' => 'web',
            'module' => 'Base',
            'created_by' => $this->adminA->id,
        ]);
        $role->givePermission(\App\Models\Permission::where('name', 'appointment create')->first());
        $user = User::create([
            'name' => 'Booker Only',
            'email' => 'booker.'.uniqid('', true).'@test.local',
            'password' => 'R1s!T3st#2026x',
            'email_verified_at' => now(),
            'type' => 'staff',
            'active_status' => 1,
            'business_id' => $this->businessA->id,
            'created_by' => $this->businessA->id,
        ]);
        $user->addRole($role);
        \App\Models\TenantMembership::create([
            'user_id' => $user->id,
            'business_id' => $this->businessA->id,
            'role' => 'receptionist',
            'is_default' => false,
            'status' => 'active',
        ]);
        TenantAuthorizer::flushAll();

        // Plain booking passes…
        $this->book($user, $this->ctService())->assertCreated();

        // …but capturing money needs `invoice payment` → 403.
        $method = $this->cashMethod($this->businessA->id);
        $this->book($user, $this->ctService(), [
            'status' => 'paid',
            'amountPaid' => (float) $this->ctService()->price,
            'method' => (string) $method->id,
        ])->assertForbidden();
    }

    public function test_unpaid_booking_cannot_record_an_amount(): void
    {
        $this->book($this->receptionist(), $this->ctService(), [
            'status' => 'unpaid',
            'amountPaid' => 100,
        ])->assertStatus(422);
    }

    public function test_partial_amount_must_be_below_payable(): void
    {
        $svc = $this->ctService();
        $method = $this->cashMethod($this->businessA->id);

        $this->book($this->receptionist(), $svc, [
            'status' => 'partial',
            'amountPaid' => (float) $svc->price,
            'method' => (string) $method->id,
        ])->assertStatus(422);
    }

    public function test_paid_amount_must_match_payable(): void
    {
        $svc = $this->ctService();
        $method = $this->cashMethod($this->businessA->id);

        $this->book($this->receptionist(), $svc, [
            'status' => 'paid',
            'amountPaid' => (float) $svc->price - 1000,
            'method' => (string) $method->id,
        ])->assertStatus(422);

        $this->book($this->receptionist(), $svc, [
            'status' => 'paid',
            'amountPaid' => (float) $svc->price + 500,
            'method' => (string) $method->id,
        ])->assertStatus(422);
    }

    public function test_negative_amount_is_rejected(): void
    {
        $method = $this->cashMethod($this->businessA->id);

        $this->book($this->receptionist(), $this->ctService(), [
            'status' => 'partial',
            'amountPaid' => -5,
            'method' => (string) $method->id,
        ])->assertStatus(422);
    }

    public function test_paid_without_method_is_rejected(): void
    {
        $this->book($this->receptionist(), $this->ctService(), [
            'status' => 'paid',
            'amountPaid' => (float) $this->ctService()->price,
        ])->assertStatus(422);
    }

    public function test_inactive_method_is_rejected(): void
    {
        $svc = $this->ctService();
        $method = $this->cashMethod($this->businessA->id);
        $method->update(['is_active' => false]);

        $this->book($this->receptionist(), $svc, [
            'status' => 'paid',
            'amountPaid' => (float) $svc->price,
            'method' => (string) $method->id,
        ])->assertStatus(422);
    }

    public function test_other_tenants_method_is_rejected(): void
    {
        $svc = $this->ctService();
        $foreign = $this->cashMethod($this->businessB->id);

        $this->assertNotEquals($this->businessA->id, $foreign->business_id);

        $this->book($this->receptionist(), $svc, [
            'status' => 'paid',
            'amountPaid' => (float) $svc->price,
            'method' => (string) $foreign->id,
        ])->assertNotFound();
    }

    public function test_booking_payment_writes_audit_trail(): void
    {
        $svc = $this->ctService();
        $method = $this->cashMethod($this->businessA->id);

        $res = $this->book($this->receptionist(), $svc, [
            'status' => 'paid',
            'amountPaid' => (float) $svc->price,
            'method' => (string) $method->id,
        ])->assertCreated();

        $invoiceId = $res->json('data.invoice.id');

        $this->assertTrue(
            AuditLog::where('business_id', $this->businessA->id)->where('action', 'appointment_created')->exists()
        );
        $this->assertTrue(
            AuditLog::where('business_id', $this->businessA->id)->where('action', 'payment_recorded')->exists()
        );
        $this->assertTrue(InvoicePayment::where('invoice_id', $invoiceId)->exists());
    }
}
