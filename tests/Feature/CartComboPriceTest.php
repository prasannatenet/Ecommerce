<?php

use App\Models\Cart;
use App\Models\Combo;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\Product;
use App\Models\User;
use App\Services\ComboService;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\post;

function comboTestProduct(string $name, float $price): Product
{
    return Product::create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.uniqid(),
        'base_price' => $price,
        'product_type' => 'simple',
        'is_active' => true,
    ]);
}

/** @param array<int, Product> $products */
function comboTestCombo(array $products, float $value, string $type = 'percent'): Combo
{
    $combo = Combo::create([
        'name' => 'Wedding Trio '.uniqid(),
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

it('allows a coupon while one product of a four-product combo is still missing', function () {
    $products = [
        comboTestProduct('Four Combo Product A', 500),
        comboTestProduct('Four Combo Product B', 600),
        comboTestProduct('Four Combo Product C', 700),
        comboTestProduct('Four Combo Product D', 800),
    ];
    comboTestCombo($products, 10);

    $user = User::factory()->create();
    comboTestAddToUserCart($user, array_slice($products, 0, 3));
    Coupon::create([
        'code' => 'THREEOFF',
        'type' => 'percent',
        'amount' => 10,
        'is_active' => true,
    ]);

    actingAs($user);

    post(route('checkout.coupon.apply'), ['coupon_code' => 'THREEOFF'])
        ->assertSessionHas('checkout_coupon.code', 'THREEOFF');

    get(route('cart.index'))
        ->assertOk()
        ->assertSee('THREEOFF')
        ->assertSee('₹1,819', false);
});

it('clears an applied coupon and disables coupons when the fourth product completes a combo', function () {
    $products = [
        comboTestProduct('Complete Combo Product A', 500),
        comboTestProduct('Complete Combo Product B', 600),
        comboTestProduct('Complete Combo Product C', 700),
        comboTestProduct('Complete Combo Product D', 800),
    ];
    comboTestCombo($products, 10);
    Coupon::create([
        'code' => 'COMBOOFF',
        'type' => 'percent',
        'amount' => 10,
        'is_active' => true,
    ]);

    $user = User::factory()->create();
    comboTestAddToUserCart($user, array_slice($products, 0, 3));

    actingAs($user);
    post(route('checkout.coupon.apply'), ['coupon_code' => 'COMBOOFF'])
        ->assertSessionHas('checkout_coupon.code', 'COMBOOFF');

    $missing = Cart::create([
        'user_id' => $user->id,
        'product_id' => $products[3]->id,
        'product_variation_id' => null,
        'quantity' => 1,
        'price' => 800,
    ]);

    $response = patchJson(route('cart.update', $missing), ['quantity' => 1])->assertOk();
    $payload = $response->json();

    expect($payload['has_applied_combo'])->toBeTrue()
        ->and((float) $payload['discount'])->toBe(0.0)
        ->and($payload['coupon'])->toBeNull()
        ->and(session('checkout_coupon'))->toBeNull();

    get(route('cart.index'))
        ->assertOk()
        ->assertSee('Coupons cannot be combined with combo offers.')
        ->assertSee('disabled', false);
});

it('does not allow a coupon on a complete automatically detected combo at checkout placement', function () {
    $products = [
        comboTestProduct('Checkout Block A', 500),
        comboTestProduct('Checkout Block B', 600),
        comboTestProduct('Checkout Block C', 700),
    ];
    comboTestCombo($products, 10);
    $coupon = Coupon::create([
        'code' => 'STALEOFF',
        'type' => 'fixed',
        'amount' => 50,
        'is_active' => true,
    ]);

    $user = User::factory()->create();
    comboTestAddToUserCart($user, $products);
    PaymentProvider::updateOrCreate(
        ['slug' => 'cod'],
        ['name' => 'Cash on Delivery', 'is_active' => true]
    );

    actingAs($user);
    session(['checkout_coupon' => ['code' => 'STALEOFF']]);

    post(route('checkout.place'), comboTestCheckoutPayload())->assertRedirect();

    $order = Order::latest('id')->first();
    $comboDiscount = collect($products)->sum(fn ($product) => (float) $product->base_price) * 0.10;
    $subtotal = collect($products)->sum(fn ($product) => (float) $product->base_price);

    expect($order)->not->toBeNull()
        ->and(data_get($order, 'payment_meta.coupon'))->toBeNull()
        ->and((float) $order->payment_meta['pricing']['discount'])->toBe(0.0)
        ->and((int) $coupon->fresh()->used_count)->toBe(0)
        ->and((float) $order->total)->toBe(round($subtotal - $comboDiscount + 199, 2));
});

it('rejects a coupon for a complete explicit combo even when the combo has zero discount', function () {
    $products = [
        comboTestProduct('Explicit Combo A', 500),
        comboTestProduct('Explicit Combo B', 600),
    ];
    $combo = comboTestCombo($products, 0);

    $user = User::factory()->create();
    foreach ($products as $product) {
        Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'combo_id' => $combo->id,
            'quantity' => 1,
            'price' => $product->base_price,
        ]);
    }
    Coupon::create([
        'code' => 'EXPLICITOFF',
        'type' => 'percent',
        'amount' => 10,
        'is_active' => true,
    ]);

    actingAs($user);
    post(route('checkout.coupon.apply'), ['coupon_code' => 'EXPLICITOFF'])
        ->assertSessionMissing('checkout_coupon')
        ->assertSessionHas('error', 'Coupons cannot be combined with combo offers.');
});

it('allows a coupon again after the combo item is removed from the current order', function () {
    $products = [
        comboTestProduct('Reusable Combo A', 500),
        comboTestProduct('Reusable Combo B', 600),
    ];
    comboTestCombo($products, 10);
    Coupon::create([
        'code' => 'REUSEOFF',
        'type' => 'percent',
        'amount' => 10,
        'is_active' => true,
    ]);

    $user = User::factory()->create();
    comboTestAddToUserCart($user, $products);
    actingAs($user);

    post(route('checkout.coupon.apply'), ['coupon_code' => 'REUSEOFF'])
        ->assertSessionMissing('checkout_coupon');

    Cart::where('user_id', $user->id)->where('product_id', $products[1]->id)->delete();

    post(route('checkout.coupon.apply'), ['coupon_code' => 'REUSEOFF'])
        ->assertSessionHas('checkout_coupon.code', 'REUSEOFF');
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
        // The order total is rounded to whole rupees, so 7459.20 is stored as
        // 7459. Line items keep the exact allocated amounts.
        ->and((float) $order->total)->toBe(7459.0)
        ->and(round((float) $order->items()->sum('line_total'), 2))->toBe(7459.20)
        ->and((float) $order->payment_meta['pricing']['combo_discount'])->toBe(828.80)
        ->and((float) $order->payment_meta['pricing']['subtotal'])->toBe(8288.0)
        ->and($order->payment_meta['combos'])->toHaveCount(1)
        ->and((float) $order->payment_meta['combos'][0]['combo_price'])->toBe(7459.2);
});

it('rounds the order total to whole rupees so the page and the charge always match', function () {
    PaymentProvider::updateOrCreate(
        ['slug' => 'cod'],
        ['name' => 'Cash on Delivery', 'is_active' => true]
    );

    // 3 x 333.33 = 999.99, + 199 shipping = 1198.99, which rounds to 1199.
    $products = [
        comboTestProduct('Rounding A', 333.33),
        comboTestProduct('Rounding B', 333.33),
        comboTestProduct('Rounding C', 333.33),
    ];

    $user = User::factory()->create();
    comboTestAddToUserCart($user, $products);

    actingAs($user);

    // The checkout page shows the rounded figure.
    get(route('checkout.index'))
        ->assertOk()
        ->assertSee('1,199');

    post(route('checkout.place'), comboTestCheckoutPayload())->assertRedirect();

    $order = Order::first();

    // And that is exactly the amount stored and charged.
    expect($order)->not->toBeNull()
        ->and((float) $order->total)->toBe(1199.0)
        ->and(fmod((float) $order->total, 1))->toBe(0.0);
});

it('exposes the rounding rule on the order model', function () {
    expect(Order::roundAmount(12147.77))->toBe(12148.0)
        ->and(Order::roundAmount(12147.20))->toBe(12147.0)
        ->and(Order::roundAmount(12147.50))->toBe(12148.0)
        // Never negative, so a discount can never produce a payable of -x.
        ->and(Order::roundAmount(-5.0))->toBe(0.0);
});


it('formats the same price identically on the card, the detail page and the cart', function () {
    // 999.75 is the value that used to render as 1,000 on the detail page
    // because that template formatted prices with 0 decimals.
    $product = comboTestProduct('Fractional Studs', 999.75);

    $cart = collect([
        $product->effectivePrice(),
        $product->regularPrice(),
    ]);

    // Every page formats unit prices with number_format($value, 2).
    expect(number_format($product->effectivePrice(), 2))->toBe('999.75')
        ->and(number_format($product->effectivePrice(), 0))->toBe('1,000')
        ->and($product->effectivePrice())->toBe(999.75);

    get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee('id="product-price">₹999.75<', false)
        ->assertSee('999.75');

    get(route('products.index'))
        ->assertOk()
        ->assertSee('Rs 999.75');

    $user = User::factory()->create();
    comboTestAddToUserCart($user, [$product]);
    actingAs($user);

    get(route('cart.index'))
        ->assertOk()
        ->assertSee('₹999.75');

    unset($cart);
});


/* ───────────────────────────────────────────────────────────────
 | Removing one product from a combo the customer selected directly |
 ─────────────────────────────────────────────────────────────── */


it('shows the selected variation image in the cart, falling back to the product image', function () {
    $product = Product::create([
        'name' => 'Variation Image Ring',
        'slug' => 'variation-image-'.uniqid(),
        'base_price' => 1000,
        'product_type' => 'variable',
        'is_active' => true,
    ]);

    $product->images()->create(['path' => 'products/product-level.png']);

    $variation = $product->variations()->create([
        'sku' => 'VIMG-'.uniqid(), 'price' => 1000, 'stock' => 5, 'is_active' => true,
    ]);
    // Not primary, so the ordering has to actually be exercised.
    $variation->images()->create(['path' => 'products/second.png', 'is_primary' => false]);
    $primary = $variation->images()->create(['path' => 'products/variation-primary.png', 'is_primary' => true]);

    $user = User::factory()->create();
    actingAs($user);

    post(route('cart.add'), [
        'product_id' => $product->id,
        'product_variation_id' => $variation->id,
        'quantity' => 1,
    ])->assertRedirect();

    $response = get(route('cart.index'))->assertOk();

    // The variation's own primary image wins over the product image.
    $response->assertSee('storage/' . $primary->path, false)
        ->assertDontSee('storage/products/product-level.png', false);

    // A variation with no images of its own falls back to the product image.
    $bare = $product->variations()->create([
        'sku' => 'VIMG-'.uniqid(), 'price' => 1200, 'stock' => 3, 'is_active' => true,
    ]);

    post(route('cart.add'), [
        'product_id' => $product->id,
        'product_variation_id' => $bare->id,
        'quantity' => 1,
    ])->assertRedirect();

    get(route('cart.index'))
        ->assertOk()
        ->assertSee('storage/products/product-level.png', false);
});

it('shows the variation image for a guest cart too', function () {
    $product = Product::create([
        'name' => 'Guest Variation Ring',
        'slug' => 'guest-variation-'.uniqid(),
        'base_price' => 800,
        'product_type' => 'variable',
        'is_active' => true,
    ]);

    $variation = $product->variations()->create([
        'sku' => 'GVAR-'.uniqid(), 'price' => 800, 'stock' => 4, 'is_active' => true,
    ]);
    $image = $variation->images()->create(['path' => 'products/guest-variation.png', 'is_primary' => true]);

    // No product-level image exists, so only the variation image can satisfy it.
    post(route('cart.add'), [
        'product_id' => $product->id,
        'product_variation_id' => $variation->id,
        'quantity' => 1,
    ])->assertRedirect();

    get(route('cart.index'))
        ->assertOk()
        ->assertSee('storage/' . $image->path, false);
});

/** Add every product of $combo to $user's cart as a real combo cart line. */
function comboTestAddComboLines(User $user, Combo $combo, int $quantity = 1): void
{
    actingAs($user);

    post(route('cart.add-combo'), ['combo_id' => $combo->id, 'quantity' => $quantity])
        ->assertRedirect();
}

it('keeps the combo price when every product of a selected combo is still in the cart', function () {
    $products = [
        comboTestProduct('Intact Combo A', 500),
        comboTestProduct('Intact Combo B', 600),
        comboTestProduct('Intact Combo C', 700),
    ];
    $combo = comboTestCombo($products, 10);

    $user = User::factory()->create();
    comboTestAddComboLines($user, $combo);

    $lines = Cart::with('product', 'variation')->where('user_id', $user->id)->get();

    // 1800 - 10% = 1620, and the allocated prices add up to exactly that.
    expect(round($lines->sum(fn ($line) => (int) $line->quantity * (float) $line->price), 2))->toBe(1620.0)
        ->and(app(ComboService::class)->hasAppliedCombo($lines))->toBeTrue();

    $fixed = app(ComboService::class)->applyExplicitComboPrices($lines);

    expect(round($fixed->sum(fn ($line) => (int) $line->quantity * (float) $line->price), 2))->toBe(1620.0);
});

it('puts the surviving combo lines back on their regular price once a product is removed', function () {
    $products = [
        comboTestProduct('Broken Combo A', 500),
        comboTestProduct('Broken Combo B', 600),
        comboTestProduct('Broken Combo C', 700),
    ];
    $combo = comboTestCombo($products, 10);

    $user = User::factory()->create();
    comboTestAddComboLines($user, $combo);

    $removed = Cart::where('user_id', $user->id)->where('product_id', $products[2]->id)->first();
    delete(route('cart.destroy', $removed->id))->assertRedirect();

    $lines = Cart::with('product', 'variation')->where('user_id', $user->id)->get();

    expect($lines)->toHaveCount(2)
        ->and(app(ComboService::class)->hasAppliedCombo($lines))->toBeFalse();

    $fixed = app(ComboService::class)->applyExplicitComboPrices($lines);

    // 500 + 600 at full price, not the discounted 450 + 540.
    expect(round($fixed->sum(fn ($line) => (int) $line->quantity * (float) $line->price), 2))->toBe(1100.0);

    foreach ($fixed as $line) {
        expect((float) $line->price)->toBe((float) $line->product->base_price);
    }
});

it('leaves the stored combo price untouched so a GET never writes to the carts table', function () {
    $products = [
        comboTestProduct('Untouched Combo A', 500),
        comboTestProduct('Untouched Combo B', 600),
    ];
    $combo = comboTestCombo($products, 10);

    $user = User::factory()->create();
    comboTestAddComboLines($user, $combo);

    $removed = Cart::where('user_id', $user->id)->where('product_id', $products[1]->id)->first();
    delete(route('cart.destroy', $removed->id));

    $survivor = Cart::where('user_id', $user->id)->first();
    $discounted = (float) $survivor->price;

    app(ComboService::class)->applyExplicitComboPrices(
        Cart::with('product', 'variation')->where('user_id', $user->id)->get()
    );

    // The correction is derived per request, so the row still holds 450.
    expect($discounted)->toBe(450.0)
        ->and((float) $survivor->fresh()->price)->toBe(450.0);
});

it('shows a restorable combo suggestion naming the product that was removed', function () {
    $removedProduct = comboTestProduct('Vanishing Bracelet', 700);
    $keptProduct = comboTestProduct('Staying Band', 500);
    $combo = comboTestCombo([$keptProduct, $removedProduct], 10);

    $user = User::factory()->create();
    comboTestAddComboLines($user, $combo);

    $removed = Cart::where('user_id', $user->id)->where('product_id', $removedProduct->id)->first();
    delete(route('cart.destroy', $removed->id));

    actingAs($user);

    get(route('cart.index'))
        ->assertOk()
        ->assertSee('You removed an item from')
        ->assertSee('combo-suggest-form')
        ->assertSee('Restore combo')
        // The surviving line is flagged instead of promising a discount.
        ->assertSee('Combo price no longer applies')
        ->assertSee('shown at regular price');
});

it('does not offer a restore button for a combo that is merely partial', function () {
    $inCart = comboTestProduct('Partial In Cart', 500);
    comboTestCombo([$inCart, comboTestProduct('Partial Missing', 600)], 10);

    $user = User::factory()->create();
    comboTestAddToUserCart($user, [$inCart]);

    actingAs($user);

    get(route('cart.index'))
        ->assertOk()
        ->assertSee('Add more items to unlock combo savings')
        ->assertDontSee('Restore combo');
});

it('restores the combo price when the customer puts the removed product back', function () {
    $products = [
        comboTestProduct('Restore Combo A', 500),
        comboTestProduct('Restore Combo B', 600),
    ];
    $combo = comboTestCombo($products, 10);

    $user = User::factory()->create();
    comboTestAddComboLines($user, $combo);

    $removed = Cart::where('user_id', $user->id)->where('product_id', $products[1]->id)->first();
    delete(route('cart.destroy', $removed->id));

    // What the suggestion card posts.
    post(route('cart.restore-combo'), ['combo_id' => $combo->id])->assertRedirect();

    $lines = Cart::with('product', 'variation')->where('user_id', $user->id)->get();

    expect($lines)->toHaveCount(2)
        ->and(app(ComboService::class)->hasAppliedCombo($lines))->toBeTrue();

    // Restoring puts the removed product back without duplicating the kept one.
    expect($lines->every(fn ($line) => (int) $line->quantity === 1))->toBeTrue();

    $fixed = app(ComboService::class)->applyExplicitComboPrices($lines);

    // Back to 1100 - 10% = 990.
    expect(round($fixed->sum(fn ($line) => (int) $line->quantity * (float) $line->price), 2))->toBe(990.0);
});

it('charges the full original total on the order for a broken combo', function () {
    $products = [
        comboTestProduct('Order Broken A', 500),
        comboTestProduct('Order Broken B', 600),
        comboTestProduct('Order Broken C', 700),
    ];
    $combo = comboTestCombo($products, 10);

    PaymentProvider::updateOrCreate(
        ['slug' => 'cod'],
        ['name' => 'Cash on Delivery', 'is_active' => true]
    );

    $user = User::factory()->create();
    comboTestAddComboLines($user, $combo);

    $removed = Cart::where('user_id', $user->id)->where('product_id', $products[2]->id)->first();
    delete(route('cart.destroy', $removed->id));

    actingAs($user);

    // 500 + 600 = 1100, no combo discount, 1100 + 199 shipping.
    post(route('checkout.place'), comboTestCheckoutPayload())->assertRedirect();

    $order = Order::first();

    expect($order)->not->toBeNull()
        ->and((float) $order->total)->toBe(1299.00)
        ->and(round((float) $order->items()->sum('line_total'), 2))->toBe(1100.0)
        ->and((float) $order->payment_meta['pricing']['combo_discount'])->toBe(0.0)
        ->and($order->payment_meta['combos'] ?? [])->toBeEmpty()
        ->and((float) $order->payment_meta['pricing']['subtotal'])->toBe(1100.0);
});

it('still charges the combo price on the order when the combo is intact', function () {
    $products = [
        comboTestProduct('Order Intact A', 500),
        comboTestProduct('Order Intact B', 600),
        comboTestProduct('Order Intact C', 700),
    ];
    $combo = comboTestCombo($products, 10);

    PaymentProvider::updateOrCreate(
        ['slug' => 'cod'],
        ['name' => 'Cash on Delivery', 'is_active' => true]
    );

    $user = User::factory()->create();
    comboTestAddComboLines($user, $combo);

    actingAs($user);

    // 1800 - 180 combo discount = 1620, + 199 shipping.
    post(route('checkout.place'), comboTestCheckoutPayload())->assertRedirect();

    $order = Order::first();

    expect((float) $order->total)->toBe(1819.00)
        ->and(round((float) $order->items()->sum('line_total'), 2))->toBe(1620.0)
        // An explicitly added combo already stores the allocated combo price on
        // each cart line, so summarize() reports no separate combo_discount.
        ->and((float) $order->payment_meta['pricing']['combo_discount'])->toBe(0.0)
        ->and((float) $order->payment_meta['pricing']['subtotal'])->toBe(1620.0);
});

/* ───────────────────────────────────────────────────────────────
 | Catalogue price the combo maths adds up                      |
 ─────────────────────────────────────────────────────────────── */

it('uses the sale price of a variable product that has no variations yet', function () {
    $variable = Product::create([
        'name' => 'Variationless Variable Bracelet',
        'slug' => 'variationless-'.uniqid(),
        'base_price' => 5999.00,
        'sale_price' => 2999.50,
        'product_type' => 'variable',
        'is_active' => true,
    ]);

    // Regression guard: this used to fall back to base_price and ignore the
    // product's own sale price, inflating every combo total it belonged to.
    expect($variable->effectivePrice())->toBe(2999.50)
        ->and((float) $variable->display_price)->toBe(2999.50)
        ->and($variable->pricingVariations())->toHaveCount(0);
});

it('adds up a combo using the sale price of every product that has one', function () {
    $simple = comboTestProduct('Combo Simple Item', 3999.00);
    $simple->update(['sale_price' => 999.75]);

    $variable = Product::create([
        'name' => 'Combo Variationless Item',
        'slug' => 'combo-variationless-'.uniqid(),
        'base_price' => 5999.00,
        'sale_price' => 2999.50,
        'product_type' => 'variable',
        'is_active' => true,
    ]);

    $combo = comboTestCombo([$simple, $variable], 5);

    // 999.75 + 2999.50 = 3999.25, and 5% off that is 199.96.
    expect($combo->productsTotal())->toBe(3999.25)
        ->and($combo->discountAmount())->toBe(199.96)
        ->and($combo->comboPrice())->toBe(3799.29)
        ->and($combo->savingsPercent())->toBe(5.0);
});

it('shows the original price struck through and the post-combo charge on the cart page', function () {
    $products = [
        comboTestProduct('Split Price A', 1000),
        comboTestProduct('Split Price B', 1000),
    ];
    $combo = comboTestCombo($products, 10);

    $user = User::factory()->create();
    comboTestAddComboLines($user, $combo);

    actingAs($user);

    // 2000 - 10% = 1800, so 900 charged per line against a 1,000 original.
    $response = get(route('cart.index'))->assertOk();

    // The price cell carries the struck-through original and its id hook.
    $response->assertSee('cart-line-price-', false);
    $response->assertSee('<s class="text-muted" style="font-weight:600;">₹1,000.00</s>', false);
    // The total cell carries the discounted amount with a combo hint.
    $response->assertSee('at combo price');
    $response->assertSee('₹900.00');
});

it('exposes the original unit price to the ajax summary so the price cell stays in sync', function () {
    $products = [
        comboTestProduct('Ajax Price A', 1000),
        comboTestProduct('Ajax Price B', 1000),
    ];
    $combo = comboTestCombo($products, 10);

    $user = User::factory()->create();
    comboTestAddComboLines($user, $combo);

    actingAs($user);

    $line = Cart::where('user_id', $user->id)->orderBy('id')->first();

    // PATCH /cart/{cart} is the endpoint the cart page calls for quantity
    // changes, and it is the response that carries the per-item price data.
    $payload = patchJson(route('cart.update', $line->id), ['quantity' => 2])->json();

    expect($payload)->toHaveKey('items');

    foreach ($payload['items'] as $item) {
        // Every line reports its own pre-combo price; only the line whose
        // quantity just changed carries the 1800 gross.
        expect((float) $item['original_unit_price'])->toBe(1000.0);
    }

    $updated = collect($payload['items'])->firstWhere('id', (string) $line->id);

    expect($updated)->not->toBeNull()
        ->and((int) $updated['quantity'])->toBe(2)
        ->and((float) $updated['line_gross_total'])->toBe(1800.0)
        ->and((float) $updated['line_total'])->toBe(1800.0);
});


it('quotes the same price on the product card and the product detail page', function () {
    $variable = Product::create([
        'name' => 'Consistent Price Ring',
        'slug' => 'consistent-ring-'.uniqid(),
        'base_price' => 1000,
        'product_type' => 'variable',
        'is_active' => true,
    ]);

    // Two buyable variations: the page used to pick the first one it found
    // while the card quoted the cheapest, so the two disagreed.
    $dearer = $variable->variations()->create([
        'sku' => 'CONSISTENT-'.uniqid(), 'price' => 1000, 'sale_price' => null, 'stock' => 5, 'is_active' => true,
    ]);
    $cheaper = $variable->variations()->create([
        'sku' => 'CONSISTENT-'.uniqid(), 'price' => 1200, 'sale_price' => 999.50, 'stock' => 4, 'is_active' => true,
    ]);

    $variable->load('variations');

    $quoted = $variable->defaultVariation();
    expect($quoted)->not->toBeNull()
        ->and($quoted->id)->toBe($cheaper->id)
        ->and((float) $quoted->effectivePrice())->toBe(999.50)
        // The card reads effectivePrice(), so the two must be identical.
        ->and($variable->effectivePrice())->toBe($quoted->effectivePrice())
        ->and((float) $variable->display_price)->toBe($quoted->effectivePrice())
        ->and($dearer->id)->not->toBe($quoted->id);
});

it('never quotes an out-of-stock variation when a buyable one exists', function () {
    $variable = Product::create([
        'name' => 'Out Of Stock Ring',
        'slug' => 'oos-ring-'.uniqid(),
        'base_price' => 1000,
        'product_type' => 'variable',
        'is_active' => true,
    ]);

    $variable->variations()->create([
        'sku' => 'OOS-'.uniqid(), 'price' => 100, 'sale_price' => 99, 'stock' => 0, 'is_active' => true,
    ]);
    $buyable = $variable->variations()->create([
        'sku' => 'OOS-'.uniqid(), 'price' => 1000, 'sale_price' => null, 'stock' => 7, 'is_active' => true,
    ]);

    $variable->load('variations');

    expect($variable->defaultVariation()->id)->toBe($buyable->id)
        ->and((float) $variable->effectivePrice())->toBe(1000.0);
});
