<?php

use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

function checkoutPayload(array $overrides = []): array
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

it('places COD order with persisted order items and transaction', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $product = Product::create([
        'name' => 'Test Product',
        'slug' => 'test-product-' . uniqid(),
        'base_price' => 100,
        'is_active' => true,
    ]);

    PaymentProvider::updateOrCreate(
        ['slug' => 'cod'],
        ['name' => 'Cash on Delivery', 'is_active' => true]
    );

    Cart::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'product_variation_id' => null,
        'quantity' => 2,
        'price' => 100,
    ]);

    actingAs($user);
    $response = post(route('checkout.place'), checkoutPayload());

    $order = Order::first();

    $response->assertRedirect(route('checkout.success', $order));

    expect($order)->not->toBeNull();
    expect($order->payment_method)->toBe('cod');
    expect($order->items()->count())->toBe(1);
    expect($order->paymentTransactions()->count())->toBe(1);
    expect(Cart::where('user_id', $user->id)->count())->toBe(0);
});

it('verifies Razorpay signature and marks payment as paid', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $product = Product::create([
        'name' => 'Razor Product',
        'slug' => 'razor-product-' . uniqid(),
        'base_price' => 500,
        'is_active' => true,
    ]);

    $provider = PaymentProvider::updateOrCreate(
        ['slug' => 'razorpay'],
        [
            'name' => 'Razorpay',
            'is_active' => true,
            'public_key' => 'rzp_test_public',
            'secret_key' => 'rzp_test_secret',
        ]
    );

    Cart::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'product_variation_id' => null,
        'quantity' => 1,
        'price' => 500,
    ]);

    Http::fake([
        'https://api.razorpay.com/v1/orders' => Http::response([
            'id' => 'order_test_123',
            'amount' => 50000,
            'currency' => 'INR',
            'status' => 'created',
        ], 200),
    ]);

    actingAs($user);
    $placeResponse = post(route('checkout.place'), checkoutPayload(['payment_method' => 'razorpay']));

    $placeResponse->assertOk();

    $payload = $placeResponse->json();

    $razorpayOrderId = $payload['razorpay']['order_id'];
    $paymentId = 'pay_test_123';
    $signature = hash_hmac('sha256', $razorpayOrderId . '|' . $paymentId, $provider->secret_key);

    $verifyResponse = postJson(route('checkout.razorpay.verify'), [
        'order_id' => $payload['order']['id'],
        'razorpay_order_id' => $razorpayOrderId,
        'razorpay_payment_id' => $paymentId,
        'razorpay_signature' => $signature,
    ]);

    $verifyResponse->assertOk();

    $order = Order::find($payload['order']['id']);

    expect($order->payment_status)->toBe('paid');
    expect($order->transaction_id)->toBe($paymentId);

    $paymentTxn = $order->paymentTransactions()->where('type', 'payment')->latest('id')->first();
    expect($paymentTxn->status)->toBe('captured');
});

it('deducts stock on payment capture and restocks on full refund webhook', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $product = Product::create([
        'name' => 'Inventory Product',
        'slug' => 'inventory-product-' . uniqid(),
        'base_price' => 250,
        'is_active' => true,
        'manage_stock' => true,
        'stock' => 10,
    ]);

    $provider = PaymentProvider::updateOrCreate(
        ['slug' => 'razorpay'],
        [
            'name' => 'Razorpay',
            'is_active' => true,
            'public_key' => 'rzp_test_public',
            'secret_key' => 'rzp_test_secret',
            'webhook_secret' => 'rzp_webhook_secret',
        ]
    );

    Cart::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'product_variation_id' => null,
        'quantity' => 2,
        'price' => 250,
    ]);

    Http::fake([
        'https://api.razorpay.com/v1/orders' => Http::response([
            'id' => 'order_stock_123',
            'amount' => 50000,
            'currency' => 'INR',
            'status' => 'created',
        ], 200),
    ]);

    actingAs($user);
    $placeResponse = post(route('checkout.place'), checkoutPayload(['payment_method' => 'razorpay']));
    $placeResponse->assertOk();

    $payload = $placeResponse->json();
    $paymentId = 'pay_stock_123';
    $signature = hash_hmac('sha256', $payload['razorpay']['order_id'] . '|' . $paymentId, $provider->secret_key);

    postJson(route('checkout.razorpay.verify'), [
        'order_id' => $payload['order']['id'],
        'razorpay_order_id' => $payload['razorpay']['order_id'],
        'razorpay_payment_id' => $paymentId,
        'razorpay_signature' => $signature,
    ])->assertOk();

    expect((int) $product->fresh()->stock)->toBe(8);

    $refundPayload = [
        'event' => 'refund.processed',
        'payload' => [
            'refund' => [
                'entity' => [
                    'id' => 'rfnd_stock_1',
                    'payment_id' => $paymentId,
                    'amount' => 50000,
                ],
            ],
        ],
    ];

    $raw = json_encode($refundPayload, JSON_THROW_ON_ERROR);
    $webhookSignature = hash_hmac('sha256', $raw, $provider->webhook_secret);

    postJson(route('webhooks.razorpay'), $refundPayload, [
        'X-Razorpay-Signature' => $webhookSignature,
    ])->assertOk();

    expect((int) $product->fresh()->stock)->toBe(10);
});
