<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HomeSection;
use App\Models\Faq;
use App\Models\Product;
use App\Models\Slider;
use App\Models\Testimonial;

class HomeController extends Controller
{
    public function index()
    {
        // Ensure the default home section records exist.
        HomeSection::ensureDefaultRecords();

        $sliders = Slider::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        $categories = Category::orderBy('position')->take(10)->get();

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

        // Home sections (middle featured + large image)
        $featuredSection = HomeSection::forSection('featured')->active()->first();
        $largeImageSection = HomeSection::forSection('large-image')->active()->first();

        // Filter featured products by category if category_name is set on featured section
        $featuredProductsQuery = Product::where('is_active', true)
            ->with('images', 'category', 'brand', 'variations');

        if ($featuredSection && !empty($featuredSection->category_name)) {
            $featuredProductsQuery->whereHas('category', function ($query) use ($featuredSection) {
                $query->where('name', $featuredSection->category_name);
            });
        }

        $featuredProducts = $featuredProductsQuery->latest()->take(12)->get();

        // All products for "Our Jewellery" section (not filtered by category)
        $allProducts = Product::where("is_active", true)
            ->with("images", "category", "brand", "variations")
            ->latest()
            ->take(8)
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

        // Get section-specific category products
        $sectionProducts = [];
        $allSections = HomeSection::active()->orderBy('sort_order')->get();
        foreach ($allSections as $section) {
            // Only show products if category_name is set for this section
            if (!empty($section->category_name)) {
                $query = Product::where('is_active', true)
                    ->with('images', 'category', 'brand', 'variations')
                    ->whereHas('category', function ($q) use ($section) {
                        $q->where('name', $section->category_name);
                    })
                    ->latest()
                    ->take(8)
                    ->get();
                $sectionProducts[$section->section_key] = $query;
            } else {
                // No category selected - return empty collection
                $sectionProducts[$section->section_key] = collect();
            }
        }

        return view('frontend.home', compact(
            'sliders', 'categories', 'featuredProducts', 'allProducts',
            'newProducts', 'tabCategories', 'brands', 'heroProducts', 'faqs', 'testimonials',
            'featuredSection', 'largeImageSection', 'sectionProducts'
        ));
    }
}
