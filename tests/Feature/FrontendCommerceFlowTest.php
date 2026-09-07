<?php

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use App\Models\Wishlist;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

function checkoutPayloadForCommerceTest(array $overrides = []): array
{
    return array_merge([
        'payment_method' => 'cod',
        'billing_name' => 'John Doe',
        'billing_email' => 'john@example.com',
        'billing_phone' => '9999999999',
        'billing_line1' => 'Street 1',
        'billing_city' => 'Mumbai',
        'billing_state' => 'MH',
        'billing_zip' => '400001',
        'billing_country' => 'India',
        'shipping_same_as_billing' => '1',
    ], $overrides);
}

it('adds cart item with product_id and increments same product quantity only', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $productA = Product::create([
        'name' => 'Bench Press',
        'slug' => 'bench-press-' . uniqid(),
        'base_price' => 120,
        'is_active' => true,
    ]);

    $productB = Product::create([
        'name' => 'Dumbbell Set',
        'slug' => 'dumbbell-set-' . uniqid(),
        'base_price' => 80,
        'is_active' => true,
    ]);

    actingAs($user);

    post(route('cart.add'), [
        'product_id' => $productA->id,
        'quantity' => 1,
    ])->assertSessionHas('success');

    post(route('cart.add'), [
        'product_id' => $productA->id,
        'quantity' => 2,
    ])->assertSessionHas('success');

    post(route('cart.add'), [
        'product_id' => $productB->id,
        'quantity' => 1,
    ])->assertSessionHas('success');

    $itemA = Cart::where('user_id', $user->id)->where('product_id', $productA->id)->first();
    $itemB = Cart::where('user_id', $user->id)->where('product_id', $productB->id)->first();

    expect($itemA)->not->toBeNull();
    expect((int) $itemA->quantity)->toBe(3);
    expect((int) Cart::where('user_id', $user->id)->count())->toBe(2);
    expect($itemB)->not->toBeNull();
});

it('allows guests to add products to the session cart without login', function () {
    $product = Product::create([
        'name' => 'Guest Cart Product',
        'slug' => 'guest-cart-product-' . uniqid(),
        'base_price' => 149,
        'is_active' => true,
    ]);

    post(route('cart.add'), [
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertRedirect()
      ->assertSessionHas('success', 'Product added to cart successfully.');

    $guestCart = (array) session('guest_cart', []);

    expect($guestCart)->toHaveCount(1);
    expect((int) ($guestCart[0]['product_id'] ?? 0))->toBe($product->id);
    expect((int) ($guestCart[0]['quantity'] ?? 0))->toBe(2);
    expect(Cart::count())->toBe(0);
});

it('applies and removes coupon from checkout session', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $product = Product::create([
        'name' => 'Kettlebell',
        'slug' => 'kettlebell-' . uniqid(),
        'base_price' => 100,
        'is_active' => true,
    ]);

    Cart::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'product_variation_id' => null,
        'quantity' => 1,
        'price' => 100,
    ]);

    Coupon::create([
        'code' => 'SAVE10',
        'type' => 'percent',
        'amount' => 10,
        'is_active' => true,
    ]);

    actingAs($user);

    post(route('checkout.coupon.apply'), [
        'coupon_code' => 'save10',
    ])->assertSessionHas('checkout_coupon.code', 'SAVE10');

    delete(route('checkout.coupon.remove'))
        ->assertSessionMissing('checkout_coupon');
});

it('toggles wishlist for authenticated user', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $product = Product::create([
        'name' => 'Treadmill',
        'slug' => 'treadmill-' . uniqid(),
        'base_price' => 900,
        'is_active' => true,
    ]);

    actingAs($user);

    post(route('wishlist.toggle'), [
        'product_id' => $product->id,
    ])->assertSessionHas('success');

    expect((int) Wishlist::where('user_id', $user->id)->where('product_id', $product->id)->count())->toBe(1);

    post(route('wishlist.toggle'), [
        'product_id' => $product->id,
    ])->assertSessionHas('success');

    expect((int) Wishlist::where('user_id', $user->id)->where('product_id', $product->id)->count())->toBe(0);
});

