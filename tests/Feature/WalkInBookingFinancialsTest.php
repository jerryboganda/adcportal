<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\TenantAuthorizer;
use App\Support\BookingMoney;

/**
 * Walk-in booking money, exercised through the real API.
 *
 * The tenant's configured procedure price is the only price that counts, the
 * discount is applied and re-derived server-side, a 100% discount is a valid
 * settled booking (never an outstanding debt, never a server error), and an
 * over-discount is refused instead of silently clamped.
 */
class WalkInBookingFinancialsTest extends ApiTestCase
{
    /** The seeded CT Brain Non-Contrast price — the spec's example price. */
    private const PRICE = 6500.0;
    private const DISCOUNT = 3500.0;
    private const PAYABLE = 3000.0;

    private function ctService(int $businessId = 0): Service
    {
        return Service::where('code', 'CT-BRAIN-NC')
            ->where('business_id', $businessId ?: $this->businessA->id)
            ->firstOrFail();
    }

    private function cashMethod(int $businessId = 0): PaymentMethod
    {
        return PaymentMethod::forClinic($businessId ?: $this->businessA->id)->where('code', 'cash')->firstOrFail();
    }

    /** Books studies and collects money, but cannot discount. */
    private function receptionist(): User
    {
        return $this->makeStaff($this->businessA, $this->adminA, 'receptionist');
    }

    /**
     * The realistic discounting actor: a front-desk supervisor holding booking
     * AND billing rights. A plain receptionist can book and collect, but the
     * existing policy reserves discounts behind `invoice edit`
     * (StudyBookingPaymentTest::test_discount_requires_invoice_edit_permission),
     * which is why the SPA hides the discount field from them.
     */
    private function discountActor(): User
    {
        return $this->userWithPermissions('front-desk-supervisor', [
            'appointment create', 'invoice edit', 'invoice payment',
        ]);
    }

