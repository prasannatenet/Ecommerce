<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;

class BackendOrderItemController extends Controller
{
    public function index()
    {
        $items = OrderItem::with('order','product')->paginate(30);
        return view('backend.order_items.index', compact('items'));
    }
}