it('allows guests to toggle wishlist products without login', function () {
    $product = Product::create([
        'name' => 'Guest Wishlist Product',
        'slug' => 'guest-wishlist-product-' . uniqid(),
        'base_price' => 199,
        'is_active' => true,
    ]);

    post(route('wishlist.toggle'), [
        'product_id' => $product->id,
    ])->assertRedirect()
      ->assertSessionHas('success', 'Added to wishlist.');

    expect((array) session('guest_wishlist', []))->toContain($product->id);
    expect(Wishlist::count())->toBe(0);

    post(route('wishlist.toggle'), [
        'product_id' => $product->id,
    ])->assertRedirect()
      ->assertSessionHas('success', 'Removed from wishlist.');

    expect((array) session('guest_wishlist', []))->not->toContain($product->id);
});

it('places cod order with coupon discount applied to total', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $product = Product::create([
        'name' => 'Row Machine',
        'slug' => 'row-machine-' . uniqid(),
        'base_price' => 100,
        'is_active' => true,
    ]);

    PaymentProvider::updateOrCreate(
        ['slug' => 'cod'],
        ['name' => 'Cash on Delivery', 'is_active' => true]
    );

    $coupon = Coupon::create([
        'code' => 'LESS50',
        'type' => 'fixed',
        'amount' => 50,
        'is_active' => true,
    ]);

    Cart::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'product_variation_id' => null,
        'quantity' => 2,
        'price' => 100,
    ]);

    actingAs($user);

    post(route('checkout.coupon.apply'), [
        'coupon_code' => 'LESS50',
    ])->assertSessionHas('checkout_coupon.code', 'LESS50');

    post(route('checkout.place'), checkoutPayloadForCommerceTest())
        ->assertRedirect();

    $order = Order::latest('id')->first();

    expect($order)->not->toBeNull();
    // 200 subtotal - 50 discount + 199 shipping = 349
    expect((float) $order->total)->toBe(349.0);
    expect((int) $coupon->fresh()->used_count)->toBe(1);
    expect(data_get($order->payment_meta, 'coupon.code'))->toBe('LESS50');
});

it('shows cart summary with coupon and supports quantity update and remove', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $product = Product::create([
        'name' => 'Power Rack',
        'slug' => 'power-rack-' . uniqid(),
        'base_price' => 250,
        'is_active' => true,
    ]);

    $cart = Cart::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'product_variation_id' => null,
        'quantity' => 1,
        'price' => 250,
    ]);

    Coupon::create([
        'code' => 'PCT10',
        'type' => 'percent',
        'amount' => 10,
        'is_active' => true,
    ]);

    actingAs($user);

    post(route('checkout.coupon.apply'), [
        'coupon_code' => 'PCT10',
    ])->assertSessionHas('checkout_coupon.code', 'PCT10');

    get(route('cart.index'))
        ->assertOk()
        ->assertSee('PCT10')
        ->assertSee('₹250')
        ->assertSee('₹424');

    patch(route('cart.update', $cart), [
        'quantity' => 3,
    ])->assertSessionHas('success');

    expect((int) $cart->fresh()->quantity)->toBe(3);

    delete(route('cart.destroy', $cart))
        ->assertSessionHas('success');

    expect((int) Cart::where('user_id', $user->id)->count())->toBe(0);
});

