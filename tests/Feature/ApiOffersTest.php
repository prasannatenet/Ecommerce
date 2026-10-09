<?php

use App\Models\Combo;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\Product;
use App\Models\User;

function offersApiProduct(string $name, array $attributes = []): Product
{
    return Product::create(array_merge([
        'name' => $name,
        'slug' => str($name)->slug().'-'.uniqid(),
        'base_price' => 1000,
        'product_type' => 'simple',
        'is_active' => true,
        'manage_stock' => true,
        'stock' => 5,
    ], $attributes));
}

function offersApiCoupon(string $code, array $attributes = []): Coupon
{
    return Coupon::create(array_merge([
        'code' => $code,
        'type' => 'percent',
        'amount' => 10,
        'is_active' => true,
    ], $attributes));
}

it('returns current coupons, combos and sale products from the public offers endpoint', function () {
    offersApiCoupon('SAVE10', ['min_order_amount' => 500]);
    $startsAt = now()->addDay();
    offersApiCoupon('UPCOMING', ['starts_at' => $startsAt]);
    offersApiCoupon('EXPIRED', ['expires_at' => now()->subDay()]);
    offersApiCoupon('DISABLED', ['is_active' => false]);

    $saleProduct = offersApiProduct('Offer Sale Ring', [
        'base_price' => 1000,
        'sale_price' => 800,
    ]);
    offersApiProduct('Regular Price Ring', ['base_price' => 900]);

    $comboProduct = offersApiProduct('Offer Combo Bracelet', ['base_price' => 2000]);
    $combo = Combo::create([
        'name' => 'Offer Bundle',
        'slug' => 'offer-bundle',
        'discount_type' => 'fixed',
        'discount_value' => 100,
        'is_active' => true,
    ]);
    $combo->products()->attach($comboProduct->id);

    $this->getJson('/api/v1/offers')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.coupons.0.code', 'UPCOMING')
        ->assertJsonPath('data.coupons.0.is_available', false)
        ->assertJsonPath('data.coupons.0.ineligible_reason', 'This coupon is not active yet. It starts on '.$startsAt->format('d M Y, h:i A').'.')
        ->assertJsonPath('data.coupons.1.code', 'SAVE10')
        ->assertJsonPath('data.coupons.1.offer_text', '10% OFF')
        ->assertJsonPath('data.coupons.1.min_order_amount', 500)
        ->assertJsonPath('data.combos.0.slug', 'offer-bundle')
        ->assertJsonPath('data.sale_products.0.id', $saleProduct->id)
        ->assertJsonPath('meta.coupons.total', 2)
        ->assertJsonPath('meta.combos.total', 1)
        ->assertJsonPath('meta.sale_products.total', 1)
        ->assertJsonMissing(['code' => 'EXPIRED'])
        ->assertJsonMissing(['code' => 'DISABLED']);
});

it('serves coupons directly from the public coupons endpoint', function () {
    $coupon = offersApiCoupon('PUBLIC15', [
        'type' => 'percent',
        'amount' => 15,
        'min_order_amount' => 1200,
        'expires_at' => now()->addDays(5),
    ]);
    offersApiCoupon('OLDCOUPON', ['expires_at' => now()->subDay()]);

    $this->getJson('/api/v1/coupons')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.id', $coupon->id)
        ->assertJsonPath('data.0.code', 'PUBLIC15')
        ->assertJsonPath('data.0.type', 'percent')
        ->assertJsonPath('data.0.amount', 15)
        ->assertJsonPath('data.0.offer_text', '15% OFF')
        ->assertJsonPath('data.0.min_order_amount', 1200)
        ->assertJsonPath('data.0.is_available', true)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonMissing(['code' => 'OLDCOUPON']);
});

it('paginates coupon and sale product lists using the public per_page limit', function () {
    offersApiCoupon('PAGE10');
    offersApiCoupon('PAGE20');
    offersApiCoupon('PAGE30');
    offersApiProduct('Offer Page Sale One', ['base_price' => 1000, 'sale_price' => 900]);
    offersApiProduct('Offer Page Sale Two', ['base_price' => 1000, 'sale_price' => 800]);
    offersApiProduct('Offer Page Sale Three', ['base_price' => 1000, 'sale_price' => 700]);

    $this->getJson('/api/v1/offers?per_page=2&page=2')
        ->assertOk()
        ->assertJsonCount(1, 'data.coupons')
        ->assertJsonCount(1, 'data.sale_products')
        ->assertJsonPath('meta.coupons.current_page', 2)
        ->assertJsonPath('meta.coupons.total', 3)
        ->assertJsonPath('meta.sale_products.current_page', 2)
        ->assertJsonPath('meta.sale_products.total', 3);
});

it('reports a coupon already used by the bearer-token customer', function () {
    $user = User::factory()->create();
    $coupon = offersApiCoupon('USED10');
    CouponUse::create([
        'coupon_id' => $coupon->id,
        'user_id' => $user->id,
    ]);
    $token = $user->createToken('offers-test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/offers')
        ->assertOk()
        ->assertJsonPath('data.coupons.0.code', 'USED10')
        ->assertJsonPath('data.coupons.0.is_available', false)
        ->assertJsonPath('data.coupons.0.is_redeemed', true)
        ->assertJsonPath('data.coupons.0.ineligible_reason', 'You have already used this coupon.');
});
