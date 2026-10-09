<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LocationGeocoder
{
    /**
     * Convert browser GPS coordinates to an Indian-style six-digit pincode.
     *
     * @return array{pincode: ?string, city: ?string, state: ?string}
     */
    public function reverse(float $latitude, float $longitude): array
    {
        $place = ['pincode' => null, 'city' => null, 'state' => null];
        $key = (string) config('services.bigdatacloud.key', '');

        if ($key !== '') {
            try {
                $response = Http::timeout(8)
                    ->connectTimeout(5)
                    ->get('https://api.bigdatacloud.net/data/reverse-geocode', [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'localityLanguage' => 'en',
                        'key' => $key,
                    ]);

                if ($response->successful()) {
                    $body = $response->json();
                    $place = $this->fromReverseGeocode($body);
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        if ($place['pincode'] === null) {
            try {
                $response = Http::timeout(8)
                    ->connectTimeout(5)
                    ->get('https://api.bigdatacloud.net/data/reverse-geocode-client', [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'localityLanguage' => 'en',
                    ]);

                if ($response->successful()) {
                    $place = $this->fromReverseGeocode($response->json());
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        if ($place['pincode'] === null) {
            try {
                $response = Http::timeout(8)
                    ->connectTimeout(5)
                    ->withHeaders(['User-Agent' => 'GEHNA-Ecommerce/1.0 (delivery-pincode-lookup)'])
                    ->get('https://nominatim.openstreetmap.org/reverse', [
                        'format' => 'jsonv2',
                        'lat' => $latitude,
                        'lon' => $longitude,
                        'addressdetails' => 1,
                        'zoom' => 18,
                    ]);

                if ($response->successful()) {
                    $address = $response->json('address', []);
                    $place = $this->normalise([
                        'pincode' => $address['postcode'] ?? null,
                        'city' => $address['city']
                            ?? $address['town']
                            ?? $address['village']
                            ?? $address['suburb']
                            ?? $address['county']
                            ?? null,
                        'state' => $address['state'] ?? null,
                    ]);
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $place;
    }

    /**
     * Resolve the public IP address to its approximate postcode.
     *
     * @return array{pincode: ?string, city: ?string, state: ?string}
     */
    public function byIp(string $ipAddress): array
    {
        $key = (string) config('services.bigdatacloud.key', '');

        if ($key === '') {
            return ['pincode' => null, 'city' => null, 'state' => null];
        }

        try {
            $response = Http::timeout(8)
                ->connectTimeout(5)
                ->get('https://api.bigdatacloud.net/data/ip-geolocation', [
                    'ip' => $ipAddress,
                    'key' => $key,
                ]);

            if (! $response->successful()) {
                return ['pincode' => null, 'city' => null, 'state' => null];
            }

            $location = $response->json('location', []);

            return $this->normalise([
                'pincode' => $location['postcode'] ?? $location['postalCode'] ?? null,
                'city' => $location['city'] ?? $location['locality'] ?? null,
                'state' => $location['principalSubdivision'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return ['pincode' => null, 'city' => null, 'state' => null];
        }
    }

    private function fromReverseGeocode(array $body): array
    {
        return $this->normalise([
            'pincode' => $body['postcode'] ?? $body['postalCode'] ?? null,
            'city' => $body['city'] ?? $body['locality'] ?? $body['principalSubdivision'] ?? null,
            'state' => $body['principalSubdivision'] ?? null,
        ]);
    }

    /**
     * @param  array{pincode: mixed, city: mixed, state: mixed}  $place
     * @return array{pincode: ?string, city: ?string, state: ?string}
     */
    private function normalise(array $place): array
    {
        $rawPincode = trim((string) ($place['pincode'] ?? ''));
        $pincode = preg_match('/\b([1-9][0-9]{5})\b/', $rawPincode, $matches)
            ? $matches[1]
            : null;

        return [
            'pincode' => $pincode,
            'city' => filled($place['city'] ?? null) ? (string) $place['city'] : null,
            'state' => filled($place['state'] ?? null) ? (string) $place['state'] : null,
        ];
    }
}
