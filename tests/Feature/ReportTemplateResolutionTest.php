<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ReportTemplate;
use App\Support\ReportTemplateResolver;

/**
 * Template resolution decides which clinical baseline a radiologist starts
 * from. The dangerous failure modes are all covered here: a pediatric study
 * receiving an adult baseline, a chest template arriving on a brain study, a
 * sex-specific template on the wrong patient, and one clinic's templates
 * leaking into another's.
 */
class ReportTemplateResolutionTest extends ApiTestCase
{
    private function resolveForAppointment(Appointment $study, int $userId): array
    {
        return ReportTemplateResolver::forAppointment($study->fresh(['ServiceData.modality', 'CustomerData', 'doseLog']), $userId);
    }

    public function test_the_procedure_baseline_is_used_for_an_adult_study(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $study = $this->makeStudy(
            $this->businessA,
            $this->tenantService($this->businessA, 'CT-BRAIN-NC'),
            $this->makePatient($this->businessA, 'Adult Patient', 40),
        );

        $match = $this->resolveForAppointment($study, $radiologist->id);

        $this->assertSame('procedure', $match['tier']);
        $this->assertSame('CT Brain (Non-Contrast) — Adult Baseline', $match['template']->name);
        $this->assertSame('adult', $match['ageGroup']);
    }

    public function test_a_child_gets_the_pediatric_baseline_of_the_same_procedure(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $study = $this->makeStudy(
            $this->businessA,
            $this->tenantService($this->businessA, 'CT-BRAIN-NC'),
            $this->makePatient($this->businessA, 'Child Patient', 6, 'male', now()->subYears(6)->toDateString()),
        );

        $match = $this->resolveForAppointment($study, $radiologist->id);

        $this->assertSame('procedure_age', $match['tier']);
        $this->assertSame('Pediatric CT Brain (Non-Contrast)', $match['template']->name);
    }

    public function test_an_adult_never_receives_a_pediatric_baseline(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $study = $this->makeStudy(
            $this->businessA,
            $this->tenantService($this->businessA, 'CT-BRAIN-NC'),
            $this->makePatient($this->businessA, 'Adult Patient', 55),
        );

        ReportTemplate::where('business_id', $this->businessA->id)
            ->where('name', 'CT Brain (Non-Contrast) — Adult Baseline')
            ->update(['is_archived' => true]);

        $match = $this->resolveForAppointment($study, $radiologist->id);

        // Nothing age-safe remains for this procedure: an empty report is
        // correct, an adult-to-pediatric substitution is not.
        $this->assertNull($match['template']);
        $this->assertSame('none', $match['tier']);
    }

    public function test_a_neonate_never_falls_back_to_the_infant_template(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $modality = $this->tenantService($this->businessA, 'US-ABD-PEL')->modality_id;

        $neonatal = ReportTemplateResolver::resolve(
            tenantId: $this->businessA->id,
            serviceId: null,
            modalityId: $modality,
            bodyRegion: 'Brain',
            ageGroup: 'neonatal',
            sex: null,
            contrast: 'without',
            userId: $radiologist->id,
        );

        $this->assertNull($neonatal['template'], 'the infant cranial template must not be served to a neonate');

        $infant = ReportTemplateResolver::resolve(
            tenantId: $this->businessA->id,
            serviceId: null,
            modalityId: $modality,
            bodyRegion: 'Brain',
            ageGroup: 'infant',
            sex: null,
            contrast: 'without',
            userId: $radiologist->id,
        );

        $this->assertSame('region_age', $infant['tier']);
        $this->assertSame('Infant Cranial Ultrasound (Neonatal Head)', $infant['template']->name);
    }

    public function test_a_chest_template_can_never_be_served_on_a_brain_study(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $study = $this->makeStudy(
            $this->businessA,
            $this->tenantService($this->businessA, 'CT-BRAIN-NC'),
            $this->makePatient($this->businessA, 'Adult Patient', 44),
        );

        // Remove every brain template: the chest CT template must NOT move in.
        ReportTemplate::where('business_id', $this->businessA->id)
            ->where('body_region', 'Brain')
            ->update(['is_archived' => true]);

        $match = $this->resolveForAppointment($study, $radiologist->id);

        $this->assertNull($match['template']);
    }

