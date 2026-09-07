<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
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

        return response()->json([
            'query'      => $q,
            'products'   => $products,
            'categories' => $categories,
        ]);
    }
}