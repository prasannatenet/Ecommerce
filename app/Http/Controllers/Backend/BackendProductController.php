<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariation;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BackendProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('brand', 'category', 'images', 'tags');

        // Filter by product type
        if ($request->filled('type')) {
            $query->where('product_type', $request->type);
        }

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by brand
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        // Filter by tag
        if ($request->filled('tag_id')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('tags.id', $request->tag_id);
            });
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->latest()->paginate(20)->withQueryString();
        $categories = Category::all();
        $brands = Brand::all();
        $tags = Tag::all();

        return view('backend.product.index', compact('products', 'categories', 'brands', 'tags'));
    }

    public function create()
    {
        $brands = Brand::all();
        $categories = Category::whereNull('parent_id')->with('childrenRecursive')->get();
        $allCategories = Category::all();
        $attributes = Attribute::with('values')->where('is_active', true)->get();
        $tags = Tag::orderBy('name')->get();

        return view('backend.product.create', compact('brands', 'categories', 'allCategories', 'attributes', 'tags'));
    }

    public function store(Request $request)
    {
        $rules = [
            'name'              => 'required|string|max:255',
            'audience'          => 'required|in:men,women,both,girl,unisex',
            'material_type'     => 'required|in:gold,silver,diamond,platinum,rose_gold,stainless_steel,other',
            'slug'              => 'required|string|max:255|unique:products,slug',
            'short_description' => 'nullable|string',
            'description'       => 'nullable|string',
            'base_price'        => 'required|numeric|min:0',
            'sale_price'        => 'nullable|numeric|min:0',
            'discount_type'     => 'nullable|in:fixed,percentage',
            'discount_value'    => 'nullable|numeric|min:0',
            'brand_id'          => 'nullable|exists:brands,id',
            'category_id'       => 'nullable|exists:categories,id',
            'is_active'         => 'boolean',
            'product_type'      => 'required|in:simple,variable',
            'primary_image'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'images'            => 'nullable|array',
            'images.*'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'tag_ids'           => 'nullable|array',
            'tag_ids.*'         => 'exists:tags,id',
        ];

        // Simple-product specific fields
        if ($request->product_type === 'simple') {
            $rules['sku']          = 'nullable|string|unique:products,sku';
            $rules['stock']        = 'nullable|integer|min:0';
            $rules['manage_stock'] = 'boolean';
            $rules['weight']       = 'nullable|numeric|min:0';
        }

        // Variable-product specific fields
        if ($request->product_type === 'variable') {
            $rules['attribute_ids']   = 'nullable|array';
            $rules['attribute_ids.*'] = 'exists:attributes,id';
            $rules['attribute_values']   = 'nullable|array';
            $rules['attribute_values.*'] = 'exists:attribute_values,id';
        }

        $validated = $request->validate($rules);

        $validated['sale_price'] = $this->calculateSalePrice($request, $validated['base_price'], $validated['sale_price'] ?? null);

        DB::beginTransaction();

        try {
            $productData = $validated;
            unset($productData['images'], $productData['tag_ids'], $productData['attribute_ids'], $productData['attribute_values']);

            if ($request->hasFile('primary_image')) {
                $productData['primary_image'] = $request->file('primary_image')->store('products', 'public');
            }

            $product = Product::create($productData);

            // Attach tags
            if ($request->has('tag_ids')) {
                $product->tags()->sync($request->tag_ids);
            }

            // Attach attributes (variable products only)
            if ($request->product_type === 'variable') {
                $attributeIds = collect($request->input('attribute_ids', []))
                    ->merge(
                        AttributeValue::query()
                            ->whereIn('id', $request->input('attribute_values', []))
                            ->pluck('attribute_id')
                            ->all()
                    )
                    ->filter()
                    ->unique()
                    ->values();

                foreach ($attributeIds as $index => $attributeId) {
                    $product->attributes()->attach($attributeId, ['position' => $index]);
                }
            }

            if (! empty($product->primary_image)) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'path'       => $product->primary_image,
                    'is_primary' => true,
                ]);
            }

            // Upload images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    $path = $file->store('products', 'public');
                    ProductImage::create([
                        'product_id' => $product->id,
                        'path'       => $path,
                        'is_primary' => false,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('admin.products.show', $product->id)
                ->with('success', 'Product created successfully.');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors($e->getMessage())->withInput();
        }
    }

    public function edit(Product $product)
    {
        $product->load('images', 'variations', 'attributes', 'tags');
        $brands = Brand::all();
        $categories = Category::whereNull('parent_id')->with('childrenRecursive')->get();
        $allCategories = Category::all();
        $attributes = Attribute::with('values')->where('is_active', true)->get();
        $tags = Tag::orderBy('name')->get();

        return view('backend.product.edit', compact('product', 'brands', 'categories', 'allCategories', 'attributes', 'tags'));
    }

    public function update(Request $request, Product $product)
    {
        $rules = [
            'name'              => 'required|string|max:255',
            'audience'          => 'required|in:men,women,both,girl,unisex',
            'material_type'     => 'required|in:gold,silver,diamond,platinum,rose_gold,stainless_steel,other',
            'slug'              => 'required|string|max:255|unique:products,slug,' . $product->id,
            'short_description' => 'nullable|string',
            'description'       => 'nullable|string',
            'base_price'        => 'required|numeric|min:0',
            'sale_price'        => 'nullable|numeric|min:0',
            'discount_type'     => 'nullable|in:fixed,percentage',
            'discount_value'    => 'nullable|numeric|min:0',
            'brand_id'          => 'nullable|exists:brands,id',
            'category_id'       => 'nullable|exists:categories,id',
            'is_active'         => 'boolean',
            'product_type'      => 'required|in:simple,variable',
            'primary_image'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'images'            => 'nullable|array',
            'images.*'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'tag_ids'           => 'nullable|array',
            'tag_ids.*'         => 'exists:tags,id',
        ];

        if ($request->product_type === 'simple') {
            $rules['sku']          = 'nullable|string|unique:products,sku,' . $product->id;
            $rules['stock']        = 'nullable|integer|min:0';
            $rules['manage_stock'] = 'boolean';
            $rules['weight']       = 'nullable|numeric|min:0';
        }

        if ($request->product_type === 'variable') {
            $rules['attribute_ids']   = 'nullable|array';
            $rules['attribute_ids.*'] = 'exists:attributes,id';
            $rules['attribute_values']   = 'nullable|array';
            $rules['attribute_values.*'] = 'exists:attribute_values,id';
        }

        $validated = $request->validate($rules);

        $validated['sale_price'] = $this->calculateSalePrice($request, $validated['base_price'], $validated['sale_price'] ?? null);

        DB::beginTransaction();

        try {
            $productData = $validated;
            unset($productData['images'], $productData['tag_ids'], $productData['attribute_ids'], $productData['attribute_values']);

            if ($request->hasFile('primary_image')) {
                $productData['primary_image'] = $request->file('primary_image')->store('products', 'public');
            }

            $product->update($productData);

            // Sync tags
            $product->tags()->sync($request->tag_ids ?? []);

            // Handle attributes based on product type
            if ($request->product_type === 'variable') {
                $attributeIds = collect($request->input('attribute_ids', []))
                    ->merge(
                        AttributeValue::query()
                            ->whereIn('id', $request->input('attribute_values', []))
                            ->pluck('attribute_id')
                            ->all()
                    )
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (! empty($attributeIds)) {
                    $syncData = [];
                    foreach ($attributeIds as $index => $attributeId) {
                        $syncData[$attributeId] = ['position' => $index];
                    }
                    $product->attributes()->sync($syncData);
                } else {
                    $product->attributes()->detach();
                }
            } else {
                // Simple product: remove attributes and variations
                $product->attributes()->detach();
            }

            if ($request->hasFile('primary_image')) {
                $primaryImage = $product->images()->where('is_primary', true)->first();

                if ($primaryImage) {
                    $primaryImage->update(['path' => $product->primary_image]);
                } else {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'path'       => $product->primary_image,
                        'is_primary' => true,
                    ]);
                }
            }

            // Upload new images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    $path = $file->store('products', 'public');
                    ProductImage::create([
                        'product_id' => $product->id,
                        'path'       => $path,
                        'is_primary' => false,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('admin.products.show', $product->id)
                ->with('success', 'Product updated successfully.');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors($e->getMessage())->withInput();
        }
    }

    public function show(Product $product)
    {
        $product->load('brand', 'category', 'images', 'variations', 'attributes.values', 'tags');
        return view('backend.product.show', compact('product'));
    }

    private function calculateSalePrice(Request $request, $basePrice, $fallbackSalePrice = null): ?float
    {
        $type = $request->input('discount_type');
        $value = $request->input('discount_value');

        if ($type === null || $value === null || $value === '') {
            return $fallbackSalePrice !== null && $fallbackSalePrice !== ''
                ? round((float) $fallbackSalePrice, 2)
                : null;
        }

        $basePrice = (float) $basePrice;
        $value = (float) $value;

        if ($type === 'percentage' && $value > 100) {
            throw ValidationException::withMessages([
                'discount_value' => 'Percentage discount cannot be greater than 100%.',
            ]);
        }

        $discountAmount = $type === 'percentage'
            ? $basePrice * ($value / 100)
            : $value;
        $salePrice = round($basePrice - $discountAmount, 2);

        if ($salePrice < 0 || $salePrice >= $basePrice) {
            throw ValidationException::withMessages([
                'discount_value' => 'Discount must produce a sale price below the regular price.',
            ]);
        }

        return $salePrice;
    }

    /* ───── Variation Methods ───── */

    public function storeVariation(Request $request, Product $product)
    {
        $validated = $request->validate([
            'sku'        => 'required|string|unique:product_variations,sku',
            'price'      => 'required|numeric|min:0',
            'stock'      => 'required|integer|min:0',
            'attributes' => 'required|array',
            'is_active'  => 'boolean',
        ]);

        $validated['product_id'] = $product->id;

        ProductVariation::create($validated);

        return back()->with('success', 'Variation created successfully');
    }

    public function updateVariation(Request $request, ProductVariation $variation)
    {
        $validated = $request->validate([
            'sku'        => 'required|string|unique:product_variations,sku,' . $variation->id,
            'price'      => 'required|numeric|min:0',
            'stock'      => 'required|integer|min:0',
            'attributes' => 'required|array',
            'is_active'  => 'boolean',
        ]);

        $variation->update($validated);

        return back()->with('success', 'Variation updated successfully');
    }

    public function destroyVariation(ProductVariation $variation)
    {
        $variation->delete();
        return back()->with('success', 'Variation deleted successfully');
    }

    public function destroyImage(Product $product, ProductImage $image)
    {
        if ((int) $image->product_id !== (int) $product->id) {
            abort(404);
        }

        $deletedPath = $image->path;
        $wasPrimary = (bool) $image->is_primary;

        $image->delete();

        if ($wasPrimary || $product->primary_image === $deletedPath) {
            $product->update(['primary_image' => null]);
        }

        if (! empty($deletedPath)) {
            $isPathStillUsed = ProductImage::where('path', $deletedPath)->exists()
                || Product::where('primary_image', $deletedPath)->exists();

            if (! $isPathStillUsed && Storage::disk('public')->exists($deletedPath)) {
                Storage::disk('public')->delete($deletedPath);
            }
        }

        return back()->with('success', 'Product image deleted successfully.');
    }

    public function destroy(Product $product)
    {
        $product->tags()->detach();
        $product->attributes()->detach();
        $product->delete();
        return back()->with('success', 'Product deleted');
    }
}
