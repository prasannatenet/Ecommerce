<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\put;

function makeDashboardAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function makeDashboardProduct(string $name, float $price = 100): Product
{
    return Product::create([
        'name' => $name,
        'slug' => str($name)->slug()->append('-'.uniqid())->value(),
        'base_price' => $price,
        'is_active' => true,
    ]);
}

function makeDashboardOrder(
    User $customer,
    string $paymentStatus,
    float $total,
    Carbon $paidAt,
    string $status = 'processing',
): Order {
    $order = Order::create([
        'user_id' => $customer->id,
        'status' => $status,
        'payment_method' => $paymentStatus === 'paid' ? 'razorpay' : 'cod',
        'payment_status' => $paymentStatus,
        'paid_at' => $paymentStatus === 'paid' ? $paidAt : null,
        'refund_status' => 'none',
        'total' => $total,
        'refunded_total' => 0,
        'stock_deducted' => false,
    ]);

    $order->forceFill([
        'created_at' => $paidAt,
        'updated_at' => $paidAt,
    ])->save();

    return $order;
}

it('shows real recent orders and paid top products on the admin dashboard', function () {
    $admin = makeDashboardAdmin();
    $customer = User::factory()->create(['name' => 'Dashboard Customer']);
    $product = makeDashboardProduct('Dashboard Diamond', 250);
    $otherProduct = makeDashboardProduct('Dashboard Sapphire', 175);

    $paidOrder = makeDashboardOrder($customer, 'paid', 500, now()->subDays(2));
    $pendingOrder = makeDashboardOrder($customer, 'pending', 900, now());

    OrderItem::create([
        'order_id' => $paidOrder->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'unit_price' => 250,
        'quantity' => 2,
        'line_total' => 500,
    ]);
    OrderItem::create([
        'order_id' => $pendingOrder->id,
        'product_id' => $otherProduct->id,
        'product_name' => $otherProduct->name,
        'unit_price' => 175,
        'quantity' => 5,
        'line_total' => 875,
    ]);

    actingAs($admin);

    get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Recent Orders')
        ->assertSee('Top Products')
        ->assertSee('Dashboard Customer')
        ->assertViewHas('topProducts', function ($topProducts): bool {
            return count($topProducts) === 1
                && $topProducts[0]['name'] === 'Dashboard Diamond';
        })
        ->assertSee($product->name)
        ->assertSee('2')
        ->assertDontSee('iPhone 15 Pro')
        ->assertDontSee('ORD-001');
});

it('returns the correct paid sales series for week, month, and year', function () {
    $admin = makeDashboardAdmin();
    $customer = User::factory()->create();
    $product = makeDashboardProduct('Sales Product', 100);

    $currentWeekOrder = makeDashboardOrder($customer, 'paid', 100, now()->setTime(10, 0));
    $previousPeriodOrder = makeDashboardOrder($customer, 'paid', 50, now()->subYear()->setTime(10, 0));
    $pendingOrder = makeDashboardOrder($customer, 'pending', 999, now());
    $failedOrder = makeDashboardOrder($customer, 'failed', 777, now());

    foreach ([$currentWeekOrder, $previousPeriodOrder, $pendingOrder, $failedOrder] as $order) {
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 100,
            'quantity' => 1,
            'line_total' => $order->total,
        ]);
    }

    actingAs($admin);

    $week = getJson(route('admin.dashboard.sales', ['period' => 'week']))
        ->assertOk()
        ->assertJsonPath('period', 'week')
        ->assertJsonCount(7, 'labels')
        ->assertJsonPath('total', 100);

    $month = getJson(route('admin.dashboard.sales', ['period' => 'month']))
        ->assertOk()
        ->assertJsonPath('period', 'month')
        ->assertJsonPath('total', 100);

    $year = getJson(route('admin.dashboard.sales', ['period' => 'year']))
        ->assertOk()
        ->assertJsonPath('period', 'year')
        ->assertJsonCount(12, 'labels')
        ->assertJsonPath('total', 100);

    expect(array_sum($week->json('revenue')))->toEqual(100.0)
        ->and(array_sum($month->json('revenue')))->toEqual(100.0)
        ->and(array_sum($year->json('revenue')))->toEqual(100.0);
});

it('rejects an invalid sales period and protects the sales endpoint', function () {
    $admin = makeDashboardAdmin();
    actingAs($admin);

    getJson(route('admin.dashboard.sales', ['period' => 'quarter']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('period');

    $customer = User::factory()->create();
    actingAs($customer);

    getJson(route('admin.dashboard.sales'))
        ->assertRedirect(route('account.index'));
});

it('records the payment timestamp when an admin marks an order paid', function () {
    $admin = makeDashboardAdmin();
    $customer = User::factory()->create();
    $order = makeDashboardOrder($customer, 'pending', 300, now(), 'pending');

    actingAs($admin);

    put(route('admin.orders.update', $order), [
        'status' => 'processing',
        'payment_status' => 'paid',
    ])->assertRedirect(route('admin.orders.show', $order));

    expect($order->fresh()->payment_status)->toBe('paid')
        ->and($order->fresh()->paid_at)->not->toBeNull();
});
