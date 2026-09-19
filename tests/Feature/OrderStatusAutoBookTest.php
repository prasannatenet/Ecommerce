<?php

use App\Models\DeliveryPartner;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\put;

function makeAutoBookAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function makeIntegratedPartner(): DeliveryPartner
{
    return DeliveryPartner::create([
        'name' => 'Delhivery Express',
        'code' => 'DELHIVERY-' . uniqid(),
        'driver' => 'delhivery',
        'is_active' => true,
        'is_default' => true,
        'is_sandbox' => true,
        'api_key' => 'test-api-key',
        'auto_book_on' => 'both',
        'auto_update_order_status' => false,
        'config' => ['pickup_pin' => '122001'],
    ]);
}

function makeAutoBookOrder(User $buyer): Order
{
    return Order::create([
        'user_id' => $buyer->id,
        'status' => 'pending',
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'refund_status' => 'none',
        'total' => 1200,
        'refunded_total' => 0,
        'stock_deducted' => false,
        'billing_address' => [
            'name' => 'Test Buyer',
            'phone' => '9812345678',
            'line1' => 'Street 1',
            'city' => 'Mumbai',
            'state' => 'MH',
            'zip' => '400001',
            'country' => 'India',
        ],
        'shipping_address' => [
            'name' => 'Test Buyer',
            'phone' => '9812345678',
            'line1' => 'Street 1',
            'city' => 'Mumbai',
            'state' => 'MH',
            'zip' => '400001',
            'country' => 'India',
        ],
    ]);
}

it('does not create a duplicate shipment when auto booking keeps failing on status updates', function () {
    $admin = makeAutoBookAdmin();
    $partner = makeIntegratedPartner();
    $buyer = User::factory()->create();
    $order = makeAutoBookOrder($buyer);

    // Courier API is down for every request.
    Http::fake(['*' => Http::response(['error' => 'api down'], 500)]);

    actingAs($admin);

    put(route('admin.orders.update', $order), ['status' => 'processing'])
        ->assertRedirect(route('admin.orders.show', $order));

    expect(Shipment::where('order_id', $order->id)->count())->toBe(1);

    put(route('admin.orders.update', $order), ['status' => 'shipped'])
        ->assertRedirect(route('admin.orders.show', $order));

    // The failed booking must be retried on the SAME shipment, not duplicated.
    expect(Shipment::where('order_id', $order->id)->count())->toBe(1);

    $shipment = Shipment::where('order_id', $order->id)->first();
    expect($shipment->delivery_partner_id)->toBe($partner->id);
    expect($shipment->status)->toBe('pending');
    expect($shipment->tracking_number)->toBeNull();
});

it('retries auto booking on the existing shipment and completes it without duplicates', function () {
    $admin = makeAutoBookAdmin();
    makeIntegratedPartner();
    $buyer = User::factory()->create();
    $order = makeAutoBookOrder($buyer);

    // First attempt hits a down courier API; the retry succeeds on the same shipment.
    $waybillAttempts = 0;

    Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$waybillAttempts) {
        if (str_contains($request->url(), '/api/waybill/')) {
            $waybillAttempts++;

            if ($waybillAttempts === 1) {
                return Http::response(['error' => 'api down'], 500);
            }

            return Http::response(['awb' => ['WB12345678']], 200);
        }

        return Http::response(['packages' => [['waybill' => 'WB12345678', 'refnum' => 'REF1']]], 200);
    });

    actingAs($admin);

    put(route('admin.orders.update', $order), ['status' => 'processing'])
        ->assertRedirect(route('admin.orders.show', $order));

    expect(Shipment::where('order_id', $order->id)->count())->toBe(1);
    expect(Shipment::where('order_id', $order->id)->first()->tracking_number)->toBeNull();

    // Second status update: the SAME shipment is retried and booked — no duplicate created.
    put(route('admin.orders.update', $order), ['status' => 'shipped'])
        ->assertRedirect(route('admin.orders.show', $order));

    expect(Shipment::where('order_id', $order->id)->count())->toBe(1);

    expect($waybillAttempts)->toBe(2);

    $shipment = Shipment::where('order_id', $order->id)->first();
    expect($shipment->tracking_number)->toBe('WB12345678');
    expect($shipment->status)->toBe('booked');
});

it('never books a shipment twice once the tracking number exists', function () {
    $admin = makeAutoBookAdmin();
    makeIntegratedPartner();
    $buyer = User::factory()->create();
    $order = makeAutoBookOrder($buyer);

    Http::fake([
        '*/api/waybill/*' => Http::response(['awb' => ['WB00000001']], 200),
        '*/api/cmu/create.json' => Http::response(['packages' => [['waybill' => 'WB00000001']]], 200),
    ]);

    actingAs($admin);

    put(route('admin.orders.update', $order), ['status' => 'processing'])
        ->assertRedirect(route('admin.orders.show', $order));

    expect(Shipment::where('order_id', $order->id)->count())->toBe(1);

    put(route('admin.orders.update', $order), ['status' => 'shipped'])
        ->assertRedirect(route('admin.orders.show', $order));

    put(route('admin.orders.update', $order), ['status' => 'delivered'])
        ->assertRedirect(route('admin.orders.show', $order));

    expect(Shipment::where('order_id', $order->id)->count())->toBe(1);

    // One waybill fetch + one booking request per successful booking.
    Http::assertSentCount(2);
});

