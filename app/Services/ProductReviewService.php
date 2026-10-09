<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductReviewService
{
    public function canReview(Product $product, User $user): bool
    {
        return OrderItem::query()
            ->where('product_id', $product->id)
            ->whereHas('order', fn ($query) => $query
                ->where('user_id', $user->id)
                ->whereNotIn('status', ['cancelled', 'failed', 'refunded']))
            ->exists();
    }

    public function store(Product $product, User $user, Request $request, array $data): Review
    {
        $review = $product->reviews()->firstOrNew(['user_id' => $user->id]);

        return $this->persist($review, $request, $data);
    }

    public function update(Review $review, Request $request, array $data): Review
    {
        return $this->persist($review, $request, $data, true);
    }

    public function delete(Review $review): void
    {
        $review->delete();
    }

    private function persist(Review $review, Request $request, array $data, bool $allowRemoval = false): Review
    {
        $uploadedFiles = collect($request->file('images', []))
            ->filter(fn ($file) => $file->isValid())
            ->values();
        $removeIds = $allowRemoval
            ? collect($data['remove_image_ids'] ?? [])->map(fn ($id) => (int) $id)
            : collect();
        $remainingCount = $review->exists
            ? $review->images()->whereNotIn('id', $removeIds)->count()
            : 0;

        if ($remainingCount + $uploadedFiles->count() > 3) {
            throw ValidationException::withMessages([
                'images' => 'A review can contain a maximum of 3 images.',
            ]);
        }

        $newPaths = [];
        $removedPaths = [];

        try {
            DB::transaction(function () use ($review, $data, $uploadedFiles, $removeIds, &$newPaths, &$removedPaths): void {
                $review->fill([
                    'rating' => (int) $data['rating'],
                    'comment' => trim($data['comment']),
                ])->save();

                if ($removeIds->isNotEmpty()) {
                    $images = $review->images()->whereIn('id', $removeIds)->get();
                    $removedPaths = $images->pluck('path')->all();
                    $review->images()->whereIn('id', $removeIds)->delete();
                }

                foreach ($uploadedFiles as $file) {
                    $path = $file->store('reviews/'.$review->id, 'public');

                    if ($path === false) {
                        throw new \RuntimeException('The review image could not be stored.');
                    }

                    $newPaths[] = $path;
                    $review->images()->create(['path' => $path]);
                }
            });
        } catch (\Throwable $exception) {
            if ($newPaths !== []) {
                Storage::disk('public')->delete($newPaths);
            }

            throw $exception;
        }

        if ($removedPaths !== []) {
            Storage::disk('public')->delete($removedPaths);
        }

        return $review->refresh()->load(['user:id,name', 'images']);
    }
}
