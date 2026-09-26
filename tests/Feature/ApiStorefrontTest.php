<?php

use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

/*
|--------------------------------------------------------------------------
| Storefront JSON API (https://astroemerging.com/gehna/api/v1)
|--------------------------------------------------------------------------
|
| Guards the contract the React client depends on: the response envelope, the
| shapes of the product and category payloads, the token auth flow, and the
| rule that one customer can never read or write another's data.
|
*/

/** Build a product the API can return. */
function apiProduct(string $name, array $overrides = []): Product
{
    return Product::create(array_merge([
        'name' => $name,
        'slug' => Str::slug($name).'-'.uniqid(),
        'base_price' => 1000,
        'product_type' => 'simple',
        'is_active' => true,
        'manage_stock' => true,
        'stock' => 10,
    ], $overrides));
}

/** Build an order belonging to a customer. */
function apiOrder(int $userId): Order
{
    return Order::create([
        'user_id' => $userId,
        'status' => 'pending',
        'payment_status' => 'pending',
        'total' => 1000,
    ]);
}

/* -- Envelope and catalogue ---------------------------------------- */

it('returns the standard envelope from the product list', function () {
    apiProduct('Envelope Ring');

    get('/api/v1/products')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [['id', 'name', 'slug', 'price', 'regular_price', 'in_stock', 'url']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

it('paginates and caps per_page', function () {
    foreach (range(1, 5) as $index) {
        apiProduct("Paged Ring {$index}");
    }

    get('/api/v1/products?per_page=2')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonCount(2, 'data');

    // A hostile ?per_page= is clamped instead of pulling the whole catalogue.
    config(['storefront.per_page_max' => 3]);

    get('/api/v1/products?per_page=100000')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 3);
});

it('hides inactive products from the list and the detail endpoint', function () {
    $draft = apiProduct('Draft Ring', ['is_active' => false]);

    get('/api/v1/products')->assertOk()->assertDontSee($draft->name, false);

    // 404 rather than 403: an unpublished product must not even be discoverable.
    get("/api/v1/products/{$draft->slug}")->assertNotFound();
});

it('filters the product list by on_sale and audience', function () {
    $onSale = apiProduct('Half Priced Chain', [
        'base_price' => 2000, 'sale_price' => 1000, 'audience' => 'women',
    ]);
    apiProduct('Full Priced Chain', ['base_price' => 2000, 'audience' => 'men']);

    get('/api/v1/products?on_sale=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $onSale->id);

    get('/api/v1/products?audience=men')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.audience', 'men');
});

it('serves the category list, the tree, and a single category', function () {
    $root = Category::create(['name' => 'Jewellery Root']);
    Category::create(['name' => 'Necklaces Child', 'parent_id' => $root->id]);

    get('/api/v1/categories')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'slug', 'is_root', 'breadcrumb', 'products_count']],
        ]);

    get('/api/v1/categories?tree=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonCount(1, 'data.0.children');

    get("/api/v1/categories/{$root->slug}")
        ->assertOk()
        ->assertJsonPath('data.slug', $root->slug);
});

it('includes child category products on a category page', function () {
    $parent = Category::create(['name' => 'Rings Parent']);
    $child = Category::create(['name' => 'Rings Child', 'parent_id' => $parent->id]);

    apiProduct('Nested Ring', ['category_id' => $child->id]);

    $response = get("/api/v1/categories/{$parent->slug}/products")->assertOk();

    $response->assertJsonCount(1, 'data')
        ->assertJsonPath('category.id', $parent->id);

    // The ids actually covered come back so the client can offer child chips.
    expect($response->json('meta.included'))
        ->toContain($parent->id)
        ->toContain($child->id);
});


/* -- Product detail ------------------------------------------------ */

