<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Models\Review;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FrontendProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('brand', 'category', 'images', 'variations')->where('is_active', true);

        // Category filter
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        // Price range filter
        if ($request->filled('max_price')) {
            $query->where(function ($q) use ($request) {
                $q->where('sale_price', '<=', $request->max_price)
                  ->orWhere(function ($q2) use ($request) {
                      $q2->whereNull('sale_price')->where('base_price', '<=', $request->max_price);
                  });
            });
        }

        // Search
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Sorting
        switch ($request->get('sort', 'default')) {
            case 'price-low':
                $query->orderByRaw('COALESCE(sale_price, base_price) ASC');
                break;
            case 'price-high':
                $query->orderByRaw('COALESCE(sale_price, base_price) DESC');
                break;
            case 'newest':
                $query->latest();
                break;
            case 'popularity':
                // No popularity/featured column exists on products; fall back
                // to the newest products instead of crashing on a bad column.
                $query->latest();
                break;
            default:
                $query->latest();
        }

        $products = $query->paginate(12)->withQueryString();
        $wishlistProductIds = Auth::check()
            ? Wishlist::where('user_id', Auth::id())
                ->whereIn('product_id', $products->pluck('id'))
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all()
            : [];

        // AJAX infinite scroll — return just the cards + pagination meta
        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'html'          => view('frontend.partials.product-cards', compact('products', 'wishlistProductIds'))->render(),
                'next_page_url' => $products->nextPageUrl(),
                'total'         => $products->total(),
                'last_page'     => $products->lastPage(),
                'current_page'  => $products->currentPage(),
            ]);
        }

        $filterCategories = Category::orderBy('name')->get();

        // Price slider bounds follow the actual product prices:
        // lowest slider position = cheapest effective price, highest = most expensive.
        $minProductPrice = (float) (Product::where('is_active', true)
            ->min(DB::raw('COALESCE(sale_price, base_price)')) ?? 0);
        $maxProductPrice = (float) (Product::where('is_active', true)
            ->max(DB::raw('COALESCE(sale_price, base_price)')) ?? 0);

        return view('frontend.product.index', compact(
            'products',
            'filterCategories',
            'wishlistProductIds',
            'minProductPrice',
            'maxProductPrice'
        ));
    }

    public function show($slug)
    {
        $product = Product::where('slug', $slug)
            ->with('variations', 'images', 'brand', 'category')
            ->firstOrFail();

        $reviews = $product->reviews()->with('user')->latest()->get();
        $reviewsCount = $reviews->count();
        $averageRating = $reviewsCount > 0 ? round((float) $reviews->avg('rating'), 1) : 0.0;

        // Active combo offers that include this product — the offer is shown
        // below the product on its detail page.
        $combos = Combo::whereHas('products', fn ($query) => $query->where('products.id', $product->id))
            ->active()
            ->with(['products' => fn ($query) => $query->with('images')])
            ->orderBy('id')
            ->get();
        $inWishlist = false;
        if (Auth::check()) {
            $inWishlist = Wishlist::where('user_id', Auth::id())
                ->where('product_id', $product->id)
                ->exists();
        } else {
            $guestWishlist = array_map('intval', (array) (session('guest_wishlist', []) ?? []));
            $inWishlist = in_array((int) $product->id, $guestWishlist, true);
        }

        // Suggestions: other active products from the same category as this
        // product, shown below the product details.
        $relatedProducts = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->with('brand', 'category', 'images', 'variations')
            ->latest()
            ->take(6)
            ->get();

        $relatedWishlistIds = Auth::check()
            ? Wishlist::where('user_id', Auth::id())
                ->whereIn('product_id', $relatedProducts->pluck('id'))
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all()
            : [];

        return view('frontend.product.show', compact(
            'product',
            'reviews',
            'reviewsCount',
            'averageRating',
            'inWishlist',
            'combos',
            'relatedProducts',
            'relatedWishlistIds'
        ));

        $inWishlist = false;
        if (Auth::check()) {
            $inWishlist = Wishlist::where('user_id', Auth::id())
                ->where('product_id', $product->id)
                ->exists();
        } else {
            $guestWishlist = array_map('intval', (array) (session('guest_wishlist', []) ?? []));
            $inWishlist = in_array((int) $product->id, $guestWishlist, true);
        }

        return view('frontend.product.show', compact(
            'product',
            'reviews',
            'reviewsCount',
            'averageRating',
            'inWishlist',
            'combos'
        ));
    }


    public function storeReview(Request $request, Product $product)
    {
        $data = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'required|string|max:2000',
        ]);

        // One review per user per product — re-submitting updates their review.
        $product->reviews()->updateOrCreate(
            ['user_id' => Auth::id()],
            [
                'rating'  => (int) $data['rating'],
                'comment' => trim($data['comment']),
            ]
        );

        return $this->reviewListResponse($product, $request, 'Thanks! Your review has been posted.');
    }

    public function updateReview(Request $request, Product $product, Review $review)
    {
        abort_unless($review->product_id === $product->id, 404);
        abort_unless($review->user_id === Auth::id(), 403, 'You can only edit your own review.');

        $data = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'required|string|max:2000',
        ]);

        $review->update([
            'rating'  => (int) $data['rating'],
            'comment' => trim($data['comment']),
        ]);

        return $this->reviewListResponse($product, $request, 'Your review has been updated.');
    }

    public function destroyReview(Request $request, Product $product, Review $review)
    {
        abort_unless($review->product_id === $product->id, 404);
        abort_unless($review->user_id === Auth::id(), 403, 'You can only delete your own review.');

        $review->delete();

        return $this->reviewListResponse($product, $request, 'Your review has been deleted.');
    }

    /**
     * Build the standard review response — freshly rendered review list plus
     * counts. AJAX requests get JSON (so the new state appears immediately
     * without a reload); normal requests redirect back to the Reviews tab.
     */
    private function reviewListResponse(Product $product, Request $request, string $message)
    {
        $reviews = $product->reviews()->with('user')->latest()->get();
        $reviewsCount = $reviews->count();
        $averageRating = $reviewsCount > 0 ? round((float) $reviews->avg('rating'), 1) : 0.0;

        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'success'        => true,
                'message'        => $message,
                'reviews_html'   => view('frontend.product.review-list', compact('reviews', 'product'))->render(),
                'reviews_count'  => $reviewsCount,
                'average_rating' => $averageRating,
            ]);
        }

        return redirect()
            ->to(route('product.show', $product->slug) . '#tabReviews')
            ->with('review_success', $message);
    }

    public function quickView($id)
    {
        abort_unless(request()->ajax() || request()->wantsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest', 404);

        $product = Product::where('id', $id)
            ->where('is_active', true)
            ->with('variations', 'images', 'brand', 'category')
            ->firstOrFail();

        return view('frontend.partials.quick-view', compact('product'));
    }
}
