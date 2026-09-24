<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;

class BackendReviewController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:255',
            'rating' => 'nullable|integer|between:1,5',
            'product_id' => 'nullable|integer|exists:products,id',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
        ]);

        $query = Review::query()->with(['user', 'product', 'images']);

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($builder) use ($search): void {
                $builder->where('comment', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('product', function ($productQuery) use ($search): void {
                        $productQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['rating'])) {
            $query->where('rating', $filters['rating']);
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $totalReviews = (clone $query)->count();
        $averageRating = round((float) (clone $query)->avg('rating'), 1);
        $reviewsWithImages = (clone $query)->whereHas('images')->count();
        $reviews = $query->latest('created_at')->latest('id')->paginate(20)->withQueryString();
        $products = Product::whereHas('reviews')->orderBy('name')->get(['id', 'name']);

        return view('backend.reviews.index', compact(
            'reviews',
            'products',
            'totalReviews',
            'averageRating',
            'reviewsWithImages',
        ));
    }
}