it('requires variation for variable product cart add and stores selected variation', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $product = Product::create([
        'name' => 'Protein Pack',
        'slug' => 'protein-pack-' . uniqid(),
        'base_price' => 100,
        'is_active' => true,
        'product_type' => 'variable',
    ]);

    $variation = ProductVariation::create([
        'product_id' => $product->id,
        'sku' => 'PROT-' . uniqid(),
        'price' => 120,
        'stock' => 10,
        'attributes' => ['size' => 'Large'],
        'is_active' => true,
    ]);

    actingAs($user);

    post(route('cart.add'), [
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertSessionHas('error');

    post(route('cart.add'), [
        'product_id' => $product->id,
        'product_variation_id' => $variation->id,
        'quantity' => 2,
    ])->assertSessionHas('success');

    $cartItem = Cart::where('user_id', $user->id)
        ->where('product_id', $product->id)
        ->where('product_variation_id', $variation->id)
        ->first();

    expect($cartItem)->not->toBeNull();
    expect((int) $cartItem->quantity)->toBe(2);
    expect((float) $cartItem->price)->toBe(120.0);
});

it('toggles wishlist by selected variation for variable products', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $product = Product::create([
        'name' => 'Energy Drink Pack',
        'slug' => 'energy-drink-pack-' . uniqid(),
        'base_price' => 75,
        'is_active' => true,
        'product_type' => 'variable',
    ]);

    $variation = ProductVariation::create([
        'product_id' => $product->id,
        'sku' => 'ENRG-' . uniqid(),
        'price' => 90,
        'stock' => 15,
        'attributes' => ['flavor' => 'Orange'],
        'is_active' => true,
    ]);

    actingAs($user);

    post(route('wishlist.toggle'), [
        'product_id' => $product->id,
    ])->assertSessionHas('error');

    post(route('wishlist.toggle'), [
        'product_id' => $product->id,
        'product_variation_id' => $variation->id,
    ])->assertSessionHas('success');

    expect((int) Wishlist::where('user_id', $user->id)
        ->where('product_id', $product->id)
        ->where('product_variation_id', $variation->id)
        ->count())->toBe(1);

    post(route('wishlist.toggle'), [
        'product_id' => $product->id,
        'product_variation_id' => $variation->id,
    ])->assertSessionHas('success');

    expect((int) Wishlist::where('user_id', $user->id)
        ->where('product_id', $product->id)
        ->where('product_variation_id', $variation->id)
        ->count())->toBe(0);
});
it('makes the lowest priced product free with a buy 1 get 1 coupon', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $expensive = Product::create([
        'name' => 'Premium Kettlebell',
        'slug' => 'premium-kettlebell-' . uniqid(),
        'base_price' => 200,
        'is_active' => true,
    ]);

    $cheap = Product::create([
        'name' => 'Gym Mat',
        'slug' => 'gym-mat-' . uniqid(),
        'base_price' => 40,
        'is_active' => true,
    ]);

    Cart::create([
        'user_id' => $user->id,
        'product_id' => $expensive->id,
        'product_variation_id' => null,
        'quantity' => 1,
        'price' => 200,
    ]);

    Cart::create([
        'user_id' => $user->id,
        'product_id' => $cheap->id,
        'product_variation_id' => null,
        'quantity' => 1,
        'price' => 40,
    ]);

    Coupon::create([
        'code' => 'BOGO1',
        'type' => 'buy_get',
        'buy_quantity' => 1,
        'get_quantity' => 1,
        'is_active' => true,
    ]);

    PaymentProvider::updateOrCreate(
        ['slug' => 'cod'],
        ['name' => 'Cash on Delivery', 'is_active' => true]
    );

    actingAs($user);

    post(route('checkout.coupon.apply'), [
        'coupon_code' => 'bogo1',
    ])->assertSessionHas('checkout_coupon.code', 'BOGO1');

    get(route('cart.index'))
        ->assertOk()
        ->assertSee('BOGO1')
        ->assertSee('Gym Mat')
        ->assertSee('1 FREE');

    get(route('checkout.index'))
        ->assertOk()
        ->assertSee('FREE gifts')
        ->assertSee('Gym Mat');

    // Subtotal 240 - free cheapest item 40 + shipping 199 = 399
    post(route('checkout.place'), checkoutPayloadForCommerceTest())
        ->assertRedirect();

    $order = Order::latest('id')->first();

    expect($order)->not->toBeNull();
    expect((float) $order->total)->toBe(399.0);
    expect((float) data_get($order->payment_meta, 'pricing.discount'))->toBe(40.0);
    expect(data_get($order->payment_meta, 'coupon.free_items'))->toHaveCount(1);

    $freeItems = data_get($order->payment_meta, 'coupon.free_items', []);
    expect((int) $freeItems[0]['product_id'])->toBe($cheap->id);
    expect((float) $freeItems[0]['unit_price'])->toBe(40.0);
    expect((int) $freeItems[0]['free_quantity'])->toBe(1);
});

