<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * AI safety-screening triage (TypeSafe System One / Jev via the Vercel AI
 * Gateway). The tests are hermetic: the HTTP layer is always faked and the
 * gateway key is empty in phpunit.xml, so no test can reach the real API.
 *
 * The invariant under test: triage is ADVISORY. Whatever Jev answers, the
 * deterministic screening clearance gate keeps exactly the behaviour proven
 * in StudyWorkflowTest — triage can add prioritisation signal, never clear
 * or block a study.
 */
class ScreeningTriageTest extends ApiTestCase
{
    /** @return array{0: User, 1: string} [technician, studyId] */
    private function mriStudy(): array
    {
        $tech = $this->makeStaff($this->businessA, $this->adminA, 'technician');

        // Book an MRI lumbar study (requires_screening = true in the seeds).
        $this->actingAs($this->adminA)->postJson('/api/v1/studies', [
            'newPatient' => ['name' => 'Triage Patient', 'phone' => '+44 7000 111222', 'age' => 52, 'gender' => 'female'],
            'serviceId' => $this->tenantService($this->businessA, 'MR-LUMBAR')->id,
            'date' => now()->toDateString(),
            'time' => '11:00 AM',
            'priority' => 'routine',
        ])->assertCreated();

        $studies = $this->actingAs($this->adminA)->getJson('/api/v1/studies')
            ->assertOk()
            ->json('data.studies');

        $studyId = $studies[0]['id'] ?? null;
        abort_if($studyId === null, 500, 'No study in worklist fixture');

        return [$tech, (string) $studyId];
    }

    /** Answer every seeded MRI safety question with the given value. */
    private function answerForm(User $tech, string $studyId, string $value): array
    {
        $form = $this->actingAs($tech)->getJson("/api/v1/studies/{$studyId}/screening")->assertOk()->json('data.form');

        // The baseline tenant seeds an MRI form with SIX safety questions.
        $questions = $form['questions'] ?? [];
        $this->assertNotEmpty($questions, 'The MRI screening form fixture must have questions');

        $answers = collect($questions)->map(fn ($q) => [
            'questionId' => $q['id'],
            'answerValue' => $value,
        ])->all();

        return $this->actingAs($tech)->postJson("/api/v1/studies/{$studyId}/screening", ['answers' => $answers])
            ->assertOk()
            ->json('data.study');
    }

    private function fakeJev(array $answers, int $status = 200): void
    {
        Http::fake([
            'ai-gateway.vercel.sh/v1/evaluate' => Http::response([
                'model' => 'typesafe-ai/jev',
                'answers' => $answers,
                'usage' => ['inputTokens' => 420, 'outputTokens' => 71],
            ], $status),
        ]);
    }

    private function confidentRoutineAnswers(): array
    {
        return [
            'proceed_with_exam' => ['type' => 'boolean', 'probability' => 0.99],
            'urgency' => [
                'type' => 'choice',
                'choice' => 'proceed_with_routine_protocol',
                'probabilities' => ['proceed_with_routine_protocol' => 0.9, 'review_before_exam' => 0.1, 'urgent_radiologist_consultation' => 0.0],
                'confidence' => 0.8,
            ],
            'safety_risk' => [
                'type' => 'score', 'score' => 0.2,
                'probabilities' => ['0' => 0.8, '1' => 0.2, '2' => 0.0, '3' => 0.0, '4' => 0.0],
                'confidence' => 0.85,
            ],
        ];
    }

    public function test_clean_screening_with_confident_routine_judgment_is_cleared_and_stores_triage(): void
    {
        config()->set('ris.typesafe.enabled', true);
        config()->set('ris.typesafe.api_key', 'test-key');
        $this->fakeJev($this->confidentRoutineAnswers());

        [$tech, $studyId] = $this->mriStudy();
        $study = $this->answerForm($tech, $studyId, 'no');

        // Deterministic gate unaffected: no flagged answers -> cleared.
        $this->assertTrue($study['screeningCleared']);

        $triage = $study['screeningTriage'];
        $this->assertNotNull($triage);
        $this->assertSame('cleared', $triage['decision']);
        $this->assertSame('proceed_with_routine_protocol', $triage['urgency']['choice']);
        $this->assertEqualsWithDelta(0.99, $triage['proceedProbability'], 0.0001);
        $this->assertFalse($triage['degraded']);
        $this->assertSame('typesafe-ai/jev', $triage['model']);
    }

