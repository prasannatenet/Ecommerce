<?php

use App\Models\DeliveryPartner;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Delivery\DelhiveryEndpoints;
use App\Services\Delivery\ServiceabilityResult;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;

function trackingAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function trackingShipment(DeliveryPartner $partner, ?string $waybill, ?string $trackingUrl = null): Shipment
{
    $order = \App\Models\Order::create([
        'user_id' => trackingAdmin()->id,
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
        'tracking_url' => $trackingUrl,
        'status' => 'booked',
        'booked_at' => now(),
    ]);
}

function delhiveryPartner(array $overrides = []): DeliveryPartner
{
    return DeliveryPartner::create(array_merge([
        'name' => 'Delhivery',
        'code' => 'DH-' . uniqid(),
        'driver' => 'delhivery',
        'is_active' => true,
        'is_sandbox' => true,
        'api_key' => 'express-token',
        'config' => ['pickup_name' => 'Main Warehouse', 'pickup_pin' => '122001'],
    ], $overrides));
}

test('the default Delhivery tracking URL uses the public awb path', function () {
    // The old hardcoded value was /track/package/{awb}, which is not a valid
    // Delhivery customer URL and sent buyers to a dead link.
    expect(DeliveryPartner::DEFAULT_TRACKING_URL)
        ->toBe('https://www.delhivery.com/track/awb/{awb}');
});

test('a partner without a template falls back to the Delhivery default', function () {
    $partner = delhiveryPartner(['tracking_url_template' => null]);

    expect($partner->trackingUrlFor('86358810000372', DeliveryPartner::DEFAULT_TRACKING_URL))
        ->toBe('https://www.delhivery.com/track/awb/86358810000372');
});

test('a configured template always wins over the fallback', function () {
    $partner = delhiveryPartner(['tracking_url_template' => 'https://track.example.com/{awb}']);

    expect($partner->trackingUrlFor('WB1', DeliveryPartner::DEFAULT_TRACKING_URL))
        ->toBe('https://track.example.com/WB1');
});

test('booking fills in the correct tracking URL from the courier response', function () {
    $partner = delhiveryPartner(['is_default' => true]);
    $shipment = trackingShipment($partner, null);

    Http::fake([
        '*/waybill/api/bulk/*' => Http::response(['awb' => ['86358810000372']], 200),
        '*/api/cmu/create.json' => Http::response(
            ['packages' => [['waybill' => '86358810000372', 'refnum' => 'REF1']]],
            200
        ),
        '*' => Http::response([], 200),
    ]);

    $result = app(\App\Services\Delivery\DeliveryManager::class)->book($shipment->fresh());

    expect($result->success)->toBeTrue()
        ->and($result->waybill)->toBe('86358810000372')
        ->and($result->trackingUrl)->toBe('https://www.delhivery.com/track/awb/86358810000372')
        ->and($shipment->fresh()->tracking_url)
        ->toBe('https://www.delhivery.com/track/awb/86358810000372');
});

test('syncing repairs a shipment that has a waybill but no tracking URL', function () {
    $partner = delhiveryPartner();
    $shipment = trackingShipment($partner, 'WB7771234', null);

    expect($shipment->tracking_url)->toBeNull();

    Http::fake([
        '*/api/v1/packages/*' => Http::response([
            'ShipmentData' => [[
                'Shipment' => [
                    'AWB' => 'WB7771234',
                    'Scans' => [[
                        'ScanDateTime' => '2026-09-28T10:30:00+05:30',
                        'ScanType' => 'DELIVERED',
                        'Scan' => 'Delivered',
                        'ScannedLocation' => 'Mumbai',
                    ]],
                ],
            ]],
        ], 200),
        '*' => Http::response([], 200),
    ]);

    app(\App\Services\Delivery\DeliveryManager::class)->sync($shipment->fresh());

    expect($shipment->fresh()->tracking_url)
        ->toBe('https://www.delhivery.com/track/awb/WB7771234');
});

test('syncing never overwrites a tracking URL that is already set', function () {
    $partner = delhiveryPartner();
    $shipment = trackingShipment($partner, 'WB8881234', 'https://custom.example.com/WB8881234');

    Http::fake([
        '*/api/v1/packages/*' => Http::response([
            'ShipmentData' => [[
                'Shipment' => [
                    'AWB' => 'WB8881234',
                    'Scans' => [[
                        'ScanDateTime' => '2026-09-28T10:30:00+05:30',
                        'ScanType' => 'DELIVERED',
                        'Scan' => 'Delivered',
                    ]],
                ],
            ]],
        ], 200),
        '*' => Http::response([], 200),
    ]);

    app(\App\Services\Delivery\DeliveryManager::class)->sync($shipment->fresh());

    expect($shipment->fresh()->tracking_url)->toBe('https://custom.example.com/WB8881234');
});

test('a partner with Delhivery One and an Express token books through Express', function () {
    // use_b2c_one alone cannot allocate waybills, which is what silently broke
    // every booking. With an Express token present, booking must succeed.
    $partner = delhiveryPartner([
        'name' => 'Delhivery One',
        'use_b2c_one' => true,
        'is_default' => true,
        'client_id' => 'ucp-service-cli',
        'client_secret' => 'secret',
    ]);

    $shipment = trackingShipment($partner, null);

    Http::fake([
        '*/waybill/api/bulk/*' => Http::response(['awb' => ['WB1234567890']], 200),
        '*/api/cmu/create.json' => Http::response(
            ['packages' => [['waybill' => 'WB1234567890', 'refnum' => 'REF1']]],
            200
        ),
        '*' => Http::response([], 200),
    ]);

    $result = app(\App\Services\Delivery\DeliveryManager::class)->book($shipment->fresh());

    expect($result->success)->toBeTrue()
        ->and($result->waybill)->toBe('WB1234567890');
});

test('a partner with Delhivery One but no Express token explains the fix', function () {
    $partner = delhiveryPartner([
        'name' => 'Delhivery One',
        'use_b2c_one' => true,
        'is_default' => true,
        'api_key' => null,
    ]);

    $shipment = trackingShipment($partner, null);

    Http::fake(['*' => Http::response([], 200)]);

    $result = app(\App\Services\Delivery\DeliveryManager::class)->book($shipment->fresh());

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('Express API token');

    Http::assertNothingSent();
});

test('the admin form warns when a Delhivery partner has no Express token', function () {
    $admin = trackingAdmin();
    $partner = delhiveryPartner([
        'name' => 'Delhivery One',
        'use_b2c_one' => true,
        'api_key' => null,
    ]);

    $response = actingAs($admin)->get(route('admin.delivery-partners.edit', $partner));

    $response->assertOk();
    $response->assertSee('No Delhivery Express API token is saved', false);
});

test('the admin form shows no token warning once a token is stored', function () {
    $admin = trackingAdmin();
    $partner = delhiveryPartner(['name' => 'Delhivery One', 'use_b2c_one' => true]);

    $response = actingAs($admin)->get(route('admin.delivery-partners.edit', $partner));

    $response->assertOk();
    $response->assertDontSee('No Delhivery Express API token is saved', false);
});

test('the admin form warns when the pickup warehouse name is missing', function () {
    $admin = trackingAdmin();
    $partner = delhiveryPartner(['config' => ['pickup_pin' => '122001']]);

    $response = actingAs($admin)->get(route('admin.delivery-partners.edit', $partner));

    $response->assertOk();
    $response->assertSee('No pickup warehouse name is set', false);
});

test('the admin form placeholder uses the public awb tracking URL', function () {
    $admin = trackingAdmin();
    $partner = delhiveryPartner();

    $response = actingAs($admin)->get(route('admin.delivery-partners.edit', $partner));

    $response->assertOk();
    $response->assertSee('https://www.delhivery.com/track/awb/{awb}', false);
    $response->assertDontSee('track/package', false);
});

test('a manual order without an AWB is never auto-rewritten by the repair', function () {
    // "delivered" with no AWB is legitimate: an operator can hand-deliver or
    // book the courier elsewhere. The repair must leave those alone, otherwise
    // it would tell a customer their delivered parcel is pending again.
    $admin = trackingAdmin();
    $partner = delhiveryPartner();
    $shipment = trackingShipment($partner, null);

    $shipment->forceFill(['status' => 'delivered'])->save();

    $this->artisan('delivery:repair-shipments')->assertSuccessful();

    expect($shipment->fresh()->status)->toBe('delivered');
});

test('the repair resets a booked shipment that has no AWB', function () {
    $admin = trackingAdmin();
    $partner = delhiveryPartner();
    $shipment = trackingShipment($partner, null);

    $shipment->forceFill(['status' => 'booked'])->save();

    $this->artisan('delivery:repair-shipments')->assertSuccessful();

    expect($shipment->fresh()->status)->toBe('pending');
});