it('returns detail with variations priced the way the storefront shows them', function () {
    $product = apiProduct('Detailed Bangle', ['base_price' => 3000, 'sale_price' => 2400]);

    ProductVariation::create([
        'product_id' => $product->id,
        'sku' => 'BNG-001',
        'price' => 3000,
        'sale_price' => 2400,
        'stock' => 5,
        'attributes' => ['Size' => '2.4'],
        'is_active' => true,
    ]);

    get("/api/v1/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.product.price', 2400)
        ->assertJsonPath('data.product.regular_price', 3000)
        ->assertJsonPath('data.product.is_on_sale', true)
        ->assertJsonPath('data.product.discount_percentage', 20)
        ->assertJsonPath('data.reviews.summary.count', 0)
        ->assertJsonCount(1, 'data.product.variations')
        ->assertJsonPath('data.product.variations.0.attributes.Size', '2.4');
});

it('returns absolute image urls so a separate origin can load them', function () {
    $product = apiProduct('Pictured Ring');
    $product->images()->create(['path' => 'products/ring.png', 'is_primary' => true]);

    $url = get("/api/v1/products/{$product->slug}")
        ->assertOk()
        ->json('data.product.primary_image');

    expect($url)->toStartWith('http')
        ->and($url)->toContain('/storage/products/ring.png');
});

/* -- Auth ---------------------------------------------------------- */

it('registers a customer and returns a usable bearer token', function () {
    $response = postJson('/api/v1/auth/register', [
        'name' => 'Reactive Rita',
        'email' => 'rita@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'phone' => '9812345678',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['token', 'token_type', 'user' => ['id', 'name', 'email']]])
        ->assertJsonPath('data.token_type', 'Bearer');

    // The password must never come back out.
    expect($response->json('data.user'))->not->toHaveKey('password');

    $this->withToken($response->json('data.token'))
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'rita@example.com');
});

it('rejects a duplicate phone with a field error, not a server error', function () {
    User::factory()->create(['phone' => '9000000001']);

    postJson('/api/v1/auth/register', [
        'name' => 'Second Number',
        'email' => 'second@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'phone' => '9000000001',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

it('logs in with valid credentials and refuses bad ones', function () {
    // The factory's default password is the literal string "password".
    User::factory()->create(['email' => 'known@example.com']);

    postJson('/api/v1/auth/login', [
        'email' => 'known@example.com',
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer');

    postJson('/api/v1/auth/login', [
        'email' => 'known@example.com',
        'password' => 'not-the-password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('rejects protected endpoints without a token', function () {
    getJson('/api/v1/cart')->assertUnauthorized();
    getJson('/api/v1/wishlist')->assertUnauthorized();
    getJson('/api/v1/orders')->assertUnauthorized();
    getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('revokes the token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    // Asserted against the row rather than a second request: within a single
    // test the harness keeps the resolved guard user between requests, so a
    // follow-up call would still look authenticated even though the token is
    // genuinely gone. A fresh request really does get a 401.
    $this->assertDatabaseMissing('personal_access_tokens', [
        'token' => hash('sha256', $token),
    ]);
});


/* -- Cart, wishlist, orders ---------------------------------------- */

it('adds, updates and removes a cart line with server-side pricing', function () {
    $user = User::factory()->create();
    $product = apiProduct('Cart Ring', ['base_price' => 1500]);

    Sanctum::actingAs($user);

    $added = postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 2]);

    $added->assertCreated()->assertJsonPath('meta.count', 2);

    // The price comes from the database, never from the request body.
    // JSON drops the ".0" on a whole number, so compare numerically.
    expect((float) $added->json('data.0.price'))->toBe(1500.0)
        ->and((float) $added->json('meta.subtotal'))->toBe(3000.0);

    $lineId = $added->json('data.0.id');

    patchJson("/api/v1/cart/{$lineId}", ['quantity' => 3])
        ->assertOk()
        ->assertJsonPath('data.0.quantity', 3);

    // quantity 0 is the API's way of removing a line.
    patchJson("/api/v1/cart/{$lineId}", ['quantity' => 0])
        ->assertOk()
        ->assertJsonCount(0, 'data');

    expect(Cart::where('user_id', $user->id)->count())->toBe(0);
});

it('tops up an existing cart line instead of duplicating it', function () {
    $user = User::factory()->create();
    $product = apiProduct('Repeat Ring');

    Sanctum::actingAs($user);

    postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 1])->assertCreated();
    postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 2])
        ->assertCreated()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.quantity', 3);
});

it('never shows one customer another customer cart', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();
    $product = apiProduct('Private Ring');

    Sanctum::actingAs($theirs);
    postJson('/api/v1/cart', ['product_id' => $product->id])->assertCreated();

    $theirLineId = Cart::where('user_id', $theirs->id)->value('id');

    Sanctum::actingAs($mine);

    getJson('/api/v1/cart')->assertOk()->assertJsonCount(0, 'data');

    // A line belonging to someone else 404s instead of being writable.
    patchJson("/api/v1/cart/{$theirLineId}", ['quantity' => 99])->assertNotFound();
    deleteJson("/api/v1/cart/{$theirLineId}")->assertNotFound();
});

it('toggles a wishlist entry and reports the new state', function () {
    $user = User::factory()->create();
    $product = apiProduct('Wished Ring');

    Sanctum::actingAs($user);

    $on = postJson('/api/v1/wishlist/toggle', ['product_id' => $product->id])->assertCreated();
    expect($on->json('data.in_wishlist'))->toBeTrue();

    $off = postJson('/api/v1/wishlist/toggle', ['product_id' => $product->id])->assertOk();
    expect($off->json('data.in_wishlist'))->toBeFalse();

    expect(Wishlist::where('user_id', $user->id)->count())->toBe(0);
});

it('lists only the signed-in customer orders', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    apiOrder($user->id);
    apiOrder($other->id);

    Sanctum::actingAs($user);

    getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 1);
});

/* -- Content ------------------------------------------------------- */

it('serves the whole home payload in one request', function () {
    $category = Category::create(['name' => 'Home Cat']);
    apiProduct('Home Product', ['category_id' => $category->id]);

    get('/api/v1/home')->assertOk()->assertJsonStructure([
        'data' => [
            'sliders', 'categories', 'new_arrivals', 'featured',
            'on_sale', 'brands', 'faqs', 'testimonials',
        ],
    ]);
});

it('requires a search term', function () {
    // getJson(), not get(): without Accept: application/json a validation
    // failure is a 302 redirect, which is correct for a browser form.
    getJson('/api/v1/search')->assertStatus(422)->assertJsonValidationErrors('q');

    apiProduct('Searchable Anklet');

    getJson('/api/v1/search?q=anklet')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['query', 'total']]);
});

