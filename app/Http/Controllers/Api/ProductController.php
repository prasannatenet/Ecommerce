<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\BuildsProductQuery;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use App\Services\ProductRecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Product catalogue for the React storefront.
 *
 *   GET /gehna/api/v1/products              paginated, filtered product grid
 *   GET /gehna/api/v1/products/{slug}       full detail (variations, media)
 *   GET /gehna/api/v1/products/{slug}/related
 *   GET /gehna/api/v1/products/{slug}/reviews
 *
 * Only `is_active` products are ever exposed; drafts and archived items 404
 * rather than returning a forbidden error, so the existence of an unpublished
 * product is not discoverable through the API.
 */
class ProductController extends ApiController
{
    use BuildsProductQuery;

    public function __construct(
        private readonly ProductRecommendationService $recommendations,
    ) {
    }

    /**
     * The main product grid, shared by the shop page, search and the
     * "all products" home section.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->withCardRelations(
            Product::query()->where('is_active', true)
        )->withCount(['reviews as reviews_count']);

        $this->applyFilters($query, $request);
        $this->applySort($query, $request);

        $products = $query->paginate($this->perPage($request))->withQueryString();

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products->items())->resolve(),
            'meta' => (object) [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
                'sort' => $request->input('sort', 'latest'),
                'filters' => $request->only([
                    'q', 'category_id', 'brand_id', 'audience',
                    'material', 'min_price', 'max_price', 'in_stock', 'on_sale',
                ]),
            ],
        ]);
    }

    /**
     * Product detail.
     *
     * Loads the full relation set â€” variations, all images, videos, attributes
     * and the newest reviews â€” in one request so the React product page never
     * has to fan out into follow-up calls before it can render.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with([
                'category',
                'brand',
                'images',
                'videos',
                'attributes',
                'variations.images',
            ])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->first();

        if (! $product) {
            return $this->fail('Product not found.', Response::HTTP_NOT_FOUND);
        }

        // `withAvg` stores the figure under <relation>_<column>; mirror it onto
        // the name ProductResource reads so the rating rides along on the
        // product object without a second query.
        $product->setAttribute('rating_average', $product->reviews_avg_rating);

        $reviews = $product->reviews()
            ->with(['user:id,name', 'images'])
            ->latest()
            ->paginate($this->perPage($request, 5));

        return $this->ok([
            'product' => (new ProductResource($product))->resolve(),
            'reviews' => [
                'summary' => [
                    'average' => round((float) $product->reviews_avg_rating, 2),
                    'count' => (int) $product->reviews_count,
                ],
                'data' => ReviewResource::collection($reviews->items())->resolve(),
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * "You may also like" rail.
     *
     * The recommender ranks by "customers who bought this also bought", which
     * returns nothing on a young catalogue. Falling back to the rest of the
     * same category keeps the rail populated instead of rendering an empty box.
     */
    public function related(Request $request, string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->where('is_active', true)->first();

        if (! $product) {
            return $this->fail('Product not found.', Response::HTTP_NOT_FOUND);
        }

        $limit = $this->perPage($request, 8);
        $related = $this->recommendations->forProduct($product, $limit);

        if ($related->isEmpty() && $product->category_id) {
            $related = $this->withCardRelations(
                Product::query()
                    ->where('is_active', true)
                    ->where('category_id', $product->category_id)
                    ->whereKeyNot($product->id)
            )
                ->latest()
                ->limit($limit)
                ->get();
        }

        return $this->ok(
            ProductResource::collection($related),
            ['total' => $related->count()],
        );
    }

    /**
     * Paginated reviews for a product, newest first.
     */
    public function reviews(Request $request, string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->where('is_active', true)->first();

        if (! $product) {
            return $this->fail('Product not found.', Response::HTTP_NOT_FOUND);
        }

        $reviews = $product->reviews()
            ->with(['user:id,name', 'images'])
            ->when($request->integer('rating') ?: null, fn ($q, $rating) => $q->where('rating', $rating))
            ->latest()
            ->paginate($this->perPage($request, 10))
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => ReviewResource::collection($reviews->items())->resolve(),
            'meta' => (object) [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'average' => round((float) $product->reviews()->avg('rating'), 2),
            ],
        ]);
    }
}