test('the repair leaves a properly booked shipment alone', function () {
    $admin = trackingAdmin();
    $partner = delhiveryPartner();
    $shipment = trackingShipment($partner, 'WB12345');

    $shipment->forceFill(['status' => 'booked'])->save();

    $this->artisan('delivery:repair-shipments')->assertSuccessful();

    expect($shipment->fresh()->status)->toBe('booked');
});

test('the shipment dashboard shows the courier error that caused a failure', function () {
    $admin = trackingAdmin();
    $partner = delhiveryPartner();
    $shipment = trackingShipment($partner, null);

    $shipment->forceFill([
        'status' => 'booking_failed',
        'last_sync_error' => 'This partner has no Delhivery Express API token, so no AWB can be generated.',
    ])->save();

    $response = actingAs($admin)->get(route('admin.shipments.index'));

    $response->assertOk();
    $response->assertSee('no Delhivery Express API token', false);
});

test('no Delhivery endpoint path is hardcoded in the driver', function () {
    // Every path must be configuration so a Delhivery change is an .env edit.
    // Doc comments are stripped first: they are allowed to *name* the endpoint
    // they explain, they just must not be the value a request is built from.
    $source = file_get_contents(app_path('Services/Delivery/Drivers/DelhiveryDriver.php'));

    $code = '';
    $inDocBlock = false;
    foreach (preg_split('/\R/', $source) as $line) {
        $trimmed = ltrim($line);
        if (str_starts_with($trimmed, '/*')) { $inDocBlock = true; continue; }
        if ($inDocBlock) { if (str_contains($trimmed, '*/')) { $inDocBlock = false; } continue; }
        if (str_starts_with($trimmed, '//')) { continue; }
        $code .= $line . "\n";
    }

    foreach ([
        '/waybill/api/bulk/',
        '/api/cmu/create.json',
        '/c/api/pin-codes/',
        '/api/v1/packages/',
        '/api/p/packing_slip',
        '/api/p/edit',
    ] as $path) {
        expect($code)->not->toContain($path);
    }

    expect($source)->toContain('DelhiveryEndpoints::');
});

test('endpoints resolve from config against the sandbox base URL', function () {
    $partner = delhiveryPartner(['is_sandbox' => true]);

    expect(DelhiveryEndpoints::url('waybill', $partner))
        ->toBe('https://staging-express.delhivery.com/waybill/api/bulk/json/')
        ->and(DelhiveryEndpoints::url('create', $partner))
        ->toBe('https://staging-express.delhivery.com/api/cmu/create.json')
        ->and(DelhiveryEndpoints::url('serviceability', $partner))
        ->toBe('https://staging-express.delhivery.com/c/api/pin-codes/json/')
        ->and(DelhiveryEndpoints::url('tracking', $partner))
        ->toBe('https://staging-express.delhivery.com/api/v1/packages/json/');
});

test('endpoints switch to production when the partner leaves sandbox', function () {
    $partner = delhiveryPartner(['is_sandbox' => false]);

    expect(DelhiveryEndpoints::url('waybill', $partner))
        ->toBe('https://track.delhivery.com/waybill/api/bulk/json/');
});

test('a per-partner endpoint override beats the config value', function () {
    $partner = delhiveryPartner([
        'config' => [
            'pickup_name' => 'Test Warehouse',
            'pickup_pin' => '122001',
            'endpoint_waybill' => '/custom/awb/allocate/',
        ],
    ]);

    expect(DelhiveryEndpoints::path('waybill', $partner))->toBe('custom/awb/allocate/')
        ->and(DelhiveryEndpoints::url('waybill', $partner))
        ->toBe('https://staging-express.delhivery.com/custom/awb/allocate/');
});

test('a per-partner base URL override is respected', function () {
    $partner = delhiveryPartner(['base_url' => 'https://proxy.internal/delhivery/']);

    expect(DelhiveryEndpoints::url('create', $partner))
        ->toBe('https://proxy.internal/delhivery/api/cmu/create.json');
});

