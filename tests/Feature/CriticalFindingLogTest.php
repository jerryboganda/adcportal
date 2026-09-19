<?php

namespace Tests\Feature;

use App\Models\CriticalFindingLog;
use App\Models\RadiologyReport;

/**
 * A telephone notification about a life-threatening finding is a medico-legal
 * COMMUNICATION EVENT, not report prose. It needs its own recipient, channel,
 * read-back flag and timestamp — and it must survive independently of the
 * report text.
 */
class CriticalFindingLogTest extends ApiTestCase
{
    private function study(): \App\Models\Appointment
    {
        return $this->makeStudy(
            $this->businessA,
            $this->tenantService($this->businessA, 'CT-BRAIN-NC'),
            $this->makePatient($this->businessA, 'Critical Patient', 49),
        );
    }

    public function test_a_radiologist_records_the_communication_separately_from_the_impression(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $study = $this->study();

        $report = RadiologyReport::create([
            'appointment_id' => $study->id,
            'version' => 1,
            'type' => 'final',
            'findings' => 'Large acute subdural haematoma.',
            'impression' => 'Acute subdural haematoma with mass effect.',
            'critical_flag' => true,
            'authored_by' => $radiologist->id,
            'signed_by' => $radiologist->id,
            'signed_at' => now(),
            'locked_at' => now(),
            'business_id' => $this->businessA->id,
            'created_by' => $radiologist->id,
        ]);

        $created = $this->actingAs($radiologist)
            ->postJson("/api/v1/reporting/critical-findings/{$study->id}", [
                'summary' => 'Acute subdural haematoma requiring urgent neurosurgical review.',
                'notifiedTo' => 'Dr Imran Yousaf',
                'notifiedRole' => 'Consultant Neurosurgeon',
                'contact' => '+92 300 1234567',
                'method' => 'phone',
                'readBackVerified' => true,
                'adviceGiven' => 'Immediate transfer to neurosurgery.',
                'reportId' => (int) $report->id,
            ])
            ->assertCreated();

        $log = CriticalFindingLog::find($created->json('data.log.id'));
        $this->assertSame($study->id, $log->appointment_id);
        $this->assertSame($report->id, $log->report_id);
        $this->assertTrue($log->read_back_verified);
        $this->assertSame($radiologist->id, $log->communicated_by);

        // The report TEXT is untouched: the communication is not spliced into
        // the clinical impression.
        $this->assertSame('Acute subdural haematoma with mass effect.', $report->fresh()->impression);

        // The report records that an acknowledgement happened…
        $this->assertNotNull($report->fresh()->critical_acked_at);

        // …and the log is retrievable as its own record.
        $logs = $this->actingAs($radiologist)
            ->getJson("/api/v1/reporting/critical-findings/{$study->id}")
            ->assertOk()
            ->json('data.logs');
        $this->assertCount(1, $logs);
        $this->assertSame('Dr Imran Yousaf', $logs[0]['notifiedTo']);
        $this->assertSame('phone', $logs[0]['method']);
    }

    public function test_recording_a_critical_result_requires_signing_authority(): void
    {
        $study = $this->study();

        foreach (['receptionist', 'technologist'] as $role) {
            $this->actingAs($this->makeStaff($this->businessA, $this->adminA, $role))
                ->postJson("/api/v1/reporting/critical-findings/{$study->id}", [
                    'summary' => 'x',
                    'notifiedTo' => 'Dr X',
                    'method' => 'phone',
                ])
                ->assertForbidden();
        }
    }

    public function test_a_report_from_another_study_cannot_be_cited(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $study = $this->study();
        $other = $this->makeStudy(
            $this->businessA,
            $this->tenantService($this->businessA, 'DX-CHEST-PA'),
            $this->makePatient($this->businessA, 'Other Patient', 30),
        );

        $otherReport = RadiologyReport::create([
            'appointment_id' => $other->id,
            'version' => 1,
            'type' => 'draft',
            'findings' => 'x',
            'impression' => 'y',
            'business_id' => $this->businessA->id,
            'created_by' => $radiologist->id,
        ]);

        $this->actingAs($radiologist)
            ->postJson("/api/v1/reporting/critical-findings/{$study->id}", [
                'summary' => 'Mismatched.',
                'notifiedTo' => 'Dr X',
                'method' => 'phone',
                'reportId' => (int) $otherReport->id,
            ])
            ->assertStatus(422);
    }

