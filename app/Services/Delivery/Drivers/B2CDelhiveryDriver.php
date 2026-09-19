<?php

namespace App\Services\Delivery\Drivers;

use App\Models\DeliveryPartner;
use App\Models\Shipment;
use App\Services\Delivery\Contracts\DeliveryDriver;
use App\Services\Delivery\DeliveryBookingResult;
use App\Services\Delivery\DeliveryDraft;
use App\Services\Delivery\DeliveryStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Delhivery One (B2C One) driver.
 *
 * Talks to Delhivery's official MCP server (JSON-RPC 2.0 over HTTP/SSE) using
 * OAuth2 client-credentials minted from the UCP auth service.
 *
 * The credential scope configured for this account exposes read/search tools
 * (wallet, transit history, shipment details, rate calculator, ...). Order
 * creation is not part of that scope, so booking/cancellation continue to use
 * the Express (token based) driver while Delhivery One powers tracking and
 * serviceability.
 */
class B2CDelhiveryDriver implements DeliveryDriver
{
    /**
     * Access tokens keyed by client id, so two partners with different
     * Delhivery One credentials never share a cached token.
     *
     * @var array<string, array{token: string, expires_at: int}>
     */
    private static array $tokenCache = [];

    private string $lastError = '';

    private ?DeliveryPartner $partner = null;

    /**
     * Bind this driver instance to a partner so credentials stored in the
     * admin panel take precedence over the application level config.
     */
    public function forPartner(DeliveryPartner $partner): static
    {
        $this->partner = $partner;

        return $this;
    }

    public function driverKey(): string
    {
        return 'delhivery-b2c';
    }

    /**
     * Read a Delhivery One credential for the bound partner, falling back to
     * the partner record defaults and then to the application config.
     */
    private function credential(string $key, mixed $default = null): mixed
    {
        if ($this->partner) {
            return $this->partner->b2cCredential($key, $default);
        }

        return match ($key) {
            'client_id' => config('delhivery.b2c_one.auth.client_id', $default),
            'client_secret' => config('delhivery.b2c_one.auth.client_secret', $default),
            'realm' => config('delhivery.b2c_one.auth.realm', $default),
            'cms' => config('delhivery.b2c_one.cms', $default),
            'user_email' => config('delhivery.b2c_one.user_email', $default),
            'mcp_url' => config('delhivery.b2c_one.mcp_url', $default),
            'auth_url' => config('delhivery.b2c_one.auth.auth_url', $default),
            'token_url' => config('delhivery.b2c_one.auth.token_url', $default),
            default => $default,
        };
    }

    public function baseUrl(DeliveryPartner $partner): string
    {
        return $this->mcpUrl();
    }

    public function defaultStatusMap(): array
    {
        return array_merge(DeliveryStatus::DELHIVERY_DEFAULT_MAP, [
            'MANIFESTED' => DeliveryStatus::BOOKED,
            'MANIFEST' => DeliveryStatus::BOOKED,
            'READY TO SHIP' => DeliveryStatus::BOOKED,
            'PICKUP SCHEDULED' => DeliveryStatus::BOOKED,
            'PICKUP PENDING' => DeliveryStatus::PENDING,
            'NOT PICKED' => DeliveryStatus::PENDING,
            'OUT FOR PICKUP' => DeliveryStatus::PENDING,
            'SHIPPED' => DeliveryStatus::SHIPPED,
            'IN TRANSIT' => DeliveryStatus::IN_TRANSIT,
            'IN-TRANSIT' => DeliveryStatus::IN_TRANSIT,
            'DELIVERY SCHEDULED' => DeliveryStatus::OUT_FOR_DELIVERY,
            'DELIVERY ATTEMPTED' => DeliveryStatus::OUT_FOR_DELIVERY,
            'REATTEMPT' => DeliveryStatus::OUT_FOR_DELIVERY,
            'OOD' => DeliveryStatus::OUT_FOR_DELIVERY,
            'DLVD' => DeliveryStatus::DELIVERED,
            'RTO IN TRANSIT' => DeliveryStatus::RTO,
            'RTO DELIVERED' => DeliveryStatus::RTO,
            'RETURNED TO SELLER' => DeliveryStatus::RTO,
            'PICKUP CANCELLED' => DeliveryStatus::CANCELLED,
            'CANCELLED' => DeliveryStatus::CANCELLED,
            'NOT DELIVERED' => DeliveryStatus::UNDELIVERED,
            'EXCEPTION' => DeliveryStatus::UNDELIVERED,
        ]);
    }