test('the waybill is allocated with a GET to the bulk endpoint', function () {
    // Verified live: POST /waybill/api/bulk/json/ answers 405, GET reaches the
    // auth layer. The verb is easy to get wrong and fails as a silent no-AWB.
    $partner = delhiveryPartner();
    $shipment = trackingShipment($partner, null);

    Http::fake([
        '*/waybill/api/bulk/*' => Http::response(['awb' => ['86358810000372']], 200),
        '*/api/cmu/create.json' => Http::response(
            ['packages' => [['waybill' => '86358810000372', 'refnum' => 'R1']]],
            200
        ),
        '*' => Http::response([], 200),
    ]);

    app(\App\Services\Delivery\DeliveryManager::class)->book($shipment->fresh());

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/waybill/api/bulk/json/')
            && str_contains($request->url(), 'count=1')
            && $request->method() === 'GET';
    });
});

test('a 405 from the waybill endpoint is reported instead of a silent no-AWB', function () {
    $partner = delhiveryPartner();
    $shipment = trackingShipment($partner, null);

    Http::fake([
        '*/waybill/api/bulk/*' => Http::response('Method Not Allowed', 405),
        '*' => Http::response([], 200),
    ]);

    $result = app(\App\Services\Delivery\DeliveryManager::class)->book($shipment->fresh());

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('did not return an AWB');
});

test('an unusable token is reported as a token problem, not a bare status code', function () {
    // Live response body: "Bad Request! Invalid request. Unable to fetch
    // client name for the token." It is plain text, not JSON.
    $partner = delhiveryPartner();
    $shipment = trackingShipment($partner, null);

    Http::fake([
        '*/waybill/api/bulk/*' => Http::response(
            'Bad Request! Invalid request. Unable to fetch client name for the token.',
            400
        ),
        '*' => Http::response([], 200),
    ]);

    $result = app(\App\Services\Delivery\DeliveryManager::class)->book($shipment->fresh());

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('could not identify this API token');
});

test('a rejected token explains how to fix it', function () {
    $partner = delhiveryPartner();
    $shipment = trackingShipment($partner, null);

    Http::fake([
        '*/waybill/api/bulk/*' => Http::response('Login or API Key Required', 401),
        '*' => Http::response([], 200),
    ]);

    $result = app(\App\Services\Delivery\DeliveryManager::class)->book($shipment->fresh());

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('rejected the API token');
});

test('the AWB is read from a data wrapped bulk response', function () {
    // The bulk endpoint has returned more than one envelope over time, so a
    // single-shape parser would silently return no AWB.
    $partner = delhiveryPartner();
    $shipment = trackingShipment($partner, null);

    Http::fake([
        '*/waybill/api/bulk/*' => Http::response(['data' => ['awb' => ['WB7770001']]], 200),
        '*/api/cmu/create.json' => Http::response(
            ['packages' => [['waybill' => 'WB7770001', 'refnum' => 'R1']]],
            200
        ),
        '*' => Http::response([], 200),
    ]);

    $result = app(\App\Services\Delivery\DeliveryManager::class)->book($shipment->fresh());

    expect($result->success)->toBeTrue()->and($result->waybill)->toBe('WB7770001');
});


test('a serviceable pincode is parsed from the real delivery_codes payload', function () {
    // Fixture taken verbatim from a live Delhivery response for 302017.
    $payload = ['delivery_codes' => [['postal_code' => [
        'cash' => 'Y', 'city' => 'Jaipur', 'cod' => 'Y', 'country_code' => 'IN',
        'district' => 'Jaipur', 'inc' => 'Jaipur_Hub (Rajasthan)', 'is_oda' => 'N',
        'max_amount' => 0, 'max_weight' => 0, 'pickup' => 'Y', 'pin' => 302017,
        'pre_paid' => 'Y', 'remarks' => '', 'repl' => 'Y', 'state_code' => 'RJ',
        'sun_tat' => false,
    ]]]];

    $partner = delhiveryPartner();
    Http::fake(['*/c/api/pin-codes/*' => Http::response($payload, 200)]);

    $result = (new \App\Services\Delivery\Drivers\DelhiveryDriver())->checkPincode($partner, '302017');

    expect($result->serviceable)->toBeTrue()
        ->and($result->codAvailable)->toBeTrue()
        ->and($result->prepaidAvailable)->toBeTrue()
        ->and($result->city)->toBe('Jaipur')
        ->and($result->stateCode)->toBe('RJ');
});

