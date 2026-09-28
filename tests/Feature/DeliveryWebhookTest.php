<?php

use App\Models\DeliveryPartner;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentTrackingEvent;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

function webhookAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function webhookPartner(array $overrides = []): DeliveryPartner
{
    return DeliveryPartner::create(array_merge([
        'name' => 'Delhivery',
        'code' => 'DELHIVERY',
        'driver' => 'delhivery',
        'is_active' => true,
        'api_key' => 'express-token',
        'webhook_secret' => 'topsecret',
        'config' => ['pickup_pin' => '122001'],
    ], $overrides));
}

function trackedShipment(DeliveryPartner $partner, string $waybill = 'WB1001'): Shipment
{
    $order = Order::create([
        'user_id' => webhookAdmin()->id,
        'status' => 'shipped',
        'payment_method' => 'prepaid',
        'payment_status' => 'paid',
        'subtotal' => 100,
        'shipping_cost' => 0,
        'tax' => 0,
        'discount' => 0,
        'total' => 100,
        'currency' => 'INR',
        'shipping_address' => ['pincode' => '400001', 'name' => 'Test Buyer'],
        'billing_address' => ['pincode' => '400001'],
    ]);

    return Shipment::create([
        'order_id' => $order->id,
        'delivery_partner_id' => $partner->id,
        'tracking_number' => $waybill,
        'status' => 'booked',
        'booked_at' => now(),
    ]);
}

function courierPayload(string $waybill, string $scan = 'Out for Delivery'): string
{
    return json_encode([
        'shipments' => [[
            'waybill' => $waybill,
            'scans' => [[
                'scan_type' => $scan,
                'scan' => $scan,
                'scan_datetime' => '2026-09-28T10:30:00+05:30',
                'scanned_location' => 'Mumbai',
            ]],
        ]],
    ]);
}

function postWebhook(string $body, ?string $secret = 'topsecret'): TestResponse
{
    $headers = ['CONTENT_TYPE' => 'application/json'];

    if ($secret !== null) {
        $headers['HTTP_X-Delhivery-Signature'] = 'sha256=' . hash_hmac('sha256', $body, $secret);
    }

    return test()->call('POST', '/webhooks/delivery/DELHIVERY', [], [], [], $headers, $body);
}

test('courier webhook accepts a correctly signed POST without CSRF', function () {
    $partner = webhookPartner();
    $shipment = trackedShipment($partner);

    postWebhook(courierPayload('WB1001', 'Out for Delivery'))
        ->assertOk()
        ->assertJson(['ok' => true, 'handled' => 1]);

    expect($shipment->fresh()->status)->toBe('out_for_delivery');
});

test('courier webhook is reachable at the public URL with no session or CSRF token', function () {
    $partner = webhookPartner();
    trackedShipment($partner);

    $this->assertGuest();

    postWebhook(courierPayload('WB1001'))
        ->assertOk()
        ->assertJson(['ok' => true]);
});

test('courier webhook rejects a tampered or missing signature', function () {
    $partner = webhookPartner();
    $shipment = trackedShipment($partner);
    $body = courierPayload('WB1001', 'Delivered');

    postWebhook($body, 'wrong-secret')->assertOk()->assertJson(['ok' => false, 'message' => 'Unauthorized']);
    postWebhook($body, null)->assertOk()->assertJson(['ok' => false, 'message' => 'Unauthorized']);

    expect($shipment->fresh()->status)->toBe('booked');
});

test('strict webhook mode rejects unsigned callbacks when no secret is configured', function () {
    $partner = webhookPartner([
        'webhook_secret' => null,
        'require_webhook_signature' => true,
    ]);
    $shipment = trackedShipment($partner);

    postWebhook(courierPayload('WB1001', 'Delivered'), null)
        ->assertOk()
        ->assertJson(['ok' => false, 'message' => 'Unauthorized']);

    expect($shipment->fresh()->status)->toBe('booked');
});

