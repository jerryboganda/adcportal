<?php

namespace App\Services;

use App\Models\TenantIntegration;
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

    /** @return array<int, string> */
    public static function secretKeys(string $type): array
    {
        return array_values((array) (self::definition($type)['secrets'] ?? []));
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
        ])->values()->all();
    }

    /** API shape: config in the clear (it is not secret), secrets masked. */
    public static function shape(TenantIntegration $i): array
    {
        $secrets = is_array($i->secrets) ? $i->secrets : [];
        $secretKeys = self::secretKeys($i->type);

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
            'createdAt' => $i->created_at?->toIso8601String(),
        ];
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
            default => self::record(
                $integration,
                'active',
                'Configuration complete. This integration type has no live probe — delivery is not asserted here.',
                'configuration'
            ),
        };
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