    public function test_another_clinics_study_is_not_addressable(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $foreign = $this->makeStudy(
            $this->businessB,
            $this->tenantService($this->businessB, 'CT-BRAIN-NC'),
            $this->makePatient($this->businessB, 'Beta Patient', 40),
        );

        $this->actingAs($radiologist)
            ->postJson("/api/v1/reporting/critical-findings/{$foreign->id}", [
                'summary' => 'x',
                'notifiedTo' => 'Dr X',
                'method' => 'phone',
            ])
            ->assertNotFound();

        $this->actingAs($radiologist)
            ->getJson("/api/v1/reporting/critical-findings/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_prIors_return_the_patients_previous_studies_with_their_reports(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $patient = $this->makePatient($this->businessA, 'Longitudinal Patient', 66);
        $ct = $this->tenantService($this->businessA, 'CT-BRAIN-NC');

        $older = $this->makeStudy($this->businessA, $ct, $patient, ['date' => now()->subYear()->toDateString()]);
        $current = $this->makeStudy($this->businessA, $ct, $patient);

        RadiologyReport::create([
            'appointment_id' => $older->id,
            'version' => 1,
            'type' => 'final',
            'findings' => 'Old findings.',
            'impression' => 'Old impression.',
            'signed_by' => $radiologist->id,
            'signed_at' => now()->subYear(),
            'locked_at' => now()->subYear(),
            'business_id' => $this->businessA->id,
            'created_by' => $radiologist->id,
        ]);

        // A different patient's study must never appear as "prior".
        $otherPatient = $this->makePatient($this->businessA, 'Unrelated Patient', 44);
        $this->makeStudy($this->businessA, $ct, $otherPatient);

        $priors = $this->actingAs($radiologist)
            ->getJson("/api/v1/reporting/priors/{$current->id}")
            ->assertOk()
            ->json('data.priors');

        $this->assertCount(1, $priors);
        $this->assertSame((string) $older->id, $priors[0]['study']['id']);
        $this->assertSame('Old impression.', $priors[0]['report']['impression']);
        $this->assertSame('Final', $priors[0]['report']['statusLabel']);
    }

    public function test_report_search_finds_reports_by_text_patient_and_token(): void
    {
        $radiologist = $this->makeStaff($this->businessA, $this->adminA, 'radiologist');
        $study = $this->makeStudy(
            $this->businessA,
            $this->tenantService($this->businessA, 'US-ABD-PEL'),
            $this->makePatient($this->businessA, 'Searchable Patient', 35),
            ['token_number' => 4242],
        );

        RadiologyReport::create([
            'appointment_id' => $study->id,
            'version' => 1,
            'type' => 'final',
            'findings' => 'Mobile calculi within the gallbladder.',
            'impression' => 'Cholelithiasis.',
            'signed_by' => $radiologist->id,
            'signed_at' => now(),
            'locked_at' => now(),
            'business_id' => $this->businessA->id,
            'created_by' => $radiologist->id,
        ]);

        // Another clinic's identical text must not leak into the results.
        $betaStudy = $this->makeStudy(
            $this->businessB,
            $this->tenantService($this->businessB, 'US-ABD-PEL'),
            $this->makePatient($this->businessB, 'Beta Searchable', 30),
        );
        RadiologyReport::create([
            'appointment_id' => $betaStudy->id,
            'version' => 1,
            'type' => 'final',
            'findings' => 'Mobile calculi within the gallbladder.',
            'impression' => 'Cholelithiasis.',
            'signed_at' => now(),
            'locked_at' => now(),
            'business_id' => $this->businessB->id,
            'created_by' => $this->businessB->created_by,
        ]);

        $byClinicalText = $this->actingAs($radiologist)
            ->getJson('/api/v1/reporting/reports?q='.urlencode('Cholelithiasis'))
            ->assertOk();
        $this->assertSame(1, $byClinicalText->json('data.total'));
        $this->assertSame('Searchable Patient', $byClinicalText->json('data.reports.0.study.patientName'));

        $byPatient = $this->actingAs($radiologist)
            ->getJson('/api/v1/reporting/reports?q='.urlencode('Searchable Patient'))
            ->assertOk();
        $this->assertSame(1, $byPatient->json('data.total'));

        $byToken = $this->actingAs($radiologist)
            ->getJson('/api/v1/reporting/reports?q=4242')
            ->assertOk();
        $this->assertSame(1, $byToken->json('data.total'));

        // Status filter narrows to signed finals only.
        $drafts = $this->actingAs($radiologist)
            ->getJson('/api/v1/reporting/reports?status=draft')
            ->assertOk();
        $this->assertSame(0, $drafts->json('data.total'));
    }

    public function test_report_search_requires_report_viewing_rights(): void
    {
        $this->actingAs($this->makeStaff($this->businessA, $this->adminA, 'receptionist'))
            ->getJson('/api/v1/reporting/reports')
            ->assertForbidden();
    }
}
