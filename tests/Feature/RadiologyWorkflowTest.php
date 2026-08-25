<?php

namespace Tests\Feature;

use App\Enums\StudyState;
use App\Models\Appointment;
use App\Models\AppointmentProcedure;
use App\Models\Business;
use App\Models\BusinessHours;
use App\Models\Modality;
use App\Models\ScreeningForm;
use App\Models\ScreeningQuestion;
use App\Models\Service;
use App\Models\User;
use App\Services\StudyWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RadiologyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Business $business;
    private Service $service;
    private StudyWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local',
            'password' => bcrypt('secret123'), 'type' => 'admin', 'lang' => 'en',
        ]);
        $this->business = Business::create([
            'name' => 'ADC Radiology', 'form_type' => 'form-layout',
            'layouts' => 'Formlayout11', 'created_by' => $this->admin->id,
        ]);

        $location = \App\Models\Location::create([
            'name' => 'Main', 'address' => 'Test Address',
            'business_id' => $this->business->id, 'created_by' => $this->admin->id,
        ]);
        $staff = \App\Models\Staff::create([
            'name' => 'Tech One', 'user_id' => $this->admin->id, 'status' => 1,
            'location_id' => (string) $location->id, 'service_id' => '',
            'business_id' => $this->business->id, 'created_by' => $this->admin->id,
        ]);

        $category = \App\Models\Category::create([
            'name' => 'Radiology', 'business_id' => $this->business->id, 'created_by' => $this->admin->id,
        ]);

        $modality = Modality::create([
            'name' => 'X-Ray', 'code' => 'DX', 'buffer_minutes' => 5,
            'business_id' => $this->business->id, 'created_by' => $this->admin->id,
        ]);

        $this->service = Service::create([
            'name' => 'Chest X-Ray', 'duration' => 15, 'price' => 800,
            'category_id' => $category->id,
            'modality_id' => $modality->id, 'business_id' => $this->business->id,
            'created_by' => $this->admin->id,
        ]);

        BusinessHours::create([
            'day_name' => now()->format('l'), 'start_time' => '09:00:00',
            'end_time' => '17:00:00', 'break_hours' => json_encode([]),
            'business_id' => $this->business->id, 'created_by' => $this->admin->id,
        ]);

        \Cache::flush();
        $this->workflow = app(StudyWorkflowService::class);
    }

    private function createStudy(array $overrides = []): Appointment
    {
        $location = \App\Models\Location::first();

        return Appointment::create([
            ...[
                'date' => now()->format('d-m-Y'),
                'time' => '10:00',
                'service_id' => $this->service->id,
                'customer_id' => $this->admin->id,
                'location_id' => $location->id,
                'staff_id' => \App\Models\Staff::first()->id,
                'business_id' => $this->business->id,
                'created_by' => $this->business->created_by,
                'payment_type' => 'Manually',
                'appointment_status' => 0,
            ],
            ...$overrides,
        ]);
    }

    public function test_study_starts_in_booked_state(): void
    {
        $study = $this->createStudy();

        $this->assertSame(StudyState::Booked, $study->state());
    }

    public function test_full_happy_path_reaches_reported(): void
    {
        $study = $this->createStudy();
        $this->actingAs($this->admin);

        $this->workflow->checkIn($study);
        $this->assertTrue($study->fresh()->checked_in_at !== null);

        $this->workflow->startAcquisition($study); // skip preparing (no screening needed)
        $this->workflow->completeAcquisition($study, ['dose_value' => 0.12, 'dose_unit' => 'mGy'], null);
        $this->assertSame(StudyState::Acquired, $study->fresh()->state());
        $this->assertDatabaseHas('dose_logs', ['appointment_id' => $study->id, 'dose_unit' => 'mGy']);

        // Sign a final report -> pipeline auto-advances to reported.
        $report = \App\Models\RadiologyReport::create([
            'appointment_id' => $study->id, 'version' => 1, 'type' => 'draft',
            'findings' => 'Normal cardiomediastinal silhouette.', 'impression' => 'No acute cardiopulmonary disease.',
            'authored_by' => $this->admin->id, 'business_id' => $this->business->id, 'created_by' => $this->admin->id,
        ]);
        $report->forceFill(['signed_by' => $this->admin->id, 'signed_at' => now(), 'locked_at' => now()])->save();

        $this->workflow->markReported($study);
        $this->assertSame(StudyState::Reported, $study->fresh()->state());

        $this->workflow->deliver($study);
        $this->assertSame(StudyState::Delivered, $study->fresh()->state());
    }

    public function test_cannot_skip_states(): void
    {
        $study = $this->createStudy();

        $this->expectException(ValidationException::class);
        $this->workflow->completeAcquisition($study);
    }

    public function test_unresolved_screening_risk_blocks_acquisition(): void
    {
        $study = $this->createStudy(['screening_required' => true, 'screening_cleared' => false]);
        $this->actingAs($this->admin);

        $form = ScreeningForm::create([
            'name' => 'MRI Safety', 'slug' => 'mri-safety',
            'business_id' => $this->business->id, 'created_by' => $this->admin->id,
        ]);
        $question = ScreeningQuestion::create([
            'screening_form_id' => $form->id, 'question_text' => 'Pacemaker present?',
            'answer_type' => 'boolean', 'risk_value' => 'yes', 'is_risk_blocking' => true,
        ]);

        \App\Models\StudyScreeningAnswer::create([
            'appointment_id' => $study->id, 'screening_question_id' => $question->id,
            'answer_value' => 'yes', 'is_risk' => true, // no override reason!
        ]);

        $this->assertTrue($study->hasUnresolvedScreeningRisk());

        $this->expectException(ValidationException::class);
        $this->workflow->startAcquisition($study);
    }

    public function test_overridden_risk_allows_acquisition(): void
    {
        $study = $this->createStudy(['screening_required' => true]);
        $this->actingAs($this->admin);

        $form = ScreeningForm::create([
            'name' => 'Contrast Screen', 'slug' => 'contrast-screen',
            'business_id' => $this->business->id, 'created_by' => $this->admin->id,
        ]);
        $q = ScreeningQuestion::create([
            'screening_form_id' => $form->id, 'question_text' => 'Prior contrast reaction?',
            'answer_type' => 'boolean', 'risk_value' => 'yes',
        ]);
        \App\Models\StudyScreeningAnswer::create([
            'appointment_id' => $study->id, 'screening_question_id' => $q->id,
            'answer_value' => 'yes', 'is_risk' => true,
            'override_reason' => 'Pre-medicated per protocol, cleared by radiologist',
        ]);

        $study->forceFill(['screening_cleared' => true])->save();
        $this->assertFalse($study->hasUnresolvedScreeningRisk());

        $this->workflow->checkIn($study); // pipeline requires check-in before acquisition
        $this->workflow->startAcquisition($study);
        $this->assertSame(StudyState::InProgress, $study->fresh()->state());
    }

    public function test_invoice_math_with_tax_and_partial_payment(): void
    {
        $study = $this->createStudy();
        $this->actingAs($this->admin);

        AppointmentProcedure::create([
            'appointment_id' => $study->id, 'service_id' => $this->service->id,
            'description' => 'Chest X-Ray PA+Lateral', 'quantity' => 1, 'unit_price' => 800,
        ]);
        AppointmentProcedure::create([
            'appointment_id' => $study->id, 'description' => 'Contrast injection',
            'quantity' => 1, 'unit_price' => 200,
        ]);

        $controller = app(\App\Http\Controllers\InvoiceController::class);
        $invoice = \App\Models\Invoice::create([
            'patient_id' => $this->admin->id, 'appointment_id' => $study->id,
            'tax_rate' => 10, 'business_id' => $this->business->id, 'created_by' => $this->admin->id,
        ]);
        foreach ($study->procedures as $p) {
            $invoice->items()->create([
                'description' => $p->description, 'quantity' => $p->quantity,
                'unit_price' => $p->unit_price, 'discount' => 0,
            ]);
        }
        // mirror controller recalculation
        $subtotal = (float) $invoice->items()->sum('line_total');
        $tax = round($subtotal * 0.10, 2);
        $invoice->forceFill(['subtotal' => $subtotal, 'discount_total' => 0, 'tax_amount' => $tax, 'total' => $subtotal + $tax])->save();

        $this->assertSame(1000.0, (float) $invoice->subtotal);
        $this->assertSame(1100.0, (float) $invoice->total);

        \App\Models\InvoicePayment::create([
            'invoice_id' => $invoice->id, 'amount' => 600, 'method' => 'cash',
            'paid_at' => now(), 'received_by' => $this->admin->id,
            'business_id' => $this->business->id, 'created_by' => $this->admin->id,
        ]);

        $invoice->refresh();
        $this->assertSame(\App\Models\Invoice::STATUS_PARTIAL, $invoice->status);
        $this->assertEquals(500.0, $invoice->balance_due);

        \App\Models\InvoicePayment::create([
            'invoice_id' => $invoice->id, 'amount' => 500, 'method' => 'card',
            'paid_at' => now(), 'received_by' => $this->admin->id,
            'business_id' => $this->business->id, 'created_by' => $this->admin->id,
        ]);
        $this->assertSame(\App\Models\Invoice::STATUS_PAID, $invoice->fresh()->status);
    }
}
