<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesStorefrontUrls;
use App\Models\ProductVariation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A storefront product.
 *
 * Listing endpoints (`/products`, `/categories/{slug}/products`) and the
 * detail endpoint (`/products/{slug}`) share this one resource so a React
 * product card can be reused verbatim on the detail page. Everything heavier
 * than a card needs — variations, reviews, attributes, videos — is emitted
 * through whenLoaded(), so the list query stays cheap.
 *
 * @mixin \App\Models\Product
 */
class ProductResource extends JsonResource
{
    use ResolvesStorefrontUrls;

    public function toArray(Request $request): array
    {
        $effective = $this->effectivePrice();
        $regular = $this->regularPrice();
        $variations = $this->relationLoaded('variations') ? $this->variations : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'url' => $this->pageUrl('product.show', $this->slug),

            'audience' => $this->audience,
            'material_type' => $this->material_type,
            'product_type' => $this->product_type ?? 'simple',
            'is_simple' => $this->isSimple(),
            'is_variable' => $this->isVariable(),

            'short_description' => $this->short_description,
            'description' => $this->description,

            // ── Pricing ──────────────────────────────────────────────
            // `price` is what the customer pays (variation aware, sale price
            // applied); `regular_price` is the number to strike through.
            'price' => $effective,
            'regular_price' => $regular,
            'sale_price' => $this->sale_price !== null ? (float) $this->sale_price : null,
            'base_price' => $this->base_price !== null ? (float) $this->base_price : null,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value !== null ? (float) $this->discount_value : null,
            'discount_percentage' => $this->discountPercentage(),
            'is_on_sale' => $this->hasDiscount(),
            'price_range' => $this->priceRange($variations),

            // ── Media ─────────────────────────────────────────────────
            'primary_image' => $this->mediaUrl($this->primary_image)
                ?? $this->mediaUrl($this->relationLoaded('images') ? $this->images->firstWhere('is_primary', true)?->path ?? $this->images->first()?->path : null),
            'images' => $this->mediaList(
                $this->whenLoaded('images', fn () => $this->images->pluck('path')->all(), [])
            ),

            // ── Stock ─────────────────────────────────────────────────
            'manage_stock' => (bool) $this->manage_stock,
            'stock' => $this->stockFor($variations),
            'in_stock' => $this->stockFor($variations) > 0,

            // ── Relations ─────────────────────────────────────────────
            'category' => new CategorySummaryResource($this->whenLoaded('category')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'variations' => ProductVariationResource::collection($variations ?? []),
            'attributes' => $this->whenLoaded('attributes', fn () => $this->attributes
                ->map(fn ($attribute) => [
                    'id' => $attribute->id,
                    'name' => $attribute->name,
                ])->values()),
            'videos' => $this->whenLoaded('videos', fn () => $this->videos->map(fn ($video) => [
                'id' => $video->id,
                'is_primary' => (bool) $video->is_primary,
                'url' => $video->stream_url,
                'download_url' => $this->mediaUrl($video->path),
            ])->values()),

            // ── Social proof ──────────────────────────────────────────
            'rating' => $this->whenLoaded('reviews', fn () => round((float) ($this->reviews->avg('rating') ?? 0), 2), $this->when(isset($this->rating_average), (float) $this->rating_average)),
            'reviews_count' => $this->whenCounted('reviews'),
        ];
    }

    /**
     * Total sellable stock.
     *
     * Product::getTotalStockAttribute() fires a SUM query per model. On a
     * paginated listing where the variations relation is already eager
     * loaded that is pure waste, so the total is summed from the loaded rows
     * instead and only falls back to the attribute when it is not.
     *
     * @param  \Illuminate\Support\Collection<int, ProductVariation>|null  $variations
     */
    private function stockFor(?iterable $variations): int
    {
        if ($variations === null) {
            return (int) $this->getTotalStockAttribute();
        }

        if ($this->isSimple()) {
            return (int) $this->stock;
        }

        return (int) $variations->where('is_active', true)->sum('stock');
    }

    /**
     * Min/max of the real selling prices, so a variable product can show a
     * "from ₹x" label. Null for a simple product with no price at all.
     *
     * @param  \Illuminate\Support\Collection<int, ProductVariation>|null  $variations
     */
    private function priceRange(?iterable $variations): ?array
    {
        if ($this->isSimple() || $variations === null) {
            return null;
        }

        $prices = $variations
            ->filter(fn (ProductVariation $variation) => (bool) $variation->is_active)
            ->map(fn (ProductVariation $variation) => $variation->effectivePrice())
            ->filter(fn (float $price) => $price > 0)
            ->values();

        if ($prices->isEmpty()) {
            return null;
        }

        return [
            'min' => (float) $prices->min(),
            'max' => (float) $prices->max(),
        ];
    }
}
