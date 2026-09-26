<?php

use App\Jobs\SendOrderInvoiceMail;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

function checkoutIdempotencyProduct(string $name, float $price = 100, bool $managed = false): Product
{
    return Product::create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.uniqid(),
        'base_price' => $price,
        'is_active' => true,
        'manage_stock' => $managed,
        'stock' => $managed ? 10 : 0,
    ]);
}

function checkoutIdempotencyPayload(string $token, string $method = 'cod'): array
{
    return [
        'payment_method' => $method,
        'checkout_token' => $token,
        'billing_name' => 'Retry Safe Buyer',
        'billing_email' => 'retry-safe@example.com',
        'billing_phone' => '9999999999',
        'billing_line1' => 'Street 1',
        'billing_city' => 'Mumbai',
        'billing_state' => 'MH',
        'billing_zip' => '400001',
        'billing_country' => 'India',
        'shipping_same_as_billing' => '1',
    ];
}

function checkoutIdempotencyCart(User $user, Product $product, int $quantity = 1): Cart
{
    return Cart::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'product_variation_id' => null,
        'quantity' => $quantity,
        'price' => (float) $product->base_price,
    ]);
}

it('returns the original order when COD checkout is submitted twice', function () {
    Queue::fake();

    $user = User::factory()->create();
    $product = checkoutIdempotencyProduct('COD Idempotency Product');
    PaymentProvider::updateOrCreate(
        ['slug' => 'cod'],
        ['name' => 'Cash on Delivery', 'is_active' => true]
    );
    checkoutIdempotencyCart($user, $product);
    actingAs($user);

    $token = (string) Str::uuid();
    $first = post(route('checkout.place'), checkoutIdempotencyPayload($token));
    $order = Order::where('checkout_token', $token)->firstOrFail();

    $first->assertRedirect(route('checkout.success', $order));
    expect((int) Order::count())->toBe(1)
        ->and((int) $order->items()->count())->toBe(1)
        ->and((int) Cart::where('user_id', $user->id)->count())->toBe(0);

    $second = post(route('checkout.place'), checkoutIdempotencyPayload($token));

    $second->assertRedirect(route('checkout.success', $order));

    expect((int) Order::count())->toBe(1)
        ->and((int) $order->paymentTransactions()->count())->toBe(1);

    Queue::assertPushed(SendOrderInvoiceMail::class, 1);
});

it('reuses one Razorpay gateway order for repeated checkout submissions', function () {
    $user = User::factory()->create();
    $product = checkoutIdempotencyProduct('Online Idempotency Product', 500);
    $provider = PaymentProvider::updateOrCreate(
        ['slug' => 'razorpay'],
        [
            'name' => 'Razorpay',
            'is_active' => true,
            'public_key' => 'rzp_test_public',
            'secret_key' => 'rzp_test_secret',
        ]
    );
    checkoutIdempotencyCart($user, $product);
    actingAs($user);

    Http::fake([
        'https://api.razorpay.com/v1/orders' => Http::response([
            'id' => 'order_idempotent_123',
            'amount' => 50000,
            'currency' => 'INR',
            'status' => 'created',
        ]),
    ]);

    $token = (string) Str::uuid();
    $first = post(route('checkout.place'), checkoutIdempotencyPayload($token, 'razorpay'));
    $first->assertOk();
    $firstPayload = $first->json();

    $second = post(route('checkout.place'), checkoutIdempotencyPayload($token, 'razorpay'));
    $second->assertOk();

    expect((int) Order::count())->toBe(1)
        ->and((int) $firstPayload['order']['id'])->toBe((int) $second->json('order.id'))
        ->and($second->json('razorpay.order_id'))->toBe($firstPayload['razorpay']['order_id']);

    Http::assertSentCount(1);
    expect((int) Order::first()->paymentTransactions()->count())->toBe(1);
});

