<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\BuildsProductQuery;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Category catalogue for the React storefront.
 *
 *   GET /gehna/api/v1/categories         flat list (?parent_id, ?tree=1)
 *   GET /gehna/api/v1/categories/tree    nested, for mega-menu components
 *   GET /gehna/api/v1/categories/{slug}  one category
 *   GET /gehna/api/v1/categories/{slug}/products  paginated products in it
 *
 * Inactive products are never returned: a customer listing a category must not
 * see a draft. `withCount` is scoped to active products for the same reason,
 * so the badge on a category card matches what the page will actually show.
 */
class CategoryController extends ApiController
{
    use BuildsProductQuery;

    /**
     * Flat category list, or the full nested tree with ?tree=1.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::query()->withCount([
            'products' => fn ($q) => $q->where('is_active', true),
        ]);

        // `parent_id=root` is the natural "top level only" filter; a bare
        // ?parent_id= with no value means "no parent".
        if ($request->has('parent_id')) {
            $parent = $request->input('parent_id');
            $query->where('parent_id', $parent === 'root' || $parent === 'null' ? null : $parent);
        }

        if ($request->boolean('tree')) {
            $tree = Category::query()
                ->whereNull('parent_id')
                ->with(['children' => fn ($q) => $q->withCount([
                    'products' => fn ($p) => $p->where('is_active', true),
                ])])
                ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('position')
                ->orderBy('name')
                ->get();

            return $this->ok(
                CategoryResource::collection($tree),
                ['total' => $tree->count()],
            );
        }

        $categories = $query
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return $this->ok(
            CategoryResource::collection($categories),
            ['total' => $categories->count()],
        );
    }

    /**
     * A single category, addressed by slug (never by id â€” slugs are the
     * stable public identifier the storefront already links to).
     */
    public function show(string $slug): JsonResponse
    {
        $category = Category::query()
            ->with(['parent', 'children'])
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->where('slug', $slug)
            ->first();

        if (! $category) {
            return $this->fail('Category not found.', Response::HTTP_NOT_FOUND);
        }

        return $this->ok(new CategoryResource($category));
    }

    /**
     * Paginated, filterable products belonging to a category.
     *
     * Accepts the same `?sort=` / `?brand=` / `?min_price=` filters as the
     * global product listing, and additionally matches child categories so
     * "Necklaces" also lists everything filed under "Pendant Necklaces".
     */
    public function products(Request $request, string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)->first();

        if (! $category) {
            return $this->fail('Category not found.', Response::HTTP_NOT_FOUND);
        }

        $categoryIds = $category->childrenRecursive()->pluck('id')->push($category->id)->unique()->values();

        $query = $this->withCardRelations(
            Product::query()
                ->where('is_active', true)
                ->whereIn('category_id', $categoryIds)
        )->withCount(['reviews as reviews_count']);

        $this->applyFilters($query, $request);
        $this->applySort($query, $request);

        $products = $query->paginate($this->perPage($request))->withQueryString();

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products->items())->resolve(),
            'category' => (new CategoryResource($category))->resolve(),
            'meta' => (object) [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
                // The category ids this response actually covered, so the
                // client can offer sub-category chips without a second call.
                'included' => $categoryIds->all(),
            ],
        ]);
    }
}
