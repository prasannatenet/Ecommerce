<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Category;

class FrontendCategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('products')->get();

        return view('frontend.category.index', compact('categories'));
    }

    public function show($slug)
    {
        $category = Category::whereSlug($slug)->firstOrFail();

        $category = Category::with(['products' => function ($q) {
            $q->where('is_active', true)->with('images', 'variations', 'brand');
        }])->withCount(['products' => fn ($q) => $q->where('is_active', true)])->findOrFail($category->id);

        return view('frontend.category.show', compact('category'));
    }
}
