<?php

namespace App\Services\Delivery;

/**
 * Outcome of one delivery attempt through one integration. Nothing here is
 * secret — `meta` may carry provider message ids and ACK codes only.
 */
final class DeliveryResult
{
    public const SENT = 'sent';
    public const FAILED = 'failed';
    public const SKIPPED = 'skipped';

    public function __construct(
        public readonly string $status,
        public readonly ?string $detail = null,
        public readonly ?int $latencyMs = null,
        public readonly array $meta = [],
    ) {}

    public static function sent(?string $detail = null, ?int $latencyMs = null, array $meta = []): self
    {
        return new self(self::SENT, $detail, $latencyMs, $meta);
    }

    public static function failed(string $detail, ?int $latencyMs = null, array $meta = []): self
    {
        return new self(self::FAILED, $detail, $latencyMs, $meta);
    }

    public static function skipped(string $detail): self
    {
        return new self(self::SKIPPED, $detail);
    }
}
