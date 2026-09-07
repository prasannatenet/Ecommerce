<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ProductVariation;
use Illuminate\Http\Request;

class BackendProductVariationController extends Controller
{
    public function index()
    {
        $items = ProductVariation::with('product')->paginate(20);
        return view('backend.product_variations.index', compact('items'));
    }

    public function create()
    {
        return view('backend.product_variations.create');
    }

    public function store(Request $request)
    {
        $data = $request->only((new ProductVariation)->getFillable());
        ProductVariation::create($data);
        return redirect()->route('admin.product-variations.index')->with('success','Variation created');
    }

    public function edit(ProductVariation $productVariation)
    {
        return view('backend.product_variations.edit', ['item' => $productVariation]);
    }

    public function update(Request $request, ProductVariation $productVariation)
    {
        $data = $request->only($productVariation->getFillable());
        $productVariation->update($data);
        return redirect()->route('admin.product-variations.index')->with('success','Variation updated');
    }

    public function destroy(ProductVariation $productVariation)
    {
        $productVariation->delete();
        return back()->with('success','Deleted');
    }
}
