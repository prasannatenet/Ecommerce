<?php

namespace App\Services\Delivery\Drivers;

use App\Models\DeliveryPartner;
use App\Models\Shipment;
use App\Services\Delivery\Contracts\DeliveryDriver;
use App\Services\Delivery\DelhiveryEndpoints;
use App\Services\Delivery\DeliveryBookingResult;
use App\Services\Delivery\DeliveryDraft;
use App\Services\Delivery\DeliveryStatus;
use App\Services\Delivery\ServiceabilityResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DelhiveryDriver implements DeliveryDriver
{
    /**
     * Last courier-supplied reason for a failure.
     *
     * DeliveryManager reads this through lastError() so the admin panel can show
     * Delhivery's own words ("facility IND... is not in active state") instead
     * of a generic failure.
     */
    private string $lastError = '';

    public function lastError(): string
    {
        return $this->lastError;
    }

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
        return DelhiveryEndpoints::baseUrl($partner);
    }

    private function headers(DeliveryPartner $partner): array
    {
        return [
            'Authorization' => 'Token ' . (string) $partner->api_key,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Pull the actionable part out of a Delhivery error body.
     *
     * Delhivery returns human-readable reasons in `error` / `error_desc` / `message`
     * - e.g. "facility IND302014AAA is not in active state or does not exist".
     * Surfacing that beats a generic "booking failed", which is what made the
     * unregistered-warehouse problem invisible for days.
     */
    private function extractError(?array $json, string $fallback): string
    {
        foreach (['error', 'error_desc', 'message', 'Error', 'error_message'] as $key) {
            $value = $json[$key] ?? null;

            if (is_array($value)) {
                $value = implode(' ', array_filter(array_map('strval', $value)));
            }

            if (is_string($value) && trim($value) !== '' && strtoupper(trim($value)) !== 'SUCCESS') {
                return trim($value);
            }
        }

        return $fallback;
    }

    public function book(DeliveryDraft $draft): DeliveryBookingResult
    {
        $partner = $draft->partner;
        $shipping = $draft->shippingAddress;
        $pickup = (array) ($partner->config ?? []);

        // The warehouse is validated by DeliveryManager::book() before this
        // driver runs, so an AWB is never burned on a doomed request.
        $waybill = $draft->waybill ?: $this->issueWaybill($partner);
        if (! $waybill) {
            // Include the driver's diagnosis: it knows whether the token was
            // rejected, the verb was wrong, or the endpoint 404'd. A generic
            // "could not fetch waybill" sends the operator hunting blind.
            $reason = trim($this->lastError);

            return DeliveryBookingResult::failed(
                'Delhivery did not return an AWB from ' . DelhiveryEndpoints::path('waybill', $partner)
                . '. Check the API token, warehouse registration and the environment setting.'
                . ($reason !== '' ? ' Reason: ' . $reason : '')
            );
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
            ->post(DelhiveryEndpoints::url('create', $partner), [
                'format' => 'json',
                'data' => json_encode($payload),
            ]);

        $json = (array) ($response->json() ?? []);

        if (! $response->successful()) {
            return DeliveryBookingResult::failed($this->extractError($json, 'Delhivery rejected the booking.'), $json);
        }

        // Delhivery answers 200 with success=false and an `error` array for
        // business rejections (unregistered facility, bad pincode, ...).
        $reported = $this->extractError($json, '');
        if ($reported !== '') {
            return DeliveryBookingResult::failed($reported, $json);
        }

        $trackingUrl = $partner->trackingUrlFor($waybill, DeliveryPartner::DEFAULT_TRACKING_URL);

        return DeliveryBookingResult::ok($waybill, [
            'tracking_url' => $trackingUrl,
            'provider_reference' => (string) ($json['packages'][0]['refnum'] ?? $waybill),
            'raw' => $json,
        ]);
    }

    public function testConnection(DeliveryPartner $partner): bool
    {
        $pin = trim((string) ($partner->configValue('pickup_pin') ?? ''));

        if ($pin === '') {
            $this->lastError = 'Set a pickup pincode before testing the connection.';

            return false;
        }

        $result = $this->checkPincode($partner, $pin);

        if (! $result->serviceable && $result->error) {
            $this->lastError = $result->error;
        }

        return $result->serviceable;
    }

    /**
     * Ask Delhivery whether a pincode is serviceable.
     *
     * Delhivery answers HTTP 200 with an empty `delivery_codes` array for an
     * unserviceable pincode, so the response body - not the status code - is
     * what decides the answer.
     */
    public function checkPincode(DeliveryPartner $partner, string $pin): ServiceabilityResult
    {
        $pin = preg_replace('/\D/', '', $pin) ?? '';

        if (strlen($pin) !== 6) {
            return ServiceabilityResult::failure('Enter a valid 6 digit pincode.');
        }

        try {
            $response = Http::withHeaders($this->headers($partner))
                ->timeout(20)
                ->get(DelhiveryEndpoints::url('serviceability', $partner), [
                    'filter_codes' => $pin,
                ]);
        } catch (\Throwable $e) {
            return ServiceabilityResult::failure('Could not reach Delhivery: ' . $e->getMessage());
        }

        $json = (array) ($response->json() ?? []);

        if (! $response->successful()) {
            return ServiceabilityResult::failure(
                $this->extractError($json, 'Delhivery returned HTTP ' . $response->status() . '.')
            );
        }

        $codes = $json['delivery_codes'] ?? $json['delivery_codes_count'] ?? null;

        if (! is_array($codes) || $codes === []) {
            return new ServiceabilityResult(false);
        }

        $postal = (array) (reset($codes)['postal_code'] ?? []);

        // Delhivery sends max_weight 0 to mean "no limit". Treating that as a
        // zero cap would reject every parcel, so it is normalised to null.
        $rawMaxWeight = (float) ($postal['max_weight'] ?? 0);

        return new ServiceabilityResult(
            serviceable: true,
            codAvailable: strtoupper((string) ($postal['cod'] ?? 'Y')) !== 'N',
            prepaidAvailable: strtoupper((string) ($postal['pre_paid'] ?? 'Y')) !== 'N',
            isOda: strtoupper((string) ($postal['is_oda'] ?? 'N')) === 'Y',
            maxWeightKg: $rawMaxWeight > 0 ? $rawMaxWeight : null,
            city: $postal['city'] ?? null,
            district: $postal['district'] ?? null,
            stateCode: $postal['state_code'] ?? null,
            remarks: $postal['remarks'] ?? null,
        );
    }

    /**
     * Turn a failed AWB request into something an operator can act on.
     *
     * Delhivery returns plain text here, not JSON, so extractError() would
     * have returned a generic message. These two statuses cover almost every
     * real cause of a booking failing at the waybill step.
     */
    private function explainWaybillFailure(int $status, string $body): string
    {
        $body = trim(preg_replace('/\s+/', ' ', $body) ?? '');

        return match (true) {
            $status === 405 => 'Delhivery rejected the waybill request method. '
                . 'Confirm DELHIVERY_EP_WAYBILL points at a GET endpoint, and that it ends with a trailing slash.',

            $status === 401 => 'Delhivery rejected the API token. '
                . 'Add or refresh the token in Delivery Settings > API & Environment.',

            // Live response: "Unable to fetch client name for the token" - the
            // request reached the right endpoint but the token is not valid.
            $status === 400 && stripos($body, 'token') !== false => 'Delhivery could not identify this API token. '
                . 'Check that the Express API token is correct and matches the sandbox environment.',

            $status === 400 => $body !== '' ? 'Delhivery rejected the waybill request: ' . $body
                : 'Delhivery rejected the waybill request (HTTP 400).',

            default => $body !== '' ? 'Delhivery returned HTTP ' . $status . ': ' . $body
                : 'Delhivery returned HTTP ' . $status . ' while allocating the AWB.',
        };
    }

    /**
     * Allocate one AWB.
     *
     * Path and verb both matter here, and both were wrong before:
     *   - the old GET /api/waybill/create.json does not exist at all (404)
     *   - the current /waybill/api/bulk/json/ answers POST with HTTP 405
     * The endpoint is a GET with ?count=N in the query string, verified live
     * against staging: POST -> 405, GET -> reaches the auth layer.
     */
    private function issueWaybill(DeliveryPartner $partner): ?string
    {
        $max = max(1, (int) config('delhivery.old_api.max_waybills_per_request', 100));

        $url = DelhiveryEndpoints::url('waybill', $partner) . '?count=' . min(1, $max);

        try {
            $response = Http::withHeaders($this->headers($partner))
                ->timeout(20)
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('Delhivery waybill request failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            $this->lastError = $this->explainWaybillFailure($response->status(), $response->body());

            return null;
        }

        // Production endpoint returns a plain JSON string e.g. "65878310000206"
        // (not a JSON object), so check the raw decoded value first.
        $decoded = $response->json();
        if (is_string($decoded) && trim($decoded) !== '') {
            return trim($decoded);
        }

        $json = is_array($decoded) ? $decoded : [];

        // The bulk endpoint has returned more than one envelope over time, so
        // every documented shape is accepted rather than guessing one.
        foreach ([['awb'], ['waybill'], ['data', 'awb'], ['data', 'waybill'], ['AWB']] as $path) {
            $value = $json;

            foreach ($path as $segment) {
                if (! is_array($value) || ! array_key_exists($segment, $value)) {
                    $value = null;
                    break;
                }

                $value = $value[$segment];
            }

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_array($value)) {
                foreach ($value as $candidate) {
                    if (is_string($candidate) && trim($candidate) !== '') {
                        return trim($candidate);
                    }
                }
            }
        }

        $this->lastError = $this->extractError($json, 'Delhivery returned no AWB in the response.');

        return null;
    }

    public function track(Shipment $shipment): array
    {
        $partner = $shipment->deliveryPartner;
        if (! $partner || empty($shipment->tracking_number)) {
            return [];
        }

        $response = Http::withHeaders($this->headers($partner))
            ->timeout(20)
            ->get(DelhiveryEndpoints::url('tracking', $partner), [
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
            ->post(DelhiveryEndpoints::url('cancel', $partner), [
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
            ->get(DelhiveryEndpoints::url('label', $partner), [
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
