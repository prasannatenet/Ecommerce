<?php

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

function discountPricingAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function discountPricingVariableProduct(): Product
{
    // The product record's own price must never be used while variations exist.
    return Product::create([
        'name' => 'Discount Pricing Ring ' . uniqid(),
        'audience' => 'women',
        'material_type' => 'gold',
        'slug' => 'discount-pricing-ring-' . uniqid(),
        'description' => 'Variation driven pricing.',
        'base_price' => 999,
        'sale_price' => 111,
        'product_type' => 'variable',
        'is_active' => true,
    ]);
}

function discountPricingVariation(Product $product, string $sku, float $price, ?float $salePrice = null): ProductVariation
{
    $variation = ProductVariation::create([
        'product_id' => $product->id,
        'sku' => $sku,
        'price' => $price,
        'stock' => 5,
        'attributes' => ['Metal' => $sku],
        'is_active' => true,
    ]);

    if ($salePrice !== null) {
        $variation->update(['sale_price' => $salePrice]);
    }

    return $variation->refresh();
}

test('product page shows the discounted variation price with its regular price and percentage off', function () {
    Storage::fake('public');

    $product = discountPricingVariableProduct();
    $discounted = discountPricingVariation($product, 'Silver', 500, 400);
    $regular = discountPricingVariation($product, 'Gold', 900);

    $response = $this->get(route('product.show', $product->slug));

    $response->assertOk()
        ->assertSee('id="product-price"', false)
        ->assertSee('id="product-price-old"', false)
        ->assertSee('id="product-price-discount"', false)
        ->assertSee('₹400', false)
        ->assertSee('₹500', false)
        ->assertSee('20% OFF', false)
        // The parent product price (999 -> 111) is never displayed.
        ->assertDontSee('₹999', false)
        ->assertDontSee('₹111', false);

    $html = $response->getContent();

    expect($html)
        ->toContain('data-variation-id="' . $discounted->id . '"')
        ->toContain('data-price="400"')
        ->toContain('data-regular-price="500"')
        ->toContain('data-discount="20"')
        ->toContain('data-variation-id="' . $regular->id . '"')
        ->toContain('data-price="900"')
        ->toContain('data-discount="0"');
});

test('shop listing uses the cheapest variation price and its discount', function () {
    Storage::fake('public');

    $product = discountPricingVariableProduct();
    discountPricingVariation($product, 'Silver', 500, 400);

    $response = $this->get(route('products.index'));

    $response->assertOk()
        ->assertSee($product->name, false)
        ->assertSee('Rs 400.00', false)
        ->assertSee('Rs 500.00', false)
        ->assertSee('-20%', false)
        // Product-level sale price/base price are ignored for variable products.
        ->assertDontSee('Rs 111.00', false)
        ->assertDontSee('Rs 999.00', false);
});

test('adding a variation to the cart stores its discounted price', function () {
    Storage::fake('public');

    /** @var User $user */
    $user = User::factory()->create();
    $product = discountPricingVariableProduct();
    $variation = discountPricingVariation($product, 'Silver', 500, 400);

    $this->actingAs($user)->post(route('cart.add'), [
        'product_id' => $product->id,
        'product_variation_id' => $variation->id,
        'quantity' => 2,
    ])->assertSessionHas('success');

    $cartItem = Cart::where('user_id', $user->id)->where('product_variation_id', $variation->id)->first();

    expect($cartItem)->not->toBeNull()
        ->and((float) $cartItem->price)->toBe(400.0)
        ->and((int) $cartItem->quantity)->toBe(2);
});

test('admin product page reports the variation price range instead of the product record price', function () {
    Storage::fake('public');

    $admin = discountPricingAdmin();
    $product = discountPricingVariableProduct();
    discountPricingVariation($product, 'Silver', 500, 400);
    discountPricingVariation($product, 'Gold', 900);

    $response = $this->actingAs($admin)->get(route('admin.products.show', $product))->assertOk();

    $response->assertSee('₹500.00', false)
        ->assertSee('₹900.00', false)
        ->assertSee('₹400.00', false)
        ->assertSee('20% OFF', false)
        ->assertSee('Prices come from the variations below', false)
        ->assertDontSee('88.89% OFF', false);
});