it('makes the lowest priced units free when a buy get coupon spans multiple bundles', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $productA = Product::create([
        'name' => 'Barbell',
        'slug' => 'barbell-' . uniqid(),
        'base_price' => 100,
        'is_active' => true,
    ]);

    $productB = Product::create([
        'name' => 'Dumbbell',
        'slug' => 'dumbbell-' . uniqid(),
        'base_price' => 50,
        'is_active' => true,
    ]);

    Cart::create([
        'user_id' => $user->id,
        'product_id' => $productA->id,
        'product_variation_id' => null,
        'quantity' => 2,
        'price' => 100,
    ]);

    Cart::create([
        'user_id' => $user->id,
        'product_id' => $productB->id,
        'product_variation_id' => null,
        'quantity' => 3,
        'price' => 50,
    ]);

    Coupon::create([
        'code' => 'BOGO2',
        'type' => 'buy_get',
        'buy_quantity' => 1,
        'get_quantity' => 1,
        'is_active' => true,
    ]);

    PaymentProvider::updateOrCreate(
        ['slug' => 'cod'],
        ['name' => 'Cash on Delivery', 'is_active' => true]
    );

    actingAs($user);

    post(route('checkout.coupon.apply'), [
        'coupon_code' => 'BOGO2',
    ])->assertSessionHas('checkout_coupon.code', 'BOGO2');

    // 5 units -> intdiv(5, 2) * 1 = 2 free units (the two Rs 50 dumbbells)
    // Subtotal 350 - discount 100 + shipping 199 = 449
    post(route('checkout.place'), checkoutPayloadForCommerceTest())
        ->assertRedirect();

    $order = Order::latest('id')->first();

    expect($order)->not->toBeNull();
    expect((float) $order->total)->toBe(449.0);
    expect((float) data_get($order->payment_meta, 'pricing.discount'))->toBe(100.0);

    $freeItems = data_get($order->payment_meta, 'coupon.free_items', []);
    expect(\count($freeItems))->toBe(1);
    expect((int) $freeItems[0]['product_id'])->toBe($productB->id);
    expect((int) $freeItems[0]['free_quantity'])->toBe(2);
    expect((float) $freeItems[0]['free_amount'])->toBe(100.0);
});

