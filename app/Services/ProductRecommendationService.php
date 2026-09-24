<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductRecommendationService
{
    private const CACHE_TTL_MINUTES = 30;

    public function forProduct(Product $product, int $limit = 6): Collection
    {
        $limit = max(1, min($limit, 12));
        $ranked = Cache::remember(
            $this->cacheKey($product->id),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn (): array => $this->rankedProductIds($product),
        );

        $ids = collect($ranked)->pluck('product_id')->map(fn ($id) => (int) $id);
        if ($ids->isEmpty()) {
            return collect();
        }

        $products = Product::query()
            ->with(['brand', 'category', 'images', 'variations'])
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return $ids
            ->take($limit)
            ->map(fn (int $id) => $products->get($id))
            ->filter()
            ->values();
    }

    public function forgetForOrder(Order $order): void
    {
        $order->items()
            ->whereNotNull('product_id')
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->each(fn (int $id) => Cache::forget($this->cacheKey($id)));
    }

    private function rankedProductIds(Product $product): array
    {
        $sourceOrderIds = $this->validOrdersQuery()
            ->whereHas('items', fn (Builder $query) => $query->where('product_id', $product->id))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($sourceOrderIds->isEmpty()) {
            return [];
        }

        $sourceOrderCount = max(1, $sourceOrderIds->count());
        $rows = DB::table('order_items')
            ->select('product_id')
            ->selectRaw('COUNT(DISTINCT order_id) as pair_orders')
            ->whereIn('order_id', $sourceOrderIds)
            ->whereNotNull('product_id')
            ->where('product_id', '!=', $product->id)
            ->groupBy('product_id')
            ->orderByDesc('pair_orders')
            ->limit(20)
            ->get()
            ->map(fn ($row): array => [
                'product_id' => (int) $row->product_id,
                'pair_orders' => (int) $row->pair_orders,
                'confidence' => (int) $row->pair_orders / $sourceOrderCount,
            ])
            ->sort(function (array $left, array $right): int {
                return [$right['pair_orders'], $right['confidence']]
                    <=> [$left['pair_orders'], $left['confidence']];
            })
            ->values()
            ->all();

        return $rows;
    }

    private function validOrdersQuery(): Builder
    {
        return Order::query()
            ->where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled', 'failed', 'refunded'])
            ->where(function (Builder $query): void {
                $query->whereNull('refund_status')
                    ->orWhere('refund_status', '!=', 'full');
            });
    }

    private function cacheKey(int $productId): string
    {
        return 'product-recommendations:v1:'.$productId;
    }
}
