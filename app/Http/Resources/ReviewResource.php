<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesStorefrontUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer review on a product.
 *
 * @mixin \App\Models\Review
 */
class ReviewResource extends JsonResource
{
    use ResolvesStorefrontUrls;

    public function toArray(Request $request): array
    {
        $user = $this->relationLoaded('user') ? $this->user : null;

        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            // Only the first name and the avatar-less initials are exposed:
            // reviews are public, so a customer's email or phone must never
            // ride along inside a product payload.
            'author' => $user ? [
                'name' => $user->name,
                'initials' => collect(preg_split('/\s+/', trim((string) $user->name)))
                    ->filter()
                    ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                    ->take(2)
                    ->implode(''),
            ] : null,
            'images' => $this->mediaList(
                $this->whenLoaded('images', fn () => $this->images->pluck('path')->all(), [])
            ),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
