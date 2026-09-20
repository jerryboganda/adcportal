<?php

namespace App\Services;

/**
 * Advisory catalog review using TypeSafe's System One model (Jev) via the
 * Vercel AI Gateway — the skill's "evidence judgment" pattern applied to
 * clinical CONFIGURATION instead of patient state.
 *
 * Two independent judgments run in one parallel call over the same state:
 *   1. A drafted screening question is judged a SAFETY question (boolean).
 *   2. A procedure's preparation instructions are judged ADEQUATE (boolean).
 *
 * Contract mirrors ScreeningTriageService: this is ADVISORY ONLY. It never
 * blocks a save, never throws, and returns null when the key is missing, the
 * gateway is down, or the answer is too uncertain to act on. The deterministic
 * validation in MastersController remains authoritative.
 */
class CatalogReviewService
{
    public function __construct(private TypeSafeService $typesafe)
    {
    }

    /**
     * Review one drafted screening question. Returns:
     *   ['isSafetyQuestion' => bool, 'confidence' => float, 'note' => ?string]
     * or null when no judgment is available (fail-open).
     *
     * @param  list<string>  $siblingQuestions  Other questions on the same
     *                                          form, so the judgment is made
     *                                          in the form's context.
     */
    public function reviewScreeningQuestion(string $questionText, array $siblingQuestions = []): ?array
    {
        $questionText = trim($questionText);

        if ($questionText === '' || ! $this->typesafe->enabled()) {
            return null;
        }

        $state = [
            'artifact' => 'MRI/radiology safety screening questionnaire question',
            'drafted_question' => $questionText,
            'other_questions_on_form' => array_values(array_filter(array_map('trim', $siblingQuestions))),
        ];

        $answers = $this->typesafe->evaluate($state, [
            'is_safety_question' => [
                'type' => 'boolean',
                'instructions' => 'Does this drafted question probe a genuine patient-safety risk for the imaging exam (implants, pregnancy, allergies, renal function, claustrophobia requiring intervention, prior reactions, metal exposure)? Answer YES only for real safety relevance, not administrative or billing concerns.',
            ],
        ]);

        $answer = $answers['answers']['is_safety_question'] ?? null;

        if (! is_array($answer) || ! isset($answer['probability'])) {
            return null;
        }

        $confidence = (float) $answer['probability'];

        // Uncertainty is information: a barely-above-half judgment is NOT a
        // confident advisory — withhold it rather than guess.
        if ($confidence < 0.75 && $confidence > 0.25) {
            return null;
        }

        return [
            'isSafetyQuestion' => $confidence >= 0.5,
            'confidence' => round($confidence, 2),
            'note' => $confidence >= 0.5 ? null : 'Jev is not confident this probes a real safety risk — consider rewording or removing it.',
        ];
    }

    /**
     * Review a procedure's patient-preparation instructions against what the
     * procedure actually requires. Same fail-open advisory contract.
     *
     * @return array{isAdequate: bool, confidence: float, note: ?string}|null
     */
    public function reviewPreparationInstructions(string $procedureName, bool $requiresContrast, bool $requiresScreening, string $instructions): ?array
    {
        $instructions = trim($instructions);

        if ($instructions === '' || $instructions === 'No special preparation needed.' || ! $this->typesafe->enabled()) {
            return null;
        }

        $state = [
            'artifact' => 'Patient preparation instructions shown before a diagnostic imaging procedure',
            'procedure' => $procedureName,
            'uses_iv_contrast' => $requiresContrast ? 'yes' : 'no',
            'requires_safety_screening' => $requiresScreening ? 'yes' : 'no',
            'instructions' => $instructions,
        ];

        $answers = $this->typesafe->evaluate($state, [
            'is_adequate' => [
                'type' => 'boolean',
                'instructions' => 'Are these preparation instructions clinically adequate? Answer NO when a required step is missing for the stated procedure (e.g. fasting, hydration, medication holds, recent-creatinine requirement for contrast studies, metal removal for MRI, pregnancy screening guidance).',
            ],
        ]);

        $answer = $answers['answers']['is_adequate'] ?? null;

        if (! is_array($answer) || ! isset($answer['probability'])) {
            return null;
        }

        $confidence = (float) $answer['probability'];

        if ($confidence < 0.75 && $confidence > 0.25) {
            return null;
        }

        $adequate = $confidence >= 0.5;

        return [
            'isAdequate' => $adequate,
            'confidence' => round($confidence, 2),
            'note' => $adequate
                ? null
                : 'Jev thinks a preparation step may be missing for this procedure — please double-check against your protocol.',
        ];
    }
}