    /**
     * Last error reported by the driver (surfaced by DeliveryManager).
     */
    public function lastError(): string
    {
        return $this->lastError;
    }

    // -----------------------------------------------------------------
    // OAuth2 + MCP transport
    // -----------------------------------------------------------------

    private function mcpUrl(): string
    {
        return (string) $this->credential('mcp_url', 'https://mcp-client.delhivery.com/mcp');
    }

    private function requestId(): string
    {
        return 'req-' . bin2hex(random_bytes(8));
    }

    private function accessToken(): string
    {
        $now = time();
        $clientId = (string) $this->credential('client_id', '');
        $cacheKey = $clientId !== '' ? $clientId : 'default';
        $cached = self::$tokenCache[$cacheKey] ?? null;

        if ($cached !== null && $now < $cached['expires_at']) {
            return $cached['token'];
        }

        $realm = (string) $this->credential('realm', '');
        $base = rtrim((string) $this->credential('auth_url', ''), '/');
        $tokenUrl = (string) $this->credential('token_url', '');

        if ($tokenUrl === '') {
            $tokenUrl = $base . '/realms/' . $realm . '/protocol/openid-connect/token';
        }

        $response = Http::asForm()
            ->timeout((int) config('delhivery.b2c_one.timeout', 45))
            ->withHeaders(['X-Request-ID' => $this->requestId()])
            ->post($tokenUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => (string) $this->credential('client_secret', ''),
            ]);

        $token = (string) ($response->json('access_token') ?? '');

        if (! $response->successful() || $token === '') {
            $error = $response->json('error_description')
                ?? $response->json('detail')
                ?? $response->json('error')
                ?? ('HTTP ' . $response->status());

            throw new \RuntimeException('Delhivery One authentication failed: ' . mb_substr((string) $error, 0, 300));
        }

        $expiresIn = (int) ($response->json('expires_in') ?: config('delhivery.b2c_one.token_cache_ttl', 600));

        self::$tokenCache[$cacheKey] = [
            'token' => $token,
            'expires_at' => $now + max(60, $expiresIn - 30),
        ];

