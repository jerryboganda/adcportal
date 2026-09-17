<?php

namespace App\Services\Delivery;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued fan-out of one workflow event to a tenant's integrations. Queued
 * so the clinical request never waits on third parties; the database queue
 * (jobs table) with the scheduled `queue:work --stop-when-empty` drains it.
 */
class IntegrationEventFanout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30];

    public function __construct(
        public int $businessId,
        public array $types,
        public string $event,
        public array $payload,
    ) {}

    public function handle(DeliveryDispatcher $dispatcher): void
    {
        $dispatcher->fanOut($this->businessId, $this->types, $this->event, $this->payload);
    }
}
