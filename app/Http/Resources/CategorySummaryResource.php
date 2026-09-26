<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Trimmed-down category used everywhere a product is embedded.
 *
 * A product card does not need a category's description, image or child tree,
 * and nesting CategoryResource inside every product of a 24-item page would
 * triple the payload for data no card renders.
 *
 * @mixin \App\Models\Category
 */
class CategorySummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
        ];
    }
}