    public function test_model_may_not_clear_a_deterministically_blocked_study(): void
    {
        config()->set('ris.typesafe.enabled', true);
        config()->set('ris.typesafe.api_key', 'test-key');
        // Jev is fully confident the exam can proceed — the rules must still win.
        $this->fakeJev([
            'proceed_with_exam' => ['type' => 'boolean', 'probability' => 1.0],
            'urgency' => [
                'type' => 'choice',
                'choice' => 'proceed_with_routine_protocol',
                'probabilities' => ['proceed_with_routine_protocol' => 1.0, 'review_before_exam' => 0.0, 'urgent_radiologist_consultation' => 0.0],
                'confidence' => 1.0,
            ],
            'safety_risk' => ['type' => 'score', 'score' => 0.0, 'probabilities' => ['0' => 1.0, '1' => 0.0, '2' => 0.0, '3' => 0.0, '4' => 0.0], 'confidence' => 1.0],
        ]);

        [$tech, $studyId] = $this->mriStudy();

        // Pacemaker = "yes": the deterministic gate stays blocked...
        $study = $this->answerForm($tech, $studyId, 'yes');

        $this->assertFalse($study['screeningCleared'], 'The model must never clear what the rules block.');

        // A naively optimistic model cannot paint a blocked study green:
        // the deterministic gate caps the advisory verdict at "review".
        $triage = $study['screeningTriage'];
        $this->assertNotNull($triage);
        $this->assertSame('review', $triage['decision']);
    }

    public function test_low_confidence_answers_route_to_review_even_when_probabilities_look_fine(): void
    {
        config()->set('ris.typesafe.enabled', true);
        config()->set('ris.typesafe.api_key', 'test-key');
        $this->fakeJev([
            'proceed_with_exam' => ['type' => 'boolean', 'probability' => 0.99],
            // Confident-looking proceed probability but a LOW-CONFIDENCE choice:
            // the distribution is spread, so code must route to human review.
            'urgency' => [
                'type' => 'choice',
                'choice' => 'proceed_with_routine_protocol',
                'probabilities' => ['proceed_with_routine_protocol' => 0.5, 'review_before_exam' => 0.5, 'urgent_radiologist_consultation' => 0.0],
                'confidence' => 0.2,
            ],
            'safety_risk' => ['type' => 'score', 'score' => 0.1, 'probabilities' => ['0' => 0.9, '1' => 0.1, '2' => 0.0, '3' => 0.0, '4' => 0.0], 'confidence' => 0.9],
        ]);

        [$tech, $studyId] = $this->mriStudy();
        $study = $this->answerForm($tech, $studyId, 'no');

        $this->assertSame('review', $study['screeningTriage']['decision']);
    }

    public function test_gateway_failure_fails_open_and_marks_existing_triage_degraded(): void
    {
        config()->set('ris.typesafe.enabled', true);
        config()->set('ris.typesafe.api_key', 'test-key');

        [$tech, $studyId] = $this->mriStudy();

        // Call 1 (submission) succeeds; calls 2-3 (re-run + its retry) fail.
        // One fake with a sequence — a second Http::fake() call would ADD a
        // stub rather than replace the first (earliest match wins).
        Http::fake([
            'ai-gateway.vercel.sh/v1/evaluate' => Http::sequence()
                ->push(['model' => 'typesafe-ai/jev', 'answers' => $this->confidentRoutineAnswers(),
                        'usage' => ['inputTokens' => 420, 'outputTokens' => 71]], 200)
                ->push('overloaded', 529)
                ->push('overloaded', 529),
        ]);

        $study = $this->answerForm($tech, $studyId, 'no');
        $this->assertNotNull($study['screeningTriage']);

        $this->actingAs($tech)->postJson("/api/v1/studies/{$studyId}/screening/triage")
            ->assertOk()
            ->assertJsonPath('data.triage', null);

        // The stored judgment must not look fresh after a failed re-run.
        $payload = Appointment::find($studyId)->screening_triage;
        $this->assertIsArray($payload);
        $this->assertTrue((bool) ($payload['degraded'] ?? false), 'A stored judgment must be marked degraded after a failed re-run.');

        // Screening still answers fine — fail-open end to end.
        $this->actingAs($tech)->getJson("/api/v1/studies/{$studyId}/screening")->assertOk();
    }

    public function test_triage_is_inert_without_configuration(): void
    {
        // phpunit.xml keeps the key empty; no Http::fake() => any outbound
        // request would explode the test. Assert the client never fires.
        config()->set('ris.typesafe.enabled', true);
        config()->set('ris.typesafe.api_key', null);

        [$tech, $studyId] = $this->mriStudy();
        $study = $this->answerForm($tech, $studyId, 'no');

        $this->assertTrue($study['screeningCleared']);
        $this->assertNull($study['screeningTriage']);
    }
}
