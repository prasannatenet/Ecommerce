<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Delhivery Old Express API (Token-based)
    |--------------------------------------------------------------------------
    |
    | Traditional Delhivery Express APIs at staging-express.delhivery.com
    | and track.delhivery.com. Uses a simple "Token <api_key>" header.
    |
    | Only the base URLs live in code. Every endpoint PATH is a config value
    | (see the `endpoints` block below) so a Delhivery path change is an .env
    | edit, never a code edit. Each path can also be overridden per partner
    | through its `config` JSON column, which wins over the config value.
    |
    */
    'old_api' => [
        'sandbox_base_url' => env('DELHIVERY_SANDBOX_URL', 'https://staging-express.delhivery.com'),
        'production_base_url' => env('DELHIVERY_PRODUCTION_URL', 'https://track.delhivery.com'),
        'default_api_key' => env('DELHIVERY_API_KEY', ''),

        /*
        | Endpoint paths. Never hardcode these into a driver - read them with
        | DelhiveryEndpoints::url('waybill', $partner) so they stay overridable.
        |
        | `waybill` allocates the AWB. Note that create.json does NOT return a
        | waybill: the shipment must be created against one already allocated.
        */
        'endpoints' => [
            'waybill' => env('DELHIVERY_EP_WAYBILL', '/waybill/api/bulk/json/'),
            'create' => env('DELHIVERY_EP_CREATE', '/api/cmu/create.json'),
            'serviceability' => env('DELHIVERY_EP_SERVICEABILITY', '/c/api/pin-codes/json/'),
            'tracking' => env('DELHIVERY_EP_TRACKING', '/api/v1/packages/json/'),
            'label' => env('DELHIVERY_EP_LABEL', '/api/p/packing_slip'),
            'cancel' => env('DELHIVERY_EP_CANCEL', '/api/p/edit'),
            'pickup_request' => env('DELHIVERY_EP_PICKUP_REQUEST', '/fm/request/new/'),
        ],

        // Delhivery caps a single bulk waybill request.
        'max_waybills_per_request' => (int) env('DELHIVERY_MAX_AWB', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delhivery B2C One (OAuth2 / MCP Gateway)
    |--------------------------------------------------------------------------
    |
    | The new B2C One developer portal APIs routed through the UCP gateway.
    | Uses OAuth2 client credentials to obtain a Bearer access token.
    |
    */
    'b2c_one' => [
        'enabled' => (bool) env('DELHIVERY_B2C_ENABLED', true),
        'gateway_url' => env('DELHIVERY_B2C_GATEWAY_URL', 'https://ucp-app-gateway.delhivery.com/open'),
        'auth' => [
            'client_id' => env('DELHIVERY_B2C_CLIENT_ID', 'ucp-service-cli'),
            'client_secret' => env('DELHIVERY_B2C_CLIENT_SECRET', ''),
            'auth_url' => env('DELHIVERY_B2C_AUTH_URL', 'https://ucp-auth.delhivery.com/facelessvoid'),
            'realm' => env('DELHIVERY_B2C_REALM', ''),
            // Optional explicit token endpoint; derived from auth_url + realm when empty.
            'token_url' => env('DELHIVERY_B2C_TOKEN_URL', ''),
        ],
        'mcp_url' => env('DELHIVERY_B2C_MCP_URL', 'https://mcp-client.delhivery.com/mcp'),
        'cms' => env('DELHIVERY_B2C_CMS', ''),
        'user_email' => env('DELHIVERY_B2C_USER_EMAIL', '--'),
        'timeout' => (int) env('DELHIVERY_B2C_TIMEOUT', 45),
        'token_cache_ttl' => (int) env('DELHIVERY_B2C_TOKEN_TTL', 600),
    ],
];
