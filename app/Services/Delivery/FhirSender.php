<?php

namespace App\Services\Delivery;

use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Http;

/**
 * FHIR R4 sender. Delivery posts a FHIR Bundle (transaction type) to the
 * configured base URL with token auth when client credentials are stored.
 * The probe reaches {baseUrl}/metadata (the FHIR capability statement) —
 * a real FHIR conformance check, not a bare TCP/HTTP ping.
 */
class FhirSender implements ChannelSender
{
    public function send(TenantIntegration $integration, string $event, array $payload): DeliveryResult
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $secrets = is_array($integration->secrets) ? $integration->secrets : [];

        $baseUrl = rtrim((string) ($config['baseUrl'] ?? ''), '/');
        $clientId = (string) ($secrets['clientId'] ?? '');
        $clientSecret = (string) ($secrets['clientSecret'] ?? '');

        if ($baseUrl === '') {
            return DeliveryResult::skipped('FHIR base URL is not configured.');
        }

        $bundle = $this->buildBundle($event, $payload);
        $url = $baseUrl.'/';

        $start = microtime(true);

        try {
            $request = Http::timeout(10)->connectTimeout(5)->acceptJson();

            if ($clientId !== '' && $clientSecret !== '') {
                $request = $request->withBasicAuth($clientId, $clientSecret);
            }

            $response = $request->withHeaders(['Prefer' => 'return=representation'])
                ->post($url, $bundle);
        } catch (\Throwable $e) {
            return DeliveryResult::failed('FHIR request to '.$baseUrl.' failed: '.$e->getMessage(), (int) round((microtime(true) - $start) * 1000));
        }

        $latency = (int) round((microtime(true) - $start) * 1000);

        // A transaction Bundle answers 200 with a Bundle response; 4xx/5xx fail.
        if ($response->status() >= 200 && $response->status() < 300) {
            $decoded = $response->json() ?? [];

            return DeliveryResult::sent("FHIR Bundle accepted by {$baseUrl} (HTTP {$response->status()}).", $latency, [
                'http_status' => $response->status(),
                'resource_type' => $decoded['resourceType'] ?? null,
            ]);
        }

        $outcome = collect($response->json('issue') ?? [])
            ->pluck('diagnostics')
            ->filter()
            ->first();

        return DeliveryResult::failed("FHIR server returned HTTP {$response->status()}".($outcome ? ": {$outcome}" : " for {$baseUrl}."), $latency, [
            'http_status' => $response->status(),
        ]);
    }

    /** Real FHIR R4 transaction Bundle carrying the event as a Task + Communication. */
    private function buildBundle(string $event, array $payload): array
    {
        $uuid = fn (): string => 'urn:uuid:'.\Ramsey\Uuid\Uuid::uuid4()->toString();
        $taskFullUrl = $uuid();
        $commFullUrl = $uuid();

        return [
            'resourceType' => 'Bundle',
            'type' => 'transaction',
            'entry' => [
                [
                    'fullUrl' => $taskFullUrl,
                    'resource' => [
                        'resourceType' => 'Task',
                        'status' => 'requested',
                        'intent' => 'order',
                        'code' => [
                            'coding' => [[
                                'system' => 'http://polytronx.local/fhir/CodeSystem/events',
                                'code' => $event,
                                'display' => 'PolytronX RIS '.$event,
                            ]],
                        ],
                        'authoredOn' => now()->toIso8601String(),
                        'note' => [['text' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]],
                    ],
                    'request' => ['method' => 'POST', 'url' => 'Task'],
                ],
                [
                    'fullUrl' => $commFullUrl,
                    'resource' => [
                        'resourceType' => 'Communication',
                        'status' => 'completed',
                        'about' => [['reference' => $taskFullUrl]],
                        'sent' => now()->toIso8601String(),
                        'payload' => [[
                            'contentString' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ]],
                    ],
                    'request' => ['method' => 'POST', 'url' => 'Communication'],
                ],
            ],
        ];
    }

    /**
     * Probe helper used by the health check: GET {baseUrl}/metadata must
     * return a CapabilityStatement (resourceType: CapabilityStatement).
     */
    public static function probeMetadata(string $baseUrl, string $clientId = '', string $clientSecret = ''): array
    {
        $baseUrl = rtrim($baseUrl, '/');

        try {
            $request = Http::timeout(6)->connectTimeout(5)->acceptJson();

            if ($clientId !== '' && $clientSecret !== '') {
                $request = $request->withBasicAuth($clientId, $clientSecret);
            }

            $response = $request->get($baseUrl.'/metadata');
        } catch (\Throwable $e) {
            return ['status' => 'error', 'detail' => 'FHIR metadata request failed: '.$e->getMessage()];
        }

        if ($response->status() >= 200 && $response->status() < 300) {
            $resourceType = $response->json('resourceType');

            if ($resourceType === 'CapabilityStatement') {
                return ['status' => 'active', 'detail' => "FHIR R4 server confirmed at {$baseUrl} (CapabilityStatement received)."];
            }

            return ['status' => 'error', 'detail' => "Endpoint answered but is not FHIR (resourceType ".var_export($resourceType, true).")."];
        }

        return ['status' => 'error', 'detail' => "FHIR metadata returned HTTP {$response->status()}."];
    }
}
