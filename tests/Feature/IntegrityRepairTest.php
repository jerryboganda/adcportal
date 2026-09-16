<?php

namespace Tests\Feature;

use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\InventoryTransaction;
use App\Models\Modality;
use App\Models\ScreeningQuestion;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Regression coverage for the frontend↔backend integrity audit: every gap
 * repaired in this change contract has a test here so the exact defect cannot
 * silently return (master-prompt §26).
 */
class IntegrityRepairTest extends ApiTestCase
{
    private function service(string $code, $business = null)
    {
        return Service::where('code', $code)
            ->where('business_id', ($business ?? $this->businessA)->id)
            ->firstOrFail();
    }

    /** Book a study in tenant A (or B) and return the API study payload. */
    private function book(array $overrides = [], $business = null, $owner = null): array
    {
        $business = $business ?? $this->businessA;
        $owner = $owner ?? $this->adminA;
        $receptionist = $this->makeStaff($business, $owner, 'receptionist');

        $payload = array_merge([
            'newPatient' => [
                'name' => 'Integrity Patient',
                'phone' => '+44 7843 985126',
                'email' => 'integrity.'.uniqid().'@example.com',
                'age' => 30,
                'gender' => 'male',
            ],
            'serviceId' => $this->service('DX-CHEST-PA', $business)->id,
            'date' => now()->toDateString(),
            'time' => '9:30 AM',
            'priority' => 'routine',
        ], $overrides);

        return $this->actingAs($receptionist)
            ->postJson('/api/v1/studies', $payload)
            ->assertCreated()
            ->json('data.study');
    }

