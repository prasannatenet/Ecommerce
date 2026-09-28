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

    /**
     * The driver that should actually place the order.
     *
     * Delhivery One (B2C) credentials are read/search scoped and cannot create
     * orders. If a partner has both B2C enabled *and* a stored Express API key,
     * book through the Express driver so the order still goes out; B2C keeps
     * handling tracking. Without this fallback such a partner could never book
     * anything and the failure only showed up in last_sync_error.
     */
    public function bookingDriverFor(DeliveryPartner $partner): ?DeliveryDriver
    {
        $driver = $this->driverFor($partner);

        if ($driver instanceof B2CDelhiveryDriver && ! empty($partner->api_key)) {
            return new DelhiveryDriver();
        }

        return $driver;
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

        // Drivers that return a structured result (Delhivery Express) carry the
        // detail an operator needs: COD/prepaid availability, weight cap and
        // the courier's own remark.
        if ($serviceable instanceof ServiceabilityResult) {
            return [
                'ok' => $serviceable->error === null,
                'serviceable' => $serviceable->serviceable,
                'message' => $serviceable->error ?? $serviceable->summary(),
                'cod_available' => $serviceable->codAvailable,
                'prepaid_available' => $serviceable->prepaidAvailable,
                'max_weight_kg' => $serviceable->maxWeightKg,
                'warnings' => $serviceable->warnings(),
            ];
        }

        return [
            'ok' => true,
            'serviceable' => (bool) $serviceable,
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

        // A ServiceabilityResult object is always truthy, so comparing it
        // directly would silently report every pincode as serviceable.
        $isServiceable = $serviceable instanceof ServiceabilityResult
            ? $serviceable->serviceable
            : (bool) $serviceable;

        if (! $isServiceable) {
            Log::warning('Pincode reported as not serviceable', [
                'shipment' => $shipment->id,
                'partner' => $partner->id,
                'pincode' => $pincode,
                'reason' => $serviceable instanceof ServiceabilityResult
                    ? ($serviceable->error ?? $serviceable->summary())
                    : (method_exists($driver, 'lastError') ? (string) $driver->lastError() : ''),
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
            // Delhivery expects exactly "Prepaid" (no hyphen) or "COD". Sending
            // "Pre-paid" gets prepaid orders rejected by the booking API.
            $isCod ? 'COD' : 'Prepaid',
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

        $driver = $this->bookingDriverFor($partner);
        if (! $driver) {
            return DeliveryBookingResult::failed('Selected partner is manual. Enter the AWB manually.');
        }

        // B2C One cannot allocate waybills, and nothing else here can either, so
        // say the one actionable thing instead of a generic failure.
        if (empty($partner->api_key)) {
            return DeliveryBookingResult::failed(
                'This partner has no Delhivery Express API token, so no AWB can be generated. '
                . 'Add the token in Delivery Settings > API & Environment; Delhivery One alone only tracks.'
            );
        }

        // A pickup warehouse that is blank or not registered in Delhivery makes
        // the booking fail at the courier. Stopping here avoids a pointless
        // serviceability round trip and never burns an AWB on a dead request.
        if ($partner->isIntegrated() && trim((string) $partner->configValue('pickup_name', '')) === '') {
            return DeliveryBookingResult::failed(
                'No pickup warehouse is configured for this partner. Set the warehouse name to '
                . 'exactly match a warehouse registered in Delhivery, otherwise every booking is rejected.'
            );
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

    /**
     * Cancel a shipment with the courier.
     *
     * The Delhivery driver has always supported cancellation, but nothing ever
     * called it: cancelling an order locally left the courier free to pick the
     * parcel up, which is billed and then returned at our cost.
     *
     * A courier outage must never trap a customer behind a support queue, so a
     * failure here is recorded on the shipment and logged, never thrown. The
     * caller can surface it for follow-up.
     */
    public function cancel(Shipment $shipment): bool
    {
        $shipment->loadMissing(['order', 'deliveryPartner']);
        $partner = $shipment->deliveryPartner;

        // Nothing was ever booked, so there is nothing to cancel with the courier.
        if (! $partner || empty($shipment->tracking_number)) {
            $shipment->forceFill([
                'status' => DeliveryStatus::CANCELLED,
                'last_sync_error' => null,
            ])->save();

            return true;
        }

        // Already in a terminal state: do not re-cancel with the courier.
        if (in_array($shipment->status, [DeliveryStatus::CANCELLED, DeliveryStatus::DELIVERED, DeliveryStatus::RTO], true)) {
            return $shipment->status === DeliveryStatus::CANCELLED;
        }

        $driver = $this->driverFor($partner);
        if (! $driver) {
            $shipment->forceFill([
                'status' => DeliveryStatus::CANCELLED,
                'last_sync_error' => null,
            ])->save();

            return true;
        }

        try {
            $ok = $driver->cancel($shipment);
        } catch (\Throwable $e) {
            Log::error('Delivery cancellation failed', [
                'shipment' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'error' => $e->getMessage(),
            ]);

            $shipment->forceFill([
                'last_sync_error' => 'Courier cancellation failed: ' . $e->getMessage(),
            ])->save();

            return false;
        }

        $shipment->forceFill([
            'status' => $ok ? DeliveryStatus::CANCELLED : $shipment->status,
            'last_sync_error' => $ok ? null : 'Courier did not accept the cancellation. Contact the courier to stop this shipment.',
        ])->save();

        return $ok;
    }

    /**
     * Cancel every bookable shipment on an order. Used when an order is
     * cancelled from the admin panel or by the customer.
     *
     * Returns a short human readable note about what happened so the caller can
     * show it ("Courier cancellation failed, call Delhivery to stop WB123").
     */
    public function cancelForOrder(Order $order): ?string
    {
        // Include shipments that were never booked: they still need to leave the
        // "pending" state so the dashboard does not keep counting them as live.
        $shipments = $order->shipments()->get();

        if ($shipments->isEmpty()) {
            return null;
        }

        $failed = [];

        foreach ($shipments as $shipment) {
            $partner = $shipment->relationLoaded('deliveryPartner')
                ? $shipment->deliveryPartner
                : $shipment->deliveryPartner()->first();

            // Per-partner opt-out, so a manual or self-pickup partner is never
            // sent an API cancellation it does not understand.
            if ($partner && ! $partner->auto_cancel_with_order) {
                continue;
            }

            if (! $this->cancel($shipment)) {
                $failed[] = $shipment->tracking_number;
            }
        }

        if ($failed === []) {
            return null;
        }

        return 'Courier cancellation failed for ' . implode(', ', $failed)
            . '. Contact the courier to stop these pickups before they are billed.';
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

    /**
     * Repair a shipment that has a waybill but no customer tracking link.
     *
     * tracking_url used to be written only during booking, so any shipment
     * booked before a tracking template existed kept a null URL forever and the
     * customer saw no "Track Delivery" link even though the AWB was fine.
     * Running this on every sync repairs those rows automatically.
     */
    private function backfillTrackingUrl(Shipment $shipment, DeliveryPartner $partner): void
    {
        if (! empty($shipment->tracking_url) || empty($shipment->tracking_number)) {
            return;
        }

        $url = $partner->trackingUrlFor((string) $shipment->tracking_number, $this->defaultTrackingTemplate($partner));

        if ($url) {
            $shipment->forceFill(['tracking_url' => $url])->save();
        }
    }

    /**
     * The courier's public tracking page for this partner. Drivers own this
     * knowledge, so B2C only (which never books) still produces a usable link.
     */
    private function defaultTrackingTemplate(DeliveryPartner $partner): ?string
    {
        if ($partner->driver === 'delhivery') {
            return DeliveryPartner::DEFAULT_TRACKING_URL;
        }

        return null;
    }

    public function sync(Shipment $shipment): bool
    {
        $shipment->loadMissing(['deliveryPartner']);
        $driver = $shipment->deliveryPartner ? $this->driverFor($shipment->deliveryPartner) : null;

        if (! $driver) {
            return false;
        }

        $this->backfillTrackingUrl($shipment, $shipment->deliveryPartner);

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
            // No secret configured: only accept if the partner is not in strict
            // mode. Previously this returned true unconditionally, which meant an
            // unsigned POST from anybody could mark shipments as delivered and
            // trigger "your order is delivered" emails to customers.
            return ! $partner->require_webhook_signature;
        }

        $signature = $request->header('X-Delhivery-Signature')
            ?? $request->header('X-Hub-Signature-256')
            ?? $request->header('X-Signature')
            ?? '';

        if ($signature === '') {
            return false;
        }

        // Accept both the `sha256=` prefixed and the bare hex digest so the
        // courier's header format does not have to match exactly.
        $signature = trim((string) $signature);
        $bare = str_starts_with(strtolower($signature), 'sha256=') ? substr($signature, 7) : $signature;
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $bare) || hash_equals('sha256=' . $expected, $signature);
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
