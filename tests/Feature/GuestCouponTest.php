<?php

use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Support\Str;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

function guestCouponProduct(string $name, float $price = 2000): Product
{
    return Product::create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.uniqid(),
        'base_price' => $price,
        'is_active' => true,
    ]);
}

function guestCouponCode(string $code, array $overrides = []): Coupon
{
    return Coupon::create(array_merge([
        'code' => $code,
        'type' => 'percent',
        'amount' => 10,
        'is_active' => true,
    ], $overrides));
}

/** Put one product in the guest's session cart, the way the storefront does. */
function guestCouponCart(Product $product, int $quantity = 1): void
{
    post(route('cart.add'), [
        'product_id' => $product->id,
        'quantity' => $quantity,
    ])->assertRedirect();
}

/* ──────────────────────────────
 |  Coupons for shoppers without |
 |  an account                   |
 ─────────────────────────────── */

it('lets a guest apply a coupon without being sent to login', function () {
    $coupon = guestCouponCode('GUEST10', ['amount' => 10]);
    $product = guestCouponProduct('Guest Coupon Product', 2000);

    guestCouponCart($product);

    // The route must be reachable with no session at all: the old behaviour
    // redirected to the login page because it lived in the auth group.
    post(route('cart.coupon.apply'), ['coupon_code' => 'GUEST10'])
        ->assertRedirect()
        ->assertSessionHas('checkout_coupon.code', 'GUEST10');
});

it('tells the guest exactly how much the coupon saves them', function () {
    $coupon = guestCouponCode('SAVE500', ['type' => 'fixed', 'amount' => 500]);
    $product = guestCouponProduct('Guest Fixed Discount Product', 2000);

    guestCouponCart($product);

    post(route('cart.coupon.apply'), ['coupon_code' => 'SAVE500'])
        ->assertSessionHas('success', 'Coupon SAVE500 applied. You saved Rs 500.00.');
});

it('shows the guest the discount in the cart summary', function () {
    $coupon = guestCouponCode('PCT25', ['amount' => 25]);
    $product = guestCouponProduct('Guest Percent Product', 2000);

    guestCouponCart($product);

    post(route('cart.coupon.apply'), ['coupon_code' => 'PCT25'])
        ->assertSessionHas('checkout_coupon.code', 'PCT25');

    // 25% of a 2,000 cart is 500, so the payable total is 1,500 + 199 shipping.
    get(route('cart.index'))
        ->assertOk()
        ->assertSee('PCT25 applied')
        ->assertSee('id="cart-summary-discount"', false)
        ->assertSee('- ₹500')
        ->assertSee('₹1,699');
});

it('describes a buy one get one coupon by the free items it gives away', function () {
    $coupon = guestCouponCode('BOGOGUEST', [
        'type' => 'buy_get',
        'amount' => 0,
        'buy_quantity' => 1,
        'get_quantity' => 1,
    ]);
    $product = guestCouponProduct('Guest Buy Get Product', 2000);

    guestCouponCart($product, 2);

    post(route('cart.coupon.apply'), ['coupon_code' => 'BOGOGUEST'])
        ->assertSessionHas('checkout_coupon.code', 'BOGOGUEST')
        ->assertSessionHas('success', 'Coupon BOGOGUEST applied. You get 1 free item!');

    get(route('cart.index'))
        ->assertOk()
        ->assertSee('FREE gifts (Buy 1 Get 1)');
});

it('explains why a coupon cannot be used instead of silently ignoring it', function () {
    $coupon = guestCouponCode('BIGONLY', ['amount' => 10, 'min_order_amount' => 10000]);
    $product = guestCouponProduct('Guest Below Minimum Product', 500);

    guestCouponCart($product);

    post(route('cart.coupon.apply'), ['coupon_code' => 'BIGONLY'])
        ->assertSessionHas('error', 'Minimum order amount for this coupon is Rs 10,000.00.')
        ->assertSessionMissing('checkout_coupon');

    // The same reason is shown on the offer list, with the button disabled.
    get(route('cart.index'))
        ->assertOk()
        ->assertSee('Minimum order amount for this coupon is Rs 10,000.00.');
});

it('reports an unknown code to a guest', function () {
    $product = guestCouponProduct('Guest Unknown Code Product', 2000);

    guestCouponCart($product);

    post(route('cart.coupon.apply'), ['coupon_code' => 'NOPE'])
        ->assertSessionHas('error', 'Coupon code not found.')
        ->assertSessionMissing('checkout_coupon');
});

it('refuses an expired coupon to a guest', function () {
    $coupon = guestCouponCode('GONE10', ['expires_at' => now()->subDay()]);
    $product = guestCouponProduct('Guest Expired Product', 2000);

    guestCouponCart($product);

    post(route('cart.coupon.apply'), ['coupon_code' => 'GONE10'])
        ->assertSessionHas('error')
        ->assertSessionMissing('checkout_coupon');
});

it('refuses a coupon once its global limit is spent', function () {
    $coupon = guestCouponCode('LIMITED1', ['max_uses' => 1, 'used_count' => 1]);
    $product = guestCouponProduct('Guest Limited Product', 2000);

    guestCouponCart($product);

    post(route('cart.coupon.apply'), ['coupon_code' => 'LIMITED1'])
        ->assertSessionHas('error', 'This coupon has reached its maximum usage limit.')
        ->assertSessionMissing('checkout_coupon');
});

it('lets a guest remove a coupon again', function () {
    $coupon = guestCouponCode('REMOVE10', ['amount' => 10]);
    $product = guestCouponProduct('Guest Remove Product', 2000);

    guestCouponCart($product);

    post(route('cart.coupon.apply'), ['coupon_code' => 'REMOVE10'])
        ->assertSessionHas('checkout_coupon.code', 'REMOVE10');

    delete(route('cart.coupon.remove'))
        ->assertRedirect()
        ->assertSessionHas('success', 'Coupon removed.')
        ->assertSessionMissing('checkout_coupon');

    // Back to the undiscounted total once it is gone.
    get(route('cart.index'))
        ->assertOk()
        ->assertDontSee('REMOVE10 applied');
});

it('keeps the guest coupon in the session so it survives the login at checkout', function () {
    $coupon = guestCouponCode('CARRY10', ['amount' => 10]);
    $product = guestCouponProduct('Guest Carry Over Product', 2000);

    guestCouponCart($product);

    post(route('cart.coupon.apply'), ['coupon_code' => 'CARRY10'])
        ->assertSessionHas('checkout_coupon.code', 'CARRY10');

    // Checkout is behind `auth`, so a guest hitting it is redirected rather than
    // served — but the applied code must still be in the session afterwards.
    get(route('checkout.index'))->assertRedirect(route('login'));

    expect(session('checkout_coupon.code'))->toBe('CARRY10');
});