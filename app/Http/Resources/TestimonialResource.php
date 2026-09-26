<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer testimonial shown on the home page.
 *
 * @mixin \App\Models\Testimonial
 */
class TestimonialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'designation' => $this->designation,
            'content' => $this->content,
            'rating' => (float) $this->rating,
            'initials' => $this->initials,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
