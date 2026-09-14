<?php

namespace App\Services\Delivery\Contracts;

use App\Models\DeliveryPartner;
use App\Models\Shipment;
use App\Services\Delivery\DeliveryBookingResult;
use App\Services\Delivery\DeliveryDraft;

interface DeliveryDriver
{
    public function driverKey(): string;

    public function baseUrl(DeliveryPartner $partner): string;

    public function testConnection(DeliveryPartner $partner): bool;

    public function book(DeliveryDraft $draft): DeliveryBookingResult;

    public function track(Shipment $shipment): array;

    public function cancel(Shipment $shipment): bool;

    public function label(Shipment $shipment): ?string;

    public function defaultStatusMap(): array;
}
