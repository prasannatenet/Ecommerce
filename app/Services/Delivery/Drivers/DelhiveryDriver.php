<?php

namespace App\Services\Delivery\Drivers;

use App\Models\DeliveryPartner;
use App\Models\Shipment;
use App\Services\Delivery\Contracts\DeliveryDriver;
use App\Services\Delivery\DeliveryBookingResult;
use App\Services\Delivery\DeliveryDraft;
use App\Services\Delivery\DeliveryStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DelhiveryDriver implements DeliveryDriver
{
    public function driverKey(): string
    {
        return 'delhivery';
    }

    public function defaultStatusMap(): array
    {
        return DeliveryStatus::DELHIVERY_DEFAULT_MAP;
    }

    public function baseUrl(DeliveryPartner $partner): string
    {
        if (! empty($partner->base_url)) {
            return rtrim($partner->base_url, '/');
        }

        return config(
            $partner->is_sandbox ? 'delhivery.old_api.sandbox_base_url' : 'delhivery.old_api.production_base_url',
            $partner->is_sandbox ? 'https://staging-express.delhivery.com' : 'https://track.delhivery.com'
        );
    }

    private function headers(DeliveryPartner $partner): array
    {
        return [
            'Authorization' => 'Token ' . (string) $partner->api_key,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    public function book(DeliveryDraft $draft): DeliveryBookingResult
    {
        $partner = $draft->partner;
        $shipping = $draft->shippingAddress;
        $pickup = (array) ($partner->config ?? []);

        $waybill = $draft->waybill ?: $this->issueWaybill($partner);
        if (! $waybill) {
            return DeliveryBookingResult::failed('Could not fetch Delhivery waybill.');
        }

        $payload = [
            'shipments' => [[
                'name' => $draft->receiverName ?: ($shipping['name'] ?? 'Customer'),
                'add' => $shipping['address_line1'] ?? ($shipping['address'] ?? ''),
                'city' => $shipping['city'] ?? '',
                'state' => $shipping['state'] ?? '',
                'country' => $shipping['country'] ?? 'India',
                'phone' => $draft->receiverPhone ?: ($shipping['phone'] ?? ''),
                'pin' => $shipping['pincode'] ?? ($shipping['zip'] ?? ''),
                'order' => (string) $draft->order->id,
                'payment_mode' => $draft->paymentMode,
                'cod_amount' => $draft->codAmount,
                'total_amount' => $draft->declaredValue,
                'weight' => max(100, $draft->weightInGrams),
                'quantity' => 1,
                'waybill' => $waybill,
                'products_desc' => 'Order #' . $draft->order->id,
            ]],
            'pickup_location' => ['name' => $pickup['pickup_name'] ?? $partner->name],
        ];

        $response = Http::withHeaders($this->headers($partner))
            ->timeout(30)
            ->post($this->baseUrl($partner) . '/api/cmu/create.json', [
                'format' => 'json',
                'data' => json_encode($payload),
            ]);

        $json = (array) ($response->json() ?? []);

        if (! $response->successful()) {
            return DeliveryBookingResult::failed('Delhivery booking failed.', $json);
        }

        $trackingUrl = $partner->trackingUrlFor($waybill)
            ?? 'https://www.delhivery.com/track/package/' . $waybill;

        return DeliveryBookingResult::ok($waybill, [
            'tracking_url' => $trackingUrl,
            'provider_reference' => (string) ($json['packages'][0]['refnum'] ?? $waybill),
            'raw' => $json,
        ]);
    }

    public function testConnection(DeliveryPartner $partner): bool
    {
        $pin = (string) ($partner->configValue('pickup_pin') ?? '122001');

        $response = Http::withHeaders($this->headers($partner))
            ->timeout(20)
            ->get($this->baseUrl($partner) . '/api/pin-codes/json/', [
                'filter_codes' => $pin === '' ? '122001' : $pin,
            ]);

        return $response->successful();
    }

    private function issueWaybill(DeliveryPartner $partner): ?string
    {
        $response = Http::withHeaders($this->headers($partner))
            ->timeout(20)
            ->get($this->baseUrl($partner) . '/api/waybill/create.json', ['count' => 1]);

        if (! $response->successful()) {
            return null;
        }

        $json = (array) ($response->json() ?? []);

        return $json['awb'][0] ?? ($json['waybill'][0] ?? null);
    }

    public function track(Shipment $shipment): array
    {
        $partner = $shipment->deliveryPartner;
        if (! $partner || empty($shipment->tracking_number)) {
            return [];
        }

        $response = Http::withHeaders($this->headers($partner))
            ->timeout(20)
            ->get($this->baseUrl($partner) . '/api/v1/packages/json/', [
                'waybill' => $shipment->tracking_number,
            ]);

        if (! $response->successful()) {
            return [];
        }

        $json = (array) ($response->json() ?? []);
        $packages = $json['ShipmentData'] ?? [];
        $scans = $packages[0]['Shipment']['Scans'] ?? [];

        $events = [];
        foreach ((array) $scans as $scan) {
            $scan = (array) $scan;
            $events[] = [
                'provider_event_id' => ($scan['ScanDateTime'] ?? '') . '|' . ($scan['ScanType'] ?? ''),
                'status_code' => strtoupper((string) ($scan['ScanType'] ?? 'UNKNOWN')),
                'status_label' => (string) ($scan['Scan'] ?? ''),
                'location' => (string) ($scan['ScannedLocation'] ?? ''),
                'remarks' => (string) ($scan['Instructions'] ?? ''),
                'scanned_at' => (string) ($scan['ScanDateTime'] ?? now()->toDateTimeString()),
                'raw_payload' => $scan,
            ];
        }

        return $events;
    }

    public function cancel(Shipment $shipment): bool
    {
        $partner = $shipment->deliveryPartner;
        if (! $partner || empty($shipment->tracking_number)) {
            return false;
        }

        $response = Http::withHeaders($this->headers($partner))
            ->timeout(20)
            ->post($this->baseUrl($partner) . '/api/p/edit', [
                'waybill' => $shipment->tracking_number,
                'cancellation' => 'true',
            ]);

        return $response->successful();
    }

    public function label(Shipment $shipment): ?string
    {
        $partner = $shipment->deliveryPartner;
        if (! $partner || empty($shipment->tracking_number)) {
            return null;
        }

        $response = Http::withHeaders($this->headers($partner))
            ->timeout(30)
            ->get($this->baseUrl($partner) . '/api/p/packing_slip', [
                'wbns' => $shipment->tracking_number,
                'pdf' => 'true',
            ]);

        if (! $response->successful() || strlen($response->body()) < 100) {
            return null;
        }

        $path = 'delivery-labels/' . $shipment->tracking_number . '.pdf';
        Storage::disk('public')->put($path, $response->body());

        return Storage::url($path);
    }
}