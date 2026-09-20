<?php

namespace App\Services;

/**
 * Advisory billing anomaly review using TypeSafe's System One model (Jev) via
 * the Vercel AI Gateway — the skill's "evidence judgment" pattern applied to
 * the money path.
 *
 * One judgment per call, each with a code-owned confidence gate:
 *   - reviewShift(): after the cashier counts the drawer, does the variance
 *     look like honest noise, or does the day's money pattern warrant a
 *     second look?
 *
 * Contract mirrors CatalogReviewService / ScreeningTriageService: ADVISORY
 * ONLY. Never throws, never blocks reconciliation, returns null when the key
 * is missing, the gateway is down, or the judgment is too uncertain to act
 * on. The deterministic variance arithmetic (counted − expected) stays
 * authoritative — this service only adds judgment on TOP of it.
 */
class BillingAnomalyService
{
    public function __construct(private TypeSafeService $typesafe)
    {
    }

    /**
     * Review a counted shift. Returns:
     *   ['verdict' => 'balanced'|'minor'|'investigate', 'confidence' => float, 'note' => ?string]
     * or null when no judgment is available (fail-open).
     */
    public function reviewShift(
        float $cashExpected,
        float $cashCounted,
        float $totalCollected,
        float $refundedTotal,
        int $paymentCount,
    ): ?array {
        if (! $this->typesafe->enabled()) {
            return null;
        }

        $variance = round($cashCounted - $cashExpected, 2);
        $varianceAbs = abs($variance);
        $variancePct = $cashExpected > 0
            ? round(($varianceAbs / $cashExpected) * 100, 1)
            : ($varianceAbs > 0 ? 100.0 : 0.0);

        $state = [
            'artifact' => 'End-of-shift cash drawer reconciliation at a radiology clinic POS counter',
            'expected_cash' => $cashExpected,
            'counted_cash' => $cashCounted,
            'variance' => $variance,
            'variance_percent' => $variancePct,
            'total_collected_all_methods' => $totalCollected,
            'refunded_total' => $refundedTotal,
            'payment_transaction_count' => $paymentCount,
        ];

        $answers = $this->typesafe->evaluate($state, [
            'warrants_review' => [
                'type' => 'boolean',
                'instructions' => [
                    'You judge whether a cash-shift variance at a clinic cash counter warrants supervisory review before sign-off.',
                    'Answer NO (routine) when the variance is small (roughly under 1% of expected cash, or a rounding-sized amount), consistent with single-note counting slips or change-rounding.',
                    'Answer YES (investigate) when the variance is material relative to the day (roughly over 1-2% of expected cash), when refunds run high against collections, or when the pattern suggests repeated short-counting, skipped receipts, or drawer skimming.',
                    'Judge the PATTERN using all supplied numbers together; do not flag a rounding-level difference.',
                ],
            ],
        ]);

        $answer = $answers['answers']['warrants_review'] ?? null;

        if (! is_array($answer) || ! isset($answer['probability'])) {
            return null;
        }

        $probability = (float) $answer['probability'];

        // Uncertainty is information: a coin-flip judgment is withheld rather
        // than guessing, so a "clean" badge is never minted from noise.
        if ($probability < 0.75 && $probability > 0.25) {
            return null;
        }

        $investigate = $probability >= 0.5;

        return [
            'verdict' => $investigate ? 'investigate' : ($varianceAbs > 0 ? 'minor' : 'balanced'),
            'confidence' => round($probability, 2),
            'note' => $investigate
                ? 'Jev recommends a second look at this shift before sign-off (variance/refund pattern).'
                : null,
        ];
    }
}
