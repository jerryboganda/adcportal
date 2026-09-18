<?php

namespace Tests\Feature;

use App\Models\InvoicePayment;
use App\Models\PaymentMethod;
use App\Models\User;

/**
 * Tenant configuration surfaces added by the booking remediation:
 * payment-method catalog management, per-tenant uniqueness, and the
 * tenant-scoped bootstrap payload (rooms + payment methods).
 */
class MastersConfigTest extends ApiTestCase
{
    public function test_legacy_method_codes_are_seeded_for_every_tenant(): void
    {
        foreach (['cash', 'card', 'bank', 'mobile', 'insurance'] as $code) {
            $this->assertTrue(
                PaymentMethod::forClinic($this->businessA->id)->where('code', $code)->exists(),
                "tenant A is missing the seeded [{$code}] method"
            );
        }
    }

    public function test_admin_can_add_edit_and_delete_payment_methods(): void
    {
        $created = $this->actingAs($this->adminA)->postJson('/api/v1/payment-methods', [
            'code' => 'Corporate-Panel',
            'name' => 'Corporate Panel Billing',
            'sortOrder' => 9,
        ])->assertCreated()->json('data.paymentMethod');

        // Code is stored normalized.
        $this->assertSame('corporate-panel', $created['code']);

        $updated = $this->actingAs($this->adminA)->putJson("/api/v1/payment-methods/{$created['id']}", [
            'name' => 'Corporate Panel (Direct)',
            'isActive' => false,
        ])->assertOk()->json('data.paymentMethod');
        $this->assertFalse($updated['isActive']);
        $this->assertSame('Corporate Panel (Direct)', $updated['name']);

        // Unused and unreferenced → deletable.
        $this->actingAs($this->adminA)
            ->deleteJson("/api/v1/payment-methods/{$created['id']}")
            ->assertOk();
        $this->assertTrue(PaymentMethod::withTrashed()->findOrFail($created['id'])->trashed());
    }

    public function test_method_referenced_by_payments_cannot_be_deleted(): void
    {
        $method = PaymentMethod::forClinic($this->businessA->id)->where('code', 'cash')->firstOrFail();

        InvoicePayment::create([
            'invoice_id' => 0, // referential anchor only for the guard test
            'amount' => 10,
            'method' => $method->code,
            'business_id' => $this->businessA->id,
            'paid_at' => now(),
        ]);

        $this->actingAs($this->adminA)
            ->deleteJson("/api/v1/payment-methods/{$method->id}")
            ->assertStatus(422);
    }

    public function test_recreating_a_deleted_code_restores_the_same_row(): void
    {
        $method = PaymentMethod::forClinic($this->businessA->id)->where('code', 'mobile')->firstOrFail();
        $method->delete();
        $this->assertTrue($method->fresh()->trashed());

        $res = $this->actingAs($this->adminA)->postJson('/api/v1/payment-methods', [
            'code' => 'mobile',
            'name' => 'Mobile Wallet',
        ])->assertCreated()->json('data.paymentMethod');

        $this->assertSame($method->id, (int) $res['id'], 'the (business_id, code) unique row must be restored, not duplicated');
    }

    public function test_receptionist_cannot_manage_payment_methods(): void
    {
        $receptionist = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        $this->actingAs($receptionist)->postJson('/api/v1/payment-methods', [
            'code' => 'nope', 'name' => 'Nope',
        ])->assertForbidden();

        $method = PaymentMethod::forClinic($this->businessA->id)->where('code', 'cash')->firstOrFail();
        $this->actingAs($receptionist)
            ->putJson("/api/v1/payment-methods/{$method->id}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_pos_payment_rejects_methods_not_configured_for_the_tenant(): void
    {
        $study = $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'receptionist'))
            ->postJson('/api/v1/studies', [
                'newPatient' => ['name' => 'POS Patient', 'gender' => 'male'],
                'serviceId' => \App\Models\Service::where('code', 'DX-CHEST-PA')->where('business_id', $this->businessA->id)->firstOrFail()->id,
                'date' => now()->toDateString(),
                'time' => '1:00 PM',
                'priority' => 'routine',
            ])->assertCreated()->json('data');

        $invoiceId = $study['invoice']['id'];

        // Tenant B has a method tenant A does not: pay from A with B's code.
        $this->actingAs($this->adminB)->postJson('/api/v1/payment-methods', [
            'code' => 'b-only-wallet',
            'name' => 'Beta Only Wallet',
        ])->assertCreated();

        $this->actingAs($this->adminA)
            ->postJson("/api/v1/invoices/{$invoiceId}/payments", [
                'amount' => 100,
                'method' => 'b-only-wallet',
            ])->assertStatus(422);

        // A's own seeded method still works.
        $this->actingAs($this->adminA)
            ->postJson("/api/v1/invoices/{$invoiceId}/payments", [
                'amount' => 100,
                'method' => 'cash',
            ])->assertOk();
    }

    public function test_bootstrap_scopes_rooms_and_payment_methods_to_the_tenant(): void
    {
        $study = $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'receptionist'))
            ->postJson('/api/v1/studies', [
                'newPatient' => ['name' => 'Bootstrap Scope', 'gender' => 'male'],
                'serviceId' => \App\Models\Service::where('code', 'DX-CHEST-PA')->where('business_id', $this->businessA->id)->firstOrFail()->id,
                'date' => now()->toDateString(),
                'time' => '1:30 PM',
                'priority' => 'routine',
            ])->assertCreated()->json('data.study');

        // (Booking with no rooms configured: room must stay empty, never fake.)
        $this->assertSame('', $study['roomNumber']);

        $room = \App\Models\Room::create([
            'name' => 'Alpha X-Ray Room',
            'modality_id' => \App\Models\Modality::where('code', 'DX')->where('business_id', $this->businessA->id)->firstOrFail()->id,
            'is_active' => true,
            'business_id' => $this->businessA->id,
            'created_by' => $this->adminA->id,
        ]);

        $aRooms = collect($this->actingAs($this->adminA)->getJson('/api/v1/bootstrap')->assertOk()->json('data.rooms'))->pluck('id');
        $this->assertContains((string) $room->id, $aRooms);

        $bRooms = collect($this->actingAs($this->adminB)->getJson('/api/v1/bootstrap')->assertOk()->json('data.rooms'))->pluck('id');
        $this->assertNotContains((string) $room->id, $bRooms);

        $aMethods = collect($this->actingAs($this->adminA)->getJson('/api/v1/bootstrap')->assertOk()->json('data.paymentMethods'))->pluck('code');
        $this->assertContains('cash', $aMethods);
        $this->assertNotContains('b-only-wallet', $aMethods);
    }
}
