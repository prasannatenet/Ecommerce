<?php

use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function offerTabCoupon(string $code, array $overrides = []): Coupon
{
    return Coupon::create(array_merge([
        'code' => $code,
        'type' => 'percent',
        'amount' => 10,
        'is_active' => true,
    ], $overrides));
}

/* ───────────────────────────────────────────────────────────────
 |  The left-edge offer tab on every storefront page           |
  ─────────────────────────────────────────────────────────────── */

it('renders the offer tab with every running coupon on the storefront', function () {
    offerTabCoupon('TAB10', ['amount' => 10, 'min_order_amount' => 5000]);
    offerTabCoupon('BUYGET1', ['type' => 'buy_get', 'amount' => 0, 'buy_quantity' => 1, 'get_quantity' => 1]);

    get(route('home'))
        ->assertOk()
        ->assertSee('id="offerTabBtn"', false)
        ->assertSee('Hot Offer')
        ->assertSee('data-offer-code="TAB10"', false)
        ->assertSee('10% OFF')
        // Minimum order and expiry are copy here, not a gate: the shopper may
        // be browsing with an empty cart.
        ->assertSee('Min order ₹5,000')
        ->assertSee('data-offer-code="BUYGET1"', false)
        ->assertSee('Buy 1, Get 1 Free');
});

it('follows the shopper down the page because the tab is fixed to the viewport', function () {
    offerTabCoupon('FIXED10');

    get(route('products.index'))
        ->assertOk()
        ->assertSee('class="offer-tab-wrap"', false)
        ->assertSee('position: fixed;', false)
        ->assertSee('data-offer-code="FIXED10"', false);
});

it('keeps dead coupons out of the promotional tab', function () {
    offerTabCoupon('INACTIVE10', ['is_active' => false]);
    offerTabCoupon('EXPIRED10', ['expires_at' => now()->subDay()]);

    get(route('home'))
        ->assertOk()
        ->assertSee('Hot Offer')
        ->assertDontSee('data-offer-code="INACTIVE10"', false)
        ->assertDontSee('data-offer-code="EXPIRED10"', false);
});

it('says so when no coupon is running at all', function () {
    get(route('home'))
        ->assertOk()
        ->assertSee('Hot Offer')
        ->assertSee('No coupons are running right now');
});

it('marks a coupon the signed-in shopper already redeemed as spent', function () {
    $coupon = offerTabCoupon('WELCOME10');
    $user = User::factory()->create();

    CouponUse::create([
        'coupon_id' => $coupon->id,
        'user_id' => $user->id,
    ]);

    actingAs($user);

    get(route('home'))
        ->assertOk()
        // Still listed, so the offer stays visible, but flagged as used.
        ->assertSee('data-offer-code="WELCOME10"', false)
        ->assertSee('You have already used this coupon.')
        ->assertSee('is-spent', false);
});

it('shows the same coupon as available to a shopper who has not used it', function () {
    offerTabCoupon('FRESH10');

    actingAs(User::factory()->create());

    get(route('home'))
        ->assertOk()
        ->assertSee('data-offer-code="FRESH10"', false)
        ->assertDontSee('You have already used this coupon.');
});

it('describes fixed and coin coupons in shopper language', function () {
    offerTabCoupon('FLAT250', ['type' => 'fixed', 'amount' => 250]);
    offerTabCoupon('COIN100', ['type' => 'coins', 'amount' => 100]);

    get(route('home'))
        ->assertOk()
        ->assertSee('₹250 OFF')
        ->assertSee('100 Gehna Coins');
});
