<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Shipment;

class DeliveryPartner extends Model
{
    protected $fillable = [
        'name',
        'code',
        'driver',
        'contact_email',
        'contact_phone',
        'is_active',
        'api_key',
        'client_id',
        'client_secret',
        'webhook_secret',
        'require_webhook_signature',
        'base_url',
        'is_sandbox',
        'is_default',
        'auto_book_on',
        'auto_cancel_with_order',
        'auto_update_order_status',
        'auto_sync_tracking',
        'auto_notify_customer',
        'notify_events',
        'use_b2c_one',
        'tracking_url_template',
        'status_map',
        'config',
        'last_connection_test_at',
        'last_connection_test_ok',
        'last_error',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'api_key' => 'encrypted',
        'client_secret' => 'encrypted',
        'webhook_secret' => 'encrypted',
        'require_webhook_signature' => 'boolean',
        'is_sandbox' => 'boolean',
        'is_default' => 'boolean',
        'auto_cancel_with_order' => 'boolean',
        'auto_update_order_status' => 'boolean',
        'auto_sync_tracking' => 'boolean',
        'auto_notify_customer' => 'boolean',
        'notify_events' => 'array',
        'use_b2c_one' => 'boolean',
        'status_map' => 'array',
        'config' => 'array',
        'last_connection_test_at' => 'datetime',
        'last_connection_test_ok' => 'boolean',
    ];

    protected $hidden = [
        'api_key',
        'client_secret',
        'webhook_secret',
    ];

    /**
     * Shipment statuses that trigger a customer notification by default.
     */
    public const DEFAULT_NOTIFY_EVENTS = [
        'shipped',
        'in_transit',
        'out_for_delivery',
        'delivered',
        'undelivered',
        'rto',
    ];

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function isIntegrated(): bool
    {
        return $this->driver !== null && $this->driver !== '' && $this->driver !== 'manual';
    }

    public function shouldAutoBookOn(string $orderStatus): bool
    {
        if (! $this->is_active || ! $this->isIntegrated()) {
            return false;
        }

        return match ($this->auto_book_on) {
            'processing' => $orderStatus === 'processing',
            'shipped' => $orderStatus === 'shipped',
            'both' => in_array($orderStatus, ['processing', 'shipped'], true),
            default => false,
        };
    }

    /**
     * Delhivery's public customer tracking page.
     *
     * This is the one place the format lives. The driver, the admin form
     * placeholder and the sync backfill all read it from here, so the URL shape
     * cannot drift between them. (It used to be hardcoded as
     * `/track/package/{awb}` in two separate places, which is not a valid
     * Delhivery customer URL and sent buyers to a dead link.)
     */
    public const DEFAULT_TRACKING_URL = 'https://www.delhivery.com/track/awb/{awb}';

    /**
     * Hosts that serve Delhivery's machine API rather than its customer pages.
     *
     * A tracking link is opened in the buyer's browser with no Authorization
     * header, so anything served from one of these hosts answers
     * "Login or API Key Required".
     *
     * These are regexes, not exact hosts, because the API is published under
     * tenant subdomains (ucp-*.delhivery.com) that vary per account. The
     * customer-facing www.delhivery.com is deliberately excluded so the public
     * page is never treated as an API endpoint.
     */
    private const API_HOST_PATTERNS = [
        '/(^|\.)track\.delhivery\.com$/',
        '/(^|\.)staging-express\.delhivery\.com$/',
        '/(^|\.)api\.delhivery\.com$/',
        '/(^|\.)ucp-[a-z0-9-]+\.delhivery\.com$/',
    ];

    /**
     * Path/query fragments that only ever appear on an API endpoint.
     *
     * The public customer page is /track/awb/{awb}; every one of these belongs
     * to the JSON API surface used by DelhiveryDriver::track().
     */
    private const API_URL_MARKERS = [
        '/api/',
        '/waybill/api/',
        '/cmu/',
        '?waybill=',
        '&waybill=',
        '.json',
    ];

    public function configValue(string $key, mixed $default = null): mixed
    {
        $config = $this->config ?? [];

        return $config[$key] ?? $default;
    }