test('max_weight of zero means unlimited, not a zero kilogram cap', function () {
    // Delhivery sends 0 for "no limit". Treating it as a real cap would reject
    // every parcel in the country.
    $payload = ['delivery_codes' => [['postal_code' => [
        'cod' => 'Y', 'pre_paid' => 'Y', 'max_weight' => 0, 'city' => 'Jaipur',
    ]]]];

    $partner = delhiveryPartner();
    Http::fake(['*/c/api/pin-codes/*' => Http::response($payload, 200)]);

    $result = (new \App\Services\Delivery\Drivers\DelhiveryDriver())->checkPincode($partner, '302017');

    expect($result->serviceable)->toBeTrue()
        ->and($result->maxWeightKg)->toBeNull()
        ->and($result->summary())->not->toContain('0 kg');
});

test('a real weight cap is reported and shown', function () {
    $payload = ['delivery_codes' => [['postal_code' => [
        'cod' => 'Y', 'pre_paid' => 'Y', 'max_weight' => 5, 'city' => 'Jaipur',
    ]]]];

    $partner = delhiveryPartner();
    Http::fake(['*/c/api/pin-codes/*' => Http::response($payload, 200)]);

    $result = (new \App\Services\Delivery\Drivers\DelhiveryDriver())->checkPincode($partner, '302017');

    expect($result->maxWeightKg)->toBe(5.0)
        ->and($result->summary())->toContain('5 kg');
});

test('an empty delivery_codes array means the pincode is not serviceable', function () {
    // Delhivery answers HTTP 200 here, so a successful() check would wrongly
    // report every pincode as serviceable.
    $partner = delhiveryPartner();
    Http::fake(['*/c/api/pin-codes/*' => Http::response(['delivery_codes' => []], 200)]);

    $result = (new \App\Services\Delivery\Drivers\DelhiveryDriver())->checkPincode($partner, '110001');

    expect($result->serviceable)->toBeFalse()
        ->and($result->summary())->toContain('not available');
});

test('a pincode without cash on delivery is flagged to the operator', function () {
    $payload = ['delivery_codes' => [['postal_code' => [
        'cod' => 'N', 'pre_paid' => 'Y', 'max_weight' => 0, 'city' => 'Leh',
        'remarks' => 'Airport delivery only',
    ]]]];

    $partner = delhiveryPartner();
    Http::fake(['*/c/api/pin-codes/*' => Http::response($payload, 200)]);

    $result = (new \App\Services\Delivery\Drivers\DelhiveryDriver())->checkPincode($partner, '194101');

    expect($result->serviceable)->toBeTrue()
        ->and($result->codAvailable)->toBeFalse()
        ->and($result->warnings())->toContain('Cash on Delivery is NOT available for this pincode.')
        ->and($result->warnings())->toContain('Courier note: Airport delivery only');
});

test('a malformed pincode is rejected before any request is made', function () {
    $partner = delhiveryPartner();
    Http::fake();

    $result = (new \App\Services\Delivery\Drivers\DelhiveryDriver())->checkPincode($partner, '3020');

    expect($result->serviceable)->toBeFalse()
        ->and($result->error)->toContain('6 digit');

    Http::assertNothingSent();
});

test('the courier error is surfaced instead of a generic booking failure', function () {
    // This is the real rejection the account hit: the facility is not active.
    $partner = delhiveryPartner();
    $shipment = trackingShipment($partner, null);

    Http::fake([
        '*/waybill/api/bulk/*' => Http::response(['awb' => ['86358810000372']], 200),
        '*/api/cmu/create.json' => Http::response([
            'success' => false,
            'error' => ['facility IND302014AAA is not in active state or does not exist'],
            'error_code' => [1005],
        ], 200),
        '*' => Http::response([], 200),
    ]);

    $result = app(\App\Services\Delivery\DeliveryManager::class)->book($shipment->fresh());

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('IND302014AAA is not in active state');
});

test('booking fails before allocating an AWB when no warehouse is configured', function () {
    $partner = delhiveryPartner(['config' => ['pickup_pin' => '302026']]);
    $shipment = trackingShipment($partner, null);

    Http::fake();

    $result = app(\App\Services\Delivery\DeliveryManager::class)->book($shipment->fresh());

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('pickup warehouse');

    // No AWB should be burned and no request made on a booking that cannot
    // possibly succeed: the warehouse is validated before any courier call.
    Http::assertNothingSent();
});


