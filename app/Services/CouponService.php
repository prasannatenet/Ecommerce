<?php

namespace App\Services;

use App\Models\Coupon;
use Illuminate\Support\Collection;

class CouponService
{
    /**
     * Calculate the discount amount a coupon gives against the current cart.
     *
     * For "Buy X Get Y" coupons the cheapest item(s) in the cart become free, so
     * the discount equals the sum of the unit prices of the cheapest free units.
     *
     * @param \Illuminate\Support\Collection<int, \App\Models\Cart>|array $cartItems
     */
    public function calculateDiscount(Coupon $coupon, $cartItems): float
    {
        $cartItems = collect($cartItems);

        $subtotal = (float) $cartItems->sum(fn ($item) => (int) $item->quantity * (float) $item->price);

        $discount = match ($coupon->type) {
            'percent' => ($subtotal * (float) $coupon->amount) / 100,
            'fixed' => (float) $coupon->amount,
            'buy_get' => $this->calculateBuyGetDiscount($coupon, $cartItems),
            default => 0.0,
        };

        $discount = max(0.0, (float) $discount);

        return min($discount, $subtotal);
    }

    /**
     * Which cart lines receive free units for a Buy X Get Y coupon.
     *
     * Only meaningful for "buy_get" coupons; returns an empty collection for
     * every other coupon type.
     *
     * Returns a collection where each entry is:
     * [
     *   'cart_item'     => \App\Models\Cart,
     *   'quantity'      => total quantity of the line,
     *   'price'         => unit price of the line,
     *   'free_quantity' => how many units of this line are free,
     *   'free_amount'   => monetary value of the free units,
     * ]
     *
     * @param \Illuminate\Support\Collection<int, \App\Models\Cart>|array $cartItems
     */
    public function freeItemsBreakdown(Coupon $coupon, $cartItems): Collection
    {
        if ($coupon->type !== 'buy_get') {
            return collect();
        }

        $cartItems = collect($cartItems);

        $freeQuantity = $this->freeUnitCount($coupon, (int) $cartItems->sum(fn ($item) => (int) $item->quantity));

        if ($freeQuantity <= 0) {
            return collect();
        }

        $lines = $cartItems
            ->map(fn ($item) => [
                'cart_item' => $item,
                'quantity' => (int) $item->quantity,
                'price' => (float) $item->price,
            ])
            ->sortBy('price')
            ->values();

        $remaining = $freeQuantity;
        $breakdown = collect();

        foreach ($lines as $line) {
            if ($remaining <= 0) {
                break;
            }

            $freeHere = min($line['quantity'], $remaining);

            $breakdown->push([
                'cart_item' => $line['cart_item'],
                'quantity' => $line['quantity'],
                'price' => $line['price'],
                'free_quantity' => $freeHere,
                'free_amount' => $freeHere * $line['price'],
            ]);

            $remaining -= $freeHere;
        }

        return $breakdown;
    }

    /**
     * Total discount value of a Buy X Get Y coupon: the sum of the unit prices
     * of the cheapest items that are free.
     *
     * @param \Illuminate\Support\Collection<int, \App\Models\Cart>|array $cartItems
     */
    public function calculateBuyGetDiscount(Coupon $coupon, $cartItems): float
    {
        return (float) $this->freeItemsBreakdown($coupon, collect($cartItems))->sum('free_amount');
    }

    private function freeUnitCount(Coupon $coupon, int $totalQuantity): int
    {
        $buyQuantity = max(1, (int) ($coupon->buy_quantity ?? 0));
        $getQuantity = max(1, (int) ($coupon->get_quantity ?? 0));
        $bundleSize = $buyQuantity + $getQuantity;

        return $bundleSize > 0 ? intdiv($totalQuantity, $bundleSize) * $getQuantity : 0;
    }
}