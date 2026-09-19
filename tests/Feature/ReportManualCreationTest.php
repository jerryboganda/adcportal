<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\RadiologyReport;

/**
 * "Create New Report" exists for external, imported or offline studies. It
 * must NOT invent a report floating outside the study/patient model: a real
 * study record is created (marked `origin = manual`, state `acquired`) so the
 * longitudinal record, the token sequence and the worklist all stay coherent.
 */
class ReportManualCreationTest extends ApiTestCase
{
    private function modalityId(string $code): int
    {
        return (int) $this->tenantService($this->businessA, $code)->modality_id;
    }

    public function test_a_radiologist_creates_a_report_for_an_existing_patient(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $patient = $this->makePatient($this->businessA, 'External Study Patient', 58);
        $service = $this->tenantService($this->businessA, 'CT-BRAIN-NC');

        $response = $this->actingAs($radiologist)->postJson('/api/v1/reporting/reports/manual', [
            'patientId' => (int) $patient->id,
            'serviceId' => (int) $service->id,
            'date' => now()->toDateString(),
            'studyDate' => now()->subDays(3)->toDateString(),
            'priority' => 'urgent',
            'indication' => 'Outside CT brought for second opinion.',
            'findings' => 'Findings from the external study.',
            'impression' => 'Impression recorded from the external images.',
        ])->assertCreated();

        $studyId = $response->json('data.study.id');
        $study = Appointment::find($studyId);

        // A real study record, explicitly marked as manually originated.
        $this->assertNotNull($study);
        $this->assertSame('manual', $study->origin);
        $this->assertSame('acquired', $study->workflow_state);
        $this->assertSame(now()->subDays(3)->toDateString(), $study->date_sort);
        $this->assertSame($service->id, $study->service_id);
        $this->assertNotNull($study->token_number, 'a manually created study still gets its token');
        $this->assertSame($radiologist->id, $study->assigned_radiologist_id);

        // The report hangs off that study, not off nothing.
        $report = RadiologyReport::find($response->json('data.report.id'));
        $this->assertSame($study->id, $report->appointment_id);
        $this->assertSame($this->businessA->id, $report->business_id);

        // And it lands on the reading worklist like any other acquired study.
        $worklist = $this->actingAs($radiologist)
            ->getJson('/api/v1/reporting/worklist?tab=unreported')
            ->json('data.studies');
        $this->assertContains((string) $study->id, collect($worklist)->pluck('id')->all());
    }

    /**
     * The browser journey creates an external study, saves a draft, reloads and
     * then FINDS IT AGAIN by searching the patient name. That last step is the
     * one that decides whether a radiologist can get back to their own work, so
     * it is asserted here rather than trusted to the browser.
     */
    public function test_a_newly_registered_external_patient_is_findable_by_name(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $service = $this->tenantService($this->businessA, 'US-ABD-PEL');
        $name = 'E2E External 1789848166197';

        $created = $this->actingAs($radiologist)->postJson('/api/v1/reporting/reports/manual', [
            'newPatient' => ['name' => $name, 'age' => 52, 'gender' => 'other'],
            'serviceId' => (int) $service->id,
            'date' => now()->toDateString(),
            'priority' => 'routine',
            'indication' => 'E2E external referral, headache.',
            'findings' => 'Drafted findings.',
            'impression' => 'Drafted impression.',
        ])->assertCreated();

        $studyId = (string) $created->json('data.study.id');

        $studies = $this->actingAs($radiologist)
            ->getJson('/api/v1/reporting/worklist?tab=unreported&q='.urlencode('E2E External'))
            ->assertOk()
            ->json('data.studies');

        $this->assertContains(
            $studyId,
            collect($studies)->pluck('id')->all(),
            'a newly registered external patient must be findable on the reading queue by name',
        );
        $this->assertSame(
            $name,
            collect($studies)->firstWhere('id', $studyId)['patientName'] ?? null,
        );
    }

    public function test_a_patient_must_be_chosen_or_registered(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $this->actingAs($radiologist)->postJson('/api/v1/reporting/reports/manual', [
            'serviceId' => (int) $this->tenantService($this->businessA, 'CT-BRAIN-NC')->id,
            'date' => now()->toDateString(),
            'priority' => 'routine',
        ])->assertStatus(422);

        // A new patient goes through the same MRN-issuing registry as booking.
        $response = $this->actingAs($radiologist)->postJson('/api/v1/reporting/reports/manual', [
            'newPatient' => ['name' => 'Newly Registered', 'age' => 27, 'gender' => 'female'],
            'serviceId' => (int) $this->tenantService($this->businessA, 'US-ABD-PEL')->id,
            'date' => now()->toDateString(),
            'priority' => 'routine',
            'findings' => 'x',
            'impression' => 'y',
        ])->assertCreated();

        $this->assertStringStartsWith('MRN-', $response->json('data.study.patient.mrn'));
    }

