<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The signed-in customer's wishlist.
 *
 *   GET    /gehna/api/v1/wishlist
 *   POST   /gehna/api/v1/wishlist/toggle     add, or remove if already saved
 *   DELETE /gehna/api/v1/wishlist/{productId}
 *   DELETE /gehna/api/v1/wishlist
 *
 * toggle() is the endpoint the heart icon on a product card calls. It returns
 * `in_wishlist` so the client can flip its own state from the response rather
 * than guessing, which is what stops the icon flickering out of sync with the
 * database.
 */
class WishlistController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $products = $this->productsQuery($request)->paginate($this->perPage($request, 20))->withQueryString();

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products->items())->resolve(),
            'meta' => (object) [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
                'count' => $this->countFor($request),
            ],
        ]);
    }

    /**
     * Add the product if it is not saved, remove it if it is.
     */
    public function toggle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variation_id' => ['nullable', 'integer', 'exists:product_variations,id'],
        ]);

        $product = Product::where('is_active', true)->findOrFail($data['product_id']);

        $existing = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->where('product_variation_id', $data['product_variation_id'] ?? null)
            ->first();

        if ($existing) {
            $existing->delete();
            $inWishlist = false;
            $message = 'Removed from wishlist';
        } else {
            Wishlist::create([
                'user_id' => $request->user()->id,
                'product_id' => $product->id,
                'product_variation_id' => $data['product_variation_id'] ?? null,
            ]);
            $inWishlist = true;
            $message = 'Added to wishlist';
        }

        return $this->ok([
            'product_id' => $product->id,
            'in_wishlist' => $inWishlist,
            'count' => $this->countFor($request),
        ], message: $message, status: $inWishlist ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function destroy(Request $request, int $productId): JsonResponse
    {
        Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();

        return $this->ok(
            ['product_id' => $productId, 'in_wishlist' => false, 'count' => $this->countFor($request)],
            message: 'Removed from wishlist',
        );
    }

    public function clear(Request $request): JsonResponse
    {
        Wishlist::where('user_id', $request->user()->id)->delete();

        return $this->ok(['count' => 0], message: 'Wishlist cleared');
    }

    /**
     * Wishlisted products, as full product cards.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    private function productsQuery(Request $request)
    {
        $ids = Wishlist::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->pluck('product_id');

        return Product::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->with([
                'category:id,name,slug,parent_id',
                'brand:id,name,slug,logo',
                'images',
                'variations',
            ])
            ->withCount(['reviews as reviews_count']);
    }

    /**
     * Distinct saved products â€” the badge number, which counts products rather
     * than variations.
     */
    private function countFor(Request $request): int
    {
        return (int) Wishlist::where('user_id', $request->user()->id)
            ->distinct('product_id')
            ->count('product_id');
    }
}
