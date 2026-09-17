<?php

namespace App\Services\Delivery;

use App\Models\TenantIntegration;

/**
 * One delivery channel. Implementations perform the REAL third-party call
 * (HTTP request, SMTP send, MLLP write) and must never fake success — a
 * `sent` result means the remote side actually accepted the payload.
 */
interface ChannelSender
{
    /**
     * @param  string  $event    e.g. report_dispatch | study.booked | report.released | test
     * @param  array<string, mixed>  $payload  JSON-safe event data
     */
    public function send(TenantIntegration $integration, string $event, array $payload): DeliveryResult;
}
