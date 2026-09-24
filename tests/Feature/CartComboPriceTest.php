<?php

use App\Models\Cart;
use App\Models\Combo;
use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\Product;
use App\Models\User;
use App\Services\ComboService;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\post;

function comboTestProduct(string $name, float $price): Product
{
    return Product::create([
        'name' => $name,
        'slug' => Str::slug($name) . '-' . uniqid(),
        'base_price' => $price,
        'product_type' => 'simple',
        'is_active' => true,
    ]);
}

/** @param array<int, Product> $products */
function comboTestCombo(array $products, float $value, string $type = 'percent'): Combo
{
    $combo = Combo::create([
        'name' => 'Wedding Trio ' . uniqid(),
        'discount_type' => $type,
        'discount_value' => $value,
        'is_active' => true,
    ]);

    $combo->products()->sync(collect($products)->pluck('id')->all());

    return $combo;
}

/** @param array<int, Product> $products */
function comboTestAddToUserCart(User $user, array $products, int $quantity = 1): void
{
    foreach ($products as $product) {
        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_variation_id' => null,
            'quantity' => $quantity,
            'price' => (float) $product->base_price,
        ]);
    }
}

function comboTestCheckoutPayload(array $overrides = []): array
{
    return array_merge([
        'payment_method' => 'cod',
        'billing_name' => 'Combo Buyer',
        'billing_email' => 'combo@example.com',
        'billing_phone' => '9999999999',
        'billing_line1' => 'Street 1',
        'billing_city' => 'Mumbai',
        'billing_state' => 'MH',
        'billing_zip' => '400001',
        'billing_country' => 'India',
        'shipping_same_as_billing' => '1',
    ], $overrides);
}

it('applies the combo price once every combo product is in the cart', function () {
    $studs = comboTestProduct('Golden Star Constellation Tiny Studs', 900);
    $roseGold = comboTestProduct('Rose Gold For My Dear Bracelet', 4388);
    $silver = comboTestProduct('Silver Elegant Butterflies Bracelet', 3000);

    comboTestCombo([$studs, $roseGold, $silver], 10);

    $user = User::factory()->create();
    comboTestAddToUserCart($user, [$studs, $roseGold, $silver]);

    $items = Cart::with('product')->where('user_id', $user->id)->get();
    $summary = app(ComboService::class)->summarize($items);

    expect($summary['discount'])->toBe(828.80)
        ->and($summary['combos'])->toHaveCount(1)
        ->and($summary['combos'][0]['product_count'])->toBe(3)
        ->and($summary['combos'][0]['regular_total'])->toBe(8288.00)
        ->and($summary['combos'][0]['combo_price'])->toBe(7459.20)
        ->and($summary['line_allocations'])->toHaveCount(3);

    // Every combo unit is charged, and the allocated prices add up exactly.
    expect(round(collect($summary['line_allocations'])->sum('combo_total'), 2))->toBe(7459.20);
    expect(round(collect($summary['line_allocations'])->sum('discount'), 2))->toBe(828.80);
});

it('keeps the full price while a combo product is still missing', function () {
    $studs = comboTestProduct('Missing Combo Studs', 900);
    $roseGold = comboTestProduct('Missing Combo Bracelet', 4388);
    $silver = comboTestProduct('Missing Combo Silver', 3000);

    comboTestCombo([$studs, $roseGold, $silver], 10);

    $user = User::factory()->create();
    comboTestAddToUserCart($user, [$studs, $roseGold]);

    $summary = app(ComboService::class)->summarize(Cart::with('product')->where('user_id', $user->id)->get());

    expect($summary['discount'])->toBe(0.0)
        ->and($summary['combos'])->toBeEmpty()
        ->and($summary['line_allocations'])->toBeEmpty();
});

it('supports fixed amount combo discounts', function () {
    $first = comboTestProduct('Fixed Combo First', 2000);
    $second = comboTestProduct('Fixed Combo Second', 1000);

    comboTestCombo([$first, $second], 500, 'fixed');

    $user = User::factory()->create();
    comboTestAddToUserCart($user, [$first, $second]);

    $summary = app(ComboService::class)->summarize(Cart::with('product')->where('user_id', $user->id)->get());

    expect($summary['discount'])->toBe(500.0)
        ->and($summary['combos'][0]['combo_price'])->toBe(2500.0)
        ->and(round(collect($summary['line_allocations'])->sum('combo_total'), 2))->toBe(2500.0);
});


