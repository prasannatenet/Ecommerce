<?php

namespace App\Services\Delivery;

class DeliveryBookingResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $waybill = null,
        public readonly ?string $trackingUrl = null,
        public readonly ?string $labelUrl = null,
        public readonly ?string $providerReference = null,
        public readonly ?string $error = null,
        public readonly array $raw = [],
    ) {
    }

    public static function ok(?string $waybill, array $extra = []): self
    {
        return new self(
            true,
            $waybill,
            $extra['tracking_url'] ?? null,
            $extra['label_url'] ?? null,
            $extra['provider_reference'] ?? null,
            null,
            $extra['raw'] ?? []
        );
    }

    public static function failed(string $error, array $raw = []): self
    {
        return new self(false, null, null, null, null, $error, $raw);
    }
}
