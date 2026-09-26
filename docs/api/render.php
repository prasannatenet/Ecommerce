<?php

/**
 * Renders the endpoint sections of API.md from responses actually captured off
 * the running server, so the JSON in the documentation is the JSON the API
 * returns rather than something hand-written and quietly out of date.
 *
 * Two rewrites happen on the way out:
 *   - the local dev origin becomes the production domain, because a doc full of
 *     127.0.0.1 is useless to the frontend developer;
 *   - long free-text and long arrays are shortened with an explicit "N more"
 *     marker, so one product's description does not bury the rest of the page.
 */

const PROD_BASE = 'https://astroemerging.com/gehna';
const LOCAL_BASE = 'http://127.0.0.1:8123';

$captured = json_decode(file_get_contents(__DIR__.'/captured.json'), true);

/**
 * Replace the dev origin with the production one, and the throwaway values the
 * capture used, so the documentation reads like a worked example rather than a
 * log from one machine.
 */
function prodUrls(mixed $value): mixed
{
    if (is_string($value)) {
        // The capture registered throwaway accounts, so their addresses (which
        // embed a timestamp) must not leak into a document people will keep.
        $value = preg_replace('/api\.doc\.\d+@example\.com/', 'you@example.com', $value);
        $value = str_replace('doc-preview@example.com', 'you@example.com', $value);

        return str_replace(LOCAL_BASE, PROD_BASE, $value);
    }

    if (is_array($value)) {
        foreach ($value as $key => $item) {
            // The capture used a throwaway phone number too. This has to be
            // matched on the decoded value: by the time the payload is encoded
            // as JSON the key syntax no longer exists to search for.
            if ($key === 'phone' && is_string($item) && preg_match('/^9\d{9}$/', $item)) {
                $value[$key] = '9812345678';

                continue;
            }

            $value[$key] = prodUrls($item);
        }
    }

    return $value;
}

/** Collapse whitespace and shorten a long free-text field. */
function trimText(mixed $value): mixed
{
    if (! is_string($value)) {
        return $value;
    }

    $collapsed = trim(preg_replace('/\s+/', ' ', $value));

    return mb_strlen($collapsed) > 160
        ? mb_substr($collapsed, 0, 160).'...'
        : $collapsed;
}

const TEXT_FIELDS = ['description', 'short_description', 'comment', 'content', 'answer'];
const ARRAY_FIELDS = ['images', 'videos', 'children', 'products'];

/**
 * Keys the API always sends as a JSON object, never an array.
 *
 * PHP cannot tell an empty object from an empty array once it has been decoded,
 * so without this an empty `meta` would be printed as `[]` and the frontend
 * developer would write `response.meta.length` against the wrong type.
 */
const OBJECT_FIELDS = ['meta', 'errors'];

/** Shorten the bulky parts of a payload, keeping the shape intact. */
function prepare(mixed $value, string $key = ''): mixed
{
    if (is_string($value)) {
        return in_array($key, TEXT_FIELDS, true) ? trimText($value) : $value;
    }

    if (! is_array($value)) {
        return $value;
    }

    // A list: keep the first entry, then say how many were elided.
    if (array_is_list($value) && count($value) > 1) {
        $first = prepare($value[0], $key);

        if (is_array($first) || is_scalar($first)) {
            $first[] = sprintf('... %d more item(s) in the real response, %d total',
                count($value) - 1, count($value));
        }

        return [$first];
    }

    foreach ($value as $childKey => $child) {
        $name = is_string($childKey) ? $childKey : $key;

        // Media and nested collections: keep two, count the rest.
        if (is_array($child) && array_is_list($child) && count($child) > 2
            && in_array($name, ARRAY_FIELDS, true)) {
            $total = count($child);
            $kept = array_slice($child, 0, 2);
            $kept[] = sprintf('... %d more, %d total', $total - 2, $total);
            $value[$childKey] = $kept;

            continue;
        }

        $value[$childKey] = prepare($child, $name);
    }

    // An object field that decoded to an empty array must go back out as {}.
    if (in_array($key, OBJECT_FIELDS, true) && $value === []) {
        return new stdClass();
    }

    return $value;
}