it('makes repeated payment verification and stock deduction idempotent', function () {
    $user = User::factory()->create();
    $product = checkoutIdempotencyProduct('Managed Online Product', 250, true);
    $provider = PaymentProvider::updateOrCreate(
        ['slug' => 'razorpay'],
        [
            'name' => 'Razorpay',
            'is_active' => true,
            'public_key' => 'rzp_test_public',
            'secret_key' => 'rzp_test_secret',
        ]
    );
    checkoutIdempotencyCart($user, $product, 2);
    actingAs($user);

    Http::fake([
        'https://api.razorpay.com/v1/orders' => Http::response([
            'id' => 'order_capture_idempotent_123',
            'amount' => 50000,
            'currency' => 'INR',
            'status' => 'created',
        ]),
    ]);

    $token = (string) Str::uuid();
    $place = post(route('checkout.place'), checkoutIdempotencyPayload($token, 'razorpay'));
    $place->assertOk();
    $payload = $place->json();
    $paymentId = 'pay_capture_idempotent_123';
    $signature = hash_hmac('sha256', $payload['razorpay']['order_id'].'|'.$paymentId, $provider->secret_key);
    $verification = [
        'order_id' => $payload['order']['id'],
        'razorpay_order_id' => $payload['razorpay']['order_id'],
        'razorpay_payment_id' => $paymentId,
        'razorpay_signature' => $signature,
    ];

    postJson(route('checkout.razorpay.verify'), $verification)->assertOk();
    postJson(route('checkout.razorpay.verify'), $verification)->assertOk();

    expect((int) $product->fresh()->stock)->toBe(8)
        ->and((int) Order::find($payload['order']['id'])->paymentTransactions()->where('type', 'payment')->count())->toBe(1)
        ->and((int) Order::find($payload['order']['id'])->stock_deducted)->toBe(1);
});

it('reuses a failed online order when the customer retries checkout', function () {
    $user = User::factory()->create();
    $product = checkoutIdempotencyProduct('Failed Retry Product', 400);
    PaymentProvider::updateOrCreate(
        ['slug' => 'razorpay'],
        [
            'name' => 'Razorpay',
            'is_active' => true,
            'public_key' => 'rzp_test_public',
            'secret_key' => 'rzp_test_secret',
        ]
    );
    checkoutIdempotencyCart($user, $product);
    actingAs($user);

    Http::fake([
        'https://api.razorpay.com/v1/orders' => Http::response([
            'id' => 'order_failed_retry_123',
            'amount' => 40000,
            'currency' => 'INR',
            'status' => 'created',
        ]),
    ]);

    $token = (string) Str::uuid();
    post(route('checkout.place'), checkoutIdempotencyPayload($token, 'razorpay'))->assertOk();
    $order = Order::where('checkout_token', $token)->firstOrFail();
    $order->update(['payment_status' => 'failed']);

    get(route('checkout.index'))->assertOk();
    $retry = post(route('checkout.place'), checkoutIdempotencyPayload($token, 'razorpay'));
    $retry->assertOk();

    expect((int) Order::count())->toBe(1)
        ->and((int) $retry->json('order.id'))->toBe($order->id)
        ->and($retry->json('razorpay.order_id'))->toBe('order_failed_retry_123');
});

it('returns protected payment status for processing and paid orders', function () {
    $user = User::factory()->create();
    $provider = PaymentProvider::updateOrCreate(
        ['slug' => 'razorpay'],
        [
            'name' => 'Razorpay',
            'is_active' => true,
            'public_key' => 'rzp_test_public',
            'secret_key' => 'rzp_test_secret',
        ]
    );
    $order = Order::create([
        'user_id' => $user->id,
        'payment_provider_id' => $provider->id,
        'status' => 'pending',
        'payment_method' => 'razorpay',
        'payment_status' => 'initiated',
        'refund_status' => 'none',
        'total' => 300,
        'refunded_total' => 0,
        'stock_deducted' => false,
    ]);

    actingAs($user);
    getJson(route('checkout.payment-status', $order))
        ->assertOk()
        ->assertJsonPath('state', 'processing')
        ->assertJsonPath('redirect_url', null);

    $order->update(['payment_status' => 'paid', 'paid_at' => now()]);
    getJson(route('checkout.payment-status', $order))
        ->assertOk()
        ->assertJsonPath('state', 'paid')
        ->assertJsonPath('redirect_url', route('checkout.success', $order));
});