    /** @param list<string> $permissions */
    private function userWithPermissions(string $roleName, array $permissions): User
    {
        $role = Role::create([
            'name' => $roleName,
            'guard_name' => 'web',
            'module' => 'Base',
            'created_by' => $this->adminA->id,
        ]);

        foreach ($permissions as $permission) {
            $role->givePermission(Permission::where('name', $permission)->first());
        }

        $user = User::create([
            'name' => ucfirst(str_replace('-', ' ', $roleName)).' '.uniqid(),
            'email' => 'actor.'.uniqid('', true).'@test.local',
            'password' => 'R1s!T3st#2026x',
            'email_verified_at' => now(),
            'type' => 'staff',
            'active_status' => 1,
            'business_id' => $this->businessA->id,
            'created_by' => $this->businessA->id,
        ]);
        $user->addRole($role);
        TenantMembership::create([
            'user_id' => $user->id,
            'business_id' => $this->businessA->id,
            'role' => 'receptionist',
            'is_default' => false,
            'status' => 'active',
        ]);
        TenantAuthorizer::flushAll();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function book(User $actor, array $payload)
    {
        return $this->actingAs($actor)->postJson('/api/v1/studies', $payload + [
            'newPatient' => ['name' => 'Financial Walk-in '.uniqid(), 'gender' => 'male'],
            'date' => now()->toDateString(),
            'time' => '10:30 AM',
            'priority' => 'routine',
        ]);
    }

    /** @return array<string, mixed> */
    private function bookingPayload(Service $service, ?float $discount = null, ?array $payment = null): array
    {
        $payload = ['serviceId' => $service->id];

        if ($discount !== null) {
            $payload['discount'] = ['amount' => $discount];
        }
        if ($payment !== null) {
            $payload['payment'] = $payment;
        }

        return $payload;
    }

    public function test_seeded_ct_procedure_is_priced_at_the_spec_amount(): void
    {
        // Anchor: every example below is the documented 6500/3500/3000 case.
        $this->assertSame(self::PRICE, (float) $this->ctService()->price);
    }

    // ==================== TC-01: no discount, full payment ====================

    public function test_full_payment_without_discount_books_the_full_price(): void
    {
        $res = $this->book($this->receptionist(), $this->bookingPayload($this->ctService(), null, [
            'status' => 'paid',
            'amountPaid' => self::PRICE,
            'method' => $this->cashMethod()->id,
        ]))->assertCreated();

        $invoice = $res->json('data.invoice');

        $this->assertEquals(self::PRICE, $invoice['subtotal']);
        $this->assertEquals(0, $invoice['discountTotal']);
        $this->assertEquals(self::PRICE, $invoice['total']);
        $this->assertEquals(self::PRICE, $invoice['paidTotal']);
        $this->assertEquals(0, $invoice['balanceDue']);
        $this->assertSame('paid', $invoice['status']);

        // Token generated server-side, in the response.
        $this->assertSame('1', $res->json('data.study.tokenNumber'));
    }

    // ==================== TC-02: partial discount ====================

    public function test_partial_discount_recomputes_payable_server_side_and_is_paid_in_full(): void
    {
        $res = $this->book($this->discountActor(), $this->bookingPayload($this->ctService(), self::DISCOUNT, [
            'status' => 'paid',
            // The receptionist collects exactly what remains — 3000.
            'amountPaid' => self::PAYABLE,
            'method' => $this->cashMethod()->id,
        ]))->assertCreated();

        $invoice = $res->json('data.invoice');

        $this->assertEquals(self::DISCOUNT, $invoice['manualDiscount']);
        $this->assertEquals(self::DISCOUNT, $invoice['discountTotal']);
        $this->assertEquals(self::PAYABLE, $invoice['total'], '6500 − 3500 must be 3000');
        $this->assertEquals(self::PAYABLE, $invoice['paidTotal']);
        $this->assertEquals(0, $invoice['balanceDue']);
        $this->assertSame('paid', $invoice['status']);

        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoice['id'],
            'method' => 'cash',
            'business_id' => $this->businessA->id,
        ]);
        $this->assertEquals(self::PAYABLE, (float) InvoicePayment::where('invoice_id', $invoice['id'])->sum('amount'));
    }

    public function test_partial_discount_without_collection_leaves_the_net_balance_outstanding(): void
    {
        $invoice = $this->book($this->discountActor(), $this->bookingPayload($this->ctService(), self::DISCOUNT))
            ->assertCreated()
            ->json('data.invoice');

        $this->assertEquals(self::PAYABLE, $invoice['total']);
        $this->assertEquals(self::PAYABLE, $invoice['balanceDue']);
        $this->assertSame('issued', $invoice['status']);
    }

    // ==================== TC-03: 100% discount ====================

    public function test_full_discount_books_a_zero_payable_study_and_still_generates_a_token(): void
    {
        $res = $this->book($this->discountActor(), $this->bookingPayload($this->ctService(), self::PRICE))
            ->assertCreated();

        $invoice = $res->json('data.invoice');

        $this->assertEquals(self::PRICE, $invoice['subtotal']);
        $this->assertEquals(self::PRICE, $invoice['manualDiscount']);
        $this->assertEquals(0, $invoice['total'], '6500 − 6500 must be 0');
        $this->assertEquals(0, $invoice['paidTotal']);
        $this->assertEquals(0, $invoice['balanceDue']);

        // Zero payable is SETTLED, not an outstanding debt…
        $this->assertSame('paid', $invoice['status']);
        // …and no money was recorded, because none was collected.
        $this->assertSame([], $invoice['payments']);

        // The booking itself is complete: token minted and persisted.
        $this->assertSame('1', $res->json('data.study.tokenNumber'));
        $this->assertDatabaseHas('appointments', [
            'id' => $res->json('data.study.id'),
            'business_id' => $this->businessA->id,
            'token_number' => 1,
        ]);
    }

    public function test_full_discount_accepts_an_explicit_zero_payment_with_no_method(): void
    {
        $invoice = $this->book($this->discountActor(), $this->bookingPayload($this->ctService(), self::PRICE, [
            'status' => 'paid',
            'amountPaid' => 0,
        ]))->assertCreated()->json('data.invoice');

        $this->assertEquals(0, $invoice['total']);
        $this->assertSame('paid', $invoice['status']);
        $this->assertSame([], $invoice['payments']);
    }

    public function test_full_discount_refuses_to_collect_money(): void
    {
        $actor = $this->discountActor();

        // A zero-payable invoice has nothing to collect: taking cash against it
        // would create revenue that no one owes.
        $refused = $this->book($actor, $this->bookingPayload($this->ctService(), self::PRICE, [
            'status' => 'paid',
            'amountPaid' => 100,
            'method' => $this->cashMethod()->id,
        ]))->assertStatus(422);

        // Laravel's error bag is flat: dotted rule names stay dotted keys.
        $this->assertSame(
            BookingMoney::ERROR_NOTHING_TO_COLLECT,
            $refused->json('errors')['payment.amountPaid'][0],
        );

        $this->book($actor, $this->bookingPayload($this->ctService(), self::PRICE, [
            'status' => 'partial',
            'amountPaid' => 0,
        ]))->assertStatus(422);
    }

    public function test_full_discount_by_a_discount_only_user_does_not_need_payment_rights(): void
    {
        // `invoice edit` (discount) without `invoice payment` (collect): a
        // waived study must not be blocked behind a permission for money that
        // is never collected.
        $discounter = $this->userWithPermissions('front-desk-discounter', [
            'appointment create', 'invoice edit',
        ]);

        $this->book($discounter, $this->bookingPayload($this->ctService(), self::PRICE))
            ->assertCreated()
            ->assertJsonPath('data.invoice.total', 0);
    }

    // ==================== TC-04 / TC-05: invalid discounts ====================

    public function test_discount_above_the_price_is_rejected_and_creates_nothing(): void
    {
        $actor = $this->discountActor();
        $before = [
            'appointments' => Appointment::count(),
            'invoices' => Invoice::count(),
            'users' => User::count(),
        ];

        $res = $this->book($actor, $this->bookingPayload($this->ctService(), self::PRICE + 1.0))
            ->assertStatus(422);

        $this->assertStringContainsString(
            'Discount cannot exceed the study price',
            $res->json('errors')['discount.amount'][0],
        );

        // No booking, no invoice, no half-created patient, no token.
        $this->assertSame($before['appointments'], Appointment::count());
        $this->assertSame($before['invoices'], Invoice::count());
        $this->assertSame($before['users'], User::count());
        $this->assertSame(0, Appointment::whereNotNull('token_number')->count());
    }

    public function test_negative_discount_is_rejected(): void
    {
        $this->book($this->discountActor(), $this->bookingPayload($this->ctService(), -500))
            ->assertStatus(422);
    }

    // ==================== TC-06: client-supplied totals are ignored ====================

    public function test_client_supplied_totals_are_ignored_and_recomputed(): void
    {
        $payload = $this->bookingPayload($this->ctService(), self::DISCOUNT, [
            'status' => 'paid',
            'amountPaid' => self::PAYABLE,
            'method' => $this->cashMethod()->id,
        ]);

        // A tampered client also posts its own price/payable — none of it is
        // part of the contract, and none of it reaches the invoice.
        $payload['price'] = 6500;
        $payload['netPayable'] = 500;
        $payload['amountPayable'] = 500;

        $invoice = $this->book($this->discountActor(), $payload)
            ->assertCreated()
            ->json('data.invoice');

        $this->assertEquals(self::PAYABLE, $invoice['total'], 'the server derives 6500 − 3500 = 3000');
        $this->assertEquals(self::PAYABLE, $invoice['paidTotal']);
    }

    public function test_a_collection_that_disagrees_with_the_server_payable_is_rejected(): void
    {
        // The client believes 500 settles the study; the server's own math says
        // 3000, and refuses.
        $this->book($this->discountActor(), $this->bookingPayload($this->ctService(), self::DISCOUNT, [
            'status' => 'paid',
            'amountPaid' => 500,
            'method' => $this->cashMethod()->id,
        ]))->assertStatus(422);
    }

    public function test_collecting_more_than_the_final_payable_is_rejected(): void
    {
        $res = $this->book($this->discountActor(), $this->bookingPayload($this->ctService(), self::DISCOUNT, [
            'status' => 'paid',
            'amountPaid' => 4000,
            'method' => $this->cashMethod()->id,
        ]))->assertStatus(422);

        $this->assertStringContainsString(
            BookingMoney::ERROR_AMOUNT_EXCEEDS_PAYABLE,
            $res->json('errors')['payment.amountPaid'][0],
        );
    }

    // ==================== TC-07 / TC-08: changing procedure & discount ====================

    public function test_switching_procedure_recomputes_from_that_procedures_price(): void
    {
        $actor = $this->discountActor();
        $mri = Service::where('code', 'MR-LUMBAR')->where('business_id', $this->businessA->id)->firstOrFail();
        $mri->update(['price' => 8000]);

        // Same 3500 discount, different procedure: 8000 − 3500 = 4500.
        $invoice = $this->book($actor, $this->bookingPayload($mri, self::DISCOUNT, [
            'status' => 'paid',
            'amountPaid' => 4500,
            'method' => $this->cashMethod()->id,
        ]))->assertCreated()->json('data.invoice');

        $this->assertEquals(8000.0, $invoice['subtotal']);
        $this->assertEquals(4500.0, $invoice['total']);
        $this->assertEquals(0, $invoice['balanceDue']);
    }

    public function test_zero_or_omitted_discount_bills_the_full_price(): void
    {
        $actor = $this->discountActor();

        // The SPA omits the discount key entirely once it is cleared.
        $omitted = $this->book($actor, $this->bookingPayload($this->ctService()))
            ->assertCreated()->json('data.invoice');
        $this->assertEquals(self::PRICE, $omitted['total']);
        $this->assertEquals(0, $omitted['manualDiscount']);

        $explicit = $this->book($actor, $this->bookingPayload($this->ctService(), 0.0))
            ->assertCreated()->json('data.invoice');
        $this->assertEquals(self::PRICE, $explicit['total']);
    }

    // ==================== TC-09 / TC-10: tokens & repeat submissions ====================

    public function test_sequential_bookings_receive_unique_sequential_tokens(): void
    {
        $actor = $this->receptionist();
        $tokens = [];

        foreach (range(1, 5) as $ignored) {
            $tokens[] = (int) $this->book($actor, $this->bookingPayload($this->ctService()))
                ->assertCreated()
                ->json('data.study.tokenNumber');
        }

        $this->assertSame([1, 2, 3, 4, 5], $tokens);
        $this->assertSame(5, Appointment::where('business_id', $this->businessA->id)->count());
    }

    public function test_repeat_submissions_never_share_a_token_or_merge_into_one_booking(): void
    {
        $actor = $this->discountActor();
        $payload = $this->bookingPayload($this->ctService(), self::DISCOUNT, [
            'status' => 'paid',
            'amountPaid' => self::PAYABLE,
            'method' => $this->cashMethod()->id,
        ]) + ['newPatient' => ['name' => 'Repeat Submit', 'gender' => 'male']];

        $first = $this->book($actor, $payload);
        $second = $this->book($actor, $payload);

        $first->assertCreated();
        $second->assertCreated();

        $this->assertNotSame($first->json('data.study.id'), $second->json('data.study.id'));
        $this->assertNotSame($first->json('data.study.tokenNumber'), $second->json('data.study.tokenNumber'));

        // Two complete, independent bookings — never one mutated twice.
        $this->assertSame(2, Invoice::where('business_id', $this->businessA->id)->count());
        $this->assertSame(2, InvoicePayment::where('business_id', $this->businessA->id)->count());
    }

    // ==================== TC-11: tenant isolation ====================

    public function test_another_tenants_procedure_cannot_be_booked(): void
    {
        $foreign = $this->ctService($this->businessB->id);
        $this->assertNotSame($this->businessA->id, $foreign->business_id);

        $this->book($this->receptionist(), $this->bookingPayload($foreign))
            ->assertNotFound();

        $this->assertSame(0, Appointment::count());
        $this->assertSame(0, Invoice::count());
    }

    public function test_another_tenants_payment_method_cannot_be_used(): void
    {
        $this->book($this->receptionist(), $this->bookingPayload($this->ctService(), null, [
            'status' => 'paid',
            'amountPaid' => self::PRICE,
            'method' => $this->cashMethod($this->businessB->id)->id,
        ]))->assertNotFound();

        // The refused transaction left nothing behind.
        $this->assertSame(0, Appointment::count());
    }

    // ==================== TC-12: historical price snapshot ====================

    public function test_a_booking_keeps_the_price_agreed_at_booking_time(): void
    {
        $actor = $this->discountActor();
        $service = $this->ctService();

        $invoice = $this->book($actor, $this->bookingPayload($service, self::DISCOUNT, [
            'status' => 'paid',
            'amountPaid' => self::PAYABLE,
            'method' => $this->cashMethod()->id,
        ]))->assertCreated()->json('data.invoice');

        // The catalog price changes the next day…
        $service->update(['price' => 7500]);

        $stored = Invoice::findOrFail($invoice['id']);

        // …and the historical invoice does not move a single paisa.
        $this->assertEquals(self::PRICE, (float) $stored->subtotal);
        $this->assertEquals(self::DISCOUNT, (float) $stored->manual_discount);
        $this->assertEquals(self::PAYABLE, (float) $stored->total);
        $this->assertEquals(self::PRICE, (float) $stored->items()->first()->unit_price);
        $this->assertSame('paid', $stored->status);

        // New bookings price from the new catalog.
        $fresh = $this->book($actor, $this->bookingPayload($service))
            ->assertCreated()->json('data.invoice');
        $this->assertEquals(7500.0, $fresh['total']);
    }

    // ==================== discount audit trail ====================

    public function test_discount_is_audited_with_price_discount_and_net_payable(): void
    {
        $actor = $this->discountActor();

        $res = $this->book($actor, $this->bookingPayload($this->ctService(), self::DISCOUNT, [
            'status' => 'paid',
            'amountPaid' => self::PAYABLE,
            'method' => $this->cashMethod()->id,
        ]))->assertCreated();

        $entry = AuditLog::where('business_id', $this->businessA->id)
            ->where('action', 'booking_discount_applied')
            ->first();

        $this->assertNotNull($entry, 'a booking discount must leave an audit trail');
        $this->assertSame($actor->id, $entry->user_id);

        $changes = $entry->changes;
        $this->assertEquals(self::PRICE, (float) $changes['originalPrice']);
        $this->assertEquals(self::DISCOUNT, (float) $changes['discountAmount']);
        $this->assertEquals(self::PAYABLE, (float) $changes['netPayable']);
        $this->assertSame($res->json('data.study.id'), (string) $changes['bookingId']);
        $this->assertSame($this->businessA->id, (int) $changes['tenantId']);
    }
}
