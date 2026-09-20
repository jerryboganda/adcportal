<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AI safety-screening triage built on TypeSafe's System One evaluation model
 * (Jev, reached through the Vercel AI Gateway — see TypeSafeService).
 *
 * Design contract (TypeSafe skill patterns: "verify and escalate" +
 * confidence-gated routing):
 *
 *  - The DETERMINISTIC gate stays authoritative: a study is only ever
 *    actually cleared by the existing flagsRisk()/override workflow in
 *    StudyController. This service NEVER grants or revokes clearance — it
 *    adds a parallel, typed clinical judgment over the same screening state
 *    to help staff prioritise review.
 *  - All independent questions are asked in ONE evaluation call over the
 *    same state (they run in parallel and cannot see one another's answers).
 *  - Confidence gates behaviour in CODE, not in the model:
 *      cleared  — the model is confident the exam can proceed routinely
 *      escalate — the model confidently flags urgent radiologist consultation
 *                 or a serious safety risk
 *      review   — everything else, including ANY low-confidence answer
 *  - Fail-open: any transport/parse failure degrades to null and the
 *    screening workflow continues untouched. Staff always see the raw form.
 *
 * PHI minimisation: the state carries clinical facts only (age band, gender,
 * allergies, history, screening answers). Names, MRN, DOB and contact
 * details are deliberately excluded from what leaves the tenant boundary.
 */
class ScreeningTriageService
{
    public function __construct(private TypeSafeService $typesafe)
    {
    }

    /**
     * Evaluate the study's screening state and persist the advisory result
     * on the appointment. Returns the stored payload, or null when triage
     * is disabled/unavailable (the appointment is left untouched unless a
     * previous judgment is being marked degraded).
     */
    public function evaluateAndStore(Appointment $appointment): ?array
    {
        // Zero queries when the AI layer is off: both the feature flag and the
        // key are checked BEFORE any database access, so a clinic running with
        // triage disabled (and every CI run with the empty test key) pays
        // nothing for the hook.
        if (! config('ris.typesafe.enabled') || ! $this->typesafe->enabled()) {
            return null;
        }

        $answers = $appointment->screeningAnswers()->with('question')->get();

        if ($answers->isEmpty()) {
            return null;
        }

        $result = $this->evaluate($appointment, $answers);

        if ($result === null) {
            // Degrade gracefully: keep any previous judgment visible but mark
            // it unavailable rather than letting it look fresh.
            $existing = $appointment->screening_triage;

            if (is_array($existing)) {
                $existing['degraded'] = true;

                try {
                    $appointment->forceFill(['screening_triage' => $existing])->save();
                } catch (Throwable $e) {
                    Log::warning('Screening triage degrade-mark failed', ['error' => $e->getMessage()]);
                }
            }

            return null;
        }

        $appointment->forceFill(['screening_triage' => $result])->save();

        return $result;
    }

    /**
     * Build the shared state, ask the three independent questions and turn
     * the raw answers into a persisted, code-gated decision.
     */
    private function evaluate(Appointment $appointment, $answers): ?array
    {
        $service = $appointment->ServiceData;
        $modality = $service?->modality;
        $patient = $appointment->CustomerData?->customer;

        $state = [
            'exam' => [
                'serviceName' => (string) ($service?->name ?? ''),
                'modality' => (string) ($modality?->code ?? ''),
                'contrastType' => (string) ($service?->contrast_type ?? 'none'),
            ],
            'patient' => [
                'ageYears' => (int) ($patient?->age ?? 0),
                'gender' => (string) ($patient?->gender ?? 'other'),
                'allergies' => (string) ($patient?->allergies ?? 'none recorded'),
                'medicalHistory' => (string) ($patient?->chronic_conditions ?? $patient?->description ?? ''),
            ],
            'screening' => [
                'deterministicGateCleared' => (bool) $appointment->screening_cleared,
                'answers' => $answers->map(fn ($a) => [
                    'question' => (string) ($a->question?->question_text ?? ''),
                    'answer' => (string) $a->answer_value,
                    'flaggedAsRiskByRules' => (bool) $a->is_risk,
                    'overrideReason' => $a->override_reason !== null ? (string) $a->override_reason : null,
                ])->all(),
            ],
            'technicianNotes' => (string) ($appointment->notes ?? ''),
        ];

        // The deterministic gate's verdict is policy input to the advisory
        // decision: a blocked study may never be painted green by the model.
        $gateCleared = (bool) $appointment->screening_cleared;

        $response = null;

        try {
            $response = $this->typesafe->evaluate($state, [
                'proceed_with_exam' => [
                    'type' => 'boolean',
                    'instructions' => 'Based on the completed safety screening, the patient can undergo this examination without further safety evaluation by the supervising clinician before acquisition.',
                ],
                'urgency' => [
                    'type' => 'choice',
                    'instructions' => 'What level of clinical review does this screening result require before the examination proceeds?',
                    'criteria' => [
                        'proceed_with_routine_protocol' => 'No screening answer suggests added risk; the standard workflow is safe.',
                        'review_before_exam' => 'At least one answer or history item warrants a clinician confirming it is safe before acquisition.',
                        'urgent_radiologist_consultation' => 'The screening suggests a potentially serious safety risk; a radiologist must be consulted urgently before acquisition.',
                    ],
                ],
                'safety_risk' => [
                    'type' => 'score',
                    'instructions' => 'Overall patient safety risk if the examination proceeds as scheduled, judging the screening answers, allergies and medical history together.',
                    'criteria' => [
                        'No screening signal of risk; proceed routinely.',
                        'Minor consideration, unlikely to affect safety.',
                        'Moderate concern; a clinician should look at it before acquisition.',
                        'High concern; examination should not proceed until reviewed.',
                        'Critical; immediate radiologist consultation, examination unsafe as scheduled.',
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            // TypeSafeService is already fail-open; this belt-and-braces guard
            // keeps an unexpected shape from breaking screening submission.
            Log::warning('Screening triage evaluation crashed', ['error' => $e->getMessage()]);
        }

        if (! is_array($response) || ! isset($response['answers']) || ! is_array($response['answers'])) {
            return null;
        }

        $raw = $response['answers'];
        $proceed = isset($raw['proceed_with_exam']['probability']) ? (float) $raw['proceed_with_exam']['probability'] : null;
        $urgency = is_array($raw['urgency'] ?? null) ? $raw['urgency'] : null;
        $risk = is_array($raw['safety_risk'] ?? null) ? $raw['safety_risk'] : null;

        return $this->decide($proceed, $urgency, $risk, $gateCleared, $response);
    }

    /**
     * Confidence-gated decision, evaluated in code against configurable
     * thresholds (calibrate on real data; see docs.typesafe.ai/confidence).
     *
     * The deterministic gate caps the advisory verdict: when the rule-based
     * screening gate is NOT cleared, the decision can be at most "review"
     * even if the model is confidently optimistic — staff must never see a
     * routine-clearance signal on a study the rules have blocked.
     */
    private function decide(?float $proceed, ?array $urgency, ?array $risk, bool $gateCleared, array $response): array
    {
        $escalateConfidence = (float) config('ris.typesafe.escalate_confidence', 0.6);
        $clearConfidence = (float) config('ris.typesafe.clear_confidence', 0.85);

        $urgencyChoice = $urgency['choice'] ?? null;
        $urgencyConfidence = isset($urgency['confidence']) ? (float) $urgency['confidence'] : 0.0;
        $riskScore = isset($risk['score']) && $risk['score'] !== null ? (float) $risk['score'] : null;
        $riskConfidence = isset($risk['confidence']) ? (float) $risk['confidence'] : 0.0;

        $decision = 'review';

        // Escalate only when the model is CONFIDENT about a serious risk.
        if (
            ($urgencyChoice === 'urgent_radiologist_consultation' && $urgencyConfidence >= $escalateConfidence)
            || ($riskScore !== null && $riskScore >= 3.0 && $riskConfidence >= $escalateConfidence)
        ) {
            $decision = 'escalate';
        } elseif (
            $proceed !== null
            && $proceed >= $clearConfidence
            && $urgencyChoice === 'proceed_with_routine_protocol'
            && $urgencyConfidence >= $escalateConfidence
            && ($riskScore === null || $riskScore < 1.0)
        ) {
            $decision = 'cleared';
        }

        // Policy cap: the advisory signal can be more cautious than the rules
        // (escalate) but never more permissive than them.
        if ($decision === 'cleared' && ! $gateCleared) {
            $decision = 'review';
        }
        // Anything else — including low confidence anywhere — stays "review".

        return [
            'provider' => 'vercel-ai-gateway',
            'model' => (string) ($response['model'] ?? config('ris.typesafe.model', 'typesafe-ai/jev')),
            'evaluatedAt' => now()->toIso8601String(),
            'decision' => $decision,
            'proceedProbability' => $proceed,
            'urgency' => $urgency !== null ? [
                'choice' => (string) ($urgency['choice'] ?? ''),
                'confidence' => $urgencyConfidence,
                'probabilities' => isset($urgency['probabilities']) && is_array($urgency['probabilities'])
                    ? array_map('floatval', $urgency['probabilities'])
                    : [],
            ] : null,
            'risk' => $risk !== null ? [
                'score' => $riskScore,
                'confidence' => $riskConfidence,
            ] : null,
            'usage' => [
                'inputTokens' => (int) ($response['usage']['inputTokens'] ?? 0),
                'outputTokens' => (int) ($response['usage']['outputTokens'] ?? 0),
            ],
            'degraded' => false,
        ];
    }
}