        return $token;
    }

    private function mcpHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->accessToken(),
            'x-hq-client-id' => (string) $this->credential('cms', ''),
            'X-UCP-User-Email' => (string) $this->credential('user_email', '--'),
            'X-UCP-Realm' => (string) $this->credential('realm', ''),
            'X-Request-ID' => $this->requestId(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json, text/event-stream',
        ];
    }

    /**
     * Invoke a Delhivery One MCP tool and return its decoded JSON payload.
     *
     * @throws \RuntimeException
     */
    public function callTool(string $tool, array $arguments = []): array
    {
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => $tool, 'arguments' => (object) $arguments],
        ]);

        $response = Http::withHeaders($this->mcpHeaders())
            ->withBody($payload, 'application/json')
            ->timeout((int) config('delhivery.b2c_one.timeout', 45))
            ->post($this->mcpUrl());

        if (! $response->successful()) {
            throw new \RuntimeException("Delhivery One tool '{$tool}' failed (HTTP {$response->status()}).");
        }

        $json = $this->decodeMcpResponse($response->body());

        if ($json === null) {
            throw new \RuntimeException("Delhivery One tool '{$tool}' returned an unreadable response.");
        }

        if (isset($json['error'])) {
            $message = $json['error']['message'] ?? json_encode($json['error']);

            throw new \RuntimeException("Delhivery One tool '{$tool}' error: " . mb_substr((string) $message, 0, 300));
        }

        $result = $json['result'] ?? [];

        if (($result['isError'] ?? false) === true) {
            $message = $result['content'][0]['text'] ?? 'Tool reported an error.';

            throw new \RuntimeException("Delhivery One tool '{$tool}' error: " . mb_substr((string) $message, 0, 300));
        }

        $decoded = $this->decodeContent($result['content'] ?? []);

        if (isset($decoded['error_code'])) {
            $message = $decoded['translated_message'] ?? $decoded['message'] ?? $decoded['error_code'];

            throw new \RuntimeException("Delhivery One tool '{$tool}': " . mb_substr((string) $message, 0, 300));
        }

        return $decoded;
    }

    private function decodeMcpResponse(string $body): ?array
    {
        $body = trim($body);

        if ($body === '') {
            return null;
        }

        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            $line = trim($line);

            if (str_starts_with($line, 'data:')) {
                $decoded = json_decode(trim(substr($line, 5)), true);

                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function decodeContent(array $content): array
    {
        foreach ($content as $part) {
            $text = $part['text'] ?? null;

            if (! is_string($text) || $text === '') {
                continue;
            }

            $decoded = json_decode($text, true);

            return is_array($decoded) ? $decoded : ['message' => $text];
        }

        return [];
    }

    // -----------------------------------------------------------------
    // DeliveryDriver interface
    // -----------------------------------------------------------------

    public function testConnection(DeliveryPartner $partner): bool
    {
        $this->lastError = '';

        try {
            $details = $this->callTool('get-wallet-details');
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        if ($details === []) {
            $this->lastError = 'Delhivery One returned an empty wallet response.';

            return false;
        }

        if (array_key_exists('is_active', $details) && $details['is_active'] === false) {
            $this->lastError = 'Delhivery One client/wallet is inactive.';

            return false;
        }

        return true;
    }

    /**
     * Best-effort serviceability check through the rate calculator tool.
     *
     * Delhivery One reports unserviceable pins as a hard gateway error, so an
     * inconclusive response is treated as "assume serviceable" to avoid
     * blocking legitimate orders.
     */
    public function checkPincode(DeliveryPartner $partner, string $pin): bool
    {
        $this->lastError = '';
        $pin = trim($pin);

        if ($pin === '') {
            return false;
        }

        $origin = trim((string) ($partner->configValue('pickup_pin', $partner->configValue('origin_pin', '')) ?? ''));

        if ($origin === '') {
            $this->lastError = 'Pickup pin code is not configured for this partner.';

            return true;
        }

        try {
            $result = $this->callTool('bulk-rate-calculator', [
                'id' => 'svc-' . $pin,
                'origin_pin' => $origin,
                'destination_pin' => $pin,
                'shipping_mode' => (string) ($partner->configValue('shipping_mode', 'Surface') ?? 'Surface'),
                'weight' => 0.5,
                'weight_unit' => 'KG',
                'payment_mode' => 'Pre-paid',
            ]);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::info('Delhivery One pincode check inconclusive', ['pin' => $pin, 'error' => $e->getMessage()]);

            return true;
        }

        return $result !== [];
    }

    public function track(Shipment $shipment): array
    {
        $this->lastError = '';
        $awb = trim((string) $shipment->tracking_number);

        if ($awb === '') {
            $this->lastError = 'Shipment has no AWB to track.';

            return [];
        }

        try {
            $history = $this->callTool('get-awb-transit-history', ['awb_number' => $awb]);
            $events = $this->normalizeTransitHistory($history);

            if ($events !== []) {
                return $events;
            }
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
        }

        // Fall back to the shipment snapshot so at least the current status syncs.
        try {
            $details = $this->callTool('get-shipment-details-by-awb', ['awb_number' => $awb]);
            $events = $this->normalizeShipmentDetails($details);

            if ($events !== []) {
                $this->lastError = '';

                return $events;
            }
        } catch (\Throwable $e) {
            $this->lastError = trim($this->lastError . ' | ' . $e->getMessage(), ' |');
            Log::info('Delhivery One tracking failed', ['awb' => $awb, 'error' => $e->getMessage()]);
        }

        return [];
    }

    // -----------------------------------------------------------------
    // Tracking payload normalisation
    // -----------------------------------------------------------------

    /**
     * Locate the first list of event-like rows inside an unknown payload shape.
     */
    private function extractEventRows(array $data): array
    {
        $candidates = [
            'transit_history', 'transitHistory', 'scans', 'events', 'shipment_transit',
            'history', 'timeline', 'ns_timeline', 'tracking', 'data', 'result', 'results',
            'shipment_data', 'ShipmentData',
        ];

        foreach ($candidates as $key) {
            if (! isset($data[$key]) || ! is_array($data[$key])) {
                continue;
            }

            $rows = $this->isListOfArrays($data[$key])
                ? $data[$key]
                : $this->extractEventRows($data[$key]);

            if ($rows !== []) {
                return $rows;
            }
        }

        if ($this->isListOfArrays($data)) {
            return $data;
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $rows = $this->extractEventRows($value);

                if ($rows !== []) {
                    return $rows;
                }
            }
        }

        return [];
    }

    private function isListOfArrays(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_array($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Convert a transit-history payload into the event shape DeliveryManager expects.
     */
    private function normalizeTransitHistory(array $payload): array
    {
        $rows = $this->extractEventRows($payload);
        $events = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $code = $this->firstValue($row, [
                'status_code', 'shipment_status', 'status_type', 'current_status',
                'scan_type', 'nsl_code', 'status', 'state',
            ]);

            $label = $this->firstValue($row, [
                'status', 'status_label', 'shipment_status', 'scan_type',
                'status_description', 'message', 'remark',
            ]);

            $statusCode = strtoupper(trim((string) $code));

            if ($statusCode === '') {
                $statusCode = strtoupper(trim((string) $label));
            }

            if ($statusCode === '') {
                continue;
            }

            $scannedAt = $this->firstValue($row, [
                'timestamp', 'scanned_at', 'scan_date', 'status_time', 'event_time',
                'event_timestamp', 'updated_at', 'created_at', 'date',
            ]);

            $location = $this->firstValue($row, [
                'location', 'scanned_location', 'current_location', 'facility_name',
                'city', 'place', 'centre',
            ]);

            $remarks = $this->firstValue($row, ['remarks', 'remark', 'instructions', 'reason', 'message']);
            $eventId = $this->firstValue($row, ['id', 'event_id', 'scan_id', 'status_id', 'sr_id']);
            $normalizedAt = $this->normalizeDate($scannedAt);

            $events[] = [
                'provider_event_id' => (string) ($eventId ?: $statusCode . '|' . (string) $normalizedAt),
                'status_code' => $statusCode,
                'status_label' => $label !== null ? (string) $label : $statusCode,
                'location' => $location !== null ? (string) $location : null,
                'remarks' => $remarks !== null ? (string) $remarks : null,
                'scanned_at' => $normalizedAt,
                'raw_payload' => $row,
            ];
        }

        return $events;
    }

    /**
     * Build a single event from a shipment-details snapshot (used when the
     * transit history is unavailable).
     */
    private function normalizeShipmentDetails(array $payload): array
    {
        $row = $payload['data'] ?? $payload;

        if (! is_array($row)) {
            return [];
        }

        $status = $this->firstValue($row, [
            'status', 'current_status', 'shipment_status', 'status_type',
            'order_status', 'state', 'shipment_state',
        ]);

        if ($status === null) {
            return [];
        }

        $statusCode = strtoupper(trim((string) $status));
        $updatedAt = $this->firstValue($row, [
            'status_updated_at', 'updated_at', 'last_updated', 'status_time',
            'manifested_at', 'created_at',
        ]);
        $location = $this->firstValue($row, ['current_location', 'location', 'city', 'destination_city']);
        $normalizedAt = $this->normalizeDate($updatedAt);

        return [[
            'provider_event_id' => 'snapshot|' . $statusCode . '|' . (string) $normalizedAt,
            'status_code' => $statusCode,
            'status_label' => (string) $status,
            'location' => $location !== null ? (string) $location : null,
            'remarks' => 'Status snapshot from Delhivery One',
            'scanned_at' => $normalizedAt,
            'raw_payload' => $row,
        ]];
    }

    private function firstValue(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            return $value;
        }

        return null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;

            if ($timestamp > 100000000000) {
                $timestamp = (int) ($timestamp / 1000);
            }

            return date('Y-m-d H:i:s', $timestamp);
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
/**
     * Delhivery One credentials for this account are read/search scoped: order
     * creation must go through the Express (token) driver.
     */
    public function book(DeliveryDraft $draft): DeliveryBookingResult
    {
        $this->lastError = 'Delhivery One (B2C) credentials cannot create orders.';

        return DeliveryBookingResult::failed(
            'Delhivery One (B2C) tracking is connected, but order creation is not enabled for this '
            . 'client scope. Use the Delhivery Express API key to book this order, then track it '
            . 'through Delhivery One.'
        );
    }

    public function cancel(Shipment $shipment): bool
    {
        $this->lastError = 'Delhivery One (B2C) credentials cannot cancel orders.';

        return false;
    }

    public function label(Shipment $shipment): ?string
    {
        $this->lastError = 'Delhivery One (B2C) credentials cannot fetch shipping labels.';

        return null;
    }

    public function edit(Shipment $shipment, array $updates = []): bool
    {
        $this->lastError = 'Delhivery One (B2C) credentials cannot edit orders.';

        return false;
    }

    public function fetchWaybills(DeliveryPartner $partner, int $count = 1): array
    {
        $this->lastError = 'Delhivery One (B2C) credentials cannot allocate waybills.';

        return [];
    }
}