it('lets guests view the cart page with their session cart items', function () {
    $product = Product::create([
        'name' => 'Guest Cart View Product',
        'slug' => 'guest-cart-view-product-' . uniqid(),
        'base_price' => 249,
        'is_active' => true,
    ]);

    post(route('cart.add'), [
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertRedirect();

    get(route('cart.index'))
        ->assertOk()
        ->assertSee($product->name);
});

it('lets guests view the wishlist page with their session wishlist items', function () {
    $product = Product::create([
        'name' => 'Guest Wishlist View Product',
        'slug' => 'guest-wishlist-view-product-' . uniqid(),
        'base_price' => 129,
        'is_active' => true,
    ]);

    post(route('wishlist.toggle'), [
        'product_id' => $product->id,
    ])->assertRedirect();

    get(route('wishlist.index'))
        ->assertOk()
        ->assertSee($product->name);
});

it('requires guests to login before checkout', function () {
    get(route('checkout.index'))
        ->assertRedirect(route('login'));

    post(route('checkout.place'), checkoutPayloadForCommerceTest())
        ->assertRedirect(route('login'));
});

it('merges guest session cart and wishlist into the account after login', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $cartProduct = Product::create([
        'name' => 'Merge Cart Product',
        'slug' => 'merge-cart-product-' . uniqid(),
        'base_price' => 199,
        'is_active' => true,
    ]);

    $wishlistProduct = Product::create([
        'name' => 'Merge Wishlist Product',
        'slug' => 'merge-wishlist-product-' . uniqid(),
        'base_price' => 299,
        'is_active' => true,
    ]);

    // Existing account rows to verify the merge increments / de-duplicates.
    Cart::create([
        'user_id' => $user->id,
        'product_id' => $cartProduct->id,
        'product_variation_id' => null,
        'quantity' => 1,
        'price' => 199,
    ]);

    Wishlist::create([
        'user_id' => $user->id,
        'product_id' => $wishlistProduct->id,
        'product_variation_id' => null,
    ]);

    // Guest (not logged in) adds items to the session cart & wishlist.
    post(route('cart.add'), [
        'product_id' => $cartProduct->id,
        'quantity' => 2,
    ])->assertRedirect();

    post(route('wishlist.toggle'), [
        'product_id' => $wishlistProduct->id,
    ])->assertRedirect();

    post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    // Guest cart merged into the existing row (1 + 2) and session cleared.
    $cartItem = Cart::where('user_id', $user->id)
        ->where('product_id', $cartProduct->id)
        ->first();

    expect($cartItem)->not->toBeNull();
    expect((int) $cartItem->quantity)->toBe(3);
    expect((array) session('guest_cart', []))->toBeEmpty();

    // Guest wishlist merged without duplicating the existing row.
    expect((int) Wishlist::where('user_id', $user->id)
        ->where('product_id', $wishlistProduct->id)
        ->count())->toBe(1);
    expect((array) session('guest_wishlist', []))->toBeEmpty();
});

it('updates logged in cart item quantity over ajax with fresh totals and no page reload', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $product = Product::create([
        'name' => 'Ajax Cart Product',
        'slug' => 'ajax-cart-product-' . uniqid(),
        'base_price' => 100,
        'is_active' => true,
    ]);

    $cartItem = Cart::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'product_variation_id' => null,
        'quantity' => 2,
        'price' => 100,
    ]);

    actingAs($user);

    patch(route('cart.update', $cartItem->id), [
        'quantity' => 5,
    ], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertOk()
      ->assertJsonPath('success', true)
      ->assertJsonPath('items.0.id', (string) $cartItem->id)
      ->assertJsonPath('items.0.quantity', 5)
      ->assertJsonPath('items.0.line_total', 500)
      ->assertJsonPath('subtotal', 500)
      ->assertJsonPath('shipping_charge', 199)
      ->assertJsonPath('grand_total', 699)
      ->assertJsonPath('cart_count', 5);

    expect((int) $cartItem->fresh()->quantity)->toBe(5);
});

