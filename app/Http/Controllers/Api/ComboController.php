<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ComboResource;
use App\Models\Combo;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bundle offers.
 *
 *   GET /gehna/api/v1/combos
 *   GET /gehna/api/v1/combos/{slug}
 *
 * Only combos that are switched on *and* inside their schedule window are
 * listed, matching Combo::scopeActive(); an expired bundle must not be
 * purchasable through the storefront.
 */
class ComboController extends ApiController
{
    public function index(): JsonResponse
    {
        $combos = Combo::query()
            ->active()
            ->with('products.images')
            ->withCount('products')
            ->orderByDesc('id')
            ->get();

        return $this->ok(ComboResource::collection($combos), ['total' => $combos->count()]);
    }

    public function show(string $slug): JsonResponse
    {
        $combo = Combo::query()
            ->where('slug', $slug)
            ->with('products.images')
            ->withCount('products')
            ->first();

        if (! $combo) {
            return $this->fail('Combo not found.', Response::HTTP_NOT_FOUND);
        }

        // A scheduled or switched-off combo still resolves, but reports
        // is_live = false so the client can render it as unavailable rather
        // than showing a price the customer cannot act on.
        return $this->ok(new ComboResource($combo));
    }
}
