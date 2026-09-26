<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesStorefrontUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One purchased line inside an order.
 *
 * The name and SKU are denormalised onto order_items at purchase time, so they
 * are read from the item rather than from the product: an order must keep
 * showing what was actually bought even after the catalogue changes.
 *
 * @mixin \App\Models\OrderItem
 */
class OrderItemResource extends JsonResource
{
    use ResolvesStorefrontUrls;

    public function toArray(Request $request): array
    {
        $product = $this->relationLoaded('product') ? $this->product : null;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variation_id' => $this->product_variation_id,
            'product_name' => $this->product_name,
            'sku' => $this->sku,
            'image' => $product ? $this->mediaUrl($product->primary_image) : null,
            'unit_price' => (float) $this->unit_price,
            'quantity' => (int) $this->quantity,
            'line_total' => (float) $this->line_total,
            'meta' => $this->meta ?? [],
            'url' => $product ? $this->pageUrl('product.show', $product->slug) : null,
        ];
    }
}
