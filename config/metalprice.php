<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Live Metal Rates (goldapi.io)
    |--------------------------------------------------------------------------
    |
    | Spot prices for gold (XAU) and silver (XAG) quoted in INR. The provider
    | answers with the full metal price list, so the per-10g figures Indian
    | shoppers actually compare are derived in MetalPriceService, not here.
    |
    | Without a key the storefront still renders: the top bar simply reports
    | that live rates are unavailable instead of showing stale numbers as if
    | they were current.
    |
    */

    'api_key' => env('METAL_PRICE_API_KEY', ''),

    'base_url' => env('METAL_PRICE_BASE_URL', 'https://www.goldapi.io/api'),

    /*
    | Rates are cached so a page view never waits on a third party and the
    | provider's request quota is not spent on page reloads.
    */
    'cache_ttl' => (int) env('METAL_PRICE_CACHE_TTL', 900),

    /*
    | When the provider is unreachable the last good payload keeps being served
    | for this long, flagged as stale, rather than blanking the top bar.
    */
    'stale_ttl' => (int) env('METAL_PRICE_STALE_TTL', 86400),

    'timeout' => (int) env('METAL_PRICE_TIMEOUT', 5),

    'connect_timeout' => (int) env('METAL_PRICE_CONNECT_TIMEOUT', 3),

    /* Indian jewellery rates are quoted per 10 grams (one tola). */
    'grams_per_unit' => (int) env('METAL_PRICE_GRAMS_PER_UNIT', 10),

];