    public function test_the_procedure_must_belong_to_the_acting_clinic(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $this->actingAs($radiologist)->postJson('/api/v1/reporting/reports/manual', [
            'newPatient' => ['name' => 'Patient'],
            'serviceId' => (int) $this->tenantService($this->businessB, 'CT-BRAIN-NC')->id,
            'date' => now()->toDateString(),
            'priority' => 'routine',
            'impression' => 'x',
        ])->assertNotFound();

        // Another clinic's patient id is likewise not addressable.
        $this->actingAs($radiologist)->postJson('/api/v1/reporting/reports/manual', [
            'patientId' => (int) $this->makePatient($this->businessB, 'Beta Patient', 40)->id,
            'serviceId' => (int) $this->tenantService($this->businessA, 'CT-BRAIN-NC')->id,
            'date' => now()->toDateString(),
            'priority' => 'routine',
            'impression' => 'x',
        ])->assertNotFound();
    }

    public function test_an_unauthorised_role_cannot_create_reports(): void
    {
        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'receptionist'))
            ->postJson('/api/v1/reporting/reports/manual', [
                'newPatient' => ['name' => 'Patient'],
                'serviceId' => (int) $this->tenantService($this->businessA, 'CT-BRAIN-NC')->id,
                'date' => now()->toDateString(),
                'priority' => 'routine',
                'impression' => 'x',
            ])->assertForbidden();
    }

    public function test_sign_now_finalizes_the_external_report_in_the_same_unit_of_work(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $response = $this->actingAs($radiologist)->postJson('/api/v1/reporting/reports/manual', [
            'newPatient' => ['name' => 'Signed On Arrival', 'age' => 61],
            'serviceId' => (int) $this->tenantService($this->businessA, 'DX-CHEST-PA')->id,
            'date' => now()->toDateString(),
            'priority' => 'routine',
            'findings' => 'Clear lungs.',
            'impression' => 'No acute cardiopulmonary abnormality.',
            'signNow' => true,
        ])->assertCreated();

        $study = Appointment::find($response->json('data.study.id'));
        $this->assertSame('reported', $study->workflow_state);

        $report = RadiologyReport::find($response->json('data.report.id'));
        $this->assertSame('final', $report->type);
        $this->assertNotNull($report->locked_at);
    }

    public function test_finalising_without_an_impression_is_refused(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $this->actingAs($radiologist)->postJson('/api/v1/reporting/reports/manual', [
            'newPatient' => ['name' => 'No Impression'],
            'serviceId' => (int) $this->tenantService($this->businessA, 'DX-CHEST-PA')->id,
            'date' => now()->toDateString(),
            'priority' => 'routine',
            'findings' => 'Findings only.',
            'signNow' => true,
        ])->assertStatus(422);

        $this->assertSame(0, Appointment::where('business_id', $this->businessA->id)->where('origin', 'manual')->count());
    }

    public function test_structured_values_are_validated_on_a_manually_created_report(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $template = \App\Models\ReportTemplate::where('business_id', $this->businessA->id)
            ->where('name', 'Whole Abdomen Ultrasound (Normal)')
            ->firstOrFail();

        $this->actingAs($radiologist)->postJson('/api/v1/reporting/reports/manual', [
            'newPatient' => ['name' => 'Structured Patient'],
            'serviceId' => (int) $this->tenantService($this->businessA, 'US-ABD-PEL')->id,
            'date' => now()->toDateString(),
            'priority' => 'routine',
            'templateId' => (int) $template->id,
            'findings' => 'x',
            'impression' => 'y',
            'structuredValues' => ['liver' => 'Not an option at all'],
        ])->assertStatus(422);

        $created = $this->actingAs($radiologist)->postJson('/api/v1/reporting/reports/manual', [
            'newPatient' => ['name' => 'Structured Patient'],
            'serviceId' => (int) $this->tenantService($this->businessA, 'US-ABD-PEL')->id,
            'date' => now()->toDateString(),
            'priority' => 'routine',
            'templateId' => (int) $template->id,
            'findings' => 'x',
            'impression' => 'y',
            'structuredValues' => ['liver' => 'Normal', 'hydronephrosis' => 'None'],
        ])->assertCreated();

        $report = RadiologyReport::find($created->json('data.report.id'));
        $this->assertSame('Normal', $report->structured_values['liver']);
        $this->assertSame($template->id, $report->template_id);
    }

    public function test_another_clinics_template_cannot_be_attached(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $foreignTemplate = \App\Models\ReportTemplate::create([
            'name' => 'Beta Template',
            'modality_id' => $this->modalityId('CT-BRAIN-NC'),
            'business_id' => $this->businessB->id,
            'created_by' => $this->businessB->created_by,
            'scope' => 'tenant',
        ]);

        $this->actingAs($radiologist)->postJson('/api/v1/reporting/reports/manual', [
            'newPatient' => ['name' => 'Patient'],
            'serviceId' => (int) $this->tenantService($this->businessA, 'CT-BRAIN-NC')->id,
            'date' => now()->toDateString(),
            'priority' => 'routine',
            'templateId' => (int) $foreignTemplate->id,
            'impression' => 'x',
        ])->assertNotFound();
    }
}
