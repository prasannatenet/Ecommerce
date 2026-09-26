<?php

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

function couponUserProduct(string $name, float $price = 2000): Product
{
    return Product::create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.uniqid(),
        'base_price' => $price,
        'product_type' => 'simple',
        'is_active' => true,
    ]);
}

function couponUserCode(string $code, array $overrides = []): Coupon
{
    return Coupon::create(array_merge([
        'code' => $code,
        'type' => 'percent',
        'amount' => 10,
        'is_active' => true,
    ], $overrides));
}

/** Put one product in a user's cart so the coupon list is reachable. */
function couponUserCart(User $user, Product $product): void
{
    Cart::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'product_variation_id' => null,
        'quantity' => 1,
        'price' => (float) $product->base_price,
    ]);
}

function couponUserPayload(array $overrides = []): array
{
    return array_merge([
        'payment_method' => 'cod',
        'billing_name' => 'Coupon Buyer',
        'billing_email' => 'coupon@example.com',
        'billing_phone' => '9999999999',
        'billing_line1' => 'Street 1',
        'billing_city' => 'Mumbai',
        'billing_state' => 'MH',
        'billing_zip' => '400001',
        'billing_country' => 'India',
        'shipping_same_as_billing' => '1',
    ], $overrides);
}

function couponUserEnableCod(): void
{
    PaymentProvider::updateOrCreate(
        ['slug' => 'cod'],
        ['name' => 'Cash on Delivery', 'is_active' => true]
    );
}

/* ───────────────────────────────────────────────────────────────
 | A coupon can only ever be redeemed once per account          |
 ─────────────────────────────────────────────────────────────── */

it('disables a coupon for a customer who has already redeemed it', function () {
    $coupon = couponUserCode('ONCEONLY');
    $user = User::factory()->create();
    $product = couponUserProduct('Once Only Product');

    CouponUse::create([
        'coupon_id' => $coupon->id,
        'user_id' => $user->id,
    ]);

    couponUserCart($user, $product);
    actingAs($user);

    // The coupon stays on the list, but disabled with a reason that explains why.
    get(route('cart.index'))
        ->assertOk()
        ->assertSee('You have already used this coupon.')
        ->assertSee('data-coupon-apply="0"', false);
});

it('reports the same reason through the service the checkout uses to apply coupons', function () {
    $coupon = couponUserCode('ONCEONLYCO');
    $user = User::factory()->create();
    $product = couponUserProduct('Once Only Checkout Product');

    CouponUse::create([
        'coupon_id' => $coupon->id,
        'user_id' => $user->id,
    ]);

    couponUserCart($user, $product);
    actingAs($user);

    // The checkout page's coupon panel is currently commented out in the
    // template, so the cart is the surface that renders the list. The service
    // is shared, so the reason the checkout would show is the same one.
    expect(app(App\Services\CouponService::class)->ineligibilityReason($coupon, 2000.0))
        ->toBe('You have already used this coupon.');
});

it('refuses to apply a coupon the customer has already redeemed', function () {
    $coupon = couponUserCode('REUSEFAIL');
    $user = User::factory()->create();
    $product = couponUserProduct('Reuse Refused Product');

    CouponUse::create([
        'coupon_id' => $coupon->id,
        'user_id' => $user->id,
    ]);

    couponUserCart($user, $product);
    actingAs($user);

    post(route('checkout.coupon.apply'), ['coupon_code' => 'REUSEFAIL'])
        ->assertSessionMissing('checkout_coupon')
        ->assertSessionHas('error', 'You have already used this coupon.');
});

