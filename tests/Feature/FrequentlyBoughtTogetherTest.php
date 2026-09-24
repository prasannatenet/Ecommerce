<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\ProductRecommendationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

use function Pest\Laravel\get;

beforeEach(function (): void {
    Cache::flush();
});

function fbtProduct(string $name, bool $active = true, ?int $categoryId = null): Product
{
    return Product::create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.uniqid(),
        'base_price' => 100,
        'product_type' => 'simple',
        'category_id' => $categoryId,
        'is_active' => $active,
    ]);
}

function fbtOrder(string $paymentStatus, string $status, string $refundStatus, Product ...$products): Order
{
    $order = Order::create([
        'user_id' => null,
        'status' => $status,
        'payment_method' => $paymentStatus === 'paid' ? 'razorpay' : 'cod',
        'payment_status' => $paymentStatus,
        'paid_at' => $paymentStatus === 'paid' ? now() : null,
        'refund_status' => $refundStatus,
        'total' => 100 * count($products),
        'refunded_total' => 0,
        'stock_deducted' => false,
    ]);

    foreach ($products as $product) {
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 100,
            'quantity' => 1,
            'line_total' => 100,
        ]);
    }

    return $order;
}

it('recommends products most often purchased with the viewed product', function () {
    $main = fbtProduct('Main Recommendation Product');
    $frequent = fbtProduct('Frequent Companion');
    $occasional = fbtProduct('Occasional Companion');
    $pending = fbtProduct('Pending Companion');
    $refunded = fbtProduct('Refunded Companion');
    $inactive = fbtProduct('Inactive Companion', false);

    fbtOrder('paid', 'processing', 'none', $main, $frequent);
    fbtOrder('paid', 'processing', 'none', $main, $frequent);
    fbtOrder('paid', 'processing', 'none', $main, $occasional);
    fbtOrder('pending', 'processing', 'none', $main, $pending);
    fbtOrder('paid', 'processing', 'full', $main, $refunded);
    fbtOrder('paid', 'processing', 'none', $main, $inactive);

    $response = get(route('product.show', $main->slug));
    $response->assertOk();

    $html = $response->getContent();
    $start = strpos($html, 'id="frequentlyBoughtTogether"');
    $nextSection = $start === false ? false : strpos($html, 'id="relatedProducts"', $start);
    $section = $start === false
        ? ''
        : substr($html, $start, $nextSection === false ? null : $nextSection - $start);

    expect($html)->toContain('Frequently Bought Together')
        ->and($section)->toContain($frequent->name)
        ->and($section)->toContain($occasional->name)
        ->and($section)->not->toContain($pending->name)
        ->and($section)->not->toContain($refunded->name)
        ->and($section)->not->toContain($inactive->name)
        ->and(strpos($section, $frequent->name))->toBeLessThan(strpos($section, $occasional->name));
});

it('refreshes cached recommendations when a new valid order is recorded', function () {
    $main = fbtProduct('Cache Main Product');
    $first = fbtProduct('Cache First Companion');
    $second = fbtProduct('Cache Second Companion');
    $service = app(ProductRecommendationService::class);

    fbtOrder('paid', 'processing', 'none', $main, $first);
    expect($service->forProduct($main)->pluck('id')->all())->toBe([$first->id]);

    $newOrder = fbtOrder('paid', 'processing', 'none', $main, $second);
    $service->forgetForOrder($newOrder);

    expect($service->forProduct($main)->pluck('id')->all())
        ->toContain($second->id)
        ->toHaveCount(2);
});

it('returns no co-purchase recommendations when no valid order exists', function () {
    $main = fbtProduct('No Co-purchase Main');

    expect(app(ProductRecommendationService::class)->forProduct($main))->toBeEmpty();
});
