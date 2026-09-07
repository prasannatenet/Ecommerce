<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\Request;

class BackendCartController extends Controller
{
    public function index()
    {
        $carts = Cart::with('user','variation.product')->paginate(20);
        return view('backend.carts.index', compact('carts'));
    }

    public function show(Cart $cart)
    {
        return view('backend.carts.show', compact('cart'));
    }

    public function destroy(Cart $cart)
    {
        $cart->delete();
        return redirect()->route('admin.carts.index')->with('success', 'Cart item removed');
    }
}
