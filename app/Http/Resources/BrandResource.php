<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesStorefrontUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A jewellery brand.
 *
 * @mixin \App\Models\Brand
 */
class BrandResource extends JsonResource
{
    use ResolvesStorefrontUrls;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo' => $this->mediaUrl($this->logo),
            'description' => $this->description,
            'products_count' => $this->whenCounted('products'),
            'url' => $this->pageUrl('brand.show', $this->slug),
        ];
    }
}
