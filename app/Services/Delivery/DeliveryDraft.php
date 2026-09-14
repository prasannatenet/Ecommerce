<?php

namespace App\Services\Delivery;

use App\Models\DeliveryPartner;
use App\Models\Order;

class DeliveryDraft
{
        public function __construct(
        public readonly Order $order,
        public readonly DeliveryPartner $partner,
        public readonly array $shippingAddress,
        public readonly ?string $receiverName,
        public readonly ?string $receiverPhone,
        public readonly string $paymentMode,
        public readonly float $codAmount,
        public readonly float $declaredValue,
        public readonly int $weightInGrams,
        public readonly int $lengthCm = 10,
        public readonly int $breadthCm = 10,
        public readonly int $heightCm = 10,
        public readonly ?string $waybill = null,
    ) {
    }
}
