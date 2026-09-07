<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
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
                $query->orderByDesc('is_featured');
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

        return view('frontend.product.show', compact('product'));
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
