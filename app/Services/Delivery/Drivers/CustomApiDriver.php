<?php

namespace App\Services\Delivery\Drivers;

use App\Models\DeliveryPartner;
use App\Models\Shipment;
use App\Services\Delivery\Contracts\DeliveryDriver;
use App\Services\Delivery\DeliveryBookingResult;
use App\Services\Delivery\DeliveryDraft;
use App\Services\Delivery\DeliveryStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;

class CustomApiDriver implements DeliveryDriver
{
    public function driverKey(): string
    {
        return 'custom_api';
    }

    public function defaultStatusMap(): array
    {
        return DeliveryStatus::DELHIVERY_DEFAULT_MAP;
    }

    public function baseUrl(DeliveryPartner $partner): string
    {
        return $partner->base_url ?? '';
    }

    private function parseHeaders(string $headersString): array
    {
        $headers = [];
        $lines = explode("\n", $headersString);
        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $headers[trim($parts[0])] = trim($parts[1]);
            }
        }
        return $headers;
    }

    private function populateTemplate(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $template = str_replace('{{' . $key . '}}', is_scalar($value) ? (string) $value : '', $template);
        }
        return $template;
    }

    public function testConnection(DeliveryPartner $partner): bool
    {
        return !empty($this->baseUrl($partner));
    }

    public function book(DeliveryDraft $draft): DeliveryBookingResult
    {
        $partner = $draft->partner;
        
        $endpoint = (string) $partner->configValue('booking_endpoint', '');
        $method = strtoupper((string) $partner->configValue('booking_method', 'POST'));
        $headersStr = (string) $partner->configValue('booking_headers', '');
        $template = (string) $partner->configValue('booking_payload_template', '{}');
        $awbPath = (string) $partner->configValue('booking_awb_path', '');

        if (!$endpoint) {
            return DeliveryBookingResult::failed('Custom API: Booking endpoint not configured.');
        }

        $headers = $this->parseHeaders($headersStr);
        if ($partner->api_key) {
            $headers['Authorization'] = 'Bearer ' . $partner->api_key;
        }
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';

        $shipping = $draft->shippingAddress;
        
        $data = [
            'order_id' => $draft->order->id,
            'customer_name' => $draft->receiverName ?: ($shipping['name'] ?? 'Customer'),
            'address' => $shipping['address_line1'] ?? ($shipping['address'] ?? ''),
            'city' => $shipping['city'] ?? '',
            'state' => $shipping['state'] ?? '',
            'country' => $shipping['country'] ?? 'India',
            'phone' => $draft->receiverPhone ?: ($shipping['phone'] ?? ''),
            'pincode' => $shipping['pincode'] ?? ($shipping['zip'] ?? ''),
            'payment_mode' => $draft->paymentMode,
            'cod_amount' => $draft->codAmount,
            'total_amount' => $draft->declaredValue,
            'weight' => max(100, $draft->weightInGrams),
        ];

        $payloadStr = $this->populateTemplate($template, $data);
        $payload = json_decode($payloadStr, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return DeliveryBookingResult::failed('Custom API: Invalid JSON payload template after replacing variables.');
        }

        $url = rtrim($this->baseUrl($partner), '/') . '/' . ltrim($endpoint, '/');
        
        $request = Http::withHeaders($headers)->timeout(30);
        $response = $method === 'POST' ? $request->post($url, $payload) : $request->get($url, $payload);
        
        $json = (array) ($response->json() ?? []);

        if (!$response->successful()) {
            return DeliveryBookingResult::failed('Custom API Booking failed: ' . $response->body(), $json);
        }

        $awb = Arr::get($json, $awbPath);
        if (!$awb) {
            return DeliveryBookingResult::failed('Custom API: Could not find AWB at path ['.$awbPath.'] in response.', $json);
        }

        $trackingUrl = $partner->trackingUrlFor((string) $awb) ?? '';

        return DeliveryBookingResult::ok((string) $awb, [
            'tracking_url' => $trackingUrl,
            'raw' => $json,
        ]);
    }

    public function track(Shipment $shipment): array
    {
        $partner = $shipment->deliveryPartner;
        if (!$partner || empty($shipment->tracking_number)) {
            return [];
        }

        $endpoint = (string) $partner->configValue('tracking_endpoint', '');
        $method = strtoupper((string) $partner->configValue('tracking_method', 'GET'));
        $headersStr = (string) $partner->configValue('tracking_headers', '');
        $eventsPath = (string) $partner->configValue('tracking_events_path', '');
        $statusPath = (string) $partner->configValue('tracking_status_path', '');
        $timestampPath = (string) $partner->configValue('tracking_timestamp_path', '');

        if (!$endpoint) {
            return [];
        }

        $headers = $this->parseHeaders($headersStr);
        if ($partner->api_key) {
            $headers['Authorization'] = 'Bearer ' . $partner->api_key;
        }

        $resolvedEndpoint = str_replace('{{waybill}}', $shipment->tracking_number, $endpoint);
        $url = rtrim($this->baseUrl($partner), '/') . '/' . ltrim($resolvedEndpoint, '/');

        $request = Http::withHeaders($headers)->timeout(20);
        $response = $method === 'POST' ? $request->post($url) : $request->get($url);

        if (!$response->successful()) {
            return [];
        }

        $json = (array) ($response->json() ?? []);
        $rawEvents = Arr::get($json, $eventsPath, []);
        
        if (!is_array($rawEvents)) {
            $rawEvents = [$json]; // Fallback
        }

        $events = [];
        foreach ($rawEvents as $scan) {
            $scan = (array) $scan;
            $statusCode = strtoupper((string) Arr::get($scan, $statusPath, 'UNKNOWN'));
            $timestamp = (string) Arr::get($scan, $timestampPath, now()->toDateTimeString());

            $events[] = [
                'provider_event_id' => $timestamp . '|' . $statusCode,
                'status_code' => $statusCode,
                'status_label' => $statusCode,
                'location' => '',
                'remarks' => '',
                'scanned_at' => $timestamp,
                'raw_payload' => $scan,
            ];
        }

        return $events;
    }

    public function cancel(Shipment $shipment): bool
    {
        return false;
    }

    public function label(Shipment $shipment): ?string
    {
        return null;
    }
}
