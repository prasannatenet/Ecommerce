<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;

class BackendWishlistController extends Controller
{
    public function index()
    {
        $items = Wishlist::with('user','product')->paginate(30);
        return view('backend.wishlists.index', compact('items'));
    }

    public function destroy(Wishlist $wishlist)
    {
        $wishlist->delete();
        return back()->with('success','Removed');
    }
}
