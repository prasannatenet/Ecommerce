<?php

use Illuminate\Support\Facades\Http;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('stores, shows and clears the delivery pincode in the session', function () {
    $response = $this->postJson(route('location.store'), [
        'pincode' => '110001',
        'city'    => 'New Delhi',
        'state'   => 'Delhi',
        'source'  => 'manual',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('pincode', '110001')
        ->assertJsonPath('title', 'Deliver to 110001')
        ->assertJsonPath('subtitle', 'New Delhi, Delhi');

    expect(session('delivery_pincode'))->toBe('110001');

    $this->getJson(route('location.show'))
        ->assertOk()
        ->assertJsonPath('pincode', '110001')
        ->assertJsonPath('city', 'New Delhi');

    $this->postJson(route('location.clear'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('pincode', null);

    expect(session('delivery_pincode'))->toBeNull();
});

it('rejects invalid pincodes', function (string $pincode) {
    $this->postJson(route('location.store'), ['pincode' => $pincode])
        ->assertStatus(422)
        ->assertJsonValidationErrors('pincode');
})->with(['12', '011001', '1100012', 'abcdef', '11000-']);

it('auto-detects the pincode from coordinates and returns a confirmable payload', function () {
    Http::fake([
        'api.bigdatacloud.net/*' => Http::response([
            'postcode'              => '302001',
            'city'                  => 'Jaipur',
            'principalSubdivision'  => 'Rajasthan',
        ]),
    ]);

    $response = $this->postJson(route('location.detect'), [
        'latitude'  => 26.9124,
        'longitude' => 75.7873,
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('pincode', '302001')
        ->assertJsonPath('city', 'Jaipur')
        ->assertJsonPath('state', 'Rajasthan')
        ->assertJsonPath('source', 'auto');

    // Detection alone must NOT persist anything until the user confirms.
    expect(session('delivery_pincode'))->toBeNull();

    // Confirming stores it like a manual entry, tagged as auto.
    $this->postJson(route('location.store'), [
        'pincode' => '302001',
        'city'    => 'Jaipur',
        'state'   => 'Rajasthan',
        'source'  => 'auto',
    ])->assertOk()->assertJsonPath('source', 'auto');

    expect(session('delivery_pincode'))->toBe('302001')
        ->and(session('delivery_pincode_source'))->toBe('auto');
});

it('falls back to the second geocoder when the first one has no pincode', function () {
    Http::fake([
        'api.bigdatacloud.net/*' => Http::response(['city' => 'Somewhere']),
        'nominatim.openstreetmap.org/*' => Http::response([
            'address' => [
                'postcode' => '110001-2706', // messy value must be normalised
                'city'     => 'Delhi',
                'state'    => 'Delhi',
            ],
        ]),
    ]);

    $this->postJson(route('location.detect'), [
        'latitude'  => 28.6139,
        'longitude' => 77.2090,
    ])->assertOk()
        ->assertJsonPath('pincode', '110001')
        ->assertJsonPath('city', 'Delhi');
});

it('responds with a helpful error when no provider can resolve a pincode', function () {
    Http::fake([
        'api.bigdatacloud.net/*' => Http::response(['city' => 'Nowhere Known']),
        'nominatim.openstreetmap.org/*' => Http::response(['address' => []]),
    ]);

    $this->postJson(route('location.detect'), [
        'latitude'  => 0.0,
        'longitude' => 0.0,
    ])->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'We could not detect a pincode for your location. Please enter it manually.');
});

it('rejects invalid coordinates', function () {
    $this->postJson(route('location.detect'), [
        'latitude'  => 123.0,
        'longitude' => 456.0,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['latitude', 'longitude']);
});