    /**
     * Build the customer facing tracking URL for a waybill.
     *
     * Uses the partner's own template when one is configured, otherwise falls
     * back to $fallback (the driver supplies the courier's public page). Returns
     * null only when neither is available, so callers can hide the link rather
     * than render a broken one.
     */
    public function trackingUrlFor(string $waybill, ?string $fallback = null): ?string
    {
        $template = trim((string) ($this->tracking_url_template ?? ''));

        if ($template === '' || self::isTrackingApiEndpoint($template)) {
            // An API endpoint pasted into the template is the single most
            // common way this breaks: the buyer's browser has no API key, so the
            // page answers "Login or API Key Required". Degrade to the public
            // page rather than shipping a dead link to the customer.
            $template = trim((string) $fallback);
        }

        if ($template === '') {
            return null;
        }

        return str_replace(
            ['{awb}', '{tracking_number}', '{waybill}'],
            $waybill,
            $template
        );
    }

    /**
     * Does this URL point at a courier API rather than a customer-facing page?
     *
     * Public because the admin form and the repair command both need the same
     * judgement, so the rule that rejects a bad template is the rule that
     * repairs a bad stored value.
     */
    public static function isTrackingApiEndpoint(?string $url): bool
    {
        $url = trim((string) $url);

        if ($url === '') {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            $host = strtolower($host);

            foreach (self::API_HOST_PATTERNS as $pattern) {
                if (preg_match($pattern, $host) === 1) {
                    return true;
                }
            }
        }

        $haystack = strtolower($url);

        foreach (self::API_URL_MARKERS as $marker) {
            if (str_contains($haystack, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Masked representation of the stored API token. The full token is never
     * rendered back into the admin UI once it has been saved.
     */
    public function maskedApiKey(): string
    {
        return $this->maskCredential($this->api_key);
    }

    public function maskedClientSecret(): string
    {
        return $this->maskCredential($this->client_secret);
    }

    private function maskCredential(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (mb_strlen($value) <= 4) {
            return str_repeat('•', 8);
        }

        return str_repeat('•', 8) . mb_substr($value, -4);
    }

    public function hasStoredApiKey(): bool
    {
        return trim((string) $this->api_key) !== '';
    }

    public function hasStoredClientSecret(): bool
    {
        return trim((string) $this->client_secret) !== '';
    }

    /**
     * Is the "Auto-book eligible orders" rule switched on?
     */
    public function autoBookEnabled(): bool
    {
        return $this->auto_book_on !== null && $this->auto_book_on !== '' && $this->auto_book_on !== 'manual';
    }

    /**
     * Shipment statuses that should notify the customer for this partner.
     */
    public function notifyEventList(): array
    {
        $events = $this->notify_events;

        if (! is_array($events) || $events === []) {
            return self::DEFAULT_NOTIFY_EVENTS;
        }

        return array_values(array_filter(array_map('strval', $events)));
    }

    public function wantsNotificationFor(string $shipmentStatus): bool
    {
        if (! $this->auto_notify_customer) {
            return false;
        }

        return in_array($shipmentStatus, $this->notifyEventList(), true);
    }

    /**
     * Delhivery One (B2C) credentials for this partner, falling back to the
     * application level config so existing .env setups keep working.
     */
    public function b2cCredential(string $key, mixed $default = null): mixed
    {
        $map = [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'realm' => $this->configValue('b2c_realm'),
            'cms' => $this->configValue('b2c_cms'),
            'user_email' => $this->configValue('b2c_user_email'),
            'mcp_url' => $this->configValue('b2c_mcp_url'),
            'auth_url' => $this->configValue('b2c_auth_url'),
            'token_url' => $this->configValue('b2c_token_url'),
        ];

        $value = $map[$key] ?? null;

        if ($value !== null && trim((string) $value) !== '') {
            return $value;
        }

        $fallback = [
            'client_id' => config('delhivery.b2c_one.auth.client_id'),
            'client_secret' => config('delhivery.b2c_one.auth.client_secret'),
            'realm' => config('delhivery.b2c_one.auth.realm'),
            'cms' => config('delhivery.b2c_one.cms'),
            'user_email' => config('delhivery.b2c_one.user_email'),
            'mcp_url' => config('delhivery.b2c_one.mcp_url'),
            'auth_url' => config('delhivery.b2c_one.auth.auth_url'),
            'token_url' => config('delhivery.b2c_one.auth.token_url'),
        ];

        return $fallback[$key] ?? $default;
    }
}
