<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\BuildsProductQuery;
use App\Http\Resources\BrandResource;
use App\Http\Resources\ProductResource;
use App\Models\Brand;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Brand catalogue and per-brand product grids.
 *
 *   GET /gehna/api/v1/brands
 *   GET /gehna/api/v1/brands/{slug}
 *   GET /gehna/api/v1/brands/{slug}/products
 */
class BrandController extends ApiController
{
    use BuildsProductQuery;

    public function index(): JsonResponse
    {
        $brands = Brand::query()
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        return $this->ok(BrandResource::collection($brands), ['total' => $brands->count()]);
    }

    public function show(string $slug): JsonResponse
    {
        $brand = Brand::withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->where('slug', $slug)
            ->first();

        if (! $brand) {
            return $this->fail('Brand not found.', Response::HTTP_NOT_FOUND);
        }

        return $this->ok(new BrandResource($brand));
    }

    public function products(Request $request, string $slug): JsonResponse
    {
        $brand = Brand::where('slug', $slug)->first();

        if (! $brand) {
            return $this->fail('Brand not found.', Response::HTTP_NOT_FOUND);
        }

        $query = $this->withCardRelations(
            Product::query()->where('is_active', true)->where('brand_id', $brand->id)
        )->withCount(['reviews as reviews_count']);

        $this->applyFilters($query, $request);
        $this->applySort($query, $request);

        $products = $query->paginate($this->perPage($request))->withQueryString();

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products->items())->resolve(),
            'brand' => (new BrandResource($brand))->resolve(),
            'meta' => (object) [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }
}
