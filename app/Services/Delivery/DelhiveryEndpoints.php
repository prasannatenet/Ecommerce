<?php

namespace App\Services\Delivery;

use App\Models\DeliveryPartner;

/**
 * Resolves Delhivery API endpoint URLs.
 *
 * Endpoint paths are configuration, never string literals in a driver. Delhivery
 * has moved paths before (the waybill allocation lives at
 * /waybill/api/bulk/json/, not /api/waybill/create.json), and a hardcoded path
 * fails as a 404 that surfaces only as a missing AWB.
 *
 * Precedence, highest first:
 *   1. Per-partner override  config.endpoint_<key> on the delivery_partners row
 *   2. Application config    config/delhivery.php, overridable from .env
 *   3. Built-in default      passed by the caller
 *
 * The same partner -> config -> default shape is used by
 * DeliveryPartner::trackingUrlFor(), so the two behave identically.
 */
final class DelhiveryEndpoints
{
    public static function path(string $key, ?DeliveryPartner $partner = null, ?string $default = null): string
    {
        $override = $partner?->configValue('endpoint_' . $key);

        $path = (is_string($override) && trim($override) !== '')
            ? $override
            : config("delhivery.old_api.endpoints.{$key}", $default);

        $path = trim((string) $path);

        // Only the leading slash is normalised away. Delhivery's paths carry a
        // meaningful trailing slash and dropping it produced a 404 on
        // /waybill/api/bulk/json/ - which is exactly the endpoint that allocates
        // the AWB, so no waybill would ever come back.
        return ltrim($path, '/');
    }

    public static function url(string $key, ?DeliveryPartner $partner = null, ?string $default = null): string
    {
        $base = $partner ? self::baseUrl($partner) : self::configBaseUrl();

        $path = self::path($key, $partner, $default);

        return $path === '' ? $base : rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    public static function baseUrl(?DeliveryPartner $partner = null): string
    {
        // A partner-level base URL wins so a partner can sit on a different
        // environment (or a proxy) without touching global config.
        if ($partner && ! empty($partner->base_url)) {
            return rtrim((string) $partner->base_url, '/');
        }

        return self::configBaseUrl($partner);
    }

    private static function configBaseUrl(?DeliveryPartner $partner = null): string
    {
        $sandbox = $partner?->is_sandbox ?? true;

        return rtrim((string) config(
            $sandbox ? 'delhivery.old_api.sandbox_base_url' : 'delhivery.old_api.production_base_url',
            $sandbox ? 'https://staging-express.delhivery.com' : 'https://track.delhivery.com'
        ), '/');
    }
}
