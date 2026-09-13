<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Service;

class StudyWorkflowTest extends ApiTestCase
{
    private function service(string $code)
    {
        return Service::where('code', $code)->where('business_id', $this->businessA->id)->firstOrFail();
    }

    private function book(array $overrides = [])
    {
        $receptionist = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        $payload = array_merge([
            'newPatient' => [
                'name' => 'Test Patient',
                'phone' => '+92 300 0000000',
                'age' => 30,
                'gender' => 'male',
            ],
            'serviceId' => $this->service('DX-CHEST-PA')->id,
            'date' => now()->toDateString(),
            'time' => '9:30 AM',
            'priority' => 'routine',
        ], $overrides);

        $response = $this->actingAs($receptionist)->postJson('/api/v1/studies', $payload);

        if ($response->status() !== 201) {
            \Log::error('BOOKING-RESPONSE: '.substr($response->getContent(), 0, 2500));
        }

        $response->assertCreated();

        return $response->json('data.study');
    }

    public function test_receptionist_books_a_study_with_server_assigned_token_and_auto_invoice(): void
    {
        $study = $this->book();

        $this->assertSame('DX-01', $study['tokenNumber']);
        $this->assertSame('booked', $study['workflowState']);
        $this->assertStringContainsString('MRN-', $study['patient']['mrn']);

        // Auto invoice at booking time, issued with the service price.
        $invoice = Invoice::where('appointment_id', $study['id'])->first();
        $this->assertNotNull($invoice);
        $this->assertEquals($this->service('DX-CHEST-PA')->price, (float) $invoice->total);
        $this->assertSame('issued', $invoice->status);

        // A workflow notification was pushed server-side.
        $this->assertTrue(
            AppNotification::where('appointment_id', $study['id'])->where('category', 'workflow')->exists()
        );
    }

    public function test_tokens_sequence_per_modality_per_day(): void
    {
        $this->book();
        $second = $this->book();

        $this->assertSame('DX-02', $second['tokenNumber']);
    }

    public function test_full_pipeline_booking_to_delivery(): void
    {
        $study = $this->book();
        $id = $study['id'];
        $tech = $this->makeStaff($this->businessA, $this->adminA, 'technologist');

        // check-in (reception desk)
        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'receptionist'))
            ->postJson("/api/v1/studies/{$id}/transition", ['action' => 'checkin'])
            ->assertOk()
            ->assertJsonPath('data.study.workflowState', 'checked_in');

        // prepare
        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/transition", ['action' => 'prepare'])
            ->assertOk()->assertJsonPath('data.study.workflowState', 'preparing');

        // start acquisition
        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/transition", ['action' => 'start'])
            ->assertOk()->assertJsonPath('data.study.workflowState', 'in_progress');

        // complete with dose log (no contrast)
        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/transition", [
            'action' => 'complete',
            'dose' => ['doseValue' => 0.15, 'doseUnit' => 'mGy (DAP)'],
        ])->assertOk()->assertJsonPath('data.study.workflowState', 'acquired');

        // report + sign
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $this->actingAs($radiologist)->postJson("/api/v1/studies/{$id}/reports", [
            'findings' => 'Normal study.',
            'impression' => 'No acute cardiopulmonary disease.',
            'signNow' => true,
        ])->assertOk()->assertJsonPath('data.study.workflowState', 'reported');

        // release by hand closes the pipeline
        $study = \App\Models\Appointment::find($id);
        $report = $study->radiologyReports()->whereNotNull('locked_at')->first();
        $this->assertNotNull($report);

        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'receptionist'))
            ->postJson("/api/v1/reports/{$report->id}/release", ['channel' => 'hand'])
            ->assertOk()->assertJsonPath('data.study.workflowState', 'delivered');
    }

    public function test_unresolved_screening_risk_blocks_acquisition(): void
    {
        $study = $this->book([
            'serviceId' => $this->service('MR-LUMBAR')->id,
            'priority' => 'urgent',
        ]);
        $id = $study['id'];
        $tech = $this->makeStaff($this->businessA, $this->adminA, 'technologist');

        $this->assertTrue($study['screeningRequired']);
        $this->assertFalse($study['screeningCleared']);

        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/transition", ['action' => 'checkin'])->assertOk();
        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/transition", ['action' => 'prepare'])->assertOk();

        // Start must be blocked by the screening gate.
        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/transition", ['action' => 'start'])
            ->assertStatus(422);

        // Resolve screening (no risk answers).
        $formResponse = $this->actingAs($tech)->getJson("/api/v1/studies/{$id}/screening")->assertOk();
        $questions = $formResponse->json('data.form.questions');
        $this->assertNotEmpty($questions);

        $answers = collect($questions)->map(fn ($q) => [
            'questionId' => $q['id'],
            'answerValue' => 'no',
        ])->all();

        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/screening", ['answers' => $answers])
            ->assertOk()
            ->assertJsonPath('data.study.screeningCleared', true);

        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/transition", ['action' => 'start'])
            ->assertOk();
    }

    public function test_contrast_acquisition_auto_deducts_inventory(): void
    {
        $item = InventoryItem::where('code', 'CT-OMNI-350-100')->where('business_id', $this->businessA->id)->firstOrFail();
        $stockBefore = $item->current_stock;

        $study = $this->book(['serviceId' => $this->service('CT-CHEST-HR')->id]);
        $id = $study['id'];
        $tech = $this->makeStaff($this->businessA, $this->adminA, 'technologist');

        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/transition", ['action' => 'checkin'])->assertOk();
        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/transition", ['action' => 'prepare'])->assertOk();

        // Contrast service requires screening — clear it (all no) before acquisition.
        $form = $this->actingAs($tech)->getJson("/api/v1/studies/{$id}/screening")->assertOk()->json('data.form');
        $answers = collect($form['questions'])->map(fn ($q) => [
            'questionId' => $q['id'],
            'answerValue' => 'no',
        ])->all();
        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/screening", ['answers' => $answers])->assertOk();

        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/transition", ['action' => 'start'])->assertOk();
        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/transition", [
            'action' => 'complete',
            'dose' => [
                'doseValue' => 8.4,
                'doseUnit' => 'mGy (CTDIvol)',
                'contrastAgent' => 'Iohexol (Omnipaque 350)',
                'contrastVolumeMl' => 75,
            ],
        ])->assertOk();

        $item->refresh();
        $this->assertSame($stockBefore - 1, $item->current_stock);
        $this->assertDatabaseHas('ris_inventory_transactions', [
            'inventory_item_id' => $item->id,
            'type' => 'usage_study',
            'appointment_id' => $id,
        ]);
    }
}
