<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesStorefrontUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A home-page hero/banner slide.
 *
 * @mixin \App\Models\Slider
 */
class SliderResource extends JsonResource
{
    use ResolvesStorefrontUrls;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subheading' => $this->subheading,
            'button_text' => $this->button_text,
            'button_link' => $this->button_link,
            'image' => $this->mediaUrl($this->image_path),
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