test('an empty Delhivery One realm is reported as missing config, not a bad realm', function () {
    // The auth server answers an empty realm with "Realm does not exist", which
    // reads like a credential problem. It is actually missing configuration, so
    // the driver must say so and point at the fix.
    config(['delhivery.b2c_one.auth.realm' => '', 'delhivery.b2c_one.auth.token_url' => '']);

    $partner = delhiveryPartner([
        'use_b2c_one' => true,
        'client_id' => 'ucp-service-cli',
        'client_secret' => 'secret',
        'config' => ['pickup_name' => 'Test Warehouse', 'pickup_pin' => '122001'],
    ]);

    $result = app(\App\Services\Delivery\DeliveryManager::class)->testConnection($partner);

    expect($result['ok'])->toBeFalse()
        ->and($result['message'])->toContain('realm is not configured')
        ->and($result['message'])->toContain('config:clear');
});

test('missing Delhivery One client credentials name the setting to use', function () {
    // .env supplies a client id/secret, so blank them in config too to exercise
    // the missing-credential path deterministically.
    config([
        'delhivery.b2c_one.auth.realm' => 'ucp-MHWERK46ZP8S',
        'delhivery.b2c_one.auth.token_url' => '',
        'delhivery.b2c_one.auth.client_id' => '',
        'delhivery.b2c_one.auth.client_secret' => '',
    ]);

    $partner = delhiveryPartner([
        'use_b2c_one' => true,
        'client_id' => null,
        'client_secret' => null,
        'config' => ['pickup_name' => 'Test Warehouse', 'pickup_pin' => '122001'],
    ]);

    $result = app(\App\Services\Delivery\DeliveryManager::class)->testConnection($partner);

    expect($result['ok'])->toBeFalse()
        ->and($result['message'])->toContain('client ID or client secret is missing');
});
test('a Delhivery API endpoint pasted as the template falls back to the public page', function () {
    // This is the live bug: the backend tracking endpoint was saved into the
    // template, so the buyer's browser hit an authenticated API URL and saw
    // "Login or API Key Required".
    $partner = delhiveryPartner([
        'tracking_url_template' => 'https://track.delhivery.com/api/v1/packages/{awb}',
    ]);

    expect($partner->trackingUrlFor('86358810000372', DeliveryPartner::DEFAULT_TRACKING_URL))
        ->toBe('https://www.delhivery.com/track/awb/86358810000372');
});

test('every Delhivery API host and path shape is recognised', function () {
    $apiUrls = [
        'https://track.delhivery.com/api/v1/packages/{awb}',
        'https://staging-express.delhivery.com/api/v1/packages/{awb}',
        'https://ucp-service-cli.delhivery.com/api/packages/{awb}',
        'https://ucp-abc123.delhivery.com/v1/packages/{awb}',
        'https://api.delhivery.com/v1/packages/{awb}',
        'https://example.com/api/packages/{awb}',
        'https://example.com/waybill/api/bulk/json/{awb}',
        'https://example.com/track?waybill={awb}',
        'https://example.com/api/cmu/create.json',
    ];

    foreach ($apiUrls as $url) {
        expect(DeliveryPartner::isTrackingApiEndpoint($url))->toBeTrue("expected {$url} to be treated as an API endpoint");
    }
});

test('a public customer tracking page is not mistaken for an API endpoint', function () {
    // www.delhivery.com contains "delhivery.com" but is the customer page, and
    // an operator may legitimately use another courier's public tracker.
    $publicUrls = [
        'https://www.delhivery.com/track/awb/{awb}',
        'https://track.example.com/{awb}',
        'https://www.shiprocket.in/shipment-tracking/{awb}',
        'https://example.com/track/myawb/{awb}',
    ];

    foreach ($publicUrls as $url) {
        expect(DeliveryPartner::isTrackingApiEndpoint($url))->toBeFalse("expected {$url} to be treated as a customer page");
    }
});

test('saving a courier API URL as the template is rejected with an actionable message', function () {
    $admin = trackingAdmin();
    $partner = delhiveryPartner();

    $response = actingAs($admin)->put(route('admin.delivery-partners.update', $partner), [
        'name' => $partner->name,
        'code' => $partner->code,
        'driver' => 'delhivery',
        'tracking_url_template' => 'https://track.delhivery.com/api/v1/packages/{awb}',
    ]);

    $response->assertSessionHasErrors('tracking_url_template');

    expect(session('errors')->first('tracking_url_template'))
        ->toContain('not the courier API')
        ->toContain('https://www.delhivery.com/track/awb/{awb}');

    expect($partner->fresh()->tracking_url_template)->toBeNull();
});