    public function test_region_agnostic_templates_are_the_only_modality_fallback(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        // No region on the service at all → a modality-wide template applies.
        ReportTemplate::create([
            'name' => 'CT — Generic Study Baseline',
            'modality_id' => $this->tenantService($this->businessA, 'CT-BRAIN-NC')->modality_id,
            'body_region' => null,
            'age_group' => null,
            'business_id' => $this->businessA->id,
            'created_by' => $this->adminA->id,
            'scope' => 'tenant',
        ]);

        $match = ReportTemplateResolver::resolve(
            tenantId: $this->businessA->id,
            serviceId: null,
            modalityId: $this->tenantService($this->businessA, 'CT-BRAIN-NC')->modality_id,
            bodyRegion: null,
            ageGroup: 'adult',
            sex: null,
            contrast: null,
            userId: $radiologist->id,
        );

        $this->assertSame('modality', $match['tier']);
        $this->assertSame('CT — Generic Study Baseline', $match['template']->name);
    }

    public function test_a_sex_specific_template_is_not_served_to_another_sex(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $study = $this->makeStudy(
            $this->businessA,
            $this->tenantService($this->businessA, 'MG-BILATERAL'),
            $this->makePatient($this->businessA, 'Male Patient', 50, 'male'),
        );

        $match = $this->resolveForAppointment($study, $radiologist->id);

        $this->assertNull($match['template']);
    }

    public function test_another_clinics_template_is_never_a_candidate(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        ReportTemplate::create([
            'name' => 'Beta Clinic CT Brain Baseline',
            'modality_id' => $this->tenantService($this->businessB, 'CT-BRAIN-NC')->modality_id,
            'body_region' => 'Brain',
            'business_id' => $this->businessB->id,
            'created_by' => $this->businessB->created_by,
            'scope' => 'tenant',
        ]);

        $study = $this->makeStudy(
            $this->businessA,
            $this->tenantService($this->businessA, 'CT-BRAIN-NC'),
            $this->makePatient($this->businessA, 'Adult Patient', 38),
        );

        $match = $this->resolveForAppointment($study, $radiologist->id);

        $this->assertNotSame('Beta Clinic CT Brain Baseline', $match['template']?->name);
    }

    public function test_resolution_uses_the_requesting_radiologists_personal_template_over_a_shared_one(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        ReportTemplate::create([
            'name' => 'My HRCT Chest Baseline',
            'modality_id' => $this->tenantService($this->businessA, 'CT-CHEST-HR')->modality_id,
            'body_region' => 'Chest',
            'business_id' => $this->businessA->id,
            'created_by' => $radiologist->id,
            'scope' => 'personal',
        ]);

        $study = $this->makeStudy(
            $this->businessA,
            $this->tenantService($this->businessA, 'CT-CHEST-HR'),
            $this->makePatient($this->businessA, 'Adult Patient', 60),
        );

        $match = $this->resolveForAppointment($study, $radiologist->id);

        $this->assertSame('My HRCT Chest Baseline', $match['template']->name);
    }

