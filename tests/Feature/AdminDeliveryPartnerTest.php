<?php

use App\Models\DeliveryPartner;
use App\Models\Shipment;
use App\Models\User;
use Spatie\Permission\Models\Role;

function makeAdminUser(): User
{
    Role::firstOrCreate(['name' => 'admin']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

test('admin can update delivery partner automation rules without re-entering secrets', function () {
    $admin = makeAdminUser();

    $partner = DeliveryPartner::create([
        'name' => 'Delhivery',
        'code' => 'DELHIVERY',
        'driver' => 'delhivery',
        'contact_email' => 'ship@example.com',
        'is_active' => true,
        'is_default' => true,
        'is_sandbox' => true,
        'api_key' => 'super-secret-token-4821',
        'client_id' => 'ucp-service-cli',
        'client_secret' => 'b2c-secret-9999',
        'auto_book_on' => 'processing',
        'auto_update_order_status' => true,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.delivery-partners.update', $partner), [
            'name' => 'Delhivery',
            'code' => 'DELHIVERY',
            'driver' => 'delhivery',
            'contact_email' => 'ship@example.com',
            'contact_phone' => '',
            'is_active' => '1',
            'is_default' => '1',
            'is_sandbox' => '1',
            'api_key' => '',
            'client_id' => 'ucp-service-cli',
            'client_secret' => '',
            'auto_book_enabled' => '1',
            'auto_book_on' => 'both',
            'auto_sync_tracking' => '1',
            'auto_notify_customer' => '1',
            'notify_events' => ['shipped', 'delivered'],
        ])->assertRedirect(route('admin.delivery-partners.index'));

    $partner->refresh();

    // Blank credential fields preserve the stored secrets.
    expect($partner->api_key)->toBe('super-secret-token-4821')
        ->and($partner->client_secret)->toBe('b2c-secret-9999')
        ->and($partner->auto_book_on)->toBe('both')
        ->and($partner->auto_sync_tracking)->toBeTrue()
        ->and($partner->auto_notify_customer)->toBeTrue()
        ->and($partner->notify_events)->toBe(['shipped', 'delivered'])
        ->and($partner->maskedApiKey())->toContain('4821')
        ->and($partner->maskedApiKey())->not->toContain('super-secret-token');
});

test('delivery settings form never renders the raw courier token', function () {
    $admin = makeAdminUser();

    $partner = DeliveryPartner::create([
        'name' => 'Delhivery',
        'code' => 'DELHIVERY',
        'driver' => 'delhivery',
        'is_active' => true,
        'api_key' => 'super-secret-token-4821',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.delivery-partners.edit', $partner));

    $response->assertOk();
    $response->assertSee('Delivery Partner Settings');
    $response->assertSee('Automatic shipping rules');
    $response->assertSee('••••••••4821', false);
    $response->assertDontSee('super-secret-token-4821', false);
});

test('shipment management dashboard shows stats and filters', function () {
    $admin = makeAdminUser();

    $partner = DeliveryPartner::create([
        'name' => 'Delhivery',
        'code' => 'DELHIVERY',
        'driver' => 'manual',
        'is_active' => true,
        'is_default' => true,
    ]);

    $order = \App\Models\Order::create([
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
        'shipping_address' => ['pincode' => '600001'],
        'billing_address' => ['pincode' => '600001'],
    ]);

    Shipment::create([
        'order_id' => $order->id,
        'delivery_partner_id' => $partner->id,
        'tracking_number' => 'AWB1001',
        'status' => 'in_transit',
        'booked_at' => now(),
        'last_synced_at' => now(),
    ]);

    Shipment::create([
        'order_id' => $order->id,
        'delivery_partner_id' => $partner->id,
        'tracking_number' => 'AWB1002',
        'status' => 'delivered',
        'booked_at' => now(),
        'last_synced_at' => now(),
        'delivered_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.shipments.index'));

    $response->assertOk();
    $response->assertSee('Shipment Management');
    $response->assertSee('AWB1001');
    $response->assertSee('Synced from provider');

    // Status filter narrows the record set.
    $filtered = $this->actingAs($admin)->get(route('admin.shipments.index', ['status' => 'delivered']));
    $filtered->assertOk();
    $filtered->assertSee('AWB1002');
});
