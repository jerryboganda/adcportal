<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin, fail-open client for TypeSafe's System One evaluation model (Jev),
 * reached through the Vercel AI Gateway.
 *
 * Wire format (verified against the gateway): POST {base_url}/v1/evaluate
 *
 *     {
 *       "model": "typesafe-ai/jev",
 *       "state": <string | object | array>,
 *       "questions": {
 *         "<question id>": {
 *           "type": "boolean" | "choice" | "score",
 *           "instructions": <string | object | array>,
 *           "criteria": <map for choice, ordered array for score>
 *         }
 *       }
 *     }
 *
 * The gateway surface names the yes/no question type "boolean"; TypeSafe's
 * own API docs (docs.typesafe.ai) call the same primitive "noul". Answers
 * come back under the same question ids:
 *
 *     boolean -> { type, probability }                       (0..1, P(yes))
 *     choice  -> { type, choice, probabilities{}, confidence }
 *     score   -> { type, score, probabilities{}, confidence }
 *
 * Contract: this service NEVER throws and NEVER blocks a user workflow.
 * `evaluate()` returns the decoded body or null — a null return means "no
 * judgment available" and callers must degrade gracefully. Confidence-based
 * behaviour lives in the CALLER (code owns policy, the model owns judgment).
 */
class TypeSafeService
{
    private ?string $apiKey;
    private string $baseUrl;
    private ?string $caBundle;
    private string $model;
    private int $timeoutSeconds;
    private int $maxAttempts;

    public function __construct()
    {
        $this->apiKey = config('ris.typesafe.api_key');
        $this->baseUrl = rtrim((string) config('ris.typesafe.base_url', 'https://ai-gateway.vercel.sh'), '/');
        $this->caBundle = config('ris.typesafe.ca_bundle');
        $this->model = (string) config('ris.typesafe.model', 'typesafe-ai/jev');
        $this->timeoutSeconds = max(1, (int) config('ris.typesafe.timeout_seconds', 6));
        $this->maxAttempts = max(1, (int) config('ris.typesafe.max_attempts', 2));
    }

    /** True when an API key is configured (the kill switch lives in config too). */
    public function enabled(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    /**
     * Evaluate independent typed questions over one shared state in a single
     * request (questions run in parallel and cannot see one another's
     * answers — ask everything that is independently useful).
     *
     * @param array $state     State for every question: strings, or named
     *                         JSON fields when the context has several parts.
     * @param array $questions Map of question id => ['type' => 'boolean'|'choice'|'score',
     *                         'instructions' => string|array, 'criteria' => array|map].
     *                         The id is code-only (never sent to the model), so every
     *                         question must carry its full meaning in `instructions`.
     * @return array|null Decoded response ({answers, usage, ...}) or null when
     *                    disabled, unreachable, or permanently rejected.
     */
    public function evaluate(array $state, array $questions): ?array
    {
        if (! $this->enabled() || $questions === []) {
            return null;
        }

        $payload = [
            'model' => $this->model,
            'state' => $state,
            'questions' => $questions,
        ];

        $attempt = 0;

        while ($attempt < $this->maxAttempts) {
            $attempt++;

            $response = null;

            try {
                $request = Http::withToken($this->apiKey)
                    ->timeout($this->timeoutSeconds)
                    ->acceptJson();

                if (is_string($this->caBundle) && $this->caBundle !== '') {
                    // Constrained environment (PHP without a configured CA
                    // store): verify against the configured bundle instead.
                    $request = $request->withOptions(['verify' => $this->caBundle]);
                }

                $response = $request->post($this->baseUrl.'/v1/evaluate', $payload);
            } catch (Throwable $e) {
                // Connection errors and timeouts are retryable — log and loop.
                Log::warning('TypeSafe evaluation request failed', [
                    'model' => $this->model,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($response !== null) {
                if ($response->successful()) {
                    return $response->json();
                }

                // 429/529/5xx are retryable per TypeSafe's guidance; anything
                // else is a client error that will not improve on retry.
                if (! in_array($response->status(), [429, 529], true) && $response->status() < 500) {
                    Log::warning('TypeSafe evaluation rejected', [
                        'model' => $this->model,
                        'status' => $response->status(),
                        'body' => substr((string) $response->body(), 0, 300),
                    ]);

                    return null;
                }

                Log::warning('TypeSafe evaluation retryable failure', [
                    'model' => $this->model,
                    'attempt' => $attempt,
                    'status' => $response->status(),
                ]);
            }

            if ($attempt < $this->maxAttempts) {
                // Exponential backoff with jitter, bounded by config.
                $delayMs = min(
                    (int) config('ris.typesafe.max_backoff_ms', 2000),
                    200 * (2 ** ($attempt - 1)) + random_int(0, 150)
                );
                usleep($delayMs * 1000);
            }
        }

        return null;
    }
}