test('saving a percentage discount applies it to the storefront price', function () {
    Storage::fake('public');

    $admin = discountPricingAdmin();
    $product = Product::create([
        'name' => 'Discount Pricing Bracelet ' . uniqid(),
        'audience' => 'women',
        'material_type' => 'gold',
        'slug' => 'discount-pricing-bracelet-' . uniqid(),
        'base_price' => 1000,
        'product_type' => 'simple',
        'is_active' => true,
    ]);

    $this->actingAs($admin)->put(route('admin.products.update', $product), [
        'name' => $product->name,
        'slug' => $product->slug,
        'audience' => 'women',
        'material_type' => 'gold',
        'base_price' => 1000,
        'product_type' => 'simple',
        'is_active' => 1,
        'discount_type' => 'percentage',
        'discount_value' => 25,
    ])->assertRedirect(route('admin.products.show', $product->id));

    $product->refresh();

    expect((float) $product->sale_price)->toBe(750.0)
        ->and($product->discount_type)->toBe('percentage')
        ->and((float) $product->discount_value)->toBe(25.0);

    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee('₹750', false)
        ->assertSee('₹1,000', false)
        ->assertSee('25% OFF', false);
});

test('selecting no discount clears the stored sale price and storefront discount', function () {
    Storage::fake('public');

    $admin = discountPricingAdmin();
    $product = Product::create([
        'name' => 'Discount Pricing Pendant ' . uniqid(),
        'audience' => 'women',
        'material_type' => 'gold',
        'slug' => 'discount-pricing-pendant-' . uniqid(),
        'base_price' => 1000,
        'sale_price' => 800,
        'discount_type' => 'percentage',
        'discount_value' => 20,
        'product_type' => 'simple',
        'is_active' => true,
    ]);

    $this->actingAs($admin)->put(route('admin.products.update', $product), [
        'name' => $product->name,
        'slug' => $product->slug,
        'audience' => 'women',
        'material_type' => 'gold',
        'base_price' => 1000,
        'product_type' => 'simple',
        'is_active' => 1,
        'discount_type' => '',
        'discount_value' => '',
        'sale_price' => '',
    ])->assertRedirect(route('admin.products.show', $product->id));

    $product->refresh();

    expect($product->sale_price)->toBeNull()
        ->and($product->discount_type)->toBeNull()
        ->and($product->discount_value)->toBeNull()
        ->and($product->hasDiscount())->toBeFalse();

    $response = $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee('₹1,000', false);

    // Only the product price block is checked: the layout renders a global
    // "Get 10% OFF" newsletter popup on every page.
    $html = $response->getContent();
    $priceBlockStart = strpos($html, 'id="product-price"');
    $priceBlock = $priceBlockStart === false ? '' : substr($html, $priceBlockStart, 600);

    expect($priceBlockStart)->toBeInt()
        ->and($priceBlock)->toContain('₹1,000')
        ->and($priceBlock)->toContain('display:none')
        ->and($priceBlock)->not->toContain('% OFF');
});

test('saving a discount type without a value is rejected', function () {
    Storage::fake('public');

    $admin = discountPricingAdmin();
    $product = Product::create([
        'name' => 'Discount Pricing Studs ' . uniqid(),
        'audience' => 'women',
        'material_type' => 'gold',
        'slug' => 'discount-pricing-studs-' . uniqid(),
        'base_price' => 1000,
        'product_type' => 'simple',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.products.edit', $product))
        ->put(route('admin.products.update', $product), [
            'name' => $product->name,
            'slug' => $product->slug,
            'audience' => 'women',
            'material_type' => 'gold',
            'base_price' => 1000,
            'product_type' => 'simple',
            'is_active' => 1,
            'discount_type' => 'percentage',
            'discount_value' => '',
        ])
        ->assertSessionHasErrors('discount_value');

    expect($product->refresh()->sale_price)->toBeNull();
});




