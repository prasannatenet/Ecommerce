<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MetalPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public JSON API for the React storefront.
 *
 *   GET /gehna/api/v1/metal-prices          every metal
 *   GET /gehna/api/v1/metal-prices/gold     one metal
 *
 * Read only, no authentication: the figures are public spot prices, and a
 * storefront widget has to be able to fetch them before anyone logs in.
 *
 * Deliberately separate from FrontendMetalPriceController, which exists purely
 * to re-render the Blade top bar and returns pre-formatted strings.
 */
class MetalPriceController extends Controller
{
    public function __construct(
        private readonly MetalPriceService $metalPrices,
    ) {
    }

    /** Every configured metal (gold and silver). */
    public function index(Request $request): JsonResponse
    {
        return $this->respond($request, $this->metalPrices->rawPayload());
    }

    /**
     * A single metal.
     *
     * The envelope is kept identical to index() so a React component can swap
     * between the two endpoints without special-casing the response shape.
     */
    public function show(Request $request, string $metal): JsonResponse
    {
        $payload = $this->metalPrices->rawPayload();
        $wanted = strtolower(trim($metal));
        $match = null;

        foreach ($payload['metals'] as $row) {
            if ($row['key'] === $wanted) {
                $match = $row;
                break;
            }
        }

        if ($match === null) {
            $known = array_column($payload['metals'], 'key');

            return $this->respond($request, [
                'available' => false,
                'message' => sprintf(
                    'Unknown metal [%s]. Supported: %s.',
                    $wanted,
                    $known === [] ? 'none' : implode(', ', $known)
                ),
                'metals' => [],
            ], status: Response::HTTP_NOT_FOUND);
        }

        $payload['metals'] = [$match];

        return $this->respond($request, $payload, ['metal' => $match['key']]);
    }

    /**
     * Wrap the payload in the envelope every endpoint shares.
     *
     * `success` mirrors availability so a client can branch on one field, and
     * the numbers are cached (see METAL_PRICE_CACHE_TTL) so a busy widget can
     * poll far more often than the provider is actually called.
     */
    private function respond(Request $request, array $data, array $meta = [], int $status = Response::HTTP_OK): JsonResponse
    {
        $available = (bool) ($data['available'] ?? false);
        $ttl = max(0, (int) config('metalprice.cache_ttl'));

        return response()->json([
            'success' => $available,
            'data' => $data,
            'meta' => array_merge([
                'source' => 'goldapi.io',
                // How long a returned figure stays fresh. Polling faster than
                // this is harmless but will not show a new number sooner.
                'cache_ttl' => $ttl,
                'generated_at' => now()->toIso8601String(),
            ], $meta),
        ], $status)->header('Cache-Control', $available
            ? "public, max-age={$ttl}, stale-while-revalidate=60"
            : 'no-store');
    }
}