it('shows the combo price in the cart order summary', function () {
    $studs = comboTestProduct('Summary Combo Studs', 900);
    $roseGold = comboTestProduct('Summary Combo Bracelet', 4388);
    $silver = comboTestProduct('Summary Combo Silver', 3000);

    comboTestCombo([$studs, $roseGold, $silver], 10);

    $user = User::factory()->create();
    comboTestAddToUserCart($user, [$studs, $roseGold, $silver]);

    actingAs($user);

    get(route('cart.index'))
        ->assertOk()
        ->assertSee('Combo Discount')
        ->assertSee('Combo Unlocked', false)
        ->assertSee('7,459.20')
        ->assertSee('Combo price applied');
});

it('applies the combo price for guests who are not logged in', function () {
    $first = comboTestProduct('Guest Combo First', 1200);
    $second = comboTestProduct('Guest Combo Second', 800);

    comboTestCombo([$first, $second], 25);

    post(route('cart.add'), ['product_id' => $first->id, 'quantity' => 1])->assertRedirect();
    post(route('cart.add'), ['product_id' => $second->id, 'quantity' => 1])->assertRedirect();

    get(route('cart.index'))
        ->assertOk()
        ->assertSee('Combo Discount')
        ->assertSee('1,500.00')
        ->assertSee('Combo price applied');
});

it('returns the combo discount with ajax cart updates', function () {
    $first = comboTestProduct('Ajax Combo First', 1000);
    $second = comboTestProduct('Ajax Combo Second', 1000);

    comboTestCombo([$first, $second], 20);

    $user = User::factory()->create();
    comboTestAddToUserCart($user, [$first, $second]);

    actingAs($user);

    // Two of every combo product: the cart now pays for two combo sets.
    foreach (Cart::where('user_id', $user->id)->get() as $cartItem) {
        patchJson(route('cart.update', $cartItem->id), ['quantity' => 2])->assertOk();
    }

    $response = patchJson(
        route('cart.update', Cart::where('user_id', $user->id)->where('product_id', $first->id)->value('id')),
        ['quantity' => 2]
    )->assertOk();

    $payload = $response->json();

    expect((float) $payload['combo_discount'])->toBe(800.0)
        ->and((float) $payload['subtotal'])->toBe(4000.0)
        ->and((float) $payload['shipping_charge'])->toBe(199.0)
        ->and((float) $payload['grand_total'])->toBe(3399.0)
        ->and((int) $payload['items'][0]['combo_units'])->toBe(2)
        ->and((float) $payload['items'][0]['line_total'])->toBe(1600.0)
        ->and((float) $payload['items'][1]['line_total'])->toBe(1600.0);

    // The charged lines must add up to the combo-discounted subtotal.
    expect(round((float) $payload['items'][0]['line_total'] + (float) $payload['items'][1]['line_total'], 2))
        ->toBe(round((float) $payload['subtotal'] - (float) $payload['combo_discount'], 2));
});

it('charges the combo price at checkout and on the generated order', function () {
    $studs = comboTestProduct('Checkout Combo Studs', 900);
    $roseGold = comboTestProduct('Checkout Combo Bracelet', 4388);
    $silver = comboTestProduct('Checkout Combo Silver', 3000);

    comboTestCombo([$studs, $roseGold, $silver], 10);

    $user = User::factory()->create();
    comboTestAddToUserCart($user, [$studs, $roseGold, $silver]);

    PaymentProvider::updateOrCreate(
        ['slug' => 'cod'],
        ['name' => 'Cash on Delivery', 'is_active' => true]
    );

    actingAs($user);

    get(route('checkout.index'))
        ->assertOk()
        ->assertSee('Combo Price Discount')
        ->assertSee('7,459.20');

    post(route('checkout.place'), comboTestCheckoutPayload())->assertRedirect();

    $order = Order::first();

    expect($order)->not->toBeNull()
        ->and((float) $order->total)->toBe(7459.20)
        ->and(round((float) $order->items()->sum('line_total'), 2))->toBe(7459.20)
        ->and((float) $order->payment_meta['pricing']['combo_discount'])->toBe(828.80)
        ->and((float) $order->payment_meta['pricing']['subtotal'])->toBe(8288.0)
        ->and($order->payment_meta['combos'])->toHaveCount(1)
        ->and((float) $order->payment_meta['combos'][0]['combo_price'])->toBe(7459.2);
});
