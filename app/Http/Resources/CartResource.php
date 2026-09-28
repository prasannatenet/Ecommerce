<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesStorefrontUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single cart line.
 *
 * `price` is the unit price the line was added at; `line_total` is the
 * authoritative subtotal the checkout maths uses.
 *
 * @mixin \App\Models\Cart
 */
class CartResource extends JsonResource
{
    use ResolvesStorefrontUrls;

    public function toArray(Request $request): array
    {
        $product = $this->relationLoaded('product') ? $this->product : null;
        $variation = $this->relationLoaded('variation') ? $this->variation : null;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variation_id' => $this->product_variation_id,
            'combo_id' => $this->combo_id,

            'name' => $product?->name,
            'slug' => $product?->slug,
            'sku' => $variation?->sku ?? $product?->sku,
            'image' => $product ? ($this->mediaUrl($product->primary_image)
                ?? $this->mediaUrl($product->relationLoaded('images') ? $product->images->first()?->path : null))
                : null,

            // Variation attributes, flattened for a cart line label such as
            // "Size: 18 · Weight: 5.2g".
            'options' => $variation?->attributes ?? [],

            'price' => (float) $this->price,
            'quantity' => (int) $this->quantity,

            // ── Bundle pricing ───────────────────────────────────────────
            // `line_total` is what the customer is actually charged, which for a
            // discounted line is lower than quantity x price.
            // `regular_unit_price` is the undiscounted figure to strike through,
            // so a combo line can read "1,000 -> 950" rather than only showing
            // the discounted number. The controller sets all three on the model
            // via syncOriginalAttribute, so a later save() cannot persist them.
            'is_combo' => (bool) $this->combo_id,
            'combo' => $this->relationLoaded('combo') && $this->combo
                ? [
                    'id' => $this->combo->id,
                    'name' => $this->combo->name,
                    'slug' => $this->combo->slug,
                    'combo_price' => $this->combo->comboPrice(),
                ]
                : null,
            'combo_units' => (int) ($this->combo_units ?? 0),
            'combo_names' => array_values((array) ($this->combo_names ?? [])),
            'regular_unit_price' => $this->regular_unit_price !== null
                ? (float) $this->regular_unit_price
                : (float) $this->price,
            'line_total' => $this->charged_line_total !== null
                ? (float) $this->charged_line_total
                : (float) $this->subtotal,

            'available_stock' => $variation
                ? (int) $variation->stock
                : (int) ($product?->getTotalStockAttribute() ?? 0),
            'url' => $product ? $this->pageUrl('product.show', $product->slug) : null,
        ];
    }
}
