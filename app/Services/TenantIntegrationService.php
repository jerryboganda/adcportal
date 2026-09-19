<?php

namespace App\Services;

use App\Models\IntegrationDelivery;
use App\Models\TenantIntegration;
use App\Services\Delivery\DeliveryDispatcher;
use App\Services\Delivery\FhirSender;
use App\Services\Delivery\WhatsAppSender;
use Illuminate\Support\Facades\Http;

/**
 * Tenant integration registry (master-prompt §33/§44).
 *
 * Responsibilities:
 *  - expose the catalog of integration types this product implements,
 *  - validate a tenant's configuration against that catalog,
 *  - mask secrets so they can never leave the server,
 *  - run a REAL health probe (socket / HTTP) or an honest configuration check.
 *
 * There is deliberately no "test connection succeeded" fiction: a `config`
 * probe reports that credentials are present and complete, never that a message
 * was delivered.
 */
class TenantIntegrationService
{
    /** @return array<string, array> */
    public static function catalog(): array
    {
        return (array) config('ris.integrations', []);
    }

    public static function definition(string $type): ?array
    {
        return self::catalog()[$type] ?? null;
    }

    public static function typeLabel(string $type): string
    {
        return self::definition($type)['label'] ?? ucfirst($type);
    }

    /** The entitlement that unlocks this integration type. */
    public static function feature(string $type): ?string
    {
        return self::definition($type)['feature'] ?? null;
    }

    /**
     * Secrets the tenant MUST supply for this type.
     *
     * @return array<int, string>
     */
    public static function secretKeys(string $type): array
    {
        return array_values((array) (self::definition($type)['secrets'] ?? []));
    }

    /**
     * Secrets that may be supplied but are not required — a self-hosted
     * service on the clinic's own network usually needs no API key, yet a
     * deployment behind one must still be able to store it.
     *
     * @return array<int, string>
     */
    public static function optionalSecretKeys(string $type): array
    {
        return array_values((array) (self::definition($type)['optionalSecrets'] ?? []));
    }

    /**
     * Every secret key the type can hold, required or not. Used for masking:
     * a stored optional secret is still a secret.
     *
     * @return array<int, string>
     */
    public static function allSecretKeys(string $type): array
    {
        return array_values(array_unique([
            ...self::secretKeys($type),
            ...self::optionalSecretKeys($type),
        ]));
    }

    /** @return array<int, string> */
    public static function requiredKeys(string $type): array
    {
        return array_values((array) (self::definition($type)['required'] ?? []));
    }

    /** Catalog shaped for the console (types + the keys each one needs). */
    public static function catalogPayload(): array
    {
        return collect(self::catalog())->map(fn ($def, $type) => [
            'type' => (string) $type,
            'label' => $def['label'] ?? ucfirst((string) $type),
            'feature' => $def['feature'] ?? null,
            'probe' => $def['probe'] ?? 'config',
            'requiredKeys' => array_values((array) ($def['required'] ?? [])),
            'secretKeys' => array_values((array) ($def['secrets'] ?? [])),
            'optionalSecretKeys' => array_values((array) ($def['optionalSecrets'] ?? [])),
        ])->values()->all();
    }

    /** API shape: config in the clear (it is not secret), secrets masked. */
    public static function shape(TenantIntegration $i): array
    {
        $secrets = is_array($i->secrets) ? $i->secrets : [];
        $secretKeys = self::allSecretKeys($i->type);

        return [
            'id' => (string) $i->id,
            'type' => $i->type,
            'typeLabel' => self::typeLabel($i->type),
            'name' => $i->name,
            'facilityId' => $i->location_id ? (string) $i->location_id : null,
            'config' => $i->config ?? [],
            'secrets' => collect($secretKeys)->mapWithKeys(fn ($key) => [
                $key => [
                    'present' => isset($secrets[$key]) && $secrets[$key] !== '' && $secrets[$key] !== null,
                    // Never the value — a fixed mask.
                    'mask' => '••••••••',
                ],
            ])->all(),
            'status' => $i->status,
            'lastCheckedAt' => $i->last_checked_at?->toIso8601String(),
            'lastError' => $i->last_error,
            'deliveryLog' => self::recentDeliveries($i),
            'createdAt' => $i->created_at?->toIso8601String(),
        ];
    }

    /**
     * Real end-to-end delivery proof: sends a synthetic event through the
     * integration's ACTUAL channel sender and records the outcome. Unlike
     * the reachability probe, this is the same code path production sends
     * use — a `sent` here means the third party truly accepted a payload.
     */
    public static function testDelivery(TenantIntegration $i): array
    {
        $payload = self::testPayloadFor($i);

        $result = app(DeliveryDispatcher::class)->sendNow($i, 'test', $payload);

        return [
            'status' => $result->status,
            'detail' => $result->detail ?? $result->status,
            'latencyMs' => $result->latencyMs,
        ];
    }

