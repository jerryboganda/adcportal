<?php

namespace Tests\Feature;

use App\Models\RadiologyReport;
use App\Models\ReportTemplate;

/**
 * A draft is edited by one radiologist at a time and a signed report is
 * immutable medico-legal history. These tests pin the rules that keep both
 * promises, including the one that only shows up in a hospital: two people
 * with the same draft open.
 */
class ReportDraftConcurrencyTest extends ApiTestCase
{
    private function studyWithDraft(): array
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $study = $this->makeStudy(
            $this->businessA,
            $this->tenantService($this->businessA, 'CT-BRAIN-NC'),
            $this->makePatient($this->businessA, 'Draft Patient', 45),
        );

        $created = $this->actingAs($radiologist)->postJson("/api/v1/studies/{$study->id}/reports", [
            'clinicalHistory' => 'Headache.',
            'technique' => 'NCCT brain.',
            'comparison' => 'None.',
            'findings' => 'Initial findings.',
            'impression' => 'Initial impression.',
            'recommendations' => '',
            'criticalFlag' => false,
        ])->assertOk();

        return [$radiologist, $study, $created->json('data.report')];
    }

    public function test_a_draft_round_trips_with_its_revision_counter(): void
    {
        [$radiologist, , $report] = $this->studyWithDraft();

        $this->assertSame(1, $report['lockVersion']);
        $this->assertSame('Draft', $report['statusLabel']);
        $this->assertFalse($report['isSigned']);

        $updated = $this->actingAs($radiologist)->putJson("/api/v1/reports/{$report['id']}", [
            'clinicalHistory' => 'Headache.',
            'technique' => 'NCCT brain.',
            'comparison' => 'None.',
            'findings' => 'Autosaved findings.',
            'impression' => 'Initial impression.',
            'lockVersion' => 1,
        ])->assertOk();

        $this->assertSame(2, $updated->json('data.report.lockVersion'));
        $this->assertSame('Autosaved findings.', $updated->json('data.report.findings'));
    }

    public function test_a_stale_autosave_is_refused_with_the_current_server_state(): void
    {
        [$radiologist, , $report] = $this->studyWithDraft();

        // Colleague A saves first.
        $this->actingAs($radiologist)->putJson("/api/v1/reports/{$report['id']}", [
            'findings' => 'A findings.',
            'impression' => 'A impression.',
            'lockVersion' => 1,
        ])->assertOk();

        // Colleague B's editor still believes revision 1.
        $conflict = $this->actingAs($radiologist)->putJson("/api/v1/reports/{$report['id']}", [
            'findings' => 'B findings.',
            'impression' => 'B impression.',
            'lockVersion' => 1,
        ])->assertStatus(409);

        $this->assertSame('report.conflict', $conflict->json('error'));
        $this->assertSame('A findings.', $conflict->json('data.report.findings'));
        $this->assertSame(2, $conflict->json('data.report.lockVersion'));

        // The losing write never landed.
        $this->assertSame('A findings.', RadiologyReport::find($report['id'])->findings);

        // Re-loading and re-saving with the fresh revision succeeds.
        $this->actingAs($radiologist)->putJson("/api/v1/reports/{$report['id']}", [
            'findings' => 'B findings merged.',
            'impression' => 'B impression.',
            'lockVersion' => 2,
        ])->assertOk()->assertJsonPath('data.report.findings', 'B findings merged.');
    }

    public function test_the_unsigned_draft_is_updated_never_duplicated(): void
    {
        [$radiologist, $study, $report] = $this->studyWithDraft();

        $this->actingAs($radiologist)->postJson("/api/v1/studies/{$study->id}/reports", [
            'findings' => 'Second draft.',
            'impression' => 'Second impression.',
        ])->assertStatus(422);

        $this->assertSame(1, RadiologyReport::where('appointment_id', $study->id)->count());
        $this->assertSame($report['id'], (string) RadiologyReport::where('appointment_id', $study->id)->first()->id);
    }

    public function test_signing_finalizes_the_study_and_freezes_the_report(): void
    {
        [$radiologist, $study, $report] = $this->studyWithDraft();

        $signed = $this->actingAs($radiologist)->putJson("/api/v1/reports/{$report['id']}", [
            'findings' => 'Final findings.',
            'impression' => 'No acute intracranial abnormality.',
            'signNow' => true,
            'signAs' => 'final',
        ])->assertOk();

        $this->assertSame('reported', $signed->json('data.study.workflowState'));
        $this->assertSame('Final', $signed->json('data.report.statusLabel'));
        $this->assertTrue($signed->json('data.report.isSigned'));

        // Drafts stop being editable the moment they are signed.
        $this->actingAs($radiologist)->putJson("/api/v1/reports/{$report['id']}", [
            'findings' => 'Rewritten history.',
            'impression' => 'Rewritten impression.',
        ])->assertStatus(422);

        $stored = RadiologyReport::find($report['id']);
        $this->assertSame('Final findings.', $stored->findings);
        $this->assertNotNull($stored->locked_at);
    }

    public function test_an_addendum_appends_an_immutable_version_and_leaves_the_final_untouched(): void
    {
        [$radiologist, $study, $report] = $this->studyWithDraft();

        $this->actingAs($radiologist)->putJson("/api/v1/reports/{$report['id']}", [
            'findings' => 'Original findings.',
            'impression' => 'Original impression.',
            'signNow' => true,
        ])->assertOk();

        $addendum = $this->actingAs($radiologist)
            ->postJson("/api/v1/reports/{$report['id']}/addendum", [
                'text' => 'Reviewed the outside MRI received today: the lesion is stable since 2024.',
            ])
            ->assertCreated();

        $this->assertSame('addendum', $addendum->json('data.report.type'));
        $this->assertSame(2, $addendum->json('data.report.version'));
        $this->assertSame($report['id'], $addendum->json('data.report.parentReportId'));
        $this->assertTrue($addendum->json('data.report.isSigned'));

        // The originally signed version is byte-for-byte what was signed.
        $original = RadiologyReport::find($report['id']);
        $this->assertSame('Original findings.', $original->findings);
        $this->assertSame('Original impression.', $original->impression);
        $this->assertSame('final', $original->type);

        // The owning study does not rewind out of "reported".
        $this->assertSame('reported', $study->fresh()->workflow_state);
    }

    public function test_an_addendum_requires_a_signed_report(): void
    {
        [$radiologist, , $report] = $this->studyWithDraft();

        $this->actingAs($radiologist)
            ->postJson("/api/v1/reports/{$report['id']}/addendum", ['text' => 'Premature addendum.'])
            ->assertStatus(422);
    }

    public function test_structured_values_are_validated_against_the_template_schema(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $service = $this->tenantService($this->businessA, 'US-ABD-PEL');

        $template = ReportTemplate::where('business_id', $this->businessA->id)
            ->where('name', 'Whole Abdomen Ultrasound (Normal)')
            ->firstOrFail();

        $study = $this->makeStudy($this->businessA, $service, $this->makePatient($this->businessA, 'US Patient', 33));

        // A value outside the declared options is refused, not stored.
        $this->actingAs($radiologist)->postJson("/api/v1/studies/{$study->id}/reports", [
            'findings' => 'x',
            'impression' => 'y',
            'templateId' => (int) $template->id,
            'structuredValues' => ['hydronephrosis' => 'Catastrophic'],
        ])->assertStatus(422);

        $created = $this->actingAs($radiologist)->postJson("/api/v1/studies/{$study->id}/reports", [
            'findings' => 'Findings.',
            'impression' => 'Impression.',
            'templateId' => (int) $template->id,
            'structuredValues' => [
                // `liver` is a required field of this template's schema.
                'liver' => 'Normal',
                'hydronephrosis' => 'Mild',
                'cbd_mm' => '4.2',
                // Not declared by the template — dropped, never stored.
                'smuggled' => 'ignore me',
            ],
        ])->assertOk();

        $values = $created->json('data.report.structuredValues');
        $this->assertSame('Mild', $values['hydronephrosis']);
        $this->assertSame('4.2', $values['cbd_mm']);
        $this->assertArrayNotHasKey('smuggled', $values);

        $stored = RadiologyReport::find($created->json('data.report.id'));
        $this->assertSame('Mild', $stored->structured_values['hydronephrosis']);
        $this->assertSame($template->id, $stored->template_id);
        $this->assertSame(1, $stored->template_version);
    }

    public function test_report_write_scope_and_permissions_are_enforced(): void
    {
        [$radiologist, , $report] = $this->studyWithDraft();

        // Reception cannot author clinical text…
        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'receptionist'))
            ->putJson("/api/v1/reports/{$report['id']}", [
                'findings' => 'x',
                'impression' => 'y',
            ])->assertForbidden();

        // …and cannot sign, even though they may hold the report.
        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'technologist'))
            ->postJson("/api/v1/reports/{$report['id']}/sign")
            ->assertForbidden();

        // A report id from another clinic does not exist here.
        $foreign = $this->makeStudy(
            $this->businessB,
            $this->tenantService($this->businessB, 'CT-BRAIN-NC'),
            $this->makePatient($this->businessB, 'Beta Patient', 40),
        );
        $foreignReport = RadiologyReport::create([
            'appointment_id' => $foreign->id,
            'version' => 1,
            'type' => 'draft',
            'findings' => 'beta',
            'impression' => 'beta',
            'business_id' => $this->businessB->id,
            'created_by' => $this->businessB->created_by,
        ]);

        $this->actingAs($radiologist)
            ->putJson("/api/v1/reports/{$foreignReport->id}", ['findings' => 'hijack', 'impression' => 'hijack'])
            ->assertNotFound();

        $this->actingAs($radiologist)
            ->getJson("/api/v1/reports/{$foreignReport->id}")
            ->assertNotFound();
    }

    public function test_another_clinics_template_id_is_rejected_on_save(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $foreignTemplate = ReportTemplate::create([
            'name' => 'Beta Template',
            'modality_id' => $this->tenantService($this->businessB, 'CT-BRAIN-NC')->modality_id,
            'business_id' => $this->businessB->id,
            'created_by' => $this->businessB->created_by,
            'scope' => 'tenant',
        ]);

        $study = $this->makeStudy(
            $this->businessA,
            $this->tenantService($this->businessA, 'CT-BRAIN-NC'),
            $this->makePatient($this->businessA, 'Patient', 30),
        );

        $this->actingAs($radiologist)->postJson("/api/v1/studies/{$study->id}/reports", [
            'findings' => 'x',
            'impression' => 'y',
            'templateId' => (int) $foreignTemplate->id,
        ])->assertNotFound();
    }
}
