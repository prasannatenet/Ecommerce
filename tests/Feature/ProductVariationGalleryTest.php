<?php

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariation;
use App\Models\VariationImage;
use Illuminate\Support\Facades\Storage;

function makeVariationGalleryProduct(): Product
{
    return Product::create([
        'name' => 'Variation Gallery Ring ' . uniqid(),
        'audience' => 'women',
        'material_type' => 'gold',
        'slug' => 'variation-gallery-ring-' . uniqid(),
        'short_description' => 'Parent product short description.',
        'description' => 'Parent product description.',
        'base_price' => 9999,
        'product_type' => 'variable',
        'is_active' => true,
    ]);
}

function makeVariationGalleryVariation(
    Product $product,
    string $sku,
    string $metal,
    float $price,
    string $description
): ProductVariation {
    return ProductVariation::create([
        'product_id' => $product->id,
        'sku' => $sku,
        'price' => $price,
        'description' => $description,
        'stock' => 3,
        'attributes' => ['Metal' => $metal],
        'is_active' => true,
    ]);
}

test('product details shows only the selected variation images, description and effective price', function () {
    Storage::fake('public');

    $product = makeVariationGalleryProduct();
    $goldDescription = 'Recycled 18K gold with a lab-grown diamond setting.';
    $silverDescription = 'Polished silver finish with a matching clasp.';

    $goldVariation = makeVariationGalleryVariation($product, 'VAR-GOLD-01', '18K Gold', 5000, $goldDescription);
    $goldVariation->update(['sale_price' => 4500]);
    $silverVariation = makeVariationGalleryVariation($product, 'VAR-SILVER-01', 'Silver', 8000, $silverDescription);

    $goldFront = VariationImage::create([
        'product_variation_id' => $goldVariation->id,
        'path' => 'variations/gold-front.jpg',
        'is_primary' => true,
    ]);
    $goldSide = VariationImage::create([
        'product_variation_id' => $goldVariation->id,
        'path' => 'variations/gold-side.jpg',
        'is_primary' => false,
    ]);
    $silverFront = VariationImage::create([
        'product_variation_id' => $silverVariation->id,
        'path' => 'variations/silver-front.jpg',
        'is_primary' => true,
    ]);
    $parentImage = ProductImage::create([
        'product_id' => $product->id,
        'path' => 'products/parent-front.jpg',
        'is_primary' => true,
    ]);

    foreach ([$goldFront, $goldSide, $silverFront, $parentImage] as $image) {
        Storage::disk('public')->put($image->path, 'image-content');
    }

    $goldFrontUrl = asset('storage/' . $goldFront->path);
    $goldSideUrl = asset('storage/' . $goldSide->path);
    $silverFrontUrl = asset('storage/' . $silverFront->path);
    $parentImageUrl = asset('storage/' . $parentImage->path);

    $response = $this->get(route('product.show', $product->slug));

    $response->assertOk()
        ->assertSee($goldFrontUrl, false)
        ->assertSee($goldSideUrl, false)
        ->assertSee($silverFrontUrl, false)
        ->assertSee($goldDescription, false)
        ->assertSee('₹4,500', false)
        ->assertDontSee($parentImageUrl, false)
        ->assertDontSee('Parent product short description.', false)
        ->assertDontSee('Parent product description.', false)
        ->assertDontSee('₹9,999', false);

    $html = $response->getContent();

    // The parent gallery is serialized as an empty list, so the client-side
    // gallery can never fall back to the product's own photos or video.
    expect($html)
        ->toContain('id="productGallery"')
        ->toContain('data-product-images="[]"')
        ->toContain('data-gallery-image="' . $goldFrontUrl . '"')
        ->toContain('data-gallery-image="' . $goldSideUrl . '"')
        ->toContain('data-variation-id="' . $silverVariation->id . '"')
        ->toContain('data-description="' . $silverDescription . '"')
        ->toContain('renderGalleryForVariation(varId)');

    // The description tab starts on the default variation's own copy.
    $descriptionStart = strpos($html, 'id="product-description"');

    expect($descriptionStart)->toBeInt();

    $descriptionBlock = substr($html, (int) $descriptionStart, 900);

    expect($descriptionBlock)
        ->toContain($goldDescription)
        ->not->toContain('Parent product description.');
});

test('quick view previews the default variation media, description and effective price', function () {
    Storage::fake('public');

    $product = makeVariationGalleryProduct();
    $description = 'Variation-only quick view description.';

    $variation = makeVariationGalleryVariation($product, 'VAR-QUICK-01', '18K Gold', 5000, $description);
    $variation->update(['sale_price' => 4750]);

    $image = VariationImage::create([
        'product_variation_id' => $variation->id,
        'path' => 'variations/quick-view.jpg',
        'is_primary' => true,
    ]);
    $parentImage = ProductImage::create([
        'product_id' => $product->id,
        'path' => 'products/quick-view-parent.jpg',
        'is_primary' => true,
    ]);

    Storage::disk('public')->put($image->path, 'image-content');
    Storage::disk('public')->put($parentImage->path, 'image-content');

    $this->getJson(route('product.quick-view', $product))
        ->assertOk()
        ->assertSee(asset('storage/' . $image->path), false)
        ->assertSee($description, false)
        ->assertSee('Rs 4,750.00', false)
        ->assertDontSee(asset('storage/' . $parentImage->path), false)
        ->assertDontSee('Parent product description.', false)
        ->assertDontSee('Rs 9,999.00', false);
});