it('never exposes mail credentials through the settings endpoint', function () {
    Setting::create([
        'site_name' => 'Gehna',
        'smtp_password' => 'super-secret-mail-password',
        'resend_api_key' => 're_super_secret_key',
    ]);

    $body = get('/api/v1/settings')->assertOk()->getContent();

    expect($body)
        ->toContain('Gehna')
        ->not->toContain('super-secret-mail-password')
        ->not->toContain('re_super_secret_key')
        ->not->toContain('smtp_password');
});

it('requires no token for the public read endpoints', function () {
    foreach ([
        '/api/v1/home',
        '/api/v1/categories',
        '/api/v1/brands',
        '/api/v1/combos',
        '/api/v1/sliders',
        '/api/v1/faqs',
        '/api/v1/testimonials',
        '/api/v1/pages',
        '/api/v1/settings',
    ] as $endpoint) {
        get($endpoint)->assertOk();
    }
});

it('reports a 404 in the standard envelope for a missing product', function () {
    get('/api/v1/products/no-such-product-here')
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['message', 'data', 'meta']);
});

/* -- Error envelope consistency ------------------------------------- */

it('uses the same envelope for every framework error, not just controller ones', function () {
    // The controllers answer with { success, message, data, errors, meta }.
    // Laravel's own 401/404/422 used to render in their own shapes, which would
    // have meant two error paths in the React app. bootstrap/app.php now
    // normalises them; these are the cases that regression would break.
    $cases = [
        // 401 raised by the auth:sanctum middleware, not by a controller.
        ['get', '/api/v1/cart', 401],
        // 404 from a route that does not exist at all.
        ['get', '/api/v1/no-such-endpoint', 404],
        // 422 raised by Laravel's validator.
        ['post', '/api/v1/auth/login', 422],
    ];

    foreach ($cases as [$method, $uri, $status]) {
        $response = $method === 'post'
            ? postJson($uri, ['email' => 'not-an-email', 'password' => ''])
            : getJson($uri);

        $response->assertStatus($status)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'data', 'errors', 'meta']);

        // `data` is always null and `meta`/`errors` are always objects, so the
        // client can read them without a type guard.
        expect($response->json('data'))->toBeNull()
            ->and($response->json('meta'))->toBe([])
            ->and($response->json('errors'))->toBeArray();
    }
});

it('leaves web error handling alone', function () {
    // The API envelope must not leak into the Blade side: a browser form must
    // still get a redirect with flash errors rather than JSON. Laravel's test
    // client disables CSRF, so this is the plain 302 the form would follow.
    $response = $this->post('/login', ['email' => 'nope', 'password' => '']);

    $response->assertStatus(302)
        ->assertSessionHasErrors('email');

    expect($response->headers->get('content-type'))
        ->not->toContain('application/json');
});

