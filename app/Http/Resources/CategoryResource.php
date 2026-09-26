<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesStorefrontUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A storefront category (or sub-category).
 *
 * `products_count` is only present when the controller eager-loads a count,
 * and `children` only when it eager-loads the tree. Both are opt-in so the
 * nested /categories/tree endpoint can recurse without every other consumer
 * paying for a full tree walk.
 *
 * @mixin \App\Models\Category
 */
class CategoryResource extends JsonResource
{
    use ResolvesStorefrontUrls;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'is_root' => $this->isRoot(),
            'position' => (int) $this->position,
            'description' => $this->description,
            'image' => $this->mediaUrl($this->image),
            'breadcrumb' => $this->breadcrumb,
            'products_count' => $this->whenCounted('products'),
            'url' => $this->pageUrl('category.show', $this->slug),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
