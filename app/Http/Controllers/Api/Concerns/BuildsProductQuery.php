<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared filtering and sorting for every endpoint that lists products.
 *
 * The React storefront has three product grids — /products, /search and
 * /categories/{slug}/products — and they must accept the exact same query
 * string, otherwise the same "Filter" panel would have to be re-implemented
 * three times in JavaScript and would drift out of sync with the server.
 *
 * Supported parameters (all optional):
 *   ?q=            free-text over name, SKU and description
 *   ?category_id=  one id, or a comma separated list
 *   ?brand_id=     one id, or a comma separated list
 *   ?audience=     women | men | unisex
 *   ?material=     gold | silver | diamond | platinum | other
 *   ?min_price=    effective price floor
 *   ?max_price=    effective price ceiling
 *   ?in_stock=1    only items that can actually be bought
 *   ?on_sale=1     only discounted items
 *   ?sort=         see applySort()
 */
trait BuildsProductQuery
{
    /**
     * Apply the storefront's filter parameters to a product query.
     *
     * Price filtering is deliberately done in PHP-side SQL over the products
     * table's own price columns rather than through variations: a product is
     * filtered out of the grid when *no* variation falls in the range, which
     * is what a shopper expects from a price slider.
     */
    protected function applyFilters(Builder $query, \Illuminate\Http\Request $request): Builder
    {
        $query
            ->when($request->filled('q'), function (Builder $q) use ($request) {
                $term = trim((string) $request->input('q'));

                $q->where(function (Builder $inner) use ($term) {
                    $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

                    $inner->where('name', 'like', $like)
                        ->orWhere('sku', 'like', $like)
                        ->orWhere('short_description', 'like', $like)
                        ->orWhere('description', 'like', $like);
                });
            })
            ->when($request->filled('category_id'), fn (Builder $q) => $q->whereIn('category_id', $this->idList($request->input('category_id'))))
            ->when($request->filled('brand_id'), fn (Builder $q) => $q->whereIn('brand_id', $this->idList($request->input('brand_id'))))
            ->when($request->filled('audience'), fn (Builder $q) => $q->where('audience', $request->input('audience')))
            ->when($request->filled('material'), fn (Builder $q) => $q->where('material_type', $request->input('material')))
            ->when($request->filled('min_price'), fn (Builder $q) => $q->where('base_price', '>=', (float) $request->input('min_price')))
            ->when($request->filled('max_price'), fn (Builder $q) => $q->where('base_price', '<=', (float) $request->input('max_price')))
            ->when($request->boolean('in_stock'), fn (Builder $q) => $q->where(function (Builder $inner) {
                $inner->where('manage_stock', false)->orWhere('stock', '>', 0);
            }))
            ->when($request->boolean('on_sale'), fn (Builder $q) => $q->whereNotNull('sale_price')->whereColumn('sale_price', '<', 'base_price'));

        return $query;
    }

    /**
     * Apply `?sort=`, defaulting to newest first.
     *
     * Unknown values fall back to the default rather than raising a 422, so a
     * stale bookmarked URL with an old sort value still renders.
     */
    protected function applySort(Builder $query, \Illuminate\Http\Request $request): Builder
    {
        return match ($request->input('sort', 'latest')) {
            'price_asc' => $query->orderBy('base_price')->orderBy('name'),
            'price_desc' => $query->orderByDesc('base_price')->orderBy('name'),
            'name' => $query->orderBy('name'),
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            default => $query->latest()->orderByDesc('id'),
        };
    }

    /**
     * Normalise a comma separated id list into an array of integers.
     *
     * @return array<int, int>
     */
    protected function idList(mixed $value): array
    {
        if (is_array($value)) {
            $value = implode(',', $value);
        }

        return array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $value)
        )));
    }

    /**
     * Eager-load exactly the relations a product *card* needs.
     *
     * Kept here so the listing endpoints stay a fixed, cheap query set; the
     * detail endpoint deliberately loads more.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    protected function withCardRelations(Builder $query): Builder
    {
        return $query->with([
            'category:id,name,slug,parent_id',
            'brand:id,name,slug,logo',
            'images',
            'variations',
        ]);
    }
}
