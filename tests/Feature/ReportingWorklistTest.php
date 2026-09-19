<?php

namespace Tests\Feature;

use App\Models\RadiologyReport;

/**
 * The reading worklist is the radiologist's whole day. It must be filtered,
 * sorted and paginated IN SQL — a hospital's study table is not a browser
 * collection — and it must never show another clinic's work or a state that
 * isn't a reading state.
 */
class ReportingWorklistTest extends ApiTestCase
{
    private function seedWorklist(): array
    {
        $ct = $this->tenantService($this->businessA, 'CT-BRAIN-NC');
        $us = $this->tenantService($this->businessA, 'US-ABD-PEL');

        $stat = $this->makeStudy($this->businessA, $ct, $this->makePatient($this->businessA, 'Ayesha Khan', 34, 'female'), [
            'priority' => 'stat', 'token_number' => 101, 'acquired_at' => now()->subHour(),
        ]);
        $urgent = $this->makeStudy($this->businessA, $us, $this->makePatient($this->businessA, 'Bilal Ahmed', 52, 'male'), [
            'priority' => 'urgent', 'token_number' => 102, 'acquired_at' => now()->subHours(3),
        ]);
        $routine = $this->makeStudy($this->businessA, $ct, $this->makePatient($this->businessA, 'Chan 100% Test', 61, 'male'), [
            'priority' => 'routine', 'token_number' => 103, 'acquired_at' => now()->subHours(5),
        ]);
        // Not a reading state: must never appear on the worklist.
        $booked = $this->makeStudy($this->businessA, $ct, $this->makePatient($this->businessA, 'Not Acquired', 30), [
            'workflow_state' => 'booked', 'acquired_at' => null, 'token_number' => 104,
        ]);

        return compact('stat', 'urgent', 'routine', 'booked');
    }

    public function test_the_unreported_tab_lists_only_studies_awaiting_interpretation(): void
    {
        $worklist = $this->seedWorklist();
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $response = $this->actingAs($radiologist)
            ->getJson('/api/v1/reporting/worklist?tab=unreported')
            ->assertOk();

        $ids = collect($response->json('data.studies'))->pluck('id')->all();

        $this->assertCount(3, $ids);
        $this->assertContains((string) $worklist['stat']->id, $ids);
        $this->assertNotContains((string) $worklist['booked']->id, $ids);
        $this->assertSame(3, $response->json('data.counts.unreported'));
    }

    public function test_stat_and_urgent_studies_are_listed_first(): void
    {
        $worklist = $this->seedWorklist();
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $studies = $this->actingAs($radiologist)
            ->getJson('/api/v1/reporting/worklist?tab=unreported')
            ->assertOk()
            ->json('data.studies');

        $this->assertSame((string) $worklist['stat']->id, $studies[0]['id']);
        $this->assertSame('stat', $studies[0]['priority']);
        $this->assertSame('urgent', $studies[1]['priority']);
        $this->assertSame('routine', $studies[2]['priority']);
        $this->assertSame(2, $this->actingAs($radiologist)->getJson('/api/v1/reporting/worklist?tab=priority')->json('data.counts.priority'));
    }

    public function test_search_matches_patient_mrn_token_and_procedure(): void
    {
        $worklist = $this->seedWorklist();
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $byName = $this->actingAs($radiologist)
            ->getJson('/api/v1/reporting/worklist?q='.urlencode('Bilal'))
            ->json('data.studies');
        $this->assertCount(1, $byName);
        $this->assertSame('Bilal Ahmed', $byName[0]['patientName']);

        $byToken = $this->actingAs($radiologist)
            ->getJson('/api/v1/reporting/worklist?q=101')
            ->json('data.studies');
        $this->assertCount(1, $byToken);
        $this->assertSame('101', $byToken[0]['tokenNumber']);

        $byMrn = $this->actingAs($radiologist)
            ->getJson('/api/v1/reporting/worklist?q='.$worklist['routine']->CustomerData->mrn)
            ->json('data.studies');
        $this->assertSame((string) $worklist['routine']->id, $byMrn[0]['id']);

        $byProcedure = $this->actingAs($radiologist)
            ->getJson('/api/v1/reporting/worklist?q='.urlencode('Abdomen'))
            ->json('data.studies');
        $this->assertSame((string) $worklist['urgent']->id, $byProcedure[0]['id']);
    }

