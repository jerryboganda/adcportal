<?php

namespace App\Services\Delivery;

use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Http;

/**
 * Generic HTTP SMS gateway sender. Covers the request shapes of the common
 * hosted gateways (Twilio-style form POST and JSON-keyed gateways) via the
 * integration's `provider` + `endpoint` config. A provider-specific SDK is
 * deliberately avoided: every gateway here is one authenticated HTTP call.
 *
 * Config: provider (twilio|json), endpoint, [from]
 * Secrets: apiKey (twilio: sid:token or token; json: bearer key), senderId
 */
class SmsSender implements ChannelSender
{
    public function send(TenantIntegration $integration, string $event, array $payload): DeliveryResult
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $secrets = is_array($integration->secrets) ? $integration->secrets : [];

        $endpoint = (string) ($config['endpoint'] ?? '');
        $provider = strtolower((string) ($config['provider'] ?? 'json'));
        $apiKey = (string) ($secrets['apiKey'] ?? '');
        $from = (string) ($config['from'] ?? $secrets['senderId'] ?? '');
        $to = (string) ($payload['to'] ?? '');
        $message = (string) ($payload['message'] ?? '');

        if ($to === '' || $message === '') {
            return DeliveryResult::skipped('SMS send needs both `to` and `message` in the payload.');
        }

        if ($endpoint === '') {
            return DeliveryResult::skipped('SMS gateway endpoint is not configured.');
        }

        $start = microtime(true);

        try {
            if ($provider === 'twilio') {
                // Twilio-compatible: basic-auth form POST. apiKey format `sid:token`.
                [$sid, $token] = array_pad(explode(':', $apiKey, 2), 2, $apiKey);

                $response = Http::timeout(10)->connectTimeout(5)->asForm()
                    ->withBasicAuth($sid, $token)
                    ->post($endpoint, [
                        'To' => $to,
                        'From' => $from,
                        'Body' => $message,
                    ]);

                $accepted = $response->status() >= 200 && $response->status() < 300;
                $decoded = $response->json() ?? [];
                $messageId = $decoded['sid'] ?? null;
            } else {
                // JSON gateway convention: bearer auth + {to, from, message}.
                $response = Http::timeout(10)->connectTimeout(5)
                    ->withToken($apiKey)
                    ->acceptJson()
                    ->post($endpoint, [
                        'to' => $to,
                        'from' => $from,
                        'message' => $message,
                    ]);

                $accepted = $response->status() >= 200 && $response->status() < 300;
                $decoded = $response->json() ?? [];
                $messageId = $decoded['messageId'] ?? $decoded['id'] ?? $decoded['message_id'] ?? null;
            }
        } catch (\Throwable $e) {
            return DeliveryResult::failed('SMS gateway request failed: '.$e->getMessage(), (int) round((microtime(true) - $start) * 1000));
        }

        $latency = (int) round((microtime(true) - $start) * 1000);

        if ($accepted) {
            return DeliveryResult::sent('SMS accepted by gateway'.($messageId ? " (id {$messageId})" : '').'.', $latency, array_filter([
                'message_id' => $messageId,
                'http_status' => $response->status(),
            ]));
        }

        $error = is_string($decoded['message'] ?? null) ? $decoded['message'] : ("HTTP {$response->status()}");

        return DeliveryResult::failed("SMS gateway rejected the message: {$error}", $latency, [
            'http_status' => $response->status(),
        ]);
    }
}
