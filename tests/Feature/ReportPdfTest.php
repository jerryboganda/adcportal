<?php

namespace Tests\Feature;

use App\Models\RadiologyReport;
use App\Models\ReportTemplate;

/**
 * The printed/PDF report is the document that leaves the department, so its
 * rendering is covered explicitly: an unsigned draft must be labelled as a
 * draft, a signed report must carry the signature block and the recorded
 * structured values, and the endpoint must stay inside the tenant boundary.
 *
 * Nothing else in the suite renders this Blade view, which is exactly how a
 * missing template variable reaches a real patient's chart undetected.
 */
class ReportPdfTest extends ApiTestCase
{
    /** @return array{0:\App\Models\User,1:\App\Models\Appointment,2:array} */
    private function draftStudy(): array
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $study = $this->makeStudy(
            $this->businessA,
            $this->tenantService($this->businessA, 'CT-BRAIN-NC'),
            $this->makePatient($this->businessA, 'Print Preview Patient', 61),
        );

        $report = $this->actingAs($radiologist)->postJson("/api/v1/studies/{$study->id}/reports", [
            'clinicalHistory' => 'Fall with occipital trauma.',
            'technique' => 'Non-contrast CT of the brain, 5 mm axial reconstructions.',
            'comparison' => 'None available.',
            'findings' => 'No acute intracranial haemorrhage. No midline shift.',
            'impression' => 'No acute intracranial abnormality.',
            'recommendations' => 'Clinical correlation advised.',
            'criticalFlag' => false,
        ])->assertOk()->json('data.report');