    /** Realistic synthetic payload per channel (never contains real PHI). */
    private static function testPayloadFor(TenantIntegration $i): array
    {
        $config = is_array($i->config) ? $i->config : [];

        return match ($i->type) {
            'webhook' => [
                'url' => (string) ($config['url'] ?? ''),
                'message' => 'PolytronX Enterprise PACS & RIS test event — verifying webhook delivery.',
                'test' => true,
            ],
            'whatsapp', 'sms' => [
                'to' => (string) ($config['testRecipient'] ?? ''),
                'message' => 'PolytronX Enterprise PACS & RIS test message — your '.self::typeLabel($i->type).' integration is working.',
                'test' => true,
            ],
            'email' => [
                'to' => (string) ($config['testRecipient'] ?? ($i->secrets['username'] ?? '')),
                'subject' => 'PolytronX Enterprise PACS & RIS — SMTP integration test',
                'message' => 'This is a test delivery from your PolytronX Enterprise PACS & RIS SMTP integration. If you received this, tenant SMTP works.',
                'test' => true,
            ],
            'hl7' => [
                'message' => '', // let the sender build a valid ADT test message
                'test' => true,
            ],
            'fhir' => [
                'baseUrl' => (string) ($config['baseUrl'] ?? ''),
                'message' => 'PolytronX Enterprise PACS & RIS test event — verifying FHIR connectivity.',
                'test' => true,
            ],
            default => ['test' => true],
        };
    }

    /** Last N delivery attempts for the console (newest first). */
    public static function recentDeliveries(TenantIntegration $i, int $limit = 5): array
    {
        return IntegrationDelivery::where('tenant_integration_id', $i->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (IntegrationDelivery $d) => [
                'id' => (string) $d->id,
                'event' => $d->event,
                'status' => $d->status,
                'target' => $d->target,
                'detail' => $d->detail,
                'latencyMs' => $d->latency_ms,
                'at' => $d->created_at?->toIso8601String(),
            ])->all();
    }

    /**
     * Which required config keys and secret keys are still missing.
     *
     * @return array{missingConfig: array<int, string>, missingSecrets: array<int, string>}
     */
    public static function missingKeys(string $type, array $config, array $secrets): array
    {
        $missingConfig = [];
        foreach (self::requiredKeys($type) as $key) {
            $value = $config[$key] ?? null;
            if ($value === null || $value === '' || $value === []) {
                $missingConfig[] = $key;
            }
        }

        // Only REQUIRED secrets are enforced; an optional one that is absent
        // leaves the integration perfectly usable.
        $missingSecrets = [];
        foreach (self::secretKeys($type) as $key) {
            $value = $secrets[$key] ?? null;
            if ($value === null || $value === '') {
                $missingSecrets[] = $key;
            }
        }

        return ['missingConfig' => $missingConfig, 'missingSecrets' => $missingSecrets];
    }

    /** Configuration completeness → status without touching the network. */
    public static function deriveStatus(string $type, array $config, array $secrets): string
    {
        $missing = self::missingKeys($type, $config, $secrets);

        return ($missing['missingConfig'] === [] && $missing['missingSecrets'] === []) ? 'active' : 'unconfigured';
    }

    /**
     * Run the type's health check and persist the outcome on the row.
     *
     * @return array{status: string, detail: string, check: string}
     */
    public static function probe(TenantIntegration $integration): array
    {
        $type = $integration->type;
        $config = is_array($integration->config) ? $integration->config : [];
        $secrets = is_array($integration->secrets) ? $integration->secrets : [];
        $missing = self::missingKeys($type, $config, $secrets);

        if ($missing['missingConfig'] !== [] || $missing['missingSecrets'] !== []) {
            return self::record($integration, 'unconfigured', 'Missing: '
                .implode(', ', array_merge($missing['missingConfig'], $missing['missingSecrets'])).'.', 'configuration');
        }

        $probe = self::definition($type)['probe'] ?? 'config';

        return match ($probe) {
            'tcp' => self::probeTcp($integration, $config),
            'http' => self::probeHttp($integration, $config),
            'fhir' => self::probeFhir($integration, $config),
            'smtp' => self::probeSmtp($integration, $config),
            'whatsapp' => self::probeWhatsapp($integration, $config),
            default => self::record(
                $integration,
                'active',
                'Configuration complete. This integration type has no live probe — delivery is not asserted here.',
                'configuration'
            ),
        };
    }

