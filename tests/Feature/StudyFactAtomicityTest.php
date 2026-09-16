<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;

/**
 * Backend-engineering skill verification:
 *
 * 1. UNIT OF WORK — a workflow transition commits state AND its domain fact
 *    (clinical notification) atomically. A crash between "state saved" and
 *    "notification written" previously lost the fact (controller wrote it
 *    after commit); a failed fact write now rolls the transition back.
 * 2. FACT PROJECTION — booking/check-in still produce the exact notifications
 *    the SPA renders (no duplicates, no missing facts).
 * 3. DATA ACCESS — screening submission batch-loads questions (N+1 removed)
 *    while keeping the strict tenant/unknown-question failure.
 * 4. ERROR CONTRACT — 403/404 abort paths answer structured JSON on /api/*.
 */
class StudyFactAtomicityTest extends ApiTestCase
{
    private function svc(): Service
    {
        return Service::where('business_id', $this->businessA->id)->firstOrFail();
    }

    private function book(string $patient, string $priority = 'routine'): array
    {
        $receptionist = $this->makeStaff($this->businessA, $this->adminA, 'receptionist');

        return $this->actingAs($receptionist)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => $patient, 'gender' => 'female'],
            'serviceId' => $this->svc()->id,
            'date' => now()->toDateString(),
            'time' => '10:30 AM',
            'priority' => $priority,
        ])->assertCreated()->json('data.study');
    }

    // ==================== 1. Unit of work ====================

    public function test_booking_rollback_also_rolls_back_the_fact(): void
    {
        // A failing listener after the projector proves BOTH effects share one
        // transaction: the projector's notification row must vanish with the
        // booking row when the transaction aborts.
        Event::listen(\App\Events\Study\StudyBooked::class, fn () => throw new RuntimeException('forced failure'));

        $response = $this->actingAs($this->adminA)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Rollback Probe', 'gender' => 'female'],
            'serviceId' => $this->svc()->id,
            'date' => now()->toDateString(),
            'time' => '09:00 AM',
            'priority' => 'routine',
        ]);

        $response->assertStatus(500);

        $this->assertFalse(
            Appointment::where('name', 'Rollback Probe')->exists(),
            'booking row survived the aborted transaction'
        );
        $this->assertFalse(
            AppNotification::where('patient_name', 'Rollback Probe')->exists(),
            'notification fact survived the aborted transaction'
        );
    }

    public function test_checkin_rollback_also_rolls_back_the_fact(): void
    {
        $study = $this->book('Checkin Rollback');
        $id = $study['id'];

        // The booking already projected exactly one fact; the aborted
        // check-in must add NO further fact and must not change the state.
        $factsBefore = AppNotification::where('appointment_id', $id)->count();
        $this->assertSame(1, $factsBefore);

        Event::listen(\App\Events\Study\StudyCheckedIn::class, fn () => throw new RuntimeException('forced failure'));

        $tech = $this->makeStaff($this->businessA, $this->adminA, 'technologist');
        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/transition", ['action' => 'checkin'])
            ->assertStatus(500);

        $fresh = Appointment::findOrFail($id);
        $this->assertSame('booked', $fresh->workflow_state, 'state change survived the aborted transaction');
        $this->assertNull($fresh->checked_in_at);
        $this->assertSame(
            1,
            AppNotification::where('appointment_id', $id)->count(),
            'check-in fact survived the aborted transaction'
        );
    }

    // ==================== 2. Fact projection ====================

    public function test_booking_projects_exactly_one_notification_fact(): void
    {
        $study = $this->book('Fact Probe Routine');

        $notifications = AppNotification::where('appointment_id', $study['id'])->get();

        $this->assertCount(1, $notifications, 'exactly one fact per booking');
        $this->assertSame('workflow', $notifications[0]->category);
        $this->assertSame('checkin', $notifications[0]->target_tab);
        $this->assertStringContainsString('New Appointment Booked', $notifications[0]->title);
    }

    public function test_stat_booking_projects_critical_fact(): void
    {
        $study = $this->book('Fact Probe STAT', 'stat');

        $notifications = AppNotification::where('appointment_id', $study['id'])->get();

        $this->assertCount(1, $notifications);
        $this->assertSame('stat', $notifications[0]->category);
        $this->assertSame('critical', $notifications[0]->priority);
        $this->assertStringContainsString('STAT Booking Created', $notifications[0]->title);
    }

    public function test_checkin_projects_exactly_one_notification_fact(): void
    {
        $study = $this->book('Fact Probe Checkin');
        $id = $study['id'];

        $tech = $this->makeStaff($this->businessA, $this->adminA, 'technologist');
        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/transition", ['action' => 'checkin'])
            ->assertOk();

        // Booking fact + check-in fact — exactly two, no duplicates.
        $notifications = AppNotification::where('appointment_id', $id)->orderBy('id')->get();
        $this->assertCount(2, $notifications, 'one fact per event, none duplicated');
        $this->assertStringContainsString('New Appointment Booked', $notifications[0]->title);
        $this->assertStringContainsString('Patient Checked In', $notifications[1]->title);
    }

    // ==================== 3. Data access ====================

    public function test_screening_submission_loads_questions_in_one_query(): void
    {
        $study = $this->book('N+1 Probe');
        $id = $study['id'];
        $tech = $this->makeStaff($this->businessA, $this->adminA, 'technologist');

        $questions = $this->actingAs($tech)->getJson("/api/v1/studies/{$id}/screening")
            ->assertOk()->json('data.form.questions');
        $this->assertNotEmpty($questions);

        $answers = collect($questions)->map(fn ($q) => [
            'questionId' => $q['id'],
            'answerValue' => 'no',
        ])->all();

        DB::enableQueryLog();
        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/screening", ['answers' => $answers])
            ->assertOk()
            ->assertJsonPath('data.study.screeningCleared', true);
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $questionQueries = collect($log)->filter(
            fn ($e) => str_contains($e['query'], 'screening_questions')
        )->count();

        // Exactly TWO table touches are legitimate: the batch whereIn load
        // plus the eager load for response serialization. The legacy N+1
        // produced one extra single-row query PER ANSWER (≥3 here).
        $this->assertLessThanOrEqual(
            2,
            $questionQueries,
            "screening must batch-load questions, got {$questionQueries} queries"
        );
    }

    public function test_unknown_screening_question_still_fails_strictly(): void
    {
        $study = $this->book('Strict Probe');
        $id = $study['id'];
        $tech = $this->makeStaff($this->businessA, $this->adminA, 'technologist');

        $this->actingAs($tech)->postJson("/api/v1/studies/{$id}/screening", [
            'answers' => [['questionId' => 999999, 'answerValue' => 'no']],
        ])->assertStatus(422);
    }

    // ==================== 4. Error contract ====================

    public function test_permission_denied_answers_json_403_on_api(): void
    {
        $study = $this->book('403 Probe');
        $id = $study['id'];
        $tech = $this->makeStaff($this->businessA, $this->adminA, 'technologist');

        // 'reject' requires 'report edit' — a technician must not have it.
        $this->actingAs($tech)
            ->postJson("/api/v1/studies/{$id}/transition", ['action' => 'reject', 'reason' => 'blur'])
            ->assertStatus(403)
            ->assertJson(['message' => 'Permission denied.']);
    }

    public function test_cross_tenant_study_answers_json_404_on_api(): void
    {
        $study = $this->book('Tenant Probe');

        // Admin of tenant B reaching for tenant A's study — opaque 404 with a
        // JSON body (never an HTML error page or a redirect).
        $this->actingAs($this->adminB)
            ->putJson("/api/v1/studies/{$study['id']}", ['notes' => 'intrusion'])
            ->assertStatus(404)
            ->assertJsonStructure(['message']);
    }

    private function lastTransition(int $appointmentId): string
    {
        $row = DB::table('audit_logs')
            ->where('log_name', 'study_state_changed')
            ->when(
                \Schema::hasColumn('audit_logs', 'subject_id'),
                fn ($q) => $q->where('subject_id', $appointmentId)
            )
            ->orderByDesc('id')
            ->first();

        if ($row && isset($row->properties)) {
            $props = json_decode((string) $row->properties, true);

            return ($props['from'] ?? '?').'→'.($props['to'] ?? '?');
        }

        return 'n/a';
    }
}
