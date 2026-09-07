<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Product;
use App\Models\Slider;
use App\Models\Testimonial;

class HomeController extends Controller
{
    public function index()
    {
        $sliders = Slider::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        $categories = Category::orderBy('position')->take(5)->get();

        // Tab categories: top 4 by position
        $tabCategories = Category::with(['products' => function ($query) {
                $query->where('is_active', true)
                      ->with('images', 'category', 'brand', 'variations')
                      ->latest()
                      ->take(8);
            }])
            ->whereHas('products', function ($q) {
                $q->where('is_active', true);
            })
            ->orderBy('position')
            ->take(4)
            ->get();

        $featuredProducts = Product::where('is_active', true)
            ->with('images', 'category', 'brand', 'variations')
            ->latest()
            ->take(12)
            ->get();

        $newProducts = Product::where('is_active', true)
            ->with('images', 'category', 'brand', 'variations')
            ->latest()
            ->take(8)
            ->get();

        $brands = Brand::get();

        // Hero section: 3 products with images for the visual cards
        $heroProducts = Product::where('is_active', true)
            ->whereHas('images')
            ->with('images')
            ->latest()
            ->take(3)
            ->get();

        $faqs = Faq::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        $testimonials = Testimonial::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        return view('frontend.home', compact(
            'sliders', 'categories', 'featuredProducts',
            'newProducts', 'tabCategories', 'brands', 'heroProducts', 'faqs', 'testimonials'
        ));
    }
}