/** Pretty-print a payload the way it will appear in the doc. */
function jsonBlock(mixed $payload): string
{
    return json_encode(
        $payload,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
}

/** Render one captured call: request body (if any) and the real response. */
function render(string $name, array $call): string
{
    $out = '';

    if ($call['request'] !== null) {
        $out .= "**Request body**\n\n";
        $out .= "```json\n".jsonBlock(prodUrls($call['request']))."\n```\n\n";
    }

    $out .= "**Response `{$call['status']}`**\n\n";
    $out .= "```json\n".jsonBlock(prepare(prodUrls($call['response'])))."\n```\n";

    return $out;
}

/**
 * The endpoint reference. Each row: [key in captured.json, method+path, title, note].
 */
$endpoints = [
    '## 1. Authentication' => [
        ['auth.register', 'POST /auth/register', 'Register',
            'Creates the account and signs in. `password_confirmation` is required.'],
        ['auth.login', 'POST /auth/login', 'Login',
            'Returns a bearer token. Rate limited to 10 requests per minute and sharing the storefront lockout policy.'],
        ['auth.me', 'GET /auth/me', 'Current user',
            'Called on app boot to restore a session. The client turns a 401 here into "signed out" rather than an error.'],
        ['auth.profile', 'PUT /auth/profile', 'Update profile', 'Name and phone only.'],
        ['auth.logout', 'POST /auth/logout', 'Sign out this device',
            'Revokes the token that made the call. Safe to call with an already-invalid token.'],
        ['auth.logout-all', 'POST /auth/logout-all', 'Sign out everywhere', 'Revokes every token for the account.'],
    ],

    '## 2. Products' => [
        ['products.list', 'GET /products', 'Product list',
            'The main grid. Accepts every filter in section 8. Inactive products are never returned.'],
        ['products.filtered', 'GET /products?on_sale=1&sort=price_asc&per_page=2', 'Filtered list',
            'The same endpoint with `on_sale`, `sort` and `per_page` applied.'],
        ['products.show', 'GET /products/{slug}', 'Product detail',
            'Returns `{ product, reviews }` in one request so the page never has to fan out into follow-up calls.'],
        ['products.related', 'GET /products/{slug}/related?per_page=2', 'Related products',
            'The "you may also like" rail.'],
        ['products.reviews', 'GET /products/{slug}/reviews?per_page=2', 'Reviews',
            'Pass `rating=5` to filter by star count.'],
    ],

    '## 3. Categories' => [
        ['categories.index', 'GET /categories', 'Category list',
            'Flat list. `?parent_id=root` for top level only. `products_count` counts active products only.'],
        ['categories.tree', 'GET /categories?tree=1', 'Category tree', 'Nested, for a mega-menu.'],
        ['categories.show', 'GET /categories/{slug}', 'One category', 'Includes the parent and the direct children.'],
        ['categories.products', 'GET /categories/{slug}/products?per_page=2', 'Products in a category',
            "Also includes products filed under the category's children. `meta.included` lists every category id covered."],
    ],

    '## 4. Brands and combos' => [
        ['brands.index', 'GET /brands', 'Brand list', ''],
        ['brands.show', 'GET /brands/{slug}', 'One brand', ''],
        ['brands.products', 'GET /brands/{slug}/products?per_page=2', 'Products for a brand',
            'Accepts the same filters as the product list.'],
        ['combos.index', 'GET /combos', 'Combo list',
            'Only combos that are switched on and inside their date window.'],
        ['combos.show', 'GET /combos/{slug}', 'One combo',
            'Pricing is computed server-side, so a bundle can never be quoted at a different figure.'],
    ],
];

$endpoints += [
    '## 5. Cart, wishlist and orders' => [
        ['cart.empty', 'GET /cart', 'Cart', '`meta.count` and `meta.subtotal` drive the header badge.'],
        ['cart.add', 'POST /cart', 'Add to cart',
            "Sending the same product twice tops up the existing line. `product_variation_id` is optional.\n\n"
            .'**The price is always resolved from the database, never from the request body.**'],
        ['cart.update', 'PATCH /cart/{id}', 'Set quantity',
            'Quantity `0` removes the line, so the drawer can drop a row without a second call.'],
        ['cart.get', 'GET /cart', 'Cart with items', ''],
        ['cart.remove', 'DELETE /cart/{id}', 'Remove one line', ''],
        ['cart.clear', 'DELETE /cart', 'Empty the cart', ''],
        ['wishlist.toggle', 'POST /wishlist/toggle', 'Add or remove',
            'Flip the heart from `data.in_wishlist` rather than guessing, or the icon drifts out of sync with the server.'],
        ['wishlist.get', 'GET /wishlist', 'Wishlist', 'Full product objects, ready to render as cards.'],
        ['wishlist.remove', 'DELETE /wishlist/{productId}', 'Remove from wishlist', ''],
        ['orders.list', 'GET /orders', 'Order history',
            'Read-only. `?status=` and `?payment_status=` filter the list.'],
    ],

    '## 6. Search and site content' => [
        ['search', 'GET /search?q=bracelet', 'Search',
            'Returns products plus the categories that matched and the customer recent searches.'],
        ['home', 'GET /home', 'Home page',
            'Sliders, categories, new arrivals, featured, on sale, brands, FAQs and testimonials in one request.'],
        ['sliders', 'GET /sliders', 'Hero sliders', ''],
        ['faqs', 'GET /faqs', 'FAQs', ''],
        ['testimonials', 'GET /testimonials', 'Testimonials', ''],
        ['pages.index', 'GET /pages', 'CMS page index', 'Titles and slugs only; the body is on the detail call.'],
        ['pages.show', 'GET /pages/{slug}', 'One CMS page', 'Full HTML content plus meta fields.'],
        ['settings', 'GET /settings', 'Store settings',
            'Name, contact details, address and social links. Mail credentials are never exposed.'],
        ['newsletter', 'POST /newsletter', 'Newsletter sign-up',
            'An address that is already subscribed returns 200 rather than an error.'],
        ['metal-prices', 'GET /metal-prices', 'Metal spot rates', 'Live gold and silver prices, cached server-side.'],
    ],

    '## 7. Errors you must handle' => [
        ['error.401', 'GET /cart (no token)', '401 Unauthenticated',
            'The token is missing, expired or revoked. The client clears the stored token so the UI re-renders as signed out.'],
        ['error.404', 'GET /products/{unknown slug}', '404 Not found',
            'Also what an **inactive** product returns, so a draft is not discoverable. There is no 403 for those.'],
        ['error.422', 'POST /auth/login (bad input)', '422 Validation failed',
            '`errors` is keyed by field name, ready to map straight onto form inputs.'],
    ],
];

$md = '';

foreach ($endpoints as $heading => $rows) {
    $md .= $heading."\n\n";

    foreach ($rows as [$key, $signature, $title, $note]) {
        $call = $captured[$key] ?? null;

        if (! $call) {
            continue;
        }

        $auth = $call['auth'] ? ' &nbsp;`Bearer token required`' : '';
        $md .= "### `{$signature}`{$auth}\n\n";
        $md .= "**{$title}**".($note === '' ? '' : "  \n{$note}")."\n\n";
        $md .= render($key, $call)."\n";
    }
}

file_put_contents(__DIR__.'/endpoints.md', $md);

echo 'endpoints.md: '.strlen($md).' bytes'.PHP_EOL;
