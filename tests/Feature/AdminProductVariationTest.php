<?php

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\VariationImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

function makeVariationAdminUser(): User
{
    Role::firstOrCreate(['name' => 'admin']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function makeVariableProductForVariationTest(): Product
{
    return Product::create([
        'name' => 'Variation Test Ring ' . uniqid(),
        'audience' => 'women',
        'material_type' => 'gold',
        'slug' => 'variation-test-ring-' . uniqid(),
        'base_price' => 5000,
        'product_type' => 'variable',
        'is_active' => true,
    ]);
}

test('admin can create and immediately see variation details and photo', function () {
    Storage::fake('public');

    $admin = makeVariationAdminUser();
    $product = makeVariableProductForVariationTest();
    $fixtureImage = public_path('frontend/assets/mc.png');

    $this->actingAs($admin)
        ->from(route('admin.products.show', $product))
        ->post(route('admin.products.variations.store', $product), [
            'sku' => 'VAR-DIAMOND-12',
            'price' => 2500,
            'stock' => 7,
            'description' => 'Recycled 18K gold with a lab-grown diamond setting.',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'attributes' => [
                'Metal' => '18K Gold',
                'Size' => '12',
            ],
            'is_active' => '1',
            'images' => [
                new UploadedFile($fixtureImage, 'variation.png', 'image/png', null, true),
            ],
        ])
        ->assertRedirect(route('admin.products.show', $product))
        ->assertSessionHas('success', 'Variation created successfully');

    $variation = ProductVariation::query()->where('sku', 'VAR-DIAMOND-12')->firstOrFail();
    $image = $variation->images()->sole();

    expect($variation->attributes)->toBe([
            'Metal' => '18K Gold',
            'Size' => '12',
        ])
        ->and($variation->description)->toBe('Recycled 18K gold with a lab-grown diamond setting.')
        ->and((float) $variation->sale_price)->toBe(2250.0)
        ->and($image->is_primary)->toBeTrue()
        ->and($image->url)->toContain('variations/');

    Storage::disk('public')->assertExists($image->path);

    $response = $this->get(route('admin.products.show', $product))->assertOk();
    $response->assertSee('18K Gold', false)
        ->assertSee('Recycled 18K gold with a lab-grown diamond setting.', false)
        ->assertSee($image->url, false)
        ->assertSee('Variation photo for ' . $product->name, false)
        ->assertSee('form="deleteVariationImage-' . $variation->id . '-' . $image->id . '"', false);

    $html = $response->getContent();
    $panelStart = strpos($html, 'id="variation-' . $variation->id . '"');

    expect($panelStart)->toBeInt()
        ->and(substr($html, $panelStart, 180))->toContain('display:block');
});

test('an existing saved variation renders its details and photo', function () {
    Storage::fake('public');

    $admin = makeVariationAdminUser();
    $product = makeVariableProductForVariationTest();

    $variation = ProductVariation::create([
        'product_id' => $product->id,
        'sku' => 'VAR-EXISTING-10',
        'price' => 3200,
        'description' => 'Polished silver finish with a matching clasp.',
        'stock' => 4,
        'attributes' => [
            'Metal' => 'Silver',
            'Size' => '10',
        ],
        'is_active' => true,
    ]);

    Storage::disk('public')->put('variations/existing-variation.jpg', 'existing-image');
    $image = VariationImage::create([
        'product_variation_id' => $variation->id,
        'path' => 'variations/existing-variation.jpg',
        'is_primary' => true,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.products.show', $product))
        ->assertOk();

    $response->assertSee('Silver', false)
        ->assertSee('Polished silver finish with a matching clasp.', false)
        ->assertSee($image->url, false);

    $html = $response->getContent();
    $panelStart = strpos($html, 'id="variation-' . $variation->id . '"');

    expect($panelStart)->toBeInt()
        ->and(substr($html, $panelStart, 180))->toContain('display:block')
        ->and($html)->toContain($variation->description);
});
