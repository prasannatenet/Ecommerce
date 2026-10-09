<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\BuildsProductQuery;
use App\Http\Resources\ComboResource;
use App\Http\Resources\ProductResource;
use App\Models\Combo;
use App\Models\Coupon;
use App\Models\Product;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OffersController extends ApiController
{
    use BuildsProductQuery;

    private const COMBO_RELATIONS = ['products.images', 'products.variations'];

    private const COMBO_COUNT = ['sellableProducts as products_count'];

    public function coupons(Request $request, CouponService $couponService): JsonResponse
    {
        $perPage = $this->perPage($request);
        $page = max(1, $request->integer('page', 1));
        $now = now();

        $query = Coupon::query()
            ->where('is_active', true)
            ->where(fn ($builder) => $builder->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->orderByDesc('id');
        $total = (clone $query)->count();
        $userId = Auth::guard('sanctum')->user()?->id;
        $coupons = $couponService->offerSummaries($perPage, ($page - 1) * $perPage, $userId);

        return $this->ok($coupons, [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ]);
    }

    public function index(Request $request, CouponService $couponService): JsonResponse
    {
        $perPage = $this->perPage($request);
        $page = max(1, $request->integer('page', 1));
        $offset = ($page - 1) * $perPage;
        $now = now();

        $couponQuery = Coupon::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->orderByDesc('id');
        $couponTotal = (clone $couponQuery)->count();
        $userId = Auth::guard('sanctum')->user()?->id;
        $coupons = $couponService->offerSummaries($perPage, $offset, $userId)->values();

        $salesQuery = $this->withCardRelations(
            Product::query()
                ->where('is_active', true)
                ->whereNotNull('sale_price')
                ->whereColumn('sale_price', '<', 'base_price')
        )->withCount(['reviews as reviews_count']);
        $sales = $salesQuery->latest()->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);

        $combos = Combo::query()
            ->active()
            ->with(self::COMBO_RELATIONS)
            ->withCount(self::COMBO_COUNT)
            ->orderByDesc('id')
            ->get();

        return $this->ok([
            'coupons' => $coupons,
            'combos' => ComboResource::collection($combos)->resolve(),
            'sale_products' => ProductResource::collection($sales->items())->resolve(),
        ], [
            'coupons' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $couponTotal,
                'last_page' => max(1, (int) ceil($couponTotal / $perPage)),
            ],
            'combos' => ['total' => $combos->count()],
            'sale_products' => [
                'current_page' => $sales->currentPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
                'last_page' => $sales->lastPage(),
            ],
        ]);
    }
}
