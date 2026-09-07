<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ProductImage;
use Illuminate\Http\Request;

class BackendProductImageController extends Controller
{
    public function index()
    {
        $items = ProductImage::with('product')->paginate(30);
        return view('backend.product_images.index', compact('items'));
    }

    public function destroy(ProductImage $productImage)
    {
        // consider deleting file on disk in future
        $productImage->delete();
        return back()->with('success','Image removed');
    }
}