it('updates guest session cart quantity over ajax without a page reload', function () {
    $product = Product::create([
        'name' => 'Ajax Guest Cart Product',
        'slug' => 'ajax-guest-cart-product-' . uniqid(),
        'base_price' => 149,
        'is_active' => true,
    ]);

    post(route('cart.add'), [
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertRedirect();

    patch(route('cart.update', 'p' . $product->id), [
        'quantity' => 3,
    ], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertOk()
      ->assertJsonPath('success', true)
      ->assertJsonPath('items.0.id', 'p' . $product->id)
      ->assertJsonPath('items.0.quantity', 3)
      ->assertJsonPath('items.0.line_total', 447)
      ->assertJsonPath('cart_count', 3)
      ->assertJsonPath('grand_total', 646);

    $guestCart = (array) session('guest_cart', []);

    expect($guestCart)->toHaveCount(1);
    expect((int) ($guestCart[0]['quantity'] ?? 0))->toBe(3);
});

it('returns validation errors as json for ajax cart quantity updates', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $product = Product::create([
        'name' => 'Ajax Invalid Qty Product',
        'slug' => 'ajax-invalid-qty-product-' . uniqid(),
        'base_price' => 100,
        'is_active' => true,
    ]);

    $cartItem = Cart::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'product_variation_id' => null,
        'quantity' => 2,
        'price' => 100,
    ]);

    actingAs($user);

    patch(route('cart.update', $cartItem->id), [
        'quantity' => 0,
    ], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertStatus(422)
      ->assertJsonValidationErrors(['quantity']);

    expect((int) $cartItem->fresh()->quantity)->toBe(2);
});

it('renders the shop price filter using real product price bounds', function () {
    // Cheapest effective price (sale price) drives the slider minimum.
    Product::create([
        'name' => 'Cheapest Ring',
        'slug' => 'cheapest-ring-' . uniqid(),
        'base_price' => 4000,
        'sale_price' => 500,
        'is_active' => true,
    ]);

    Product::create([
        'name' => 'Mid Necklace',
        'slug' => 'mid-necklace-' . uniqid(),
        'base_price' => 1500,
        'is_active' => true,
    ]);

    // Most expensive product drives the slider maximum and default position.
    Product::create([
        'name' => 'Priciest Necklace',
        'slug' => 'priciest-necklace-' . uniqid(),
        'base_price' => 3000,
        'is_active' => true,
    ]);

    // Inactive products must not affect the slider bounds.
    Product::create([
        'name' => 'Hidden Draft',
        'slug' => 'hidden-draft-' . uniqid(),
        'base_price' => 99999,
        'is_active' => false,
    ]);

    get(route('products.index'))
        ->assertOk()
        ->assertSee('min="500"', false)
        ->assertSee('max="3000"', false)
        ->assertSee('value="3000"', false)
        ->assertSee('₹500', false)
        ->assertDontSee('max="100000"', false);
});

it('filters products by the max price slider value', function () {
    Product::create([
        'name' => 'Budget Ring',
        'slug' => 'budget-ring-' . uniqid(),
        'base_price' => 500,
        'is_active' => true,
    ]);

    Product::create([
        'name' => 'Luxury Necklace',
        'slug' => 'luxury-necklace-' . uniqid(),
        'base_price' => 9000,
        'is_active' => true,
    ]);

    get(route('products.index', ['max_price' => 1000]))
        ->assertOk()
        ->assertSee('Budget Ring')
        ->assertDontSee('Luxury Necklace');
});

it('shows gehna coins balance in the account dropdown for logged in users', function () {
    $user = User::factory()->create([
        'gehna_coins' => 250,
    ]);

    actingAs($user);

    get(route('home'))
        ->assertOk()
        ->assertSee('Gehna Coins')
        ->assertSee('site-account-coins-value')
        ->assertSeeInOrder(['Gehna Coins', '250'])
        ->assertSee(route('logout'), false);
});

it('keeps account as a plain login link for guests without coins', function () {
    get(route('home'))
        ->assertOk()
        ->assertSee(route('login'))
        ->assertDontSee('Gehna Coins');
});

it('credits gehna coins to the user when placing an order with a coins coupon', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $product = Product::create([
        'name' => 'Coin Bracelet',
        'slug' => 'coin-bracelet-' . uniqid(),
        'base_price' => 100,
        'is_active' => true,
    ]);

    PaymentProvider::updateOrCreate(
        ['slug' => 'cod'],
        ['name' => 'Cash on Delivery', 'is_active' => true]
    );

    Coupon::create([
        'code' => 'COINS100',
        'type' => 'gehna_coins',
        'reward_coins' => 100,
        'is_active' => true,
    ]);

    Cart::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'product_variation_id' => null,
        'quantity' => 2,
        'price' => 100,
    ]);

    actingAs($user);

    post(route('checkout.coupon.apply'), [
        'coupon_code' => 'COINS100',
    ])->assertSessionHas('checkout_coupon.code', 'COINS100');

    post(route('checkout.place'), checkoutPayloadForCommerceTest())
        ->assertRedirect();

    expect((int) $user->fresh()->gehna_coins)->toBe(100);

    $order = Order::latest('id')->first();
    expect(data_get($order->payment_meta, 'coupon.reward_coins'))->toBe(100);
});