    public function test_search_folds_case_on_every_engine(): void
    {
        $this->seedWorklist();
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        // PostgreSQL's LIKE is case-sensitive and SQLite's is not, so a search
        // that only ever runs lowercase would silently break in production.
        foreach (['bilal', 'BILAL', 'BiLaL'] as $term) {
            $hits = $this->actingAs($radiologist)
                ->getJson('/api/v1/reporting/worklist?q='.urlencode($term))
                ->json('data.studies');

            $this->assertCount(1, $hits, "Search for '{$term}' should match 'Bilal Ahmed'.");
            $this->assertSame('Bilal Ahmed', $hits[0]['patientName']);
        }
    }

    public function test_a_percent_sign_in_a_patient_name_is_treated_literally(): void
    {
        $this->seedWorklist();
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        // "100%" must match the one patient whose name contains it, and a bare
        // "%" must not turn into "match everything".
        $match = $this->actingAs($radiologist)
            ->getJson('/api/v1/reporting/worklist?q='.urlencode('100%'))
            ->json('data.studies');
        $this->assertCount(1, $match);

        // A bare '%' is a literal character too: it matches only the patient
        // whose name contains it, not the whole worklist.
        $wildcard = $this->actingAs($radiologist)
            ->getJson('/api/v1/reporting/worklist?q='.urlencode('%'))
            ->json('data.studies');
        $this->assertCount(1, $wildcard);
        $this->assertSame('Chan 100% Test', $wildcard[0]['patientName']);
    }

    public function test_filters_narrow_the_list_and_pagination_is_server_side(): void
    {
        $worklist = $this->seedWorklist();
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $ctModalityId = $this->tenantService($this->businessA, 'CT-BRAIN-NC')->modality_id;

        $byModality = $this->actingAs($radiologist)
            ->getJson("/api/v1/reporting/worklist?modalityId={$ctModalityId}")
            ->json('data.studies');
        $this->assertCount(2, $byModality);

        $byPriority = $this->actingAs($radiologist)
            ->getJson('/api/v1/reporting/worklist?priority=routine')
            ->json('data.studies');
        $this->assertCount(1, $byPriority);
        $this->assertSame((string) $worklist['routine']->id, $byPriority[0]['id']);

        $page1 = $this->actingAs($radiologist)
            ->getJson('/api/v1/reporting/worklist?perPage=2&page=1')
            ->assertOk();
        $this->assertCount(2, $page1->json('data.studies'));
        $this->assertSame(3, $page1->json('data.total'));
        $this->assertTrue($page1->json('data.hasMore'));

        $page2 = $this->actingAs($radiologist)
            ->getJson('/api/v1/reporting/worklist?perPage=2&page=2')
            ->assertOk();
        $this->assertCount(1, $page2->json('data.studies'));
        $this->assertFalse($page2->json('data.hasMore'));
    }

    public function test_draft_and_finalized_tabs_track_report_state(): void
    {
        $worklist = $this->seedWorklist();
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        RadiologyReport::create([
            'appointment_id' => $worklist['stat']->id,
            'version' => 1,
            'type' => 'draft',
            'findings' => 'Draft findings.',
            'impression' => 'Draft impression.',
            'authored_by' => $radiologist->id,
            'business_id' => $this->businessA->id,
            'created_by' => $radiologist->id,
        ]);

        $drafts = $this->actingAs($radiologist)->getJson('/api/v1/reporting/worklist?tab=drafts')->assertOk();
        $this->assertSame(1, $drafts->json('data.counts.drafts'));
        $this->assertSame('Draft', $drafts->json('data.studies.0.reportStatus'));

        // A draft the caller did not author is not "my draft".
        $colleague = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $this->assertSame(
            0,
            $this->actingAs($colleague)->getJson('/api/v1/reporting/worklist?tab=drafts')->json('data.counts.drafts'),
        );

        // Signing moves the study to the finalized tab and out of unreported.
        $study = \App\Models\Appointment::find($worklist['stat']->id);
        app(\App\Http\Controllers\Api\V1\ReportController::class)
            ->signReport($study->radiologyReports()->first(), 'final');

        $finalized = $this->actingAs($radiologist)->getJson('/api/v1/reporting/worklist?tab=finalized')->assertOk();
        $this->assertSame(1, $finalized->json('data.counts.finalized'));
        $this->assertSame('Final', $finalized->json('data.studies.0.reportStatus'));

        $this->assertSame(
            2,
            $this->actingAs($radiologist)->getJson('/api/v1/reporting/worklist?tab=unreported')->json('data.counts.unreported'),
        );
    }

