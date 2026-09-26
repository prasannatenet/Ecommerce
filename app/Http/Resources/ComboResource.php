<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesStorefrontUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A bundle offer (Combo) and the products inside it.
 *
 * The totals are computed by the Combo model itself — productsTotal(),
 * discountAmount(), comboPrice() — so the API, the Blade combo builder and
 * the checkout maths can never quote three different prices for one bundle.
 *
 * @mixin \App\Models\Combo
 */
class ComboResource extends JsonResource
{
    use ResolvesStorefrontUrls;

    public function toArray(Request $request): array
    {
        $total = $this->productsTotal();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value !== null ? (float) $this->discount_value : null,
            'products_total' => $total,
            'discount_amount' => $this->discountAmount(),
            'combo_price' => $this->comboPrice(),
            'savings_percent' => $this->savingsPercent(),
            'is_live' => $this->isLive(),
            'starts_at' => optional($this->starts_at)->toIso8601String(),
            'expires_at' => optional($this->expires_at)->toIso8601String(),
            'products' => ProductResource::collection($this->whenLoaded('products')),
            'products_count' => $this->whenCounted('products'),
        ];
    }
}
