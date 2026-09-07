<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariation;

class OrderInventoryService
{
    public function deductForOrder(Order $order): void
    {
        if ($order->stock_deducted) {
            return;
        }

        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $qty = (int) $item->quantity;
            if ($qty <= 0) {
                continue;
            }

            if (! empty($item->product_variation_id)) {
                $variation = ProductVariation::find($item->product_variation_id);
                if ($variation) {
                    $variation->stock = max(0, (int) $variation->stock - $qty);
                    $variation->save();
                }

                continue;
            }

            if (! empty($item->product_id)) {
                $product = Product::find($item->product_id);
                if ($product && $product->manage_stock) {
                    $product->stock = max(0, (int) $product->stock - $qty);
                    $product->save();
                }
            }
        }

        $order->update(['stock_deducted' => true]);
    }

    public function restockForOrder(Order $order): void
    {
        if (! $order->stock_deducted) {
            return;
        }

        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $qty = (int) $item->quantity;
            if ($qty <= 0) {
                continue;
            }

            if (! empty($item->product_variation_id)) {
                $variation = ProductVariation::find($item->product_variation_id);
                if ($variation) {
                    $variation->stock = (int) $variation->stock + $qty;
                    $variation->save();
                }

                continue;
            }

            if (! empty($item->product_id)) {
                $product = Product::find($item->product_id);
                if ($product && $product->manage_stock) {
                    $product->stock = (int) $product->stock + $qty;
                    $product->save();
                }
            }
        }

        $order->update(['stock_deducted' => false]);
    }
}
