<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SiteLocationController extends Controller
{
    /**
     * Store the user's delivery pincode (entered manually or detected via GPS).
     * The value lives in the session so it works for both guests and logged-in users.
     */

    public function show(Request $request): JsonResponse
    {
        return response()->json(
            $this->locationPayload(
                session('delivery_pincode'),
                session('delivery_city'),
                session('delivery_state'),
                source: session('delivery_pincode_source'),
            ) + ['success' => true]
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pincode' => ['required', 'digits:6', 'regex:/^[1-9][0-9]{5}$/'],
            'city'    => ['nullable', 'string', 'max:120'],
            'state'   => ['nullable', 'string', 'max:120'],
            'source'  => ['nullable', 'in:manual,auto'],
        ]);

        session([
            'delivery_pincode'        => $data['pincode'],
            'delivery_city'           => $data['city'] ?? null,
            'delivery_state'          => $data['state'] ?? null,
            'delivery_pincode_source' => $data['source'] ?? 'manual',
        ]);

        return response()->json(
            $this->locationPayload(
                $data['pincode'],
                $data['city'] ?? null,
                $data['state'] ?? null,
                "Delivery pincode updated to {$data['pincode']}.",
                $data['source'] ?? 'manual',
            ) + ['success' => true]
        );
    }

    public function clear(Request $request): JsonResponse
    {
        $request->session()->forget([
            'delivery_pincode',
            'delivery_city',
            'delivery_state',
            'delivery_pincode_source',
        ]);

        return response()->json(
            $this->locationPayload(null, null, null, 'Delivery pincode removed.') + ['success' => true]
        );
    }

    /**
     * Auto-detect the pincode from the browser's GPS coordinates.
     * Frontend sends latitude/longitude obtained via navigator.geolocation.
     */
    public function detect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $place = $this->reverseGeocode((float) $data['latitude'], (float) $data['longitude']);

        if (empty($place['pincode'])) {
            return response()->json([
                'success' => false,
                'message' => 'We could not detect a pincode for your location. Please enter it manually.',
            ], 422);
        }

        return response()->json(
            $this->locationPayload(
                $place['pincode'],
                $place['city'],
                $place['state'],
                'Location detected successfully.',
                'auto',
            ) + ['success' => true]
        );
    }

    /**
     * Look up the pincode for coordinates. Tries BigDataCloud's free endpoint
     * first (no API key required) and falls back to OpenStreetMap Nominatim.
     */
    private function reverseGeocode(float $latitude, float $longitude): array
    {
        $place = ['pincode' => null, 'city' => null, 'state' => null];

        try {
            $response = Http::timeout(8)
                ->connectTimeout(5)
                ->get('https://api.bigdatacloud.net/data/reverse-geocode-client', [
                    'latitude'         => $latitude,
                    'longitude'        => $longitude,
                    'localityLanguage' => 'en',
                ]);

            if ($response->successful()) {
                $body = $response->json();

                $place['pincode'] = $body['postcode'] ?? null;
                $place['city']    = $body['city'] ?? $body['locality'] ?? $body['principalSubdivision'] ?? null;
                $place['state']   = $body['principalSubdivision'] ?? null;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        if (empty($place['pincode'])) {
            try {
                $response = Http::timeout(8)
                    ->connectTimeout(5)
                    ->withHeaders(['User-Agent' => 'GEHNA-Ecommerce/1.0 (delivery-pincode-lookup)'])
                    ->get('https://nominatim.openstreetmap.org/reverse', [
                        'format'         => 'jsonv2',
                        'lat'            => $latitude,
                        'lon'            => $longitude,
                        'addressdetails' => 1,
                        'zoom'           => 18,
                    ]);

                if ($response->successful()) {
                    $address = $response->json('address', []);

                    $place['pincode'] = $address['postcode'] ?? null;
                    $place['city']    = $address['city']
                        ?? $address['town']
                        ?? $address['village']
                        ?? $address['suburb']
                        ?? $address['county']
                        ?? null;
                    $place['state'] = $address['state'] ?? null;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Normalise pincodes such as "110001-2706" or "PIN 110001" down to 6 digits.
        if (! empty($place['pincode'])) {
            $place['pincode'] = preg_match('/\b([1-9][0-9]{5})\b/', (string) $place['pincode'], $m)
                ? $m[1]
                : null;
        }

        return $place;
    }

    /**
     * Common payload the navbar JS uses to update the button label.
     */
    private function locationPayload(
        ?string $pincode,
        ?string $city,
        ?string $state,
        string $message = '',
        string $source = 'manual',
    ): array {
        $area = trim((string) collect([$city, $state])->filter()->unique()->implode(', '));

        return [
            'pincode'  => $pincode,
            'city'     => $city,
            'state'    => $state,
            'source'   => $source,
            'title'    => $pincode ? "Deliver to {$pincode}" : 'Where to Deliver?',
            'subtitle' => $pincode && $area !== '' ? $area : 'Update Delivery Pincode',
            'message'  => $message,
        ];
    }
}
