<?php

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function checkoutProduct(string $name = 'Gold Ring', float $price = 1000): Product
{
    return Product::create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.uniqid(),
        'base_price' => $price,
        'product_type' => 'simple',
        'is_active' => true,
        'manage_stock' => true,
        'stock' => 10,
    ]);
}

beforeEach(function () {
    PaymentProvider::updateOrCreate(
        ['slug' => 'cod'],
        ['name' => 'Cash on Delivery', 'is_active' => true]
    );

    PaymentProvider::updateOrCreate(
        ['slug' => 'razorpay'],
        ['name' => 'Razorpay', 'is_active' => true, 'public_key' => 'rzp_test_123', 'secret_key' => 'secret_123']
    );
});

it('returns checkout summary for authenticated user with cart items', function () {
    $user = User::factory()->create(['gehna_coins' => 100]);
    Sanctum::actingAs($user);

    $product = checkoutProduct('Diamond Ring', 6000);
    Cart::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => 6000,
    ]);

    getJson('/api/v1/checkout/summary')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.pricing.subtotal', 6000)
        ->assertJsonPath('data.pricing.shipping_charge', 0)
        ->assertJsonPath('data.pricing.grand_total', 6000)
        ->assertJsonPath('data.user_coins.balance', 100)
        ->assertJsonPath('data.payment_providers.cod.enabled', true)
        ->assertJsonPath('data.payment_providers.razorpay.enabled', true);
});

it('places an order with Cash on Delivery (COD) via API', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $product = checkoutProduct('Gold Chain', 2000);
    Cart::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => 2000,
    ]);

    $payload = [
        'payment_method' => 'cod',
        'billing_name' => 'John Doe',
        'billing_email' => 'john@example.com',
        'billing_phone' => '9876543210',
        'billing_line1' => '123 Main St',
        'billing_city' => 'Mumbai',
        'billing_state' => 'Maharashtra',
        'billing_zip' => '400001',
        'billing_country' => 'India',
        'shipping_same_as_billing' => true,
    ];

    postJson('/api/v1/checkout/place-order', $payload)
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.payment_method', 'cod')
        ->assertJsonPath('data.order.payment_status', 'pending');

    expect(Cart::where('user_id', $user->id)->count())->toBe(0);
    expect(Order::where('user_id', $user->id)->count())->toBe(1);
});

it('applies and validates a coupon code via API', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $product = checkoutProduct('Silver Bangle', 3000);
    Cart::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => 3000,
    ]);

    $coupon = Coupon::create([
        'code' => 'SAVE500',
        'type' => 'fixed',
        'amount' => 500,
        'is_active' => true,
    ]);

    postJson('/api/v1/checkout/apply-coupon', ['coupon_code' => 'SAVE500'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.coupon.discount', 500);
});

it('applies Gehna Coins via API', function () {
    $user = User::factory()->create(['gehna_coins' => 300]);
    Sanctum::actingAs($user);

    $product = checkoutProduct('Gold Pendant', 4000);
    Cart::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => 4000,
    ]);

    postJson('/api/v1/checkout/apply-coins', ['coins' => 200])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.coins_used', 200)
        ->assertJsonPath('data.discount', 200);
});
