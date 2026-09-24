<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;
use function Pest\Laravel\get;

function relTestProduct(string $name, ?int $categoryId, float $price = 500): Product
{
    return Product::create([
        'name'        => $name,
        'slug'        => Str::slug($name) . '-' . uniqid(),
        'base_price'  => $price,
        'category_id' => $categoryId,
        'product_type' => 'simple',
        'is_active'   => true,
    ]);
}

it('shows other same-category products below the product details', function () {
    $category = Category::create(['name' => 'Rings ' . uniqid()]);
    $other = Category::create(['name' => 'Necklaces ' . uniqid()]);

    $main = relTestProduct('Main Gold Ring', $category->id);
    $sibA = relTestProduct('Sibling Silver Ring', $category->id);
    $sibB = relTestProduct('Sibling Diamond Ring', $category->id);
    $sibC = relTestProduct('Sibling Pearl Ring', $category->id);
    $unrelated = relTestProduct('Unrelated Chain', $other->id);

    $response = get(route('product.show', $main->slug));

    $response->assertOk();

    $html = $response->getContent();
    $section = substr($html, (int) strpos($html, 'id="relatedProducts"'));

    expect($html)->toContain('id="relatedProducts"')
        ->and($section)->toContain('More From')
        ->and($section)->toContain($category->name)
        ->and($section)->toContain($sibA->name)
        ->and($section)->toContain($sibB->name)
        ->and($section)->toContain($sibC->name)
        ->and($section)->toContain('/products?category=' . $category->id)
        // Only same-category products, and never the viewed product itself:
        ->and($section)->not->toContain($unrelated->name)
        ->and($section)->not->toContain('>' . $main->name . '<');
});

it('hides the suggestions section when the category has no other products', function () {
    $category = Category::create(['name' => 'Solo ' . uniqid()]);
    $lonely = relTestProduct('Lonely Product', $category->id);

    get(route('product.show', $lonely->slug))
        ->assertOk()
        ->assertDontSee('id="relatedProducts"');
});

it('serves the shop page with the popularity sort without a featured column', function () {
    relTestProduct('Popularity Sort Probe', null);

    get(route('products.index', ['sort' => 'popularity']))->assertOk();
});