it('records the redemption when the order is placed', function () {
    couponUserEnableCod();

    $coupon = couponUserCode('RECORDED');
    $user = User::factory()->create();
    $product = couponUserProduct('Recorded Product', 2000);

    couponUserCart($user, $product);
    actingAs($user);

    post(route('checkout.coupon.apply'), ['coupon_code' => 'RECORDED'])
        ->assertSessionHas('checkout_coupon.code', 'RECORDED');

    post(route('checkout.place'), couponUserPayload())->assertRedirect();

    $order = Order::first();

    expect(CouponUse::where('user_id', $user->id)->where('coupon_id', $coupon->id)->count())->toBe(1)
        ->and($coupon->fresh()->used_count)->toBe(1)
        // The row points back at the order that burned it.
        ->and(CouponUse::first()->order_id)->toBe($order->id);
});

it('still lets a different customer redeem the same coupon', function () {
    couponUserEnableCod();

    $coupon = couponUserCode('SHARED10');
    $first = User::factory()->create();
    $second = User::factory()->create();
    $product = couponUserProduct('Shared Coupon Product', 2000);

    // The first customer burns it.
    couponUserCart($first, $product);
    actingAs($first);
    post(route('checkout.coupon.apply'), ['coupon_code' => 'SHARED10'])
        ->assertSessionHas('checkout_coupon.code', 'SHARED10');
    post(route('checkout.place'), couponUserPayload())->assertRedirect();

    expect(CouponUse::where('coupon_id', $coupon->id)->count())->toBe(1);

    // The second customer is unaffected.
    couponUserCart($second, $product);
    actingAs($second);

    get(route('checkout.index'))
        ->assertOk()
        ->assertDontSee('You have already used this coupon.');

    post(route('checkout.coupon.apply'), ['coupon_code' => 'SHARED10'])
        ->assertSessionHas('checkout_coupon.code', 'SHARED10');

    post(route('checkout.place'), couponUserPayload())->assertRedirect();

    expect(CouponUse::where('coupon_id', $coupon->id)->count())->toBe(2)
        ->and($coupon->fresh()->used_count)->toBe(2);
});

it('leaves a coupon usable by a customer who redeemed a different one', function () {
    $used = couponUserCode('USEDBEFORE');
    $fresh = couponUserCode('STILLMINE');
    $user = User::factory()->create();
    $product = couponUserProduct('Two Coupon Product', 2000);

    CouponUse::create(['coupon_id' => $used->id, 'user_id' => $user->id]);

    couponUserCart($user, $product);
    actingAs($user);

    post(route('checkout.coupon.apply'), ['coupon_code' => 'STILLMINE'])
        ->assertSessionHas('checkout_coupon.code', 'STILLMINE');
});

it('does not limit guests, who cannot be tracked per account', function () {
    $coupon = couponUserCode('GUESTUSE');
    $product = couponUserProduct('Guest Coupon Product', 2000);

    // Guests shop through the session cart, not the carts table.
    post(route('cart.add'), [
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertRedirect();

    $service = app(App\Services\CouponService::class);

    expect($service->usedCouponIdsFor())->toBe([])
        ->and($service->hasRedeemed($coupon))->toBeFalse()
        ->and($service->ineligibilityReason($coupon, 2000.0))->toBeNull();
});

it('enforces one redemption per account in the database as well', function () {
    $coupon = couponUserCode('UNIQUE01');
    $user = User::factory()->create();

    CouponUse::create(['coupon_id' => $coupon->id, 'user_id' => $user->id]);

    // The unique index is the backstop for a concurrent double checkout.
    expect(fn () => CouponUse::create([
        'coupon_id' => $coupon->id,
        'user_id' => $user->id,
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('still prefers the global reason when the coupon is already dead for everyone', function () {
    $coupon = couponUserCode('EXPIRED10', ['expires_at' => now()->subDay()]);
    $user = User::factory()->create();
    $product = couponUserProduct('Expired Coupon Product', 2000);

    CouponUse::create(['coupon_id' => $coupon->id, 'user_id' => $user->id]);
    couponUserCart($user, $product);
    actingAs($user);

    $reason = app(App\Services\CouponService::class)->ineligibilityReason($coupon, 2000.0);

    expect($reason)->toContain('expired on');
});
