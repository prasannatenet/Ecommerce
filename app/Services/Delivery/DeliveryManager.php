<?php

namespace App\Services\Delivery;

use App\Mail\ShipmentStatusMail;
use App\Models\DeliveryPartner;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentTrackingEvent;
use App\Services\Delivery\Contracts\DeliveryDriver;
use App\Services\Delivery\Drivers\B2CDelhiveryDriver;
use App\Services\Delivery\Drivers\DelhiveryDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DeliveryManager
{
    public function driverFor(DeliveryPartner $partner): ?DeliveryDriver
    {
        return match ($partner->driver) {
            'delhivery' => ($partner->use_b2c_one && config('delhivery.b2c_one.enabled'))
                ? (new B2CDelhiveryDriver())->forPartner($partner)
                : new DelhiveryDriver(),
            'custom_api' => new \App\Services\Delivery\Drivers\CustomApiDriver(),
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

        if (! ($driver instanceof B2CDelhiveryDriver) && empty($partner->api_key)) {
            return ['ok' => false, 'message' => 'Add the courier API key before testing.'];
        }

        $testError = null;

        try {
            $ok = $driver->testConnection($partner);
        } catch (\Throwable $e) {
            Log::warning('Delivery connection test failed', ['partner' => $partner->id, 'error' => $e->getMessage()]);
            $ok = false;
            $testError = $e->getMessage();
        }

        if (! $ok && ! $testError && method_exists($driver, 'lastError')) {
            $testError = $driver->lastError() ?: null;
        }

        $reason = $testError ?: 'Connection test failed. Check the API credentials and environment.';

        $partner->forceFill([
            'last_connection_test_at' => now(),
            'last_connection_test_ok' => $ok,
            'last_error' => $ok ? null : $reason,
        ])->save();

        return ['ok' => $ok, 'message' => $ok ? 'Courier API is reachable.' : $reason];
    }

    /**
     * Admin-facing serviceability probe. Unlike the booking-time check this
     * surfaces the courier's answer (and any API error) back to the operator.
     */
    public function checkServiceability(DeliveryPartner $partner, string $pincode): array
    {
        $driver = $this->driverFor($partner);

        if (! $driver) {
            return [
                'ok' => false,
                'serviceable' => null,
                'message' => 'Manual partners do not have a pincode serviceability API.',
            ];
        }

        if (! method_exists($driver, 'checkPincode')) {
            return [
                'ok' => false,
                'serviceable' => null,
                'message' => 'This courier driver does not support pincode checks.',
            ];
        }

        try {
            $serviceable = $driver->checkPincode($partner, $pincode);
        } catch (\Throwable $e) {
            Log::warning('Pincode serviceability probe failed', [
                'partner' => $partner->id,
                'pincode' => $pincode,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'serviceable' => null, 'message' => $e->getMessage()];
        }

        $reason = method_exists($driver, 'lastError') ? trim((string) $driver->lastError()) : '';

        return [
            'ok' => true,
            'serviceable' => $serviceable,
            'message' => $serviceable
                ? 'Pincode ' . $pincode . ' is serviceable for ' . $partner->name . '.'
                : ('Pincode ' . $pincode . ' is not serviceable for ' . $partner->name . '.' . ($reason !== '' ? ' ' . $reason : '')),
        ];
    }

    /**
     * Advisory serviceability probe. It never blocks a booking - it only records
     * the courier's opinion so operators can spot bad addresses early.
     */
    protected function logPincodeServiceability(Shipment $shipment, DeliveryPartner $partner, DeliveryDriver $driver): void
    {
        if (! method_exists($driver, 'checkPincode')) {
            return;
        }

        if ($partner->configValue('serviceability_check', true) === false) {
            return;
        }

        $shipping = (array) ($shipment->order?->shipping_address ?? []);
        $pincode = trim((string) ($shipping['pincode'] ?? $shipping['zip'] ?? $shipping['postal_code'] ?? ''));

        if ($pincode === '') {
            return;
        }

        try {
            $serviceable = $driver->checkPincode($partner, $pincode);
        } catch (\Throwable $e) {
            Log::warning('Pincode serviceability check failed', ['pincode' => $pincode, 'error' => $e->getMessage()]);

            return;
        }

        if (! $serviceable) {
            Log::warning('Pincode reported as not serviceable', [
                'shipment' => $shipment->id,
                'partner' => $partner->id,
                'pincode' => $pincode,
                'reason' => method_exists($driver, 'lastError') ? (string) $driver->lastError() : '',
            ]);
        }
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

        // Already booked with a waybill — nothing to do.
        if ($existing && ! empty($existing->tracking_number)) {
            return $existing;
        }

        // A shipment is already queued for this order but has no waybill yet
        // (first attempt failed or is still pending). Re-attempt the booking
        // on the same shipment instead of creating a duplicate shipment row
        // on every order status update.
        if ($existing && $existing->status === DeliveryStatus::PENDING) {
            $result = $this->book($existing->fresh());

            if (! $result->success) {
                Log::warning('Delivery auto-book retry failed', ['order' => $order->id, 'error' => $result->error]);
            }

            return $existing->fresh();
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

        if (! ($driver instanceof B2CDelhiveryDriver) && empty($partner->api_key)) {
            return DeliveryBookingResult::failed('Partner API key is missing.');
        }

        $this->logPincodeServiceability($shipment, $partner, $driver);

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
        $previousStatus = $shipment->status;

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

        // Rule: "Automatic customer notifications" - only mail when the mapped
        // status actually changed, so repeated syncs never spam the customer.
        if ($latestStatus !== $previousStatus) {
            $this->notifyCustomerOfStatusChange($shipment, $partner, $latestStatus);
        }

        return true;
    }

    /**
     * Send the shipment status email when the partner's notification rule
     * allows it. Failures are logged and never break the tracking sync.
     */
    protected function notifyCustomerOfStatusChange(
        Shipment $shipment,
        DeliveryPartner $partner,
        string $status
    ): void {
        if (! $partner->wantsNotificationFor($status)) {
            return;
        }

        $order = $shipment->order;
        $email = $order?->user?->email;

        if (! $order || ! $email) {
            return;
        }

        try {
            Mail::to($email)->queue(new ShipmentStatusMail($shipment->fresh(['order', 'deliveryPartner']), $status));
        } catch (\Throwable $e) {
            Log::warning('Shipment status notification failed', [
                'shipment' => $shipment->id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
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
