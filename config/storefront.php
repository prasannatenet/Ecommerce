<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storefront URL
    |--------------------------------------------------------------------------
    |
    | The public base URL of the shop, including the install sub-directory:
    |
    |   https://astroemerging.com/gehna
    |
    | Every absolute link the API emits (product images, category images,
    | product pages) is built from this value, because a React app is served
    | from a different origin than the API: a path-only `/storage/...` would
    | resolve against the React dev server, not against astroemerging.com.
    |
    | Left empty (the default) the app falls back to the *current request's*
    | own root, which is correct out of the box for local development, for
    | production, and for an install living in a sub-directory. Set it only
    | when a reverse proxy makes the request's own root wrong.
    |
    | Leave it empty to fall back to APP_URL, which is what local development
    | and the test suite want.
    |
    */

    'url' => ($storefront = env('STOREFRONT_URL'))
        ? rtrim((string) $storefront, '/')
        : null,

    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    |
    | The version segment prefixed onto every JSON route. Bumping this to "v2"
    | gives the React app a fresh surface to migrate to without breaking the
    | deployed build.
    |
    */

    'api_version' => env('API_VERSION', 'v1'),

    /*
    |--------------------------------------------------------------------------
    | Pagination Defaults
    |--------------------------------------------------------------------------
    |
    | Applied by the API controllers when a request does not pass `per_page`.
    | `max` is a hard ceiling: an unbounded `?per_page=100000` would let a
    | single request pull the whole catalogue and exhaust memory.
    |
    */

    'per_page' => (int) env('API_PER_PAGE', 12),
    'per_page_max' => (int) env('API_PER_PAGE_MAX', 60),

];