test('webhook signature is accepted with or without the sha256 prefix', function () {
    $partner = webhookPartner();
    $shipment = trackedShipment($partner, 'WB8888');

    $body = courierPayload('WB8888', 'In Transit');
    $bare = hash_hmac('sha256', $body, 'topsecret');

    test()->call('POST', '/webhooks/delivery/DELHIVERY', [], [], [], [
        'HTTP_X-Delhivery-Signature' => $bare,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk()->assertJson(['ok' => true]);

    expect($shipment->fresh()->status)->toBe('in_transit');
});

test('a repeated webhook does not duplicate tracking events', function () {
    $partner = webhookPartner();
    $shipment = trackedShipment($partner);

    $body = courierPayload('WB1001', 'In Transit');

    postWebhook($body)->assertOk();
    postWebhook($body)->assertOk();
    postWebhook($body)->assertOk();

    expect(ShipmentTrackingEvent::where('shipment_id', $shipment->id)->count())->toBe(1);
});

test('cancelling an order asks the courier to stop the shipment', function () {
    $admin = webhookAdmin();
    $partner = webhookPartner();
    $shipment = trackedShipment($partner, 'WB5555');

    $order = $shipment->order;
    $order->update(['status' => 'processing']);

    $cancelCalled = false;
    Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$cancelCalled) {
        if (str_contains($request->url(), '/api/p/edit')) {
            $cancelCalled = true;
        }

        return Http::response([], 200);
    });

    actingAs($admin);

    post(route('admin.orders.cancel', $order), ['reason' => 'Customer changed mind'])
        ->assertRedirect(route('admin.orders.show', $order));

    expect($cancelCalled)->toBeTrue()
        ->and($shipment->fresh()->status)->toBe('cancelled')
        ->and($order->fresh()->status)->toBe('cancelled');
});

test('cancelling via the order status dropdown also cancels the courier shipment', function () {
    $admin = webhookAdmin();
    $partner = webhookPartner();
    $shipment = trackedShipment($partner, 'WB7777');

    $order = $shipment->order;
    $order->update(['status' => 'processing']);

    $cancelCalled = false;
    Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$cancelCalled) {
        if (str_contains($request->url(), '/api/p/edit')) {
            $cancelCalled = true;
        }

        return Http::response([], 200);
    });

    actingAs($admin);

    test()->put(route('admin.orders.update', $order), ['status' => 'cancelled'])
        ->assertRedirect(route('admin.orders.show', $order));

    expect($cancelCalled)->toBeTrue()
        ->and($shipment->fresh()->status)->toBe('cancelled');
});

test('a courier outage never blocks an order cancellation but is flagged', function () {
    $admin = webhookAdmin();
    $partner = webhookPartner();
    $shipment = trackedShipment($partner, 'WB6666');

    $order = $shipment->order;
    $order->update(['status' => 'processing']);

    Http::fake(['*' => Http::response(['error' => 'service unavailable'], 503)]);

    actingAs($admin);

    post(route('admin.orders.cancel', $order))
        ->assertRedirect(route('admin.orders.show', $order))
        ->assertSessionHas('delivery_error');

    expect($order->fresh()->status)->toBe('cancelled')
        ->and($shipment->fresh()->last_sync_error)->not->toBeNull();
});

test('a shipment that was never booked is cancelled without calling the courier', function () {
    $admin = webhookAdmin();
    $partner = webhookPartner();

    $order = Order::create([
        'user_id' => $admin->id,
        'status' => 'processing',
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'subtotal' => 100,
        'shipping_cost' => 0,
        'tax' => 0,
        'discount' => 0,
        'total' => 100,
        'currency' => 'INR',
        'shipping_address' => ['pincode' => '400001'],
        'billing_address' => ['pincode' => '400001'],
    ]);

    $shipment = Shipment::create([
        'order_id' => $order->id,
        'delivery_partner_id' => $partner->id,
        'status' => 'pending',
    ]);

    Http::fake();
    actingAs($admin);

    post(route('admin.orders.cancel', $order))->assertRedirect();

    expect($shipment->fresh()->status)->toBe('cancelled');
    Http::assertNothingSent();
});

test('prepaid orders are sent to the courier as Prepaid, not Pre-paid', function () {
    $partner = webhookPartner();
    $shipment = trackedShipment($partner, 'WB9999');

    $manager = app(\App\Services\Delivery\DeliveryManager::class);

    $shipment->order->update(['payment_method' => 'prepaid']);
    expect($manager->buildDraft($shipment->fresh())->paymentMode)->toBe('Prepaid');

    $shipment->order->update(['payment_method' => 'cod']);
    expect($manager->buildDraft($shipment->fresh())->paymentMode)->toBe('COD');
});

test('the webhook URL rendered in the admin panel is the live public route', function () {
    $admin = webhookAdmin();
    $partner = webhookPartner();

    $response = actingAs($admin)->get(route('admin.delivery-partners.edit', $partner));

    $response->assertOk();
    $response->assertSee(route('webhooks.delivery', 'DELHIVERY'), false);
    // The panel used to render /webhooks/delivery/... while the route actually
    // lived under the /admin prefix, so copying the shown URL produced a 404.
    $response->assertDontSee('/admin/webhooks/delivery', false);
});
