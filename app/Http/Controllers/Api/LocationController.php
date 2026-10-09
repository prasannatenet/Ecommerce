<?php

namespace App\Http\Controllers\Api;

use App\Services\LocationGeocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LocationController extends ApiController
{
    public function detect(Request $request, LocationGeocoder $geocoder): JsonResponse
    {
        $coordinates = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $place = $geocoder->reverse(
            (float) $coordinates['latitude'],
            (float) $coordinates['longitude'],
        );

        if ($place['pincode'] === null) {
            return $this->fail(
                'We could not detect a pincode for your location. Please enter it manually.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $this->ok(
            $place + ['source' => 'gps'],
            message: 'Location detected successfully.',
        );
    }

    public function detectByIp(Request $request, LocationGeocoder $geocoder): JsonResponse
    {
        $place = $geocoder->byIp($request->ip());

        if ($place['pincode'] === null) {
            return $this->fail(
                'We could not estimate a pincode from your network. Please allow location access or enter it manually.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $this->ok(
            $place + ['source' => 'ip'],
            message: 'Approximate location detected successfully.',
        );
    }
}
