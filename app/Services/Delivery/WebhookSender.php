<?php

namespace App\Services\Delivery;

use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Http;

/**
 * Outbound webhook delivery. Every payload is signed with the tenant's
 * configured signing secret (HMAC-SHA256 over "timestamp.body") so the
 * receiver can verify authenticity and reject replays.
 */
class WebhookSender implements ChannelSender
{
    public const HEADER_SIGNATURE = 'X-RIS-Signature';
    public const HEADER_TIMESTAMP = 'X-RIS-Timestamp';
    public const HEADER_EVENT = 'X-RIS-Event';

    public function send(TenantIntegration $integration, string $event, array $payload): DeliveryResult
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $secrets = is_array($integration->secrets) ? $integration->secrets : [];

        $url = (string) ($config['url'] ?? '');
        $signingSecret = (string) ($secrets['signingSecret'] ?? '');

        $body = (string) json_encode([
            'event' => $event,
            'tenant' => $integration->business?->tenant_code,
            'sentAt' => now()->toIso8601String(),
            'data' => $payload,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $timestamp = (string) now()->getTimestamp();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $signingSecret);

        $start = microtime(true);

        try {
            $response = Http::timeout(8)->connectTimeout(5)
                ->withHeaders([
                    self::HEADER_SIGNATURE => $signature,
                    self::HEADER_TIMESTAMP => $timestamp,
                    self::HEADER_EVENT => $event,
                    'Content-Type' => 'application/json',
                ])
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (\Throwable $e) {
            return DeliveryResult::failed('Webhook request to '.$url.' failed: '.$e->getMessage(), (int) round((microtime(true) - $start) * 1000));
        }

        $latency = (int) round((microtime(true) - $start) * 1000);

        // 2xx = accepted. 4xx/5xx = the receiver refused; that is a failed delivery.
        if ($response->status() >= 200 && $response->status() < 300) {
            return DeliveryResult::sent("Webhook accepted by {$url} (HTTP {$response->status()}).", $latency, [
                'http_status' => $response->status(),
            ]);
        }

        return DeliveryResult::failed("Webhook receiver returned HTTP {$response->status()} for {$url}.", $latency, [
            'http_status' => $response->status(),
        ]);
    }
}
