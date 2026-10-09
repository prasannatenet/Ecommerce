<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $key = '0123456789abcdef0123456789abcdef';
    Config::set('app.key', $key);
    Config::set('app.cipher', 'AES-256-CBC');
    app()->forgetInstance('encrypter');
    $encrypter = new Encrypter($key, 'AES-256-CBC');
    app()->instance('encrypter', $encrypter);
    app()->instance(EncrypterContract::class, $encrypter);
});

function customerApiProduct(string $name): Product
{
    return Product::create([
        'name' => $name,
        'slug' => str($name)->slug().'-'.uniqid(),
        'base_price' => 1000,
        'product_type' => 'simple',
        'is_active' => true,
        'manage_stock' => true,
        'stock' => 10,
    ]);
}

function customerApiOrder(User $user, Product $product, string $status = 'pending', int $quantity = 1): Order
{
    $order = Order::create([
        'user_id' => $user->id,
        'status' => $status,
        'payment_status' => 'pending',
        'payment_method' => 'cod',
        'total' => 1000 * $quantity,
        'stock_deducted' => true,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'unit_price' => 1000,
        'quantity' => $quantity,
        'line_total' => 1000 * $quantity,
    ]);

    return $order;
}

it('allows a verified buyer to create, update and delete their own product review through the API', function () {
    $user = User::factory()->create();
    $product = customerApiProduct('API Review Ring');
    customerApiOrder($user, $product, 'delivered');
    Sanctum::actingAs($user);

    $created = $this->postJson("/api/v1/products/{$product->slug}/reviews", [
        'rating' => 5,
        'comment' => 'Beautiful quality.',
    ])->assertCreated()
        ->assertJsonPath('data.review.rating', 5)
        ->assertJsonPath('data.summary.count', 1);

    $reviewId = $created->json('data.review.id');

    $this->putJson("/api/v1/products/{$product->slug}/reviews/{$reviewId}", [
        'rating' => 4,
        'comment' => 'Still a lovely product.',
    ])->assertOk()
        ->assertJsonPath('data.review.rating', 4)
        ->assertJsonPath('data.summary.average', 4);

    $this->deleteJson("/api/v1/products/{$product->slug}/reviews/{$reviewId}")
        ->assertOk()
        ->assertJsonPath('data.review', null)
        ->assertJsonPath('data.summary.count', 0);

    expect(Review::where('product_id', $product->id)->count())->toBe(0);
});

it('requires a purchase to review and prevents editing another customer review', function () {
    $buyer = User::factory()->create();
    $otherUser = User::factory()->create();
    $product = customerApiProduct('Review Protected Ring');
    customerApiOrder($buyer, $product, 'delivered');
    Sanctum::actingAs($buyer);

    $review = $product->reviews()->create([
        'user_id' => $otherUser->id,
        'rating' => 5,
        'comment' => 'Other customer review',
    ]);

    $this->putJson("/api/v1/products/{$product->slug}/reviews/{$review->id}", [
        'rating' => 1,
        'comment' => 'Tampered review',
    ])->assertForbidden();

    $guest = User::factory()->create();
    $unreviewedProduct = customerApiProduct('Never Purchased Ring');
    Sanctum::actingAs($guest);

    $this->postJson("/api/v1/products/{$unreviewedProduct->slug}/reviews", [
        'rating' => 5,
        'comment' => 'Not purchased.',
    ])->assertForbidden();
});

it('cancels an owned eligible order, restores stock and rejects another users order', function () {
    $owner = User::factory()->create();
    $product = customerApiProduct('API Cancel Bracelet');
    $order = customerApiOrder($owner, $product);
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Changed my mind'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancel_reason', 'Changed my mind');

    expect((int) $product->fresh()->stock)->toBe(11)
        ->and($order->fresh()->stock_deducted)->toBeFalse();

    $anotherOrder = customerApiOrder(User::factory()->create(), $product);
    $this->postJson("/api/v1/orders/{$anotherOrder->id}/cancel")
        ->assertNotFound();
});

it('accepts a valid return request for a delivered owned order and exposes no payout details', function () {
    Mail::fake();
    $user = User::factory()->create();
    $product = customerApiProduct('API Return Necklace');
    $order = customerApiOrder($user, $product, 'delivered', 2);
    $itemId = $order->items()->first()->id;
    Sanctum::actingAs($user);

    $response = $this->postJson("/api/v1/orders/{$order->id}/return-requests", [
        'reason' => 'Item arrived damaged',
        'payout_method' => 'upi',
        'upi_id' => 'private@upi',
        'items' => [['order_item_id' => $itemId, 'quantity' => 1]],
    ])->assertCreated()
        ->assertJsonPath('data.status', 'requested')
        ->assertJsonPath('data.amount', 1000)
        ->assertJsonPath('data.notification_sent', true);

    expect($response->getContent())
        ->not->toContain('private@upi')
        ->and(ReturnRequest::count())->toBe(1);

    $this->getJson("/api/v1/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.return_requests.0.status', 'requested')
        ->assertJsonPath('data.return_requests.0.items.0.quantity', 1)
        ->assertDontSee('private@upi');
});

it('rejects return requests for orders not delivered or for unavailable quantities', function () {
    $user = User::factory()->create();
    $product = customerApiProduct('Return Quantity Ring');
    $order = customerApiOrder($user, $product, 'processing');
    $itemId = $order->items()->first()->id;
    Sanctum::actingAs($user);

    $payload = [
        'reason' => 'Return this',
        'payout_method' => 'gehna_coins',
        'items' => [['order_item_id' => $itemId, 'quantity' => 1]],
    ];

    $this->postJson("/api/v1/orders/{$order->id}/return-requests", $payload)
        ->assertConflict();

    $order->update(['status' => 'delivered']);

    $this->postJson("/api/v1/orders/{$order->id}/return-requests", array_replace_recursive($payload, [
        'items' => [['order_item_id' => $itemId, 'quantity' => 2]],
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors('items');
});