    /** Drive a study to the `acquired` state through the real pipeline. */
    private function acquire(array $study): void
    {
        $tech = $this->makeStaff($this->businessA, $this->adminA, 'technologist');
        $id = $study['id'];

        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/transition", ['action' => 'checkin'])->assertOk();
        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/transition", ['action' => 'prepare'])->assertOk();
        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/transition", ['action' => 'start'])->assertOk();
        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/transition", [
            'action' => 'complete',
            'dose' => ['doseValue' => 0.15, 'doseUnit' => 'dGy*cm² (DAP)'],
        ])->assertOk();
    }

    // ==================== billing: cash discount ====================

    public function test_cash_discount_travels_through_the_contract_into_totals(): void
    {
        $study = $this->book();
        $service = $this->service('DX-CHEST-PA');
        $billing = $this->makeStaff($this->businessA, $this->adminA, 'billing');

        $response = $this->actingAs($billing)
            ->postJson("/api/v1/studies/{$study['id']}/invoices", [
                'items' => [['description' => $service->name, 'quantity' => 1, 'unitPrice' => $service->price]],
                'discountAmount' => 500,
            ])->assertStatus(201);

        $invoice = $response->json('data.invoice');
        $this->assertEquals(500.0, $invoice['manualDiscount']);
        $this->assertEquals(500.0, $invoice['discountTotal']);
        $this->assertEquals(max(0, $service->price - 500), $invoice['total']);
    }

    public function test_oversized_discount_never_drives_totals_negative(): void
    {
        $study = $this->book();
        $service = $this->service('DX-CHEST-PA');
        $billing = $this->makeStaff($this->businessA, $this->adminA, 'billing');

        $invoice = $this->actingAs($billing)
            ->postJson("/api/v1/studies/{$study['id']}/invoices", [
                'items' => [['description' => $service->name, 'quantity' => 1, 'unitPrice' => $service->price]],
                'discountAmount' => $service->price * 10,
            ])->assertStatus(201)
            ->json('data.invoice');

        $this->assertGreaterThanOrEqual(0, $invoice['total']);
        $this->assertEquals((float) $service->price, $invoice['discountTotal']);
    }

    // ==================== reports: draft dedup + versioning ====================

    public function test_second_draft_is_rejected_update_edits_and_addendum_versions(): void
    {
        $study = $this->book();
        $this->acquire($study);
        $id = $study['id'];
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        // First save: draft v1 (moves the study acquired → reading).
        $first = $this->actingAs($radiologist)->postJson("/api/v1/studies/{$id}/reports", [
            'findings' => 'Initial findings.',
            'impression' => 'Initial impression.',
        ])->assertOk();
        $this->assertSame(1, $first->json('data.study.report.version'));
        $reportId = $first->json('data.study.report.id');

        // Editing the draft must not pile up a second version row.
        $this->actingAs($radiologist)
            ->postJson("/api/v1/studies/{$id}/reports", [
                'findings' => 'More findings.',
                'impression' => 'More impression.',
            ])->assertStatus(422);

        $this->actingAs($radiologist)
            ->putJson("/api/v1/reports/{$reportId}", [
                'findings' => 'Revised findings.',
                'impression' => 'Revised impression.',
            ])->assertOk();

        $draft = \App\Models\RadiologyReport::find($reportId);
        $this->assertSame('Revised findings.', $draft->findings);
        $this->assertSame(1, (int) $draft->version);
        $this->assertSame(1, \App\Models\RadiologyReport::where('appointment_id', $id)->count());

        // Sign: signing from the update path locks the report.
        $this->actingAs($radiologist)
            ->putJson("/api/v1/reports/{$reportId}", [
                'findings' => 'Revised findings.',
                'impression' => 'Revised impression.',
                'signNow' => true,
            ])->assertOk();

        // A signed report is immutable — a new save is an ADDENDUM version.
        $addendum = $this->actingAs($radiologist)
            ->postJson("/api/v1/studies/{$id}/reports", [
                'findings' => '[ADDENDUM] New info.',
                'impression' => 'See addendum; prior impression unchanged.',
            ])->assertOk();

        $report = $addendum->json('data.study.report');
        $this->assertSame(2, $report['version']);
        $this->assertSame('addendum', $report['type']);
        $this->assertSame(2, \App\Models\RadiologyReport::where('appointment_id', $id)->count());
    }

    // ==================== pipeline: send to reading + queue calls ====================

    public function test_send_to_reading_is_a_real_guarded_transition(): void
    {
        $study = $this->book();
        $this->acquire($study);
        $id = $study['id'];

        // Receptionists cannot push studies to reading.
        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'receptionist'))
            ->postJson("/api/v1/studies/{$id}/transition", ['action' => 'send_to_reading'])
            ->assertForbidden();

        $response = $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'technologist'))
            ->postJson("/api/v1/studies/{$id}/transition", ['action' => 'send_to_reading'])
            ->assertOk();

        $this->assertSame('reading', $response->json('data.study.workflowState'));
        $this->assertNotNull($response->json('data.study.readingAt'));
        $this->assertNotNull(Appointment::find($id)->reading_at);
    }

    public function test_queue_call_persists_and_audits(): void
    {
        $study = $this->book();

        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'receptionist'))
            ->postJson("/api/v1/studies/{$study['id']}/transition", ['action' => 'call'])
            ->assertOk()
            ->assertJsonPath('data.study.calledAt', fn ($v) => $v !== null);

        $this->assertNotNull(Appointment::find($study['id'])->called_at);
        $this->assertTrue(
            AuditLog::where('action', 'queue_patient_called')
                ->where('subject_id', (int) $study['id'])
                ->where('business_id', $this->businessA->id)
                ->exists()
        );
    }

    // ==================== auth: patient accounts are login-less ====================

    public function test_customer_accounts_cannot_sign_in_to_the_staff_portal(): void
    {
        $email = 'portal.'.uniqid().'@example.com';
        User::create([
            'name' => 'Legacy Patient',
            'email' => $email,
            'password' => 'R1s!T3st#2026x',
            'type' => 'customer',
            'active_status' => 1,
            'is_enable_login' => 1,
            'business_id' => $this->businessA->id,
            'created_by' => $this->businessA->id,
        ]);

        $this->postJson('/api/v1/login', ['email' => $email, 'password' => 'R1s!T3st#2026x'])
            ->assertStatus(403);
    }

    // ==================== tenant scoping ====================

    public function test_screening_answers_with_foreign_question_ids_are_rejected(): void
    {
        $study = $this->book();

        $foreignQuestion = ScreeningQuestion::whereHas('form', fn ($q) => $q->where('business_id', $this->businessB->id))
            ->first();
        $this->assertNotNull($foreignQuestion, 'TenantBootstrap must seed screening questions for tenant B');

        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'technologist'))
            ->postJson("/api/v1/studies/{$study['id']}/screening", [
                'answers' => [
                    ['questionId' => $foreignQuestion->id, 'answerValue' => 'no'],
                ],
            ])->assertStatus(422);
    }

    public function test_services_cannot_reference_another_tenants_modality(): void
    {
        $betaModality = Modality::where('business_id', $this->businessB->id)->first();
        $this->assertNotNull($betaModality);

        $this->actingAs($this->adminA)
            ->postJson('/api/v1/services', [
                'name' => 'Cross-Tenant Probe',
                'code' => 'XT-1',
                'modalityId' => $betaModality->id,
                'price' => 100,
            ])->assertStatus(422);
    }

    public function test_services_with_studies_are_delete_guarded(): void
    {
        $study = $this->book();
        $usedService = $this->service('DX-CHEST-PA');

        // Referenced by a booked study → refused (the FK would cascade history).
        $this->actingAs($this->adminA)
            ->deleteJson("/api/v1/services/{$usedService->id}")
            ->assertStatus(422);

        // An unreferenced service deletes cleanly.
        $free = Service::where('business_id', $this->businessA->id)
            ->whereNotIn('id', [$usedService->id])
            ->whereDoesntHave('appointments')
            ->first();
        if ($free) {
            $this->actingAs($this->adminA)
                ->deleteJson("/api/v1/services/{$free->id}")
                ->assertOk();
        }
    }

    public function test_adverse_reactions_reject_foreign_appointments(): void
    {
        $foreignStudy = $this->book([], $this->businessB, $this->adminB);

        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'technologist'))
            ->postJson('/api/v1/inventory/adverse-reactions', [
                'appointmentId' => (int) $foreignStudy['id'],
                'tokenNumber' => 'XT-9',
                'patientName' => 'Cross Tenant',
                'modality' => 'CT',
                'contrastAgent' => 'Iohexol',
                'severity' => 'mild',
                'symptoms' => ['itching'],
                'treatmentGiven' => 'Observation',
                'outcome' => 'resolved_on_site',
            ])->assertStatus(404);
    }

    // ==================== inventory ====================

    public function test_sku_codes_are_unique_per_tenant_and_opening_stock_hits_the_ledger(): void
    {
        $admin = $this->makeStaff($this->businessA, $this->adminA, 'admin');

        $payload = [
            'code' => 'CT-CONTRAST-1',
            'name' => 'Iohexol 350 (Audit SKU)',
            'category' => 'contrast_ct',
            'unit' => 'vial',
            'batches' => [
                ['batchNumber' => 'BATCH-A1', 'expiryDate' => now()->addYear()->toDateString(), 'quantity' => 5],
                ['batchNumber' => 'BATCH-A2', 'expiryDate' => now()->addYear()->toDateString(), 'quantity' => 3],
            ],
        ];

        $response = $this->actingAs($admin)->postJson('/api/v1/inventory/items', $payload)->assertCreated();
        $this->assertEquals(8, $response->json('data.item.currentStock'));
        $this->assertCount(2, $response->json('data.openingTransactions'));

        $this->assertSame(
            8,
            (int) InventoryTransaction::where('business_id', $this->businessA->id)
                ->where('item_name', 'Iohexol 350 (Audit SKU)')
                ->where('type', 'stock_in')
                ->sum('quantity')
        );

        // The SAME code is legal in another tenant (per-tenant uniqueness).
        $betaAdmin = $this->makeStaff($this->businessB, $this->adminB, 'admin');
        $this->actingAs($betaAdmin)
            ->postJson('/api/v1/inventory/items', $payload)
            ->assertCreated();
    }

    // ==================== appointment reminders ====================

    public function test_appointment_reminders_are_opt_in_idempotent_and_honest(): void
    {
        Mail::fake();

        $study = $this->book([
            'date' => now()->addDay()->toDateString(),
            'time' => '10:00 AM',
        ]); // newPatient carries a real address

        // Tenant B also has an upcoming study but reminders stay OFF there.
        $betaStudy = $this->book([
            'date' => now()->addDay()->toDateString(),
            'time' => '10:00 AM',
        ], $this->businessB, $this->adminB);

        Setting::updateOrCreate(
            ['key' => 'ris_clinic_profile', 'business' => $this->businessA->id],
            [
                'created_by' => $this->adminA->id,
                'value' => json_encode([
                    'name' => $this->businessA->name,
                    'sendAppointmentReminders' => true,
                    'reminderHours' => 48,
                ]),
            ]
        );

        $this->artisan('app:appointment-reminder')->assertSuccessful();

        Mail::assertSent(AppointmentReminderMail::class, 1);
        $this->assertNotNull(Appointment::find($study['id'])->reminder_sent_at);
        $this->assertNull(Appointment::find($betaStudy['id'])->reminder_sent_at);
        $this->assertTrue(
            AuditLog::where('action', 'appointment_reminder_sent')
                ->where('business_id', $this->businessA->id)
                ->exists()
        );

        // Idempotent: the second sweep must not re-send.
        $this->artisan('app:appointment-reminder')->assertSuccessful();
        Mail::assertSent(AppointmentReminderMail::class, 1);
    }

    public function test_walk_in_placeholder_addresses_are_never_mailed_but_stamped(): void
    {
        Mail::fake();

        // Booking without an email creates a walk-in user with a @patients.local address.
        $receptionist = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');
        $study = $this->actingAs($receptionist)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Walk-in Patient', 'phone' => '+44 7843 985126', 'age' => 40, 'gender' => 'female'],
            'serviceId' => $this->service('DX-CHEST-PA')->id,
            'date' => now()->addDay()->toDateString(),
            'time' => '11:00 AM',
            'priority' => 'routine',
        ])->assertCreated()->json('data.study');

        $customer = Customer::where('business_id', $this->businessA->id)->where('name', 'Walk-in Patient')->first();
        $this->assertNotNull($customer);
        $this->assertStringEndsWith('@patients.local', (string) $customer->customer->email);

        Setting::updateOrCreate(
            ['key' => 'ris_clinic_profile', 'business' => $this->businessA->id],
            [
                'created_by' => $this->adminA->id,
                'value' => json_encode(['sendAppointmentReminders' => true, 'reminderHours' => 48]),
            ]
        );

        $this->artisan('app:appointment-reminder')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertNotNull(Appointment::find($study['id'])->reminder_sent_at);
    }
}
