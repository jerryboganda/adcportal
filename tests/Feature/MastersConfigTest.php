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
    public function test_bootstrap_preserves_tenant_catalog_configuration(): void
    {
        $service = \App\Models\Service::forClinic($this->businessA->id)
            ->where('code', 'CT-BRAIN-NC')->firstOrFail();
        $service->update([
            'name' => 'Local CT Brain',
            'price' => '7123.45',
            'duration_minutes' => 40,
            'preparation_instructions' => 'Tenant instructions',
        ]);
        $modality = $service->modality;
        $modality->update(['name' => 'Local CT', 'is_active' => false]);

        app(\Database\Seeders\TenantBootstrap::class)->run($this->businessA, $this->adminA);

        $this->assertSame('Local CT Brain', $service->fresh()->name);
        $this->assertEquals('7123.45', $service->fresh()->price);
        $this->assertSame(40, $service->fresh()->duration_minutes);
        $this->assertSame('Tenant instructions', $service->fresh()->preparation_instructions);
        $this->assertSame('Local CT', $modality->fresh()->name);
        $this->assertFalse($modality->fresh()->is_active);
    }

    public function test_bootstrap_does_not_restore_or_duplicate_deleted_catalog_records(): void
    {
        $service = \App\Models\Service::forClinic($this->businessA->id)
            ->where('code', 'CT-BRAIN-NC')->firstOrFail();
        $modality = $service->modality;
        $service->delete();
        $modality->delete();

        app(\Database\Seeders\TenantBootstrap::class)->run($this->businessA, $this->adminA);
        app(\Database\Seeders\TenantBootstrap::class)->run($this->businessA, $this->adminA);

        $this->assertTrue($service->fresh()->trashed());
        $this->assertTrue($modality->fresh()->trashed());
        $this->assertSame(1, \App\Models\Service::withTrashed()->forClinic($this->businessA->id)
            ->where('code', 'CT-BRAIN-NC')->count());
        $this->assertSame(1, \App\Models\Modality::withTrashed()->forClinic($this->businessA->id)
            ->where('code', 'CT')->count());
    }

    public function test_bootstrap_does_not_add_missing_services_under_unavailable_modalities(): void
    {
        $service = $this->tenantService($this->businessA, 'CT-BRAIN-NC');
        $modality = $service->modality;
        $service->update(['code' => 'LOCAL-CT-NC']);
        $modality->update(['is_active' => false]);

        app(\Database\Seeders\TenantBootstrap::class)->run($this->businessA, $this->adminA);
        $this->assertDatabaseMissing('services', ['business_id' => $this->businessA->id, 'code' => 'CT-BRAIN-NC']);

        $modality->update(['is_active' => true]);
        $modality->delete();
        app(\Database\Seeders\TenantBootstrap::class)->run($this->businessA, $this->adminA);

        $this->assertTrue($modality->fresh()->trashed());
        $this->assertSame('LOCAL-CT-NC', $service->fresh()->code);
        $this->assertDatabaseMissing('services', ['business_id' => $this->businessA->id, 'code' => 'CT-BRAIN-NC']);
    }

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

    // ==================== catalog lifecycle (A→Z) ====================

    public function test_duplicate_procedure_codes_are_rejected_within_a_tenant(): void
    {
        // Both tenants seed their own DX catalog, so cross-tenant reuse of a
        // code must be ALLOWED while intra-tenant duplication is rejected.
        $payload = fn (\App\Models\Business $business) => [
            'name' => 'Uniqueness Probe',
            'code' => 'PROBE-1',
            'modalityId' => \App\Models\Modality::where('business_id', $business->id)->firstOrFail()->id,
            'price' => 1000,
        ];

        $this->actingAs($this->adminB)->postJson('/api/v1/services', $payload($this->businessB))->assertCreated();

        $this->actingAs($this->adminA)->postJson('/api/v1/services', $payload($this->businessA))->assertCreated();

        // Same clinic, same code → rejected.
        $this->actingAs($this->adminA)->postJson('/api/v1/services', $payload($this->businessA))->assertStatus(422);
    }

    public function test_duplicate_modality_codes_are_rejected_within_a_tenant(): void
    {
        $this->actingAs($this->adminA)->postJson('/api/v1/modalities', [
            'name' => 'Second CT Suite',
            'code' => 'CT',
        ])->assertStatus(422);

        // Editing a suite keeps its own code (ignore-self), and custom codes
        // beyond the five preset ones are accepted.
        $modality = \App\Models\Modality::where('code', 'MR')->where('business_id', $this->businessA->id)->firstOrFail();
        $this->actingAs($this->adminA)->putJson("/api/v1/modalities/{$modality->id}", [
            'name' => $modality->name,
            'code' => 'MR',
            'bufferMinutes' => 12,
        ])->assertOk();

        $this->actingAs($this->adminA)->postJson('/api/v1/modalities', [
            'name' => 'Nuclear Medicine',
            'code' => 'NM',
        ])->assertCreated();
    }

    public function test_room_names_must_be_unique_per_tenant(): void
    {
        $modalityId = \App\Models\Modality::where('code', 'CT')->where('business_id', $this->businessA->id)->firstOrFail()->id;

        $this->actingAs($this->adminA)->postJson('/api/v1/rooms', [
            'name' => 'CT Suite 1',
            'modalityId' => $modalityId,
        ])->assertCreated();

        $this->actingAs($this->adminA)->postJson('/api/v1/rooms', [
            'name' => 'CT Suite 1',
            'modalityId' => $modalityId,
        ])->assertStatus(422);
    }

    public function test_procedures_can_be_retired_without_deleting_history(): void
    {
        $service = \App\Models\Service::where('code', 'DX-CHEST-PA')->where('business_id', $this->businessA->id)->firstOrFail();

        // Book a real study against the procedure through the API (the same
        // path production uses — the patient is minted by the booking flow).
        $studyId = $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'receptionist'))
            ->postJson('/api/v1/studies', [
                'newPatient' => ['name' => 'Retire Probe', 'gender' => 'male'],
                'serviceId' => $service->id,
                'date' => now()->toDateString(),
                'time' => '1:00 PM',
                'priority' => 'routine',
            ])->assertCreated()->json('data.study.id');

        $updated = $this->actingAs($this->adminA)->putJson("/api/v1/services/{$service->id}", [
            'name' => $service->name,
            'code' => $service->code,
            'modalityId' => $service->modality_id,
            'price' => $service->price,
            'isBookableOnline' => false,
        ])->assertOk()->json('data.service');

        $this->assertFalse($updated['isBookableOnline']);
        $this->assertDatabaseHas('services', ['id' => $service->id, 'is_bookable_online' => false]);

        // The retirement decision itself is audited (price changes especially).
        $this->assertDatabaseHas('audit_logs', ['action' => 'catalog_service_saved']);

        // Deleting a procedure with a booked study stays guarded.
        $this->assertNotNull($studyId);
        $this->actingAs($this->adminA)->deleteJson("/api/v1/services/{$service->id}")->assertStatus(422);
    }

    public function test_referrers_with_booked_studies_cannot_be_deleted(): void
    {
        $referrer = \App\Models\Referrer::create([
            'name' => 'Dr. Deletable',
            'clinic' => 'Test Clinic',
            'is_active' => true,
            'business_id' => $this->businessA->id,
            'created_by' => $this->adminA->id,
        ]);

        $service = \App\Models\Service::where('code', 'DX-CHEST-PA')->where('business_id', $this->businessA->id)->firstOrFail();
        $studyId = $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'receptionist'))
            ->postJson('/api/v1/studies', [
                'newPatient' => ['name' => 'Referrer Probe', 'gender' => 'male'],
                'serviceId' => $service->id,
                'date' => now()->toDateString(),
                'time' => '2:00 PM',
                'priority' => 'routine',
            ])->assertCreated()->json('data.study.id');
        $study = \App\Models\Appointment::findOrFail($studyId);
        $study->forceFill(['referrer_id' => $referrer->id])->save();

        $this->actingAs($this->adminA)->deleteJson("/api/v1/referrers/{$referrer->id}")->assertStatus(422);

        // Deactivation is the retirement path, and it reaches the wire shape.
        $this->actingAs($this->adminA)->putJson("/api/v1/referrers/{$referrer->id}", [
            'name' => $referrer->name,
            'isActive' => false,
        ])->assertOk()->assertJsonPath('data.referrer.isActive', false);

        // An unreferenced referrer deletes cleanly.
        $fresh = \App\Models\Referrer::create([
            'name' => 'Dr. Unused',
            'is_active' => true,
            'business_id' => $this->businessA->id,
            'created_by' => $this->adminA->id,
        ]);
        $this->actingAs($this->adminA)->deleteJson("/api/v1/referrers/{$fresh->id}")->assertOk();
    }

    public function test_screening_form_full_lifecycle_and_permissions(): void
    {
        // The server must accept `setting manage` (the permission the SPA gates
        // on) — previously it demanded `report template create` and 403'd
        // exactly the admins the UI showed the button to.
        $created = $this->actingAs($this->adminA)->postJson('/api/v1/screening-forms', [
            'name' => 'MRI Safety Lifecycle',
            'description' => 'Created by the lifecycle test',
            'questions' => [
                ['questionText' => 'Do you have a pacemaker?', 'answerType' => 'boolean', 'riskValue' => 'yes', 'isRiskBlocking' => true],
            ],
        ])->assertCreated()->json('data.form');

        $formId = $created['id'];

        // Metadata edits (rename / re-target modality / deactivate) all land on PUT.
        $renamed = $this->actingAs($this->adminA)->putJson("/api/v1/screening-forms/{$formId}", [
            'name' => 'MRI Safety Lifecycle v2',
            'description' => 'Updated description',
            'isActive' => false,
        ])->assertOk()->json('data.form');

        $this->assertSame('MRI Safety Lifecycle v2', $renamed['name']);
        $this->assertFalse($renamed['isActive']);

        // Question replacement stays supported.
        $replaced = $this->actingAs($this->adminA)->putJson("/api/v1/screening-forms/{$formId}", [
            'questions' => [
                ['questionText' => 'Any ferromagnetic implants?', 'answerType' => 'boolean', 'riskValue' => 'yes', 'isRiskBlocking' => true],
                ['questionText' => 'Are you claustrophobic?', 'answerType' => 'boolean', 'riskValue' => 'yes', 'isRiskBlocking' => false],
            ],
        ])->assertOk()->json('data.form');
        $this->assertCount(2, $replaced['questions']);

        // A form with COLLECTED ANSWERS cannot be deleted — deactivate instead.
        $questionId = $replaced['questions'][0]['id'];
        $studyId = $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'receptionist'))
            ->postJson('/api/v1/studies', [
                'newPatient' => ['name' => 'Form History Probe', 'gender' => 'male'],
                'serviceId' => \App\Models\Service::where('code', 'DX-CHEST-PA')->where('business_id', $this->businessA->id)->firstOrFail()->id,
                'date' => now()->toDateString(),
                'time' => '3:00 PM',
                'priority' => 'routine',
            ])->assertCreated()->json('data.study.id');

        \App\Models\StudyScreeningAnswer::create([
            'appointment_id' => $studyId,
            'screening_question_id' => $questionId,
            'answer_value' => 'no',
            'is_risk' => false,
            'answered_by' => $this->adminA->id,
        ]);

        $this->actingAs($this->adminA)->deleteJson("/api/v1/screening-forms/{$formId}")->assertStatus(422);

        $this->actingAs($this->adminA)->postJson("/api/v1/screening-forms/{$formId}/toggle")
            ->assertOk()->assertJsonPath('data.form.isActive', true);

        $this->assertDatabaseHas('audit_logs', ['action' => 'catalog_screening_form_saved']);

        // A form that never collected answers deletes cleanly.
        $fresh = $this->actingAs($this->adminA)->postJson('/api/v1/screening-forms', [
            'name' => 'Disposable Form',
            'questions' => [['questionText' => 'Q?', 'answerType' => 'boolean']],
        ])->assertCreated()->json('data.form');
        $this->actingAs($this->adminA)->deleteJson("/api/v1/screening-forms/{$fresh['id']}")->assertOk();
    }

    public function test_receptionist_cannot_touch_screening_forms(): void
    {
        $receptionist = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        $this->actingAs($receptionist)->postJson('/api/v1/screening-forms', [
            'name' => 'Sneaky Form',
            'questions' => [['questionText' => 'Q?', 'answerType' => 'boolean']],
        ])->assertForbidden();

        $formId = \App\Models\ScreeningForm::where('business_id', $this->businessA->id)->firstOrFail()->id;
        $this->actingAs($receptionist)->postJson("/api/v1/screening-forms/{$formId}/toggle")->assertForbidden();
        $this->actingAs($receptionist)->deleteJson("/api/v1/screening-forms/{$formId}")->assertForbidden();
    }

    public function test_catalog_uniqueness_and_lifecycle_are_audited(): void
    {
        $this->actingAs($this->adminA)->postJson('/api/v1/modalities', ['name' => 'Audit CT', 'code' => 'AUD'])->assertCreated();
        $this->actingAs($this->adminA)->postJson('/api/v1/rooms', [
            'name' => 'Audit Room',
            'modalityId' => \App\Models\Modality::where('code', 'AUD')->where('business_id', $this->businessA->id)->firstOrFail()->id,
        ])->assertCreated();
        $service = $this->actingAs($this->adminA)->postJson('/api/v1/services', [
            'name' => 'Audit Procedure',
            'code' => 'AUD-1',
            'modalityId' => \App\Models\Modality::where('code', 'AUD')->where('business_id', $this->businessA->id)->firstOrFail()->id,
            'price' => 100,
        ])->assertCreated()->json('data.service');
        $this->actingAs($this->adminA)->deleteJson("/api/v1/services/{$service['id']}")->assertOk();
        $this->actingAs($this->adminA)->postJson('/api/v1/referrers', ['name' => 'Dr. Audit'])->assertCreated();
        $form = $this->actingAs($this->adminA)->postJson('/api/v1/screening-forms', [
            'name' => 'Audit Form',
            'questions' => [['questionText' => 'Q?', 'answerType' => 'boolean']],
        ])->assertCreated()->json('data.form');
        $this->actingAs($this->adminA)->deleteJson("/api/v1/screening-forms/{$form['id']}")->assertOk();

        foreach (['catalog_modality_saved', 'catalog_service_saved', 'catalog_service_deleted',
            'catalog_room_saved', 'catalog_referrer_saved', 'catalog_screening_form_saved',
            'catalog_screening_form_deleted'] as $action) {
            $this->assertDatabaseHas('audit_logs', [
                'action' => $action,
                'business_id' => $this->businessA->id,
            ]);
        }
    }

    public function test_jev_question_review_is_advisory_and_fails_open(): void
    {
        // No gateway key in phpunit.xml → no judgment, and the endpoint must
        // still answer (fail-open) instead of erroring.
        $this->actingAs($this->adminA)->postJson('/api/v1/catalog/review-screening-question', [
            'questionText' => 'Do you have a pacemaker?',
        ])->assertOk()->assertJsonPath('data.review', null);

        // With a (faked) gateway, the typed judgment reaches the wire.
        config(['ris.typesafe.api_key' => 'test-key']);
        \Illuminate\Support\Facades\Http::fake([
            '*/v1/evaluate' => \Illuminate\Support\Facades\Http::response([
                'model' => 'typesafe-ai/jev',
                'answers' => [
                    'is_safety_question' => ['type' => 'boolean', 'probability' => 0.93],
                ],
            ], 200),
        ]);

        $review = $this->actingAs($this->adminA)->postJson('/api/v1/catalog/review-screening-question', [
            'questionText' => 'Do you have a pacemaker?',
            'siblingQuestions' => ['Any metal implants?'],
        ])->assertOk()->json('data.review');

        $this->assertTrue($review['isSafetyQuestion']);
        $this->assertSame(0.93, $review['confidence']);
    }
}
