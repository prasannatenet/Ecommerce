<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\SearchLog;
use Illuminate\Http\Request;

class FrontendSearchController extends Controller
{
    /**
     * AJAX autocomplete endpoint used by the navbar search bar.
     * Returns matching active products and categories so the UI can
     * show type-labelled ("Product" / "Category") suggestions.
     */
    public function search(Request $request)
    {
        $q = trim((string) ($request->get('q') ?? $request->get('search') ?? ''));

        if ($q === '' || strlen($q) < 2) {
            return response()->json([
                'query'      => $q,
                'products'   => [],
                'categories' => [],
                'recent'     => SearchLog::recentForUser(
                    auth()->id() ? (int) auth()->id() : null,
                    5
                ),
            ]);
        }

        // Persist the search to the search_logs table for logged-in users.
        // Done inside the autocomplete handler so it's recorded even when
        // the user picks a suggestion (without a full GET to /search).
        if (auth()->check()) {
            $userId = (int) auth()->id();
            SearchLog::create([
                'user_id'     => $userId,
                'searchable'  => $q,
                'searched_at' => now(),
            ]);
        }

        $like = '%' . $q . '%';

        $products = Product::where('is_active', true)
            ->where('name', 'like', $like)
            ->with('images', 'category')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'id'       => $p->id,
                'name'     => $p->name,
                'slug'     => $p->slug,
                'image'    => $p->images->isNotEmpty() ? $p->images->first()->path : $p->primary_image,
                'price'    => (float) ($p->display_price ?? 0),
                'category' => $p->category ? $p->category->name : null,
            ])
            ->values()
            ->all();

        $categories = Category::where('name', 'like', $like)
            ->withCount('products')
            ->limit(4)
            ->get()
            ->map(fn ($c) => [
                'id'            => $c->id,
                'name'          => $c->name,
                'slug'          => $c->slug,
                'image'         => $c->image,
                'product_count' => (int) ($c->products_count ?? 0),
            ])
            ->values()
            ->all();

        $recent = SearchLog::recentForUser(
            auth()->id() ? (int) auth()->id() : null,
            5
        );

        return response()->json([
            'query'      => $q,
            'products'   => $products,
            'categories' => $categories,
            'recent'     => $recent,
        ]);
    }

    /**
     * Full GET search page (e.g. user hits Enter or clicks the search
     * button). Renders the search results index and also records the
     * search so it appears in "recent searches" afterward.
     */
    public function index(Request $request)
    {
        $q = trim((string) ($request->get('search') ?? ''));

        // Record the search (same logic as the autocomplete handler, kept
        // here in case the search is performed via a plain form GET).
        if (auth()->check() && $q !== '') {
            SearchLog::create([
                'user_id'     => (int) auth()->id(),
                'searchable'  => $q,
                'searched_at' => now(),
            ]);
        }

        $query = Product::query()->where('is_active', true);

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $like = '%' . $q . '%';
                $builder->where('name', 'like', $like)
                    ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like))
                    ->orWhere('sku', 'like', $like);
            });
        }

        $products = $query
            ->with('images', 'category')
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        $recent = SearchLog::recentForUser(
            auth()->id() ? (int) auth()->id() : null,
            5
        );

        return view('frontend.search.index', [
            'query'    => $q,
            'products' => $products,
            'recent'   => $recent,
        ]);
    }
}