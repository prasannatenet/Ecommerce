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
    /**
     * Relations every combo query needs.
     *
     * `products.variations` is not optional. Combo::productsTotal() prices a
     * variable product from its cheapest variation, so the bundle price was
     * already derived from variation data - but only `products.images` was
     * eager loaded, which meant ProductResource emitted an empty `variations`
     * array and a null `price_range` for every variable product, and
     * getTotalStockAttribute() fired an extra SUM query per product. The API
     * quoted a bundle price built from a number it never showed the client.
     */
    private const COMBO_RELATIONS = ['products.images', 'products.variations'];

    /**
     * Count the products that can actually be sold, so products_count agrees
     * with the products_total and combo_price quoted beside it. Counting the
     * raw pivot rows would advertise a product the customer cannot buy.
     */
    private const COMBO_COUNT = ['sellableProducts as products_count'];

    public function index(): JsonResponse
    {
        $combos = Combo::query()
            ->active()
            ->with(self::COMBO_RELATIONS)
            ->withCount(self::COMBO_COUNT)
            ->orderByDesc('id')
            ->get();

        return $this->ok(ComboResource::collection($combos), ['total' => $combos->count()]);
    }

    public function show(string $slug): JsonResponse
    {
        $combo = Combo::query()
            ->where('slug', $slug)
            ->with(self::COMBO_RELATIONS)
            ->withCount(self::COMBO_COUNT)
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
