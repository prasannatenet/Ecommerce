<?php

namespace App\Services\Delivery;

use App\Models\DeliveryPartner;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentTrackingEvent;
use App\Services\Delivery\Contracts\DeliveryDriver;
use App\Services\Delivery\Drivers\DelhiveryDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeliveryManager
{
    public function driverFor(DeliveryPartner $partner): ?DeliveryDriver
    {
        return match ($partner->driver) {
            'delhivery' => new DelhiveryDriver(),
            default => null,
        };
    }

    public function baseUrl(DeliveryPartner $partner): string
    {
        $driver = $this->driverFor($partner);

        return $driver ? $driver->baseUrl($partner) : '';
    }

    public function testConnection(DeliveryPartner $partner): array
    {
        $driver = $this->driverFor($partner);

        if (! $driver) {
            return ['ok' => false, 'message' => 'Manual partners do not have an API connection.'];
        }

        if (empty($partner->api_key)) {
            return ['ok' => false, 'message' => 'Add the courier API key before testing.'];
        }

        try {
            $ok = $driver->testConnection($partner);
        } catch (\Throwable $e) {
            Log::warning('Delivery connection test failed', ['partner' => $partner->id, 'error' => $e->getMessage()]);
            $ok = false;
        }

        $partner->forceFill([
            'last_connection_test_at' => now(),
            'last_connection_test_ok' => $ok,
            'last_error' => $ok ? null : 'Connection test failed. Check API key and environment.',
        ])->save();

        return ['ok' => $ok, 'message' => $ok ? 'Courier API is reachable.' : 'Courier API test failed.'];
    }

    public function defaultPartner(): ?DeliveryPartner
    {
        return DeliveryPartner::where('is_active', true)->where('is_default', true)->first()
            ?? DeliveryPartner::where('is_active', true)->where('driver', '!=', 'manual')->orderBy('id')->first();
    }

    public function createShipmentForOrder(Order $order, ?DeliveryPartner $partner = null): ?Shipment
    {
        $partner = $partner && $partner->is_active ? $partner : $this->defaultPartner();

        if (! $partner) {
            Log::info('Delivery auto-book skipped: no default partner', ['order' => $order->id]);

            return null;
        }

        $shipment = Shipment::create([
            'order_id' => $order->id,
            'delivery_partner_id' => $partner->id,
            'status' => DeliveryStatus::PENDING,
            'booking_requested_at' => now(),
        ]);

        $result = $this->book($shipment->fresh());

        if (! $result->success) {
            Log::warning('Delivery auto-book failed', ['order' => $order->id, 'error' => $result->error]);
        }

        return $shipment->fresh();
    }

    public function maybeAutoBook(Order $order): ?Shipment
    {
        $partner = $this->defaultPartner();

        if (! $partner || ! $partner->shouldAutoBookOn((string) $order->status)) {
            return null;
        }

        $existing = $order->shipments()->latest()->first();
        if ($existing && ! empty($existing->tracking_number)) {
            return $existing;
        }

        return $this->createShipmentForOrder($order, $partner);
    }

    public function buildDraft(Shipment $shipment, ?string $waybill = null): DeliveryDraft
    {
        $shipment->loadMissing(['order.items', 'deliveryPartner']);
        $order = $shipment->order;
        $partner = $shipment->deliveryPartner;
        $shipping = $order->shipping_address ?? [];
        if (! is_array($shipping)) {
            $shipping = [];
        }

        $fullName = trim(($shipping['first_name'] ?? '') . ' ' . ($shipping['last_name'] ?? ''));
        $receiverName = $fullName !== '' ? $fullName : ($shipping['name'] ?? $order->user?->name);
        $receiverPhone = $shipping['phone'] ?? $order->user?->phone;
        $isCod = strtolower((string) ($order->payment_method ?? '')) === 'cod';

                return new DeliveryDraft(
            $order,
            $partner,
            $shipping,
            $receiverName,
            $receiverPhone,
            $isCod ? 'COD' : 'Pre-paid',
            $isCod ? (float) $order->total : 0.0,
            max(1.0, (float) $order->total),
            (int) ($partner->configValue('default_weight', 500)),
            (int) ($partner->configValue('default_length', 10)),
            (int) ($partner->configValue('default_breadth', 10)),
            (int) ($partner->configValue('default_height', 10)),
            $waybill
        );
    }

    public function book(Shipment $shipment): DeliveryBookingResult
    {
        $shipment->loadMissing(['order', 'deliveryPartner']);
        $partner = $shipment->deliveryPartner;

        if (! $partner) {
            return DeliveryBookingResult::failed('No delivery partner selected.');
        }

        if (! empty($shipment->tracking_number)) {
            return DeliveryBookingResult::ok($shipment->tracking_number, [
                'tracking_url' => $shipment->tracking_url,
                'label_url' => $shipment->label_url,
            ]);
        }

        $driver = $this->driverFor($partner);
        if (! $driver) {
            return DeliveryBookingResult::failed('Selected partner is manual. Enter the AWB manually.');
        }

        if (empty($partner->api_key)) {
            return DeliveryBookingResult::failed('Partner API key is missing.');
        }

        $shipment->forceFill(['booking_requested_at' => now()])->save();

        try {
            $result = $driver->book($this->buildDraft($shipment));
        } catch (\Throwable $e) {
            Log::error('Delivery booking exception', ['shipment' => $shipment->id, 'error' => $e->getMessage()]);
            $shipment->forceFill(['last_sync_error' => $e->getMessage()])->save();

            return DeliveryBookingResult::failed('Courier booking failed: ' . $e->getMessage());
        }

        if (! $result->success) {
            $shipment->forceFill(['last_sync_error' => $result->error])->save();

            return $result;
        }

        $shipment->forceFill([
            'tracking_number' => $result->waybill,
            'tracking_url' => $result->trackingUrl ?? $shipment->tracking_url,
            'provider_shipment_id' => $result->providerReference,
            'label_url' => $result->labelUrl ?? $shipment->label_url,
            'status' => DeliveryStatus::BOOKED,
            'booked_at' => now(),
            'shipped_at' => $shipment->shipped_at ?? now(),
            'last_sync_error' => null,
        ])->save();

        if ($partner->auto_update_order_status && $shipment->order && $shipment->order->status === 'processing') {
            $shipment->order->forceFill(['status' => 'shipped'])->save();
        }

        return $result;
    }

    public function mapStatus(DeliveryPartner $partner, string $providerCode): string
    {
        $code = strtoupper(trim($providerCode));
        $custom = $partner->status_map ?? [];
        if (isset($custom[$code])) {
            return $custom[$code];
        }

        $driver = $this->driverFor($partner);
        $defaults = $driver ? $driver->defaultStatusMap() : DeliveryStatus::DELHIVERY_DEFAULT_MAP;

        return $defaults[$code] ?? DeliveryStatus::IN_TRANSIT;
    }

    public function applyEvents(Shipment $shipment, array $events): bool
    {
        $shipment->loadMissing(['order', 'deliveryPartner']);
        $partner = $shipment->deliveryPartner;

        if (! $partner || empty($events)) {
            return false;
        }

        $latestStatus = $shipment->status;
        $latestAt = null;

        foreach ($events as $event) {
            $code = strtoupper((string) ($event['status_code'] ?? 'UNKNOWN'));
            $eventId = (string) ($event['provider_event_id'] ?? $code . '|' . ($event['scanned_at'] ?? ''));
            $scannedAt = $event['scanned_at'] ?? null;

            ShipmentTrackingEvent::updateOrCreate(
                ['shipment_id' => $shipment->id, 'provider_event_id' => $eventId],
                [
                    'status_code' => $code,
                    'status_label' => $event['status_label'] ?? null,
                    'location' => $event['location'] ?? null,
                    'remarks' => $event['remarks'] ?? null,
                    'scanned_at' => $scannedAt,
                    'raw_payload' => $event['raw_payload'] ?? $event,
                ]
            );

            $latestStatus = $this->mapStatus($partner, $code);
            $latestAt = $scannedAt ?? $latestAt;
        }

        $shipment->forceFill([
            'status' => $latestStatus,
            'last_synced_at' => now(),
            'last_sync_error' => null,
            'shipped_at' => $shipment->shipped_at ?? now(),
            'delivered_at' => $latestStatus === DeliveryStatus::DELIVERED
                ? ($shipment->delivered_at ?? now())
                : $shipment->delivered_at,
        ])->save();

        if ($partner->auto_update_order_status && $shipment->order) {
            $this->mirrorOrderStatus($shipment->order, $latestStatus);
        }

        return true;
    }

    protected function mirrorOrderStatus(Order $order, string $shipmentStatus): void
    {
        $target = match ($shipmentStatus) {
            DeliveryStatus::BOOKED, DeliveryStatus::SHIPPED,
            DeliveryStatus::IN_TRANSIT, DeliveryStatus::OUT_FOR_DELIVERY => 'shipped',
            DeliveryStatus::DELIVERED => 'delivered',
            DeliveryStatus::CANCELLED => 'cancelled',
            default => null,
        };

        if ($target && $order->status !== $target && $order->status !== 'delivered') {
            $order->forceFill(['status' => $target])->save();
        }
    }

    public function sync(Shipment $shipment): bool
    {
        $shipment->loadMissing(['deliveryPartner']);
        $driver = $shipment->deliveryPartner ? $this->driverFor($shipment->deliveryPartner) : null;

        if (! $driver) {
            return false;
        }

        try {
            $events = $driver->track($shipment);
        } catch (\Throwable $e) {
            Log::warning('Delivery sync failed', ['shipment' => $shipment->id, 'error' => $e->getMessage()]);
            $shipment->forceFill(['last_sync_error' => $e->getMessage()])->save();

            return false;
        }

        if (empty($events)) {
            $shipment->forceFill([
                'last_synced_at' => now(),
                'last_sync_error' => 'No tracking events returned by courier.',
            ])->save();

            return false;
        }

        return $this->applyEvents($shipment, $events);
    }

    public function label(Shipment $shipment): ?string
    {
        $shipment->loadMissing(['deliveryPartner']);
        $driver = $shipment->deliveryPartner ? $this->driverFor($shipment->deliveryPartner) : null;

        if (! $driver) {
            return $shipment->label_url;
        }

        try {
            $url = $driver->label($shipment);
        } catch (\Throwable $e) {
            Log::warning('Delivery label failed', ['shipment' => $shipment->id, 'error' => $e->getMessage()]);

            return $shipment->label_url;
        }

        if ($url) {
            $shipment->forceFill(['label_url' => $url])->save();
        }

        return $url ?? $shipment->label_url;
    }

    public function verifyWebhook(DeliveryPartner $partner, Request $request): bool
    {
        $secret = (string) ($partner->webhook_secret ?? '');

        if ($secret === '') {
            return true;
        }

        $signature = $request->header('X-Delhivery-Signature')
            ?? $request->header('X-Hub-Signature-256')
            ?? $request->header('X-Signature')
            ?? '';

        if ($signature === '') {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function handleWebhook(DeliveryPartner $partner, array $payload): int
    {
        $shipments = $payload['shipments'] ?? $payload['Shipments'] ?? $payload['data'] ?? [];

        if (isset($shipments['waybill']) || isset($shipments['awb'])) {
            $shipments = [$shipments];
        }

        $handled = 0;

        foreach ((array) $shipments as $row) {
            $row = (array) $row;
            $waybill = $row['waybill'] ?? $row['awb'] ?? $row['Waybill'] ?? $row['tracking_number'] ?? null;

            if (! $waybill) {
                continue;
            }

            $shipment = Shipment::where('delivery_partner_id', $partner->id)
                ->where('tracking_number', (string) $waybill)
                ->first();

            if (! $shipment) {
                continue;
            }

            $scans = $row['scans'] ?? $row['Scans'] ?? $row['events'] ?? [$row];
            $events = [];

            foreach ((array) $scans as $scan) {
                $scan = (array) $scan;
                $scanType = strtoupper((string) ($scan['scan_type'] ?? $scan['ScanType'] ?? $scan['status'] ?? 'UNKNOWN'));
                $at = (string) ($scan['scan_datetime'] ?? $scan['ScanDateTime'] ?? now()->toDateTimeString());
                $events[] = [
                    'provider_event_id' => $at . '|' . $scanType,
                    'status_code' => $scanType,
                    'status_label' => (string) ($scan['scan'] ?? $scan['Scan'] ?? ''),
                    'location' => (string) ($scan['scanned_location'] ?? $scan['ScannedLocation'] ?? ''),
                    'remarks' => (string) ($scan['instructions'] ?? $scan['Instructions'] ?? ''),
                    'scanned_at' => $at,
                    'raw_payload' => $scan,
                ];
            }

            if ($this->applyEvents($shipment, $events)) {
                $handled++;
            }
        }

        return $handled;
    }
}
