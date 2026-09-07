<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BackendCategoryController extends Controller
{
    public function index()
    {
        // Show only root categories with nested children
        $categories = Category::whereNull('parent_id')
            ->with('childrenRecursive')
            ->withCount('products')
            ->orderBy('position')
            ->get();

        $allCategories = Category::withCount('products')->get();

        return view('backend.categories.index', compact('categories', 'allCategories'));
    }

    public function create()
    {
        $categories = Category::whereNull('parent_id')
            ->with('childrenRecursive')
            ->orderBy('position')
            ->get();

        return view('backend.categories.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'position'    => 'nullable|integer',
            'parent_id'   => 'nullable|exists:categories,id',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $data = $request->only('name', 'description', 'position', 'parent_id');

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        Category::create($data);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function show(Category $category)
    {
        $category->load('parent', 'children.products', 'products');
        return view('backend.categories.show', compact('category'));
    }

    public function edit(Category $category)
    {
        $categories = Category::whereNull('parent_id')
            ->with('childrenRecursive')
            ->where('id', '!=', $category->id)
            ->orderBy('position')
            ->get();

        return view('backend.categories.edit', compact('category', 'categories'));
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'position'    => 'nullable|integer',
            'parent_id'   => 'nullable|exists:categories,id',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $data = $request->only('name', 'description', 'position', 'parent_id');

        if ($request->hasFile('image')) {
            // Delete old image
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($data);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category)
    {
        // Delete category image
        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
