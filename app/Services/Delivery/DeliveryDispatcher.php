<?php

namespace App\Services\Delivery;

use App\Models\IntegrationDelivery;
use App\Models\TenantIntegration;
use Illuminate\Support\Facades\Auth;

/**
 * Central entry point for integration delivery. Resolves the right sender
 * per integration type, performs the real send, and records the outcome in
 * the tenant-scoped `integration_deliveries` log. Every send goes through
 * here — nothing else writes delivery rows.
 */
class DeliveryDispatcher
{
    /** @var array<string, class-string<ChannelSender>> */
    public const SENDERS = [
        'webhook' => WebhookSender::class,
        'whatsapp' => WhatsAppSender::class,
        'sms' => SmsSender::class,
        'email' => EmailSender::class,
        'hl7' => Hl7MllpSender::class,
        'fhir' => FhirSender::class,
    ];

    /**
     * Send an event through one integration NOW (synchronous — used by the
     * dispatch endpoint and test-delivery).
     */
    public function sendNow(TenantIntegration $integration, string $event, array $payload): DeliveryResult
    {
        $senderClass = self::SENDERS[$integration->type] ?? null;

        if ($senderClass === null) {
            return $this->record($integration, $event, DeliveryResult::skipped("Integration type {$integration->type} does not support message delivery."), $payload);
        }

        $result = app($senderClass)->send($integration, $event, $payload);

        return $this->record($integration, $event, $result, $payload);
    }

    /**
     * Fan out an event to EVERY active integration of the given types on the
     * tenant (used by workflow events). Failures never throw — a broken
     * integration must not break the clinical workflow around it.
     */
    public function fanOut(int $businessId, array $types, string $event, array $payload): void
    {
        $integrations = TenantIntegration::where('business_id', $businessId)
            ->whereIn('type', $types)
            ->where('status', 'active')
            ->get();

        foreach ($integrations as $integration) {
            try {
                $this->sendNow($integration, $event, $payload);
            } catch (\Throwable $e) {
                report($e);

                $this->record($integration, $event, DeliveryResult::failed('Sender crashed: '.$e->getMessage()), $payload);
            }
        }
    }

    /** Persist one delivery attempt in the tenant-scoped log. */
    private function record(TenantIntegration $integration, string $event, DeliveryResult $result, array $payload): DeliveryResult
    {
        IntegrationDelivery::create([
            'business_id' => $integration->business_id,
            'tenant_integration_id' => $integration->id,
            'channel' => $integration->type,
            'event' => $event,
            'status' => $result->status,
            'target' => (string) ($payload['to'] ?? $payload['url'] ?? $payload['baseUrl'] ?? ''),
            'detail' => $result->detail,
            'latency_ms' => $result->latencyMs,
            'meta' => $result->meta,
            'triggered_by' => Auth::id(),
        ]);

        return $result;
    }
}