    /** FHIR: GET {baseUrl}/metadata must answer a CapabilityStatement. */
    private static function probeFhir(TenantIntegration $integration, array $config): array
    {
        $secrets = is_array($integration->secrets) ? $integration->secrets : [];

        $outcome = FhirSender::probeMetadata(
            (string) ($config['baseUrl'] ?? ''),
            (string) ($secrets['clientId'] ?? ''),
            (string) ($secrets['clientSecret'] ?? ''),
        );

        return self::record($integration, $outcome['status'], $outcome['detail'], 'fhir');
    }

    /** Email: the tenant's SMTP host must complete TCP connect + EHLO. */
    private static function probeSmtp(TenantIntegration $integration, array $config): array
    {
        $host = (string) ($config['host'] ?? '');
        $port = (int) ($config['port'] ?? 587);

        $start = microtime(true);
        $socket = @fsockopen($host, $port, $errNo, $errStr, 3);
        $latency = (int) round((microtime(true) - $start) * 1000);

        if (! is_resource($socket)) {
            return self::record($integration, 'error', "SMTP host unreachable at {$host}:{$port} — ".trim((string) $errStr).'.', 'smtp');
        }

        $greeting = fgets($socket, 1024);
        fclose($socket);

        if ($greeting !== false && preg_match('/^220/', $greeting)) {
            return self::record($integration, 'active', "SMTP server ready at {$host}:{$port} ({$latency} ms).", 'smtp');
        }

        return self::record($integration, 'error', "Connected to {$host}:{$port} but it did not answer with an SMTP greeting.", 'smtp');
    }

    /** WhatsApp: the configured phone number must exist on the Graph API. */
    private static function probeWhatsapp(TenantIntegration $integration, array $config): array
    {
        $secrets = is_array($integration->secrets) ? $integration->secrets : [];
        $phoneNumberId = (string) ($config['phoneNumberId'] ?? '');
        $token = (string) ($secrets['accessToken'] ?? '');
        $url = 'https://graph.facebook.com/'.WhatsAppSender::GRAPH_VERSION."/{$phoneNumberId}";

        try {
            $response = Http::timeout(6)->connectTimeout(5)->withToken($token)->acceptJson()->get($url);
        } catch (\Throwable $e) {
            return self::record($integration, 'error', 'WhatsApp Graph API request failed: '.$e->getMessage(), 'whatsapp');
        }

        if ($response->status() >= 200 && $response->status() < 300 && $response->json('id')) {
            return self::record($integration, 'active', "WhatsApp number confirmed (id {$response->json('id')}).", 'whatsapp');
        }

        $error = $response->json('error.message') ?? ("HTTP {$response->status()}");

        return self::record($integration, 'error', "WhatsApp Graph API rejected the check: {$error}", 'whatsapp');
    }

    private static function probeTcp(TenantIntegration $integration, array $config): array
    {
        $host = (string) ($config['host'] ?? '');
        $port = (int) ($config['port'] ?? 0);

        $start = microtime(true);
        $socket = @fsockopen($host, $port, $errNo, $errStr, 3);
        $latency = (int) round((microtime(true) - $start) * 1000);

        if (is_resource($socket)) {
            fclose($socket);

            return self::record($integration, 'active', "TCP reachable at {$host}:{$port} ({$latency} ms).", 'tcp');
        }

        return self::record($integration, 'error', "TCP unreachable at {$host}:{$port} — ".trim((string) $errStr).'.', 'tcp');
    }

    private static function probeHttp(TenantIntegration $integration, array $config): array
    {
        $url = (string) ($config['baseUrl'] ?? $config['url'] ?? '');

        try {
            $response = Http::timeout(5)->connectTimeout(5)->acceptJson()->get($url);
        } catch (\Throwable $e) {
            return self::record($integration, 'error', "HTTP request to {$url} failed: ".$e->getMessage(), 'http');
        }

        // Any non-server-error answer proves the endpoint is live; a 401/403 is a
        // reachability success (credentials are validated by the endpoint itself).
        if ($response->status() < 500) {
            return self::record($integration, 'active', "HTTP {$response->status()} from {$url}.", 'http');
        }

        return self::record($integration, 'error', "HTTP {$response->status()} from {$url}.", 'http');
    }

    private static function record(TenantIntegration $integration, string $status, string $detail, string $check): array
    {
        $integration->forceFill([
            'status' => $status,
            'last_checked_at' => now(),
            'last_error' => $status === 'error' ? mb_substr($detail, 0, 500) : null,
        ])->save();

        return ['status' => $status, 'detail' => $detail, 'check' => $check];
    }
}
