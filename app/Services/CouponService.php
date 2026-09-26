<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CouponService
{
    /**
     * Coupon ids the current customer has already redeemed, loaded at most once
     * per request.
     *
     * @var array<int, int>|null
     */
    protected ?array $usedCouponIds = null;

    /**
     * Ids of every coupon $userId has already redeemed.
     *
     * The cart and checkout pages evaluate the whole visible coupon list, so
     * this is fetched in one query and reused rather than asked per coupon.
     *
     * @return array<int, int>
     */
    public function usedCouponIdsFor(?int $userId = null): array
    {
        $userId ??= Auth::id();

        // Guests are not tracked, so there is nothing to limit them by.
        if (! $userId) {
            return [];
        }

        if ($this->usedCouponIds === null) {
            $this->usedCouponIds = CouponUse::where('user_id', $userId)
                ->distinct()
                ->pluck('coupon_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $this->usedCouponIds;
    }

    /** Has this customer already spent this coupon? */
    public function hasRedeemed(Coupon $coupon, ?int $userId = null): bool
    {
        return in_array((int) $coupon->id, $this->usedCouponIdsFor($userId), true);
    }

    /**
     * Return why a coupon cannot be used, or null when it is eligible.
     */
    public function ineligibilityReason(Coupon $coupon, float $subtotal, bool $hasAppliedCombo = false): ?string
    {
        if ($hasAppliedCombo) {
            return 'Coupons cannot be combined with combo offers.';
        }

        if (! $coupon->is_active) {
            return 'This coupon is inactive.';
        }

        $now = now();

        if ($coupon->starts_at && $coupon->starts_at->greaterThan($now)) {
            return 'This coupon is not active yet. It starts on '.$coupon->starts_at->format('d M Y, h:i A').'.';
        }

        if ($coupon->expires_at && $coupon->expires_at->lessThan($now)) {
            return 'This coupon expired on '.$coupon->expires_at->format('d M Y, h:i A').'.';
        }

        if (! is_null($coupon->max_uses) && (int) $coupon->used_count >= (int) $coupon->max_uses) {
            return 'This coupon has reached its maximum usage limit.';
        }

        // Checked after the global limits so a dead coupon still explains itself
        // to everyone, and a live one the customer has already spent tells them
        // personally.
        if ($this->hasRedeemed($coupon)) {
            return 'You have already used this coupon.';
        }

        if (! is_null($coupon->min_order_amount) && $subtotal < (float) $coupon->min_order_amount) {
            return 'Minimum order amount for this coupon is Rs '.number_format((float) $coupon->min_order_amount, 2).'.';
        }

        return null;
    }

    public function isApplicable(Coupon $coupon, float $subtotal, bool $hasAppliedCombo = false): bool
    {
        return $this->ineligibilityReason($coupon, $subtotal, $hasAppliedCombo) === null;
    }

    /**
     * Display-ready summaries of the running coupons, for the storefront's
     * always-on offer tab (see frontend.partials.floating-offers).
     *
     * Two deliberate differences from the cart's coupon list:
     *
     *  - Expired coupons are dropped instead of being listed as unusable, so
     *    the promotional tab never advertises a dead offer.
     *  - The minimum order value is copy, not a gate, because this tab is shown
     *    while browsing and the shopper's cart is irrelevant here. Hence the
     *    "infinite" subtotal handed to ineligibilityReason().
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function offerSummaries(int $limit = 12): Collection
    {
        $now = now();

        return Coupon::query()
            ->where('is_active', true)
            ->where(function ($query) use ($now): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (Coupon $coupon) {
                $validityParts = [];

                if ($coupon->starts_at) {
                    $validityParts[] = 'From '.$coupon->starts_at->format('d M Y');
                }

                if ($coupon->expires_at) {
                    $validityParts[] = 'Till '.$coupon->expires_at->format('d M Y');
                }

                $reason = $this->ineligibilityReason($coupon, PHP_FLOAT_MAX);

                return [
                    'code' => (string) $coupon->code,
                    'offer_text' => $this->offerText($coupon),
                    'min_order_amount' => (float) ($coupon->min_order_amount ?? 0),
                    'validity_text' => $validityParts !== [] ? implode(' · ', $validityParts) : 'No expiry',
                    'is_available' => $reason === null,
                    'ineligible_reason' => $reason,
                    'is_redeemed' => $this->hasRedeemed($coupon),
                ];
            });
    }

    /**
     * The headline a coupon is advertised with, e.g. "10% OFF" or
     * "Buy 1, Get 1 Free".
     */
    public function offerText(Coupon $coupon): string
    {
        $amount = rtrim(rtrim(number_format((float) $coupon->amount, 2), '0'), '.');

        return match ($coupon->type) {
            'percent' => $amount.'% OFF',
            'fixed' => '₹'.$amount.' OFF',
            'buy_get' => 'Buy '.max(1, (int) $coupon->buy_quantity)
                .', Get '.max(1, (int) $coupon->get_quantity).' Free',
            default => $amount.' Gehna Coins',
        };
    }


    /**
     * Calculate the discount amount a coupon gives against the current cart.
     *
     * For "Buy X Get Y" coupons the cheapest item(s) in the cart become free, so
     * the discount equals the sum of the unit prices of the cheapest free units.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Cart>|array  $cartItems
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
     * @param  \Illuminate\Support\Collection<int, \App\Models\Cart>|array  $cartItems
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
     * @param  \Illuminate\Support\Collection<int, \App\Models\Cart>|array  $cartItems
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