test('saving a public tracking page as the template is accepted', function () {
    $admin = trackingAdmin();
    $partner = delhiveryPartner();

    $response = actingAs($admin)->put(route('admin.delivery-partners.update', $partner), [
        'name' => $partner->name,
        'code' => $partner->code,
        'driver' => 'delhivery',
        'tracking_url_template' => 'https://www.delhivery.com/track/awb/{awb}',
    ]);

    $response->assertSessionHasNoErrors();

    expect($partner->fresh()->tracking_url_template)
        ->toBe('https://www.delhivery.com/track/awb/{awb}');
});
test('the repair command rewrites a stored API URL from the waybill', function () {
    $partner = delhiveryPartner();
    $shipment = trackingShipment(
        $partner,
        'WB5551234',
        'https://track.delhivery.com/api/v1/packages/WB5551234'
    );

    $this->artisan('delivery:repair-tracking-urls', ['--dry-run' => true])
        ->assertSuccessful();

    // Dry run must not write.
    expect($shipment->fresh()->tracking_url)
        ->toBe('https://track.delhivery.com/api/v1/packages/WB5551234');

    $this->artisan('delivery:repair-tracking-urls')->assertSuccessful();

    expect($shipment->fresh()->tracking_url)
        ->toBe('https://www.delhivery.com/track/awb/WB5551234');
});

test('the repair command leaves a valid custom tracking URL untouched', function () {
    $partner = delhiveryPartner();
    $shipment = trackingShipment($partner, 'WB6661234', 'https://track.example.com/WB6661234');

    $this->artisan('delivery:repair-tracking-urls')->assertSuccessful();

    expect($shipment->fresh()->tracking_url)->toBe('https://track.example.com/WB6661234');
});

test('the repair command is idempotent', function () {
    $partner = delhiveryPartner();
    $shipment = trackingShipment(
        $partner,
        'WB7779999',
        'https://track.delhivery.com/api/v1/packages/WB7779999'
    );

    $this->artisan('delivery:repair-tracking-urls')->assertSuccessful();
    $first = $shipment->fresh()->tracking_url;

    $this->artisan('delivery:repair-tracking-urls')->assertSuccessful();

    expect($first)->toBe('https://www.delhivery.com/track/awb/WB7779999')
        ->and($shipment->fresh()->tracking_url)->toBe($first);
});

test('the repair command fixes a bad row even while the partner template is still wrong', function () {
    // The partner row is the root cause, but a deploy should not have to fix the
    // template before the customer-facing links start working.
    $partner = delhiveryPartner([
        'tracking_url_template' => 'https://track.delhivery.com/api/v1/packages/{awb}',
    ]);
    $shipment = trackingShipment(
        $partner,
        'WB8887777',
        'https://track.delhivery.com/api/v1/packages/WB8887777'
    );

    $this->artisan('delivery:repair-tracking-urls')->assertSuccessful();

    expect($shipment->fresh()->tracking_url)
        ->toBe('https://www.delhivery.com/track/awb/WB8887777');
});

test('the repair command flags a partner template that points at the API', function () {
    $partner = delhiveryPartner([
        'tracking_url_template' => 'https://track.delhivery.com/api/v1/packages/{awb}',
    ]);

    $this->artisan('delivery:repair-tracking-urls')
        ->expectsOutputToContain('point at the courier API')
        ->assertSuccessful();
});

test('the admin form warns that the template must not be the courier API', function () {
    $admin = trackingAdmin();
    $partner = delhiveryPartner();

    $response = actingAs($admin)->get(route('admin.delivery-partners.edit', $partner));

    $response->assertOk();
    $response->assertSee('not the courier API', false);
});
test('a valid custom template still wins over the fallback', function () {
    // Guards the hardening from over-reaching: a legitimate partner URL that
    // merely contains "track" must keep working.
    $partner = delhiveryPartner(['tracking_url_template' => 'https://track.example.com/{awb}']);

    expect($partner->trackingUrlFor('WB123', DeliveryPartner::DEFAULT_TRACKING_URL))
        ->toBe('https://track.example.com/WB123');
});