    public function test_report_status_filter_separates_not_started_from_drafted(): void
    {
        $worklist = $this->seedWorklist();
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        RadiologyReport::create([
            'appointment_id' => $worklist['routine']->id,
            'version' => 1,
            'type' => 'draft',
            'findings' => 'x',
            'impression' => 'y',
            'authored_by' => $radiologist->id,
            'business_id' => $this->businessA->id,
            'created_by' => $radiologist->id,
        ]);

        $this->assertSame(
            2,
            $this->actingAs($radiologist)->getJson('/api/v1/reporting/worklist?reportStatus=not_started')->json('data.total'),
        );
        $this->assertSame(
            1,
            $this->actingAs($radiologist)->getJson('/api/v1/reporting/worklist?reportStatus=draft')->json('data.total'),
        );
    }

    public function test_the_worklist_never_leaks_another_clinics_studies(): void
    {
        $this->seedWorklist();
        $this->makeStudy(
            $this->businessB,
            $this->tenantService($this->businessB, 'CT-BRAIN-NC'),
            $this->makePatient($this->businessB, 'Beta Patient', 30),
            ['priority' => 'stat', 'token_number' => 999],
        );

        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $names = collect($this->actingAs($radiologist)->getJson('/api/v1/reporting/worklist')->json('data.studies'))
            ->pluck('patientName');

        $this->assertNotContains('Beta Patient', $names->all());
    }

