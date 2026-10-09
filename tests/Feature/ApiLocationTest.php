<?php

use Illuminate\Support\Facades\Http;

it('detects a delivery pincode from GPS coordinates without requiring authentication', function () {
    config()->set('services.bigdatacloud.key', 'server-side-test-key');
    Http::fake([
        'api.bigdatacloud.net/data/reverse-geocode*' => Http::response([
            'postcode' => '302001',
            'city' => 'Jaipur',
            'principalSubdivision' => 'Rajasthan',
        ]),
    ]);

    $this->postJson('/api/v1/location/detect', [
        'latitude' => 26.9124,
        'longitude' => 75.7873,
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.pincode', '302001')
        ->assertJsonPath('data.city', 'Jaipur')
        ->assertJsonPath('data.state', 'Rajasthan')
        ->assertJsonPath('data.source', 'gps')
        ->assertJsonMissing(['key' => 'server-side-test-key']);

    Http::assertSent(function ($request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_starts_with($request->url(), 'https://api.bigdatacloud.net/data/reverse-geocode?')
            && $query['latitude'] === '26.9124'
            && $query['longitude'] === '75.7873'
            && $query['key'] === 'server-side-test-key';
    });
});

it('returns validation errors for invalid GPS coordinates', function () {
    $this->postJson('/api/v1/location/detect', [
        'latitude' => 91,
        'longitude' => -181,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['latitude', 'longitude']);
});

it('detects an approximate pincode from the request IP when configured', function () {
    config()->set('services.bigdatacloud.key', 'server-side-test-key');
    Http::fake([
        'api.bigdatacloud.net/data/ip-geolocation*' => Http::response([
            'location' => [
                'postcode' => '110001',
                'city' => 'New Delhi',
                'principalSubdivision' => 'Delhi',
            ],
        ]),
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->postJson('/api/v1/location/detect-by-ip')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.pincode', '110001')
        ->assertJsonPath('data.city', 'New Delhi')
        ->assertJsonPath('data.state', 'Delhi')
        ->assertJsonPath('data.source', 'ip');

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.bigdatacloud.net/data/ip-geolocation?')
        && $request['ip'] !== null
        && $request['key'] === 'server-side-test-key');
});
