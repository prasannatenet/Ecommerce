<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesStorefrontUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One buyable variation of a variable product.
 *
 * Price is resolved through ProductVariation::effectivePrice() so the API can
 * never quote a figure the rest of the storefront would not quote.
 *
 * @mixin \App\Models\ProductVariation
 */
class ProductVariationResource extends JsonResource
{
    use ResolvesStorefrontUrls;

    public function toArray(Request $request): array
    {
        $price = $this->effectivePrice();

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'price' => $price,
            'regular_price' => (float) $this->price,
            'sale_price' => $this->sale_price !== null ? (float) $this->sale_price : null,
            'discount_percentage' => $this->discountPercentage(),
            'is_on_sale' => $this->discountPercentage() !== null,
            'stock' => (int) $this->stock,
            'in_stock' => (int) $this->stock > 0,
            'is_active' => (bool) $this->is_active,
            'description' => $this->description,
            // Free-form { "Size": "18", "Weight": "5.2g" } map the admin editor stores.
            'attributes' => $this->attributes ?? [],
            'images' => $this->mediaList(
                $this->whenLoaded('images', fn () => $this->images->pluck('path')->all(), [])
            ),
        ];
    }
}
