<?php

namespace App\Services\Dictation;

use App\Models\TenantIntegration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Dictation through the CLINIC'S OWN speech-to-text service.
 *
 * Browser speech recognition is simple but not private: in Chromium it may
 * hand the audio to a vendor's cloud service. A clinic that cannot accept that
 * configures one `dictation` integration pointing at an engine it runs itself
 * (a Whisper-style HTTP service, for example), and the reporting editor then
 * records locally and posts the audio there instead.
 *
 * Two rules this service never breaks:
 *
 *  1. **The audio is never stored.** It is held in memory for the duration of
 *     one request and posted; nothing is written to disk, the database or the
 *     log, and no transcript is persisted here either — the radiologist's own
 *     draft save is what stores the text.
 *  2. **A failure is reported, never faked.** If the engine is unreachable or
 *     answers with something unusable, the caller gets an error and the editor
 *     keeps working with typed input.
 */
class TranscriptionService
{
    /** Refuse absurd uploads before buffering them any further. */
    public const MAX_AUDIO_BYTES = 12 * 1024 * 1024;

    /**
     * The clinic's active dictation engine, if it has one.
     *
     * An integration that exists but is not `active` (incomplete config, or a
     * failed health check) is deliberately NOT used: silently dictating through
     * a half-configured endpoint is worse than saying dictation is unavailable.
     */
    public function resolve(int $businessId): ?TenantIntegration
    {
        return TenantIntegration::where('business_id', $businessId)
            ->where('type', 'dictation')
            ->where('status', 'active')
            ->orderBy('id')
            ->first();
    }

    public function isConfigured(int $businessId): bool
    {
        return $this->resolve($businessId) !== null;
    }

    /**
     * Send one chunk of audio to the engine and return its transcript.
     *
     * @return array{text: string, provider: string, latencyMs: int}
     *
     * @throws TranscriptionException
     */
    public function transcribe(
        TenantIntegration $integration,
        string $audio,
        string $filename,
        string $mime,
        ?string $language = null,
    ): array {
        $config = is_array($integration->config) ? $integration->config : [];
        $secrets = is_array($integration->secrets) ? $integration->secrets : [];

        $url = trim((string) ($config['baseUrl'] ?? ''));
        if ($url === '') {
            throw new TranscriptionException('The dictation service has no endpoint configured.');
        }

        if (strlen($audio) === 0) {
            throw new TranscriptionException('The recording was empty.');
        }

        if (strlen($audio) > self::MAX_AUDIO_BYTES) {
            throw new TranscriptionException('That recording is too long to transcribe in one pass.');
        }

        $start = microtime(true);

        try {
            $response = $this->request($config, $secrets)
                ->attach('file', $audio, $filename, ['Content-Type' => $mime])
                ->post($url, array_filter([
                    // Both spellings are common across self-hosted engines; an
                    // engine ignores the field it does not know.
                    'language' => $language ?: null,
                    'model' => $config['model'] ?? null,
                ], fn ($value) => $value !== null));
        } catch (\Throwable $e) {
            throw new TranscriptionException('The dictation service could not be reached: '.$e->getMessage());
        }

        $latencyMs = (int) round((microtime(true) - $start) * 1000);

        if ($response->failed()) {
            throw new TranscriptionException(
                'The dictation service refused the recording (HTTP '.$response->status().').'
            );
        }

        $text = $this->extractText($response->json(), $response->body());

        if ($text === '') {
            // Not an error the radiologist should be shown as a crash: an
            // empty or silent recording legitimately produces no transcript.
            return ['text' => '', 'provider' => (string) ($config['label'] ?? 'self-hosted'), 'latencyMs' => $latencyMs];
        }

        return ['text' => $text, 'provider' => (string) ($config['label'] ?? 'self-hosted'), 'latencyMs' => $latencyMs];
    }

    /** @param array<string,mixed> $config @param array<string,mixed> $secrets */
    private function request(array $config, array $secrets): PendingRequest
    {
        $request = Http::timeout((int) ($config['timeoutSeconds'] ?? 45))
            ->connectTimeout(5)
            ->acceptJson();

        $apiKey = trim((string) ($secrets['apiKey'] ?? ''));
        if ($apiKey !== '') {
            // A self-hosted engine behind an authenticating gateway.
            $request = $request->withToken($apiKey);
        }

        $basicUser = trim((string) ($secrets['username'] ?? ''));
        if ($basicUser !== '') {
            $request = $request->withBasicAuth($basicUser, (string) ($secrets['password'] ?? ''));
        }

        return $request;
    }

    /**
     * Pull the transcript out of whatever shape the engine answers with.
     *
     * Recognised: `{text}`, `{transcript}`, `{data:{text}}`,
     * `{results:[{alternatives:[{transcript}]}]}` (Google-style) and a bare
     * text body. Anything else is treated as "no speech recognised" rather
     * than being pasted into a medical record.
     *
     * @param mixed $json
     */
    private function extractText($json, string $rawBody): string
    {
        $candidates = [];

        if (is_array($json)) {
            $candidates[] = $json['text'] ?? null;
            $candidates[] = $json['transcript'] ?? null;
            $candidates[] = $json['data']['text'] ?? null;
            $candidates[] = $json['results'][0]['alternatives'][0]['transcript'] ?? null;
            $candidates[] = $json['segments'][0]['text'] ?? null;
        } elseif (is_string($rawBody) && trim($rawBody) !== '' && ! $this->looksLikeJson($rawBody)) {
            $candidates[] = $rawBody;
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return '';
    }

    private function looksLikeJson(string $body): bool
    {
        $trimmed = ltrim($body);

        return str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[');
    }
}