        return [$radiologist, $study, $report];
    }

    public function test_the_pdf_endpoint_streams_a_real_document(): void
    {
        [$radiologist, , $report] = $this->draftStudy();

        $response = $this->actingAs($radiologist)->get("/api/v1/reports/{$report['id']}/pdf");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));

        // The PDF is streamed from the storage disk (not returned inline), so
        // the body must be read through streamedContent().
        $body = (string) $response->streamedContent();
        $this->assertNotSame('', $body);
        // A missing Blade variable or a broken layout class yields an error
        // page, not a PDF magic number.
        $this->assertStringStartsWith('%PDF', $body);
    }

    public function test_the_rendered_document_shows_the_patient_the_narrative_and_the_signature_block(): void
    {
        [$radiologist, $study, $report] = $this->draftStudy();

        // A PUT replaces the whole report from the editor buffer, so the full
        // payload is sent (this is exactly what the workspace does on sign-off).
        $signed = $this->actingAs($radiologist)->putJson("/api/v1/reports/{$report['id']}", [
            'clinicalHistory' => 'Fall with occipital trauma.',
            'technique' => 'Non-contrast CT of the brain, 5 mm axial reconstructions.',
            'comparison' => 'None available.',
            'findings' => 'No acute intracranial haemorrhage. Grey-white differentiation is preserved.',
            'impression' => 'No acute intracranial abnormality.',
            'recommendations' => 'Clinical correlation advised.',
            'signNow' => true,
            'signAs' => 'final',
        ])->assertOk()->json('data.report');

        $model = RadiologyReport::with(['appointment.ServiceData', 'author', 'signer'])->find($signed['id']);
        $html = view('reports.pdf', ['report' => $model])->render();

        // Patient identity: a report must never be printable against the wrong patient.
        $this->assertStringContainsString('Print Preview Patient', $html);
        $this->assertStringContainsString($study->CustomerData->mrn, $html);

        // Study context and the signed narrative.
        $this->assertStringContainsString('No acute intracranial haemorrhage', $html);
        $this->assertStringContainsString('No acute intracranial abnormality', $html);
        $this->assertStringContainsString('Non-contrast CT of the brain', $html);

        // Sign-off identity is taken from the signed record, not hardcoded.
        $this->assertStringContainsString('Electronically signed', $html);
        $this->assertStringContainsString($radiologist->name, $html);
        $this->assertStringNotContainsString('Reporting Radiologist', $html);

        // Tenant branding/letterhead.
        $this->assertStringContainsString($this->businessA->name, $html);
    }

    public function test_an_unsigned_document_is_labelled_a_draft(): void
    {
        [$radiologist, , $report] = $this->draftStudy();

        $model = RadiologyReport::with(['appointment.ServiceData', 'author', 'signer'])->find($report['id']);
        $html = view('reports.pdf', ['report' => $model])->render();

        // A working copy must never look like a filed report.
        $this->assertStringContainsString('DRAFT', $html);
        $this->assertStringNotContainsString('Electronically signed', $html);
    }

    public function test_recorded_structured_values_and_critical_logs_print(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $study = $this->makeStudy(
            $this->businessA,
            $this->tenantService($this->businessA, 'CT-BRAIN-NC'),
            $this->makePatient($this->businessA, 'Structured Print Patient', 39),
        );

        $template = ReportTemplate::create([
            'name' => 'CT Brain with measurements',
            'modality_id' => $this->tenantService($this->businessA, 'CT-BRAIN-NC')->modality_id,
            'scope' => 'tenant',
            'is_default' => false,
            'version' => 1,
            'structured_fields' => [
                ['key' => 'midline_shift', 'label' => 'Midline shift', 'type' => 'measurement', 'unit' => 'mm'],
                ['key' => 'haemorrhage', 'label' => 'Haemorrhage', 'type' => 'radio', 'options' => ['None', 'Present']],
            ],
            'business_id' => $this->businessA->id,
            'created_by' => $radiologist->id,
        ]);

        $report = $this->actingAs($radiologist)->postJson("/api/v1/studies/{$study->id}/reports", [
            'findings' => 'Measurements recorded.',
            'impression' => 'No haemorrhage.',
            'templateId' => $template->id,
            'structuredValues' => ['midline_shift' => '0', 'haemorrhage' => 'None'],
        ])->assertOk()->json('data.report');

        // A critical communication is its OWN record — it must print as such.
        $this->actingAs($radiologist)->postJson("/api/v1/reporting/critical-findings/{$study->id}", [
            'summary' => 'Incidental 8 mm aneurysm reported to the referring clinician.',
            'notifiedTo' => 'Dr. Referral',
            'method' => 'phone',
            'readBackVerified' => true,
        ])->assertCreated();

        $model = RadiologyReport::with(['appointment.ServiceData', 'author', 'signer', 'template'])->find($report['id']);
        $html = view('reports.pdf', ['report' => $model])->render();

        // Structured observations render with their unit.
        $this->assertStringContainsString('Midline shift', $html);
        $this->assertStringContainsString('0 mm', $html);
        $this->assertStringContainsString('Haemorrhage', $html);

        // The communication log is a table, not prose inside the impression.
        $this->assertStringContainsString('Critical Result Communication Record', $html);
        $this->assertStringContainsString('Dr. Referral', $html);
        $this->assertStringContainsString('Verified', $html);
    }

    public function test_another_clinics_report_pdf_is_not_downloadable(): void
    {
        [$radiologist, , $report] = $this->draftStudy();

        $foreignAdmin = $this->adminB;
        $this->actingAs($foreignAdmin)
            ->get("/api/v1/reports/{$report['id']}/pdf")
            ->assertNotFound();
    }

    public function test_an_addendum_pdf_keeps_the_original_report_visible(): void
    {
        [$radiologist, , $report] = $this->draftStudy();

        $signed = $this->actingAs($radiologist)->putJson("/api/v1/reports/{$report['id']}", [
            'findings' => 'Original signed findings.',
            'impression' => 'Original signed impression.',
            'signNow' => true,
            'signAs' => 'final',
        ])->assertOk()->json('data.report');

        $addendum = $this->actingAs($radiologist)
            ->postJson("/api/v1/reports/{$signed['id']}/addendum", [
                'text' => 'Addendum: outside MRI reviewed; unchanged.',
            ])
            ->assertCreated()
            ->json('data.report');

        $model = RadiologyReport::with(['appointment.ServiceData', 'author', 'signer', 'parentReport'])->find($addendum['id']);
        $html = view('reports.pdf', ['report' => $model])->render();

        $this->assertStringContainsString('ADDENDUM', $html);
        $this->assertStringContainsString('Original Report', $html);
        $this->assertStringContainsString('Original signed impression.', $html);
        $this->assertStringContainsString('outside MRI reviewed', $html);
    }
}
