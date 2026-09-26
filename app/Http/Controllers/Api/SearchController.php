<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\BuildsProductQuery;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\SearchLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Site-wide search.
 *
 *   GET /gehna/api/v1/search?q=ring
 *
 * The parameter set is the product listing's, so the React search page can
 * reuse the same filter panel and the same ProductResource as /products.
 * Searches are logged so the storefront can show a signed-in customer their
 * recent queries; that is why this endpoint accepts an optional token and
 * never requires one.
 */
class SearchController extends ApiController
{
    use BuildsProductQuery;

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:120'],
        ]);

        $term = trim((string) $request->string('q'));

        $this->log($request, $term);

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
                'query' => $term,
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'categories' => CategoryResource::collection(
                    Category::query()
                        // whereHas rather than having('products_count', '>', 0):
                        // a HAVING with no GROUP BY is a MySQL extension and is
                        // rejected outright by SQLite and PostgreSQL, so the
                        // search endpoint would 500 there. The relation filter
                        // says the same thing portably, and the count is then
                        // only used for ordering.
                        ->whereHas('products', function ($q) use ($term) {
                            $q->where('is_active', true)->where('name', 'like', '%'.$term.'%');
                        })
                        ->withCount([
                            'products' => function ($q) use ($term) {
                                $q->where('is_active', true)->where('name', 'like', '%'.$term.'%');
                            },
                        ])
                        ->orderByDesc('products_count')
                        ->limit(8)
                        ->get()
                )->resolve(),
                'recent' => SearchLog::recentForUser($request->user()?->id),
            ],
        ]);
    }

    /**
     * Record the search for this user, ignoring duplicates.
     *
     * Anonymous searches are skipped: there is no user to attribute them to,
     * and the searches table is keyed on user_id for the recent-searches list.
     */
    private function log(Request $request, string $term): void
    {
        $userId = $request->user()?->id;

        if (! $userId) {
            return;
        }

        $already = SearchLog::where('user_id', $userId)
            ->where('searchable', $term)
            ->where('searched_at', '>=', now()->subDay())
            ->exists();

        if ($already) {
            return;
        }

        SearchLog::create([
            'user_id' => $userId,
            'searchable' => $term,
            'searched_at' => now(),
        ]);
    }
}