    public function test_the_worklist_requires_report_viewing_rights(): void
    {
        // `report manage` is "view reports": reception has no business seeing
        // the reading queue at all…
        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'receptionist'))
            ->getJson('/api/v1/reporting/worklist')
            ->assertForbidden();

        // …while the technologist role deliberately holds it (they track the
        // report status of their own studies) and gets a read-only view.
        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'technologist'))
            ->getJson('/api/v1/reporting/worklist')
            ->assertOk();
    }

    public function test_an_unknown_tab_is_rejected_rather_than_silently_defaulted(): void
    {
        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'radiologist'))
            ->getJson('/api/v1/reporting/worklist?tab=everything')
            ->assertStatus(422);
    }

    public function test_a_radiologist_can_claim_a_study_and_unassign_it(): void
    {
        $worklist = $this->seedWorklist();
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $studyId = (int) $worklist['stat']->id;

        $this->actingAs($radiologist)
            ->postJson("/api/v1/reporting/studies/{$studyId}/assign", ['radiologistId' => (int) $radiologist->id])
            ->assertOk()
            ->assertJsonPath('data.study.assignedRadiologistId', (string) $radiologist->id);

        $this->assertSame(
            1,
            $this->actingAs($radiologist)->getJson('/api/v1/reporting/worklist?tab=assigned')->json('data.counts.assigned'),
        );

        $this->actingAs($radiologist)
            ->postJson("/api/v1/reporting/studies/{$studyId}/assign", ['radiologistId' => null])
            ->assertOk()
            ->assertJsonPath('data.study.assignedRadiologistId', null);
    }

    public function test_pushing_work_onto_a_colleague_needs_the_assignment_permission(): void
    {
        $worklist = $this->seedWorklist();
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $colleague = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $studyId = (int) $worklist['stat']->id;

        // Claiming is allowed with report-authoring rights…
        $this->actingAs($radiologist)
            ->postJson("/api/v1/reporting/studies/{$studyId}/assign", ['radiologistId' => (int) $radiologist->id])
            ->assertOk();

        // …but assigning someone ELSE is an administrative act.
        $this->actingAs($radiologist)
            ->postJson("/api/v1/reporting/studies/{$studyId}/assign", ['radiologistId' => (int) $colleague->id])
            ->assertForbidden();

        // The clinic owner holds `study assign` and may do it.
        $this->actingAs($this->adminA)
            ->postJson("/api/v1/reporting/studies/{$studyId}/assign", ['radiologistId' => (int) $colleague->id])
            ->assertOk()
            ->assertJsonPath('data.study.assignedRadiologistName', $colleague->name);
    }

    public function test_a_non_reporting_staff_member_cannot_be_assigned_and_booked_studies_cannot_be_claimed(): void
    {
        $worklist = $this->seedWorklist();
        $receptionist = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        $this->actingAs($this->adminA)
            ->postJson("/api/v1/reporting/studies/{$worklist['stat']->id}/assign", ['radiologistId' => (int) $receptionist->id])
            ->assertStatus(422);

        $this->actingAs($this->adminA)
            ->postJson("/api/v1/reporting/studies/{$worklist['booked']->id}/assign", ['radiologistId' => (int) $this->adminA->id])
            ->assertStatus(422);
    }

    /**
     * Hand-off target for the dashboard's "Manage" button, global search and
     * the notification centre. Those boards hand over a study id, so the study
     * must be resolvable on its own — the queue is paginated and the row that
     * was clicked need not be on the page the SPA holds.
     */
    public function test_a_single_study_can_be_fetched_in_worklist_shape(): void
    {
        $worklist = $this->seedWorklist();
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');

        $study = $this->actingAs($radiologist)
            ->getJson('/api/v1/reporting/studies/'.$worklist['stat']->id)
            ->assertOk()
            ->json('data.study');

        $this->assertSame((string) $worklist['stat']->id, $study['id']);
        $this->assertSame('Ayesha Khan', $study['patientName']);
        $this->assertSame('stat', $study['priority']);
        $this->assertSame((int) $worklist['stat']->service_id, $study['serviceId']);
        $this->assertNotEmpty($study['serviceName']);
        $this->assertArrayHasKey('reportStatus', $study);
        $this->assertArrayHasKey('modalityCode', $study);
    }

    public function test_another_clinics_study_cannot_be_opened_by_id(): void
    {
        $foreign = $this->makeStudy(
            $this->businessB,
            $this->tenantService($this->businessB, 'CT-BRAIN-NC'),
            $this->makePatient($this->businessB, 'Beta Patient', 30),
            ['priority' => 'stat', 'token_number' => 999],
        );

        // A wrong-tenant id is a 404, never an empty 200: the SPA must not be
        // able to confirm that another clinic's study exists at all.
        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'radiologist'))
            ->getJson('/api/v1/reporting/studies/'.$foreign->id)
            ->assertNotFound();
    }

    public function test_a_study_that_is_not_awaiting_interpretation_cannot_be_opened(): void
    {
        $worklist = $this->seedWorklist();

        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'radiologist'))
            ->getJson('/api/v1/reporting/studies/'.$worklist['booked']->id)
            ->assertStatus(409);
    }

    public function test_opening_a_single_study_requires_report_viewing_rights(): void
    {
        $worklist = $this->seedWorklist();

        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'receptionist'))
            ->getJson('/api/v1/reporting/studies/'.$worklist['stat']->id)
            ->assertForbidden();
    }

    public function test_the_roster_lists_signing_radiologists_without_user_management_rights(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        $roster = $this->actingAs($radiologist)->getJson('/api/v1/reporting/roster')->assertOk()->json('data.radiologists');
        $names = collect($roster)->pluck('name');

        // Anyone who can sign a report is assignable; a receptionist is not.
        $this->assertContains($radiologist->name, $names->all());
        $this->assertNotContains('Receptionist of '.$this->businessA->name, $names->all());
        $this->assertTrue(collect($roster)->every(
            fn (array $row) => $row['id'] !== (string) $this->businessB->created_by
        ));
    }
}
