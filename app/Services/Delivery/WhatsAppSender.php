<?php

namespace App\Services\Delivery;

use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp Cloud API sender (Meta Graph). Sends a text message to one
 * recipient using the tenant's own phone number id + access token.
 */
class WhatsAppSender implements ChannelSender
{
    public const GRAPH_VERSION = 'v21.0';

    public function send(TenantIntegration $integration, string $event, array $payload): DeliveryResult
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $secrets = is_array($integration->secrets) ? $integration->secrets : [];

        $phoneNumberId = (string) ($config['phoneNumberId'] ?? '');
        $token = (string) ($secrets['accessToken'] ?? '');
        $to = (string) ($payload['to'] ?? '');
        $message = (string) ($payload['message'] ?? '');

        if ($to === '' || $message === '') {
            return DeliveryResult::skipped('WhatsApp send needs both `to` (E.164 number) and `message` in the payload.');
        }

        $url = "https://graph.facebook.com/".self::GRAPH_VERSION."/{$phoneNumberId}/messages";

        $start = microtime(true);

        try {
            $response = Http::timeout(10)->connectTimeout(5)
                ->withToken($token)
                ->acceptJson()
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'type' => 'text',
                    'text' => ['preview_url' => false, 'body' => $message],
                    'to' => $to,
                ]);
        } catch (\Throwable $e) {
            return DeliveryResult::failed('WhatsApp Cloud API request failed: '.$e->getMessage(), (int) round((microtime(true) - $start) * 1000));
        }

        $latency = (int) round((microtime(true) - $start) * 1000);
        $decoded = $response->json() ?? [];

        if ($response->status() >= 200 && $response->status() < 300 && isset($decoded['messages'][0]['id'])) {
            return DeliveryResult::sent("WhatsApp message accepted (wamid {$decoded['messages'][0]['id']}).", $latency, [
                'wamid' => $decoded['messages'][0]['id'],
                'http_status' => $response->status(),
            ]);
        }

        $error = $decoded['error']['message'] ?? ("HTTP {$response->status()}");

        return DeliveryResult::failed("WhatsApp Cloud API rejected the message: {$error}", $latency, [
            'http_status' => $response->status(),
        ]);
    }
}
