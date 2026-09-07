<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Wishlist;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FrontendCartController extends Controller
{
    public function __construct(private readonly CouponService $couponService)
    {
    }

    public function index(): View|RedirectResponse
    {
        $cartItems = $this->currentCartItems();

        if ($cartItems->isEmpty()) {
            return redirect()->route('products.index')
                ->with('error', 'Your cart is empty. Please add products first.');
        }

        $subtotal = (float) $cartItems->sum(fn ($item) => (int) $item->quantity * (float) $item->price);
        $totalQuantity = (int) $cartItems->sum(fn ($item) => (int) $item->quantity);
        $appliedCoupon = $this->getAppliedCouponSummary($cartItems);
        $discount = (float) ($appliedCoupon['discount'] ?? 0);
        $freeItems = collect($appliedCoupon['free_items'] ?? []);
        $shippingCharge = $subtotal >= 5000 ? 0.0 : 199.0;
        $grandTotal = max(0, $subtotal - $discount) + $shippingCharge;
        $availableCoupons = Coupon::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(function (Coupon $coupon) use ($subtotal) {
                $reason = $this->getCouponIneligibilityReason($coupon, (float) $subtotal);
                $validityParts = [];

                if ($coupon->starts_at) {
                    $validityParts[] = 'From ' . $coupon->starts_at->format('d M Y, h:i A');
                }
                if ($coupon->expires_at) {
                    $validityParts[] = 'Till ' . $coupon->expires_at->format('d M Y, h:i A');
                }

                return [
                    'code' => $coupon->code,
                    'type' => $coupon->type,
                    'amount' => (float) $coupon->amount,
                    'buy_quantity' => (int) ($coupon->buy_quantity ?? 0),
                    'get_quantity' => (int) ($coupon->get_quantity ?? 0),
                    'reward_coins' => (int) ($coupon->reward_coins ?? 0),
                    'min_order_amount' => (float) ($coupon->min_order_amount ?? 0),
                    'max_uses' => $coupon->max_uses,
                    'used_count' => (int) $coupon->used_count,
                    'validity_text' => empty($validityParts) ? 'No date restriction' : implode(' | ', $validityParts),
                    'is_applicable' => $reason === null,
                    'ineligible_reason' => $reason,
                ];
            })
            ->values();

        return view('frontend.cart.index', compact('cartItems', 'subtotal', 'discount', 'shippingCharge', 'grandTotal', 'appliedCoupon', 'availableCoupons', 'freeItems'));
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variation_id' => 'nullable|integer|exists:product_variations,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::with(['variations' => fn ($q) => $q->where('is_active', true)])->findOrFail($data['product_id']);

        $hasActiveVariations = $product->variations->isNotEmpty();
        $selectedVariation = null;

        if ($hasActiveVariations) {
            if (empty($data['product_variation_id'])) {
                return back()->with('error', 'Please select a variation before adding to cart.');
            }

            $selectedVariation = ProductVariation::query()
                ->where('id', (int) $data['product_variation_id'])
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->first();

            if (! $selectedVariation) {
                return back()->with('error', 'Selected variation is invalid or unavailable.');
            }
        }

        $variationId = $selectedVariation?->id;
        $unitPrice = $selectedVariation ? (float) $selectedVariation->price : (float) ($product->sale_price ?? $product->base_price);

        if (! Auth::check()) {
            return $this->addToGuestCart($request, $product->id, $variationId, $unitPrice);
        }

        // Merge any items a guest saved before logging in.
        $this->mergeGuestCartToUser(Auth::id());

        $cartItem = Cart::where('user_id', Auth::id())
                        ->where('product_id', $product->id)
                        ->where('product_variation_id', $variationId)
                        ->first();

        if ($cartItem) {
            $cartItem->quantity += (int) $data['quantity'];
            $cartItem->save();
        } else {
            Cart::create([
                'user_id' => Auth::id(),
                'product_id' => $product->id,
                'product_variation_id' => $variationId,
                'quantity' => (int) $data['quantity'],
                'price' => $unitPrice,
            ]);
        }

        return back()->with('success', 'Product added to cart successfully.');
    }

    public function update(Request $request, string $cart): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1|max:99',
        ]);

        $wantsJson = $request->ajax() || $request->wantsJson();

        if (! Auth::check()) {
            return $this->updateGuestCartItem($cart, (int) $data['quantity'], $wantsJson);
        }

        $cartItem = Cart::findOrFail((int) $cart);
        abort_if($cartItem->user_id !== Auth::id(), 403);

        $cartItem->update([
            'quantity' => (int) $data['quantity'],
        ]);

        // AJAX quantity changes get fresh totals back instead of a full page reload.
        if ($wantsJson) {
            return response()->json($this->cartSummaryResponse('Cart quantity updated.'));
        }

        return back()->with('success', 'Cart quantity updated.');
    }

    public function destroy(string $cart): RedirectResponse
    {
        if (! Auth::check()) {
            return $this->destroyGuestCartItem($cart);
        }

        $cartItem = Cart::findOrFail((int) $cart);
        abort_if($cartItem->user_id !== Auth::id(), 403);

        $cartItem->delete();

        return back()->with('success', 'Item removed from cart.');
    }

    public function moveToWishlist(string $cart): RedirectResponse
    {
        if (! Auth::check()) {
            return $this->moveGuestCartItemToWishlist($cart);
        }

        $cartItem = Cart::findOrFail((int) $cart);
        abort_if($cartItem->user_id !== Auth::id(), 403);

        $alreadyInWishlist = Wishlist::where('user_id', Auth::id())
            ->where('product_id', $cartItem->product_id)
            ->where('product_variation_id', $cartItem->product_variation_id)
            ->exists();

        if (! $alreadyInWishlist) {
            Wishlist::create([
                'user_id' => Auth::id(),
                'product_id' => $cartItem->product_id,
                'product_variation_id' => $cartItem->product_variation_id,
            ]);
        }

        $cartItem->delete();

        return back()->with(
            'success',
            $alreadyInWishlist ? 'Item removed from cart (already in wishlist).' : 'Item moved to wishlist.'
        );
    }

    /* ───── Guest (no login) cart helpers ───── */

    private function guestCartItems()
    {
        $entries = (array) (session('guest_cart', []) ?? []);

        if (empty($entries)) {
            return collect();
        }

        $items = [];
        foreach ($entries as $entry) {
            $product = Product::with('images')->find((int) ($entry['product_id'] ?? 0));
            if (! $product) {
                continue;
            }

            $variation = null;
            if (! empty($entry['product_variation_id'])) {
                $variation = ProductVariation::find((int) $entry['product_variation_id']);
            }

            $items[] = (object) [
                'id' => (string) ($entry['key'] ?? 'p' . $product->id),
                'product' => $product,
                'variation' => $variation,
                'quantity' => (int) ($entry['quantity'] ?? 1),
                'price' => (float) ($entry['price'] ?? ($product->sale_price ?? $product->base_price)),
            ];
        }

        return collect($items);
    }

    private function addToGuestCart(Request $request, int $productId, ?int $variationId, float $unitPrice)
    {
        $entries = (array) (session('guest_cart', []) ?? []);
        $key = $variationId ? 'p' . $productId . '_v' . $variationId : 'p' . $productId;
        $quantity = max(1, (int) $request->input('quantity', 1));

        $found = false;
        foreach ($entries as $i => $entry) {
            if (($entry['key'] ?? '') === $key) {
                $entries[$i]['quantity'] = (int) ($entries[$i]['quantity'] ?? 1) + $quantity;
                $found = true;
                break;
            }
        }

        if (! $found) {
            $entries[] = [
                'key' => $key,
                'product_id' => $productId,
                'product_variation_id' => $variationId,
                'quantity' => $quantity,
                'price' => $unitPrice,
            ];
        }

        session(['guest_cart' => $entries]);

        return back()->with('success', 'Product added to cart successfully.');
    }

    private function updateGuestCartItem(string $key, int $qty, bool $wantsJson = false): RedirectResponse|JsonResponse
    {
        $entries = (array) (session('guest_cart', []) ?? []);
        $found = false;

        foreach ($entries as $i => $entry) {
            if (($entry['key'] ?? '') === $key) {
                $clamped = $qty < 1 ? 1 : ($qty > 99 ? 99 : $qty);
                $entries[$i]['quantity'] = $clamped;
                $found = true;
                break;
            }
        }

        if (! $found) {
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found in cart.',
                ], 404);
            }

            return back()->with('error', 'Item not found in cart.');
        }

        session(['guest_cart' => $entries]);

        if ($wantsJson) {
            return response()->json($this->cartSummaryResponse('Cart quantity updated.'));
        }

        return back()->with('success', 'Cart quantity updated.');
    }

    private function destroyGuestCartItem(string $key): RedirectResponse
    {
        $entries = (array) (session('guest_cart', []) ?? []);
        $remaining = [];

        foreach ($entries as $entry) {
            if (($entry['key'] ?? '') !== $key) {
                $remaining[] = $entry;
            }
        }

        session(['guest_cart' => $remaining]);

        return back()->with('success', 'Item removed from cart.');
    }

    private function moveGuestCartItemToWishlist(string $key): RedirectResponse
    {
        $entries = (array) (session('guest_cart', []) ?? []);
        $remaining = [];
        $productId = null;

        foreach ($entries as $entry) {
            if (($entry['key'] ?? '') === $key) {
                $productId = (int) ($entry['product_id'] ?? 0);
                continue;
            }
            $remaining[] = $entry;
        }

        if ($productId > 0 && Product::find($productId)) {
            $ids = (array) (session('guest_wishlist', []) ?? []);
            $already = false;
            foreach ($ids as $id) {
                if ((int) $id === $productId) {
                    $already = true;
                    break;
                }
            }

            if (! $already) {
                $ids[] = $productId;
            }

            session(['guest_wishlist' => $ids]);
        }

        session(['guest_cart' => $remaining]);

        return back()->with('success', 'Item moved to wishlist.');
    }

    private function mergeGuestCartToUser(int $userId): void
    {
        $entries = (array) (session('guest_cart', []) ?? []);

        if (empty($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            $productId = (int) ($entry['product_id'] ?? 0);
            $variationId = $entry['product_variation_id'] ?? null;
            $quantity = max(1, (int) ($entry['quantity'] ?? 1));
            $price = (float) ($entry['price'] ?? 0);

            if ($productId <= 0 || ! Product::find($productId)) {
                continue;
            }

            $existing = Cart::where('user_id', $userId)
                ->where('product_id', $productId)
                ->where('product_variation_id', $variationId)
                ->first();

            if ($existing) {
                $existing->quantity += $quantity;
                $existing->save();
            } else {
                Cart::create([
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'product_variation_id' => $variationId,
                    'quantity' => $quantity,
                    'price' => $price,
                ]);
            }
        }

        session()->forget('guest_cart');
    }

    /* ───── Cart totals (shared by page render & AJAX quantity updates) ───── */

    private function currentCartItems()
    {
        if (! Auth::check()) {
            return $this->guestCartItems();
        }

        return Cart::with('product.images', 'variation')
            ->where('user_id', Auth::id())
            ->get()
            ->filter(fn ($item) => $item->product)
            ->values();
    }

    /**
     * Recomputed cart totals returned to the cart page after an AJAX
     * quantity change so the UI can update itself without a reload.
     */
    private function cartSummaryResponse(string $message): array
    {
        $cartItems = $this->currentCartItems();

        $subtotal = (float) $cartItems->sum(fn ($item) => (int) $item->quantity * (float) $item->price);
        $appliedCoupon = $this->getAppliedCouponSummary($cartItems);
        $discount = (float) ($appliedCoupon['discount'] ?? 0);
        $freeItems = collect($appliedCoupon['free_items'] ?? []);
        $freeByCartId = $freeItems->keyBy(fn ($free) => $free['cart_item']->id);
        $shippingCharge = $subtotal >= 5000 ? 0.0 : 199.0;
        $grandTotal = max(0, $subtotal - $discount) + $shippingCharge;

        return [
            'success' => true,
            'message' => $message,
            'coupon' => $appliedCoupon ? [
                'code' => (string) $appliedCoupon['code'],
                'type' => (string) $appliedCoupon['type'],
                'buy_quantity' => (int) ($appliedCoupon['buy_quantity'] ?? 0),
                'get_quantity' => (int) ($appliedCoupon['get_quantity'] ?? 0),
            ] : null,
            'items' => $cartItems
                ->map(function ($item) use ($freeByCartId) {
                    $freeQty = (int) ($freeByCartId[$item->id]['free_quantity'] ?? 0);

                    return [
                        'id' => (string) $item->id,
                        'quantity' => (int) $item->quantity,
                        'free_quantity' => $freeQty,
                        'line_total' => round(max(0, (int) $item->quantity - $freeQty) * (float) $item->price, 2),
                        'line_gross_total' => round((int) $item->quantity * (float) $item->price, 2),
                    ];
                })
                ->values()
                ->all(),
            'free_items' => $freeItems
                ->map(fn ($free) => [
                    'name' => (string) e(optional($free['cart_item']->product)->name ?? 'Item'),
                    'free_quantity' => (int) $free['free_quantity'],
                ])
                ->values()
                ->all(),
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'shipping_charge' => $shippingCharge,
            'grand_total' => round($grandTotal, 2),
            'cart_count' => (int) $cartItems->sum(fn ($item) => (int) $item->quantity),
        ];
    }

    private function getAppliedCouponSummary($cartItems): ?array
    {
        $stored = session('checkout_coupon');
        $code = strtoupper(trim((string) ($stored['code'] ?? '')));

        if ($code === '') {
            return null;
        }

        $coupon = Coupon::whereRaw('UPPER(TRIM(code)) = ?', [$code])->first();
        if (! $coupon || ! $this->isCouponApplicable($coupon, (float) collect($cartItems)->sum(fn ($item) => (int) $item->quantity * (float) $item->price))) {
            session()->forget('checkout_coupon');

            return null;
        }

        $discount = $this->calculateCouponDiscount($coupon, collect($cartItems));

        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'amount' => (float) $coupon->amount,
            'buy_quantity' => (int) ($coupon->buy_quantity ?? 0),
            'get_quantity' => (int) ($coupon->get_quantity ?? 0),
            'reward_coins' => (int) ($coupon->reward_coins ?? 0),
            'discount' => $discount,
            'free_items' => $this->couponService->freeItemsBreakdown($coupon, collect($cartItems)),
        ];
    }

    private function isCouponApplicable(Coupon $coupon, float $subtotal): bool
    {
        return $this->getCouponIneligibilityReason($coupon, $subtotal) === null;
    }

    private function getCouponIneligibilityReason(Coupon $coupon, float $subtotal): ?string
    {
        if (! $coupon->is_active) {
            return 'This coupon is inactive.';
        }

        $now = now();

        if ($coupon->starts_at && $coupon->starts_at->greaterThan($now)) {
            return 'This coupon is not active yet. It starts on ' . $coupon->starts_at->format('d M Y, h:i A') . '.';
        }

        if ($coupon->expires_at && $coupon->expires_at->lessThan($now)) {
            return 'This coupon expired on ' . $coupon->expires_at->format('d M Y, h:i A') . '.';
        }

        if (! is_null($coupon->max_uses) && (int) $coupon->used_count >= (int) $coupon->max_uses) {
            return 'This coupon has reached its maximum usage limit.';
        }

        if (! is_null($coupon->min_order_amount) && $subtotal < (float) $coupon->min_order_amount) {
            return 'Minimum order amount for this coupon is Rs ' . number_format((float) $coupon->min_order_amount, 2) . '.';
        }

        return null;
    }

    private function calculateCouponDiscount(Coupon $coupon, $cartItems): float
    {
        return $this->couponService->calculateDiscount($coupon, collect($cartItems));
    }
}