    public function test_resolve_endpoint_explains_the_match_and_is_permission_gated(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $study = $this->makeStudy(
            $this->businessA,
            $this->tenantService($this->businessA, 'CT-BRAIN-NC'),
            $this->makePatient($this->businessA, 'Adult Patient', 41),
        );

        $this->actingAs($radiologist)
            ->postJson('/api/v1/reporting/templates/resolve', ['appointmentId' => (int) $study->id])
            ->assertOk()
            ->assertJsonPath('data.tier', 'procedure')
            ->assertJsonPath('data.matched.name', 'CT Brain (Non-Contrast) — Adult Baseline')
            ->assertJsonPath('data.ageGroupLabel', 'Adult (18–64 years)');

        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'receptionist'))
            ->postJson('/api/v1/reporting/templates/resolve', ['appointmentId' => (int) $study->id])
            ->assertForbidden();
    }

    public function test_resolve_endpoint_refuses_another_clinics_study_and_service(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $foreignStudy = $this->makeStudy(
            $this->businessB,
            $this->tenantService($this->businessB, 'CT-BRAIN-NC'),
            $this->makePatient($this->businessB, 'Beta Patient', 40),
        );

        $this->actingAs($radiologist)
            ->postJson('/api/v1/reporting/templates/resolve', ['appointmentId' => (int) $foreignStudy->id])
            ->assertNotFound();

        $this->actingAs($radiologist)
            ->postJson('/api/v1/reporting/templates/resolve', [
                'serviceId' => (int) $this->tenantService($this->businessB, 'CT-BRAIN-NC')->id,
            ])
            ->assertNotFound();
    }

    public function test_a_radiologist_may_curate_personal_templates_but_not_shared_ones(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $modalityId = $this->tenantService($this->businessA, 'CT-BRAIN-NC')->modality_id;

        // Shared clinic content is governed: the radiologist cannot publish it.
        $this->actingAs($radiologist)->postJson('/api/v1/report-templates', [
            'name' => 'Shared Baseline',
            'modalityId' => $modalityId,
            'scope' => 'tenant',
            'impression' => 'x',
        ])->assertForbidden();

        // Their own private template needs only report-authoring rights.
        $created = $this->actingAs($radiologist)->postJson('/api/v1/report-templates', [
            'name' => 'My Private Baseline',
            'modalityId' => $modalityId,
            'scope' => 'personal',
            'impression' => 'x',
            'structuredFields' => [
                ['label' => 'Midline shift', 'type' => 'radio', 'options' => ['Absent', 'Present'], 'normalText' => 'No midline shift.'],
            ],
        ])->assertCreated();

        $templateId = $created->json('data.template.id');
        $this->assertSame('personal', $created->json('data.template.scope'));
        $this->assertSame(1, $created->json('data.template.version'));

        $fields = $created->json('data.template.structuredFields');
        $this->assertSame('midline_shift', $fields[0]['key']);
        $this->assertSame('No midline shift.', $fields[0]['normalText']);

        // The author may edit it; a colleague's shared-template rights are not
        // needed for their own copy.
        $this->actingAs($radiologist)->putJson("/api/v1/report-templates/{$templateId}", [
            'name' => 'My Private Baseline v2',
            'modalityId' => $modalityId,
            'scope' => 'personal',
            'impression' => 'y',
        ])->assertOk()->assertJsonPath('data.template.version', 2);

        // But an unrelated staff member cannot.
        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'technologist'))
            ->putJson("/api/v1/report-templates/{$templateId}", [
                'name' => 'Hijacked',
                'modalityId' => $modalityId,
                'impression' => 'z',
            ])->assertForbidden();
    }

    public function test_duplicate_creates_a_private_copy_and_archive_retires_a_template(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $shared = ReportTemplate::where('business_id', $this->businessA->id)
            ->where('name', 'Whole Abdomen Ultrasound (Normal)')
            ->firstOrFail();

        $copy = $this->actingAs($radiologist)
            ->postJson("/api/v1/report-templates/{$shared->id}/duplicate", ['scope' => 'personal'])
            ->assertCreated();

        $this->assertSame('personal', $copy->json('data.template.scope'));
        $this->assertStringContainsString('(copy)', $copy->json('data.template.name'));

        // Archival, not deletion: historical reports keep their provenance.
        $this->actingAs($radiologist)
            ->postJson("/api/v1/report-templates/{$shared->id}/archive", ['archived' => true])
            ->assertForbidden(); // shared content is not theirs to retire

        $this->actingAs($this->adminA)
            ->postJson("/api/v1/report-templates/{$shared->id}/archive", ['archived' => true])
            ->assertOk()
            ->assertJsonPath('data.template.isArchived', true);

        $this->assertTrue($shared->fresh()->is_archived);
    }
}
