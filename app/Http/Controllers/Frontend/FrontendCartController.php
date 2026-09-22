<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Combo;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Wishlist;
use App\Services\ComboService;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FrontendCartController extends Controller
{
    public function __construct(
        private readonly CouponService $couponService,
        private readonly ComboService $comboService,
    ) {
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

        // Combo price: when every product of an active combo is in the cart the
        // customer pays the discounted combo price instead of the sum of the
        // individual product prices.
        $comboSummary = $this->comboService->summarize($cartItems);
        $comboDiscount = (float) ($comboSummary['discount'] ?? 0);

        $grandTotal = max(0, $subtotal - $comboDiscount - $discount) + $shippingCharge;
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

            $comboSuggestions = $this->getComboSuggestions($cartItems, $comboSummary);

            return view('frontend.cart.index', compact('cartItems', 'subtotal', 'discount', 'shippingCharge', 'grandTotal', 'appliedCoupon', 'availableCoupons', 'freeItems', 'comboSuggestions', 'comboSummary', 'comboDiscount'));
    }

    public function add(Request $request)
    {
        try {
            $data = $request->validate([
                'product_id' => 'required|exists:products,id',
                'product_variation_id' => 'nullable|integer|exists:product_variations,id',
                'quantity' => 'required|integer|min:1',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->cartActionResponse($request, false, 'Please choose a valid product and quantity.', 422);
        }

        $product = Product::with(['variations' => fn ($q) => $q->where('is_active', true)])->findOrFail($data['product_id']);

        $hasActiveVariations = $product->variations->isNotEmpty();
        $selectedVariation = null;

        if ($hasActiveVariations) {
            if (empty($data['product_variation_id'])) {
                return $this->cartActionResponse($request, false, 'Please select a variation before adding to cart.', 422);
            }

            $selectedVariation = ProductVariation::query()
                ->where('id', (int) $data['product_variation_id'])
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->first();

            if (! $selectedVariation) {
                return $this->cartActionResponse($request, false, 'Selected variation is invalid or unavailable.');
            }
        }

        $variationId = $selectedVariation?->id;
        $unitPrice = $selectedVariation ? (float) $selectedVariation->price : (float) ($product->sale_price ?? $product->base_price);

        if (! Auth::check()) {
            return $this->addToGuestCart($request, $product->id, $variationId, $unitPrice, (int) $data['quantity']);
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

        return $this->cartActionResponse($request, true, 'Product added to cart successfully.');
    }

    /**
     * Add a combo offer (multiple products at a bundled price) to the cart.
     * Each product in the combo is added as a separate line, linked by combo_id,
     * with the combo price allocated proportionally across the products.
     */
    public function addCombo(Request $request)
    {
        try {
            $data = $request->validate([
                'combo_id' => 'required|exists:combos,id',
                'quantity' => 'required|integer|min:1',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->cartActionResponse($request, false, 'Invalid combo selected.', 422);
        }

        $combo = Combo::where('id', $data['combo_id'])
            ->with(['products' => fn ($q) => $q->with('images')])
            ->first();

        if (! $combo || ! $combo->isLive()) {
            return $this->cartActionResponse($request, false, 'This combo is no longer available.');
        }

        $quantity = (int) $data['quantity'];
        $products = $combo->products;

        if ($products->isEmpty()) {
            return $this->cartActionResponse($request, false, 'This combo has no products.');
        }

        // Allocate the combo price across products proportionally
        $totalOriginal = (float) $products->sum(fn ($p) => (float) $p->display_price);
        $comboTotalPrice = $combo->comboPrice();
        $allocatedPrices = [];

        foreach ($products as $product) {
            $origPrice = (float) $product->display_price;
            if ($totalOriginal > 0) {
                $allocatedPrices[$product->id] = round(($origPrice / $totalOriginal) * $comboTotalPrice, 2);
            } else {
                $allocatedPrices[$product->id] = 0.0;
            }
        }

        // Adjust for rounding so the sum equals comboTotalPrice
        $allocatedSum = round(array_sum($allocatedPrices), 2);
        $diff = round($comboTotalPrice - $allocatedSum, 2);
        if (abs($diff) > 0.001 && $products->isNotEmpty()) {
            $firstProductId = $products->first()->id;
            $allocatedPrices[$firstProductId] = round($allocatedPrices[$firstProductId] + $diff, 2);
        }

        $message = 'Combo added to cart successfully.';

        if (! Auth::check()) {
            return $this->addToGuestComboCart($request, $combo, $products, $allocatedPrices, $quantity, $message);
        }

        $this->mergeGuestCartToUser(Auth::id());

        foreach ($products as $product) {
            $unitPrice = $allocatedPrices[$product->id];

            $cartItem = Cart::where('user_id', Auth::id())
                ->where('product_id', $product->id)
                ->where('combo_id', $combo->id)
                ->first();

            if ($cartItem) {
                $cartItem->quantity += $quantity;
                $cartItem->save();
            } else {
                Cart::create([
                    'user_id' => Auth::id(),
                    'product_id' => $product->id,
                    'combo_id' => $combo->id,
                    'quantity' => $quantity,
                    'price' => $unitPrice,
                ]);
            }
        }

        return $this->cartActionResponse($request, true, $message);
    }

    private function addToGuestComboCart(Request $request, Combo $combo, $products, array $allocatedPrices, int $quantity, string $message)
    {
        $entries = (array) (session('guest_cart', []) ?? []);

        foreach ($products as $product) {
            $key = 'c' . $combo->id . '_p' . $product->id;
            $unitPrice = $allocatedPrices[$product->id];

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
                    'product_id' => $product->id,
                    'combo_id' => $combo->id,
                    'quantity' => $quantity,
                    'price' => $unitPrice,
                ];
            }
        }

        session(['guest_cart' => $entries]);

        return $this->cartActionResponse($request, true, $message);
    }

    /**
     * Same redirect for normal submits, JSON with fresh counts for AJAX submits.
     */
    private function cartActionResponse(Request $request, bool $success, string $message, int $status = 200, ?int $productId = null)
    {
        $wantsJson = $request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'fetch';

        if ($wantsJson) {
            $summary = $this->cartSummaryResponse($message, $request);

            return response()->json(array_merge($summary, [
                'success' => $success,
                'message' => $message,
            ]), $success ? 200 : $status);
        }

        return $success
            ? back()->with('success', $message)
            : back()->with('error', $message);
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
            return response()->json($this->cartSummaryResponse('Cart quantity updated.', $request));
        }

        return back()->with('success', 'Cart quantity updated.');
    }

    /**
     * Current quantity of SIMPLE (variation-free) products, keyed by product id.
     * Product cards use this to swap "Add to Cart" with an inline counter.
     */
    public function quantities(): JsonResponse
    {
        $items = $this->currentCartItems();

        return response()->json([
            'success' => true,
            'quantities' => $this->simpleProductQuantities($items),
            'cart_count' => (int) $items->sum(fn ($item) => (int) $item->quantity),
        ]);
    }

    /**
     * Set an exact quantity for a SIMPLE product from a product card.
     * quantity=0 removes the line so the card flips back to "Add to Cart".
     */
    public function setQuantityByProduct(Request $request): RedirectResponse|JsonResponse
    {
        try {
            $data = $request->validate([
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:0|max:99',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->cartActionResponse($request, false, 'Please choose a valid product and quantity.', 422);
        }

        $productId = (int) $data['product_id'];
        $quantity = (int) $data['quantity'];
        $product = Product::with(['variations' => fn ($q) => $q->where('is_active', true)])->findOrFail($productId);

        if ($product->variations->isNotEmpty()) {
            return $this->cartActionResponse($request, false, 'Please select a variation before updating the cart.', 422);
        }

        if ($quantity > 0 && (bool) ($product->manage_stock ?? false) && (int) ($product->stock ?? 0) < $quantity) {
            return $this->cartActionResponse($request, false, 'Only ' . (int) ($product->stock ?? 0) . ' available in stock.', 422);
        }

        $unitPrice = (float) ($product->sale_price ?? $product->base_price);

        if (! Auth::check()) {
            $entries = array_values((array) (session('guest_cart', []) ?? []));
            $key = 'p' . $productId;
            $found = null;
            foreach ($entries as $i => $entry) {
                if (($entry['key'] ?? '') === $key) {
                    $found = $i;
                    break;
                }
            }

            if ($quantity <= 0) {
                if ($found !== null) {
                    unset($entries[$found]);
                    session(['guest_cart' => array_values($entries)]);
                }

                return $this->cartActionResponse($request, true, 'Removed from cart.', 200, $productId);
            }

            if ($found !== null) {
                $entries[$found]['quantity'] = $quantity;
                $entries[$found]['price'] = $unitPrice;
            } else {
                $entries[] = [
                    'key' => $key,
                    'product_id' => $productId,
                    'product_variation_id' => null,
                    'quantity' => $quantity,
                    'price' => $unitPrice,
                ];
            }

            session(['guest_cart' => $entries]);

            $msg = $quantity === 1 ? 'Product added to cart successfully.' : 'Cart quantity updated.';

            return $this->cartActionResponse($request, true, $msg, 200, $productId);
        }

        $this->mergeGuestCartToUser(Auth::id());

        $cartItem = Cart::where('user_id', Auth::id())
            ->where('product_id', $productId)
            ->whereNull('product_variation_id')
            ->first();

        if (! $cartItem) {
            $cartItem = Cart::where('user_id', Auth::id())
                ->where('product_id', $productId)
                ->where('product_variation_id', 0)
                ->first();
        }

        if ($quantity <= 0) {
            $cartItem?->delete();

            return $this->cartActionResponse($request, true, 'Removed from cart.', 200, $productId);
        }

        if ($cartItem) {
            $cartItem->quantity = $quantity;
            $cartItem->price = $unitPrice;
            $cartItem->save();
        } else {
            Cart::create([
                'user_id' => Auth::id(),
                'product_id' => $productId,
                'product_variation_id' => null,
                'quantity' => $quantity,
                'price' => $unitPrice,
            ]);
        }

        $msg = $quantity === 1 ? 'Product added to cart successfully.' : 'Cart quantity updated.';

        return $this->cartActionResponse($request, true, $msg, 200, $productId);
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
                'combo_id' => $entry['combo_id'] ?? null,
                'quantity' => (int) ($entry['quantity'] ?? 1),
                'price' => (float) ($entry['price'] ?? ($product->sale_price ?? $product->base_price)),
            ];
        }

        return collect($items);
    }

    private function addToGuestCart(Request $request, int $productId, ?int $variationId, float $unitPrice, int $quantity = 1)
    {
        $entries = (array) (session('guest_cart', []) ?? []);
        $key = $variationId ? 'p' . $productId . '_v' . $variationId : 'p' . $productId;
        $quantity = max(1, (int) $request->input('quantity', $quantity));

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

        return $this->cartActionResponse($request, true, 'Product added to cart successfully.');
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
            $comboId = $entry['combo_id'] ?? null;
            $quantity = max(1, (int) ($entry['quantity'] ?? 1));
            $price = (float) ($entry['price'] ?? 0);

            if ($productId <= 0 || ! Product::find($productId)) {
                continue;
            }

            $existing = Cart::where('user_id', $userId)
                ->where('product_id', $productId)
                ->where('product_variation_id', $variationId)
                ->where('combo_id', $comboId)
                ->first();

            if ($existing) {
                $existing->quantity += $quantity;
                $existing->save();
            } else {
                Cart::create([
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'product_variation_id' => $variationId,
                    'combo_id' => $comboId,
                    'quantity' => $quantity,
                    'price' => $price,
                ]);
            }
        }

        session()->forget('guest_cart');
    }

    /* ───── Cart totals (shared by page render & AJAX quantity updates) ───── */

    /**
     * Find combos where some (but not all) products are already in the cart.
     * Suggested to the user as "complete the combo to save X%".
     */
    private function getComboSuggestions($cartItems, ?array $comboSummary = null): array
    {
        $comboSummary ??= $this->comboService->summarize($cartItems);

        $cartProductIds = $cartItems
            ->filter(fn ($item) => empty($item->combo_id))
            ->map(fn ($item) => (int) ($item->product_id ?? optional($item->product)->id ?? 0))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($cartProductIds)) {
            return [];
        }

        $combos = Combo::whereHas('products', fn ($q) => $q->whereIn('products.id', $cartProductIds))
            ->active()
            ->with(['products' => fn ($q) => $q->with('images')])
            ->get();

        // Combos this cart has already unlocked, keyed by combo id, so the banner
        // and the order summary always quote the same combo price.
        $appliedCombos = collect($comboSummary['combos'] ?? [])->keyBy(fn ($row) => (int) $row['combo']->id);

        $incomplete = [];   // some products in cart, some missing
        $complete   = [];   // all products in cart → show combo price

        foreach ($combos as $combo) {
            $comboProductIds = $combo->products->pluck('id')->toArray();
            $inCart  = $combo->products->filter(fn ($p) =>  in_array($p->id, $cartProductIds, true));
            $missing = $combo->products->filter(fn ($p) => !in_array($p->id, $cartProductIds, true));

            if ($missing->isEmpty() && $inCart->isNotEmpty()) {
                // Every product of this combo is in the cart → combo is complete
                $applied = $appliedCombos->get((int) $combo->id);

                $complete[] = [
                    'combo'           => $combo,
                    'in_cart'         => $inCart,
                    'combo_price'     => $applied['combo_price'] ?? $combo->comboPrice(),
                    'original_total'  => $applied['regular_total'] ?? $combo->productsTotal(),
                    'discount_amount' => $applied['discount'] ?? $combo->discountAmount(),
                    'savings_percent' => $applied['savings_percent'] ?? $combo->savingsPercent(),
                ];
            } elseif ($inCart->isNotEmpty()) {
                // Partial match → show suggestion to add missing products
                $incomplete[] = [
                    'combo'           => $combo,
                    'in_cart'         => $inCart,
                    'missing'         => $missing,
                    'combo_price'     => $combo->comboPrice(),
                    'original_total'  => $combo->productsTotal(),
                    'discount_amount' => $combo->discountAmount(),
                    'savings_percent' => $combo->savingsPercent(),
                ];
            }
        }

        return [
            'incomplete' => $incomplete,
            'complete'   => $complete,
        ];
    }


    private function currentCartItems()
    {
        if (! Auth::check()) {
            return $this->guestCartItems();
        }

        return Cart::with('product.images', 'variation', 'combo')
            ->where('user_id', Auth::id())
            ->get()
            ->filter(fn ($item) => $item->product)
            ->values();
    }

    /**
     * Recomputed cart totals returned to the cart page after an AJAX
     * quantity change so the UI can update itself without a reload.
     */
    private function cartSummaryResponse(string $message, ?\Illuminate\Http\Request $request = null): array
    {
        $cartItems = $this->currentCartItems();

        $subtotal = (float) $cartItems->sum(fn ($item) => (int) $item->quantity * (float) $item->price);
        $appliedCoupon = $this->getAppliedCouponSummary($cartItems);
        $discount = (float) ($appliedCoupon['discount'] ?? 0);
        $freeItems = collect($appliedCoupon['free_items'] ?? []);
        $freeByCartId = $freeItems->keyBy(fn ($free) => $free['cart_item']->id);

        // Same combo price maths the page render uses, so AJAX updates never
        // drift away from what the customer sees.
        $comboSummary = $this->comboService->summarize($cartItems);
        $comboDiscount = (float) ($comboSummary['discount'] ?? 0);

        $shippingCharge = $subtotal >= 5000 ? 0.0 : 199.0;
        $grandTotal = max(0, $subtotal - $comboDiscount - $discount) + $shippingCharge;

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
                ->map(function ($item) use ($freeByCartId, $comboSummary) {
                    $freeQty = (int) ($freeByCartId[$item->id]['free_quantity'] ?? 0);
                    $allocation = $comboSummary['line_allocations'][(string) $item->id] ?? null;

                    // Combo lines are charged the allocated combo price; lines
                    // with coupon free units keep their existing charged value.
                    $lineTotal = $freeQty > 0
                        ? round(max(0, (int) $item->quantity - $freeQty) * (float) $item->price, 2)
                        : $this->comboService->chargedLineTotal($item, $comboSummary);

                    return [
                        'id' => (string) $item->id,
                        'quantity' => (int) $item->quantity,
                        'free_quantity' => $freeQty,
                        'combo_units' => (int) ($allocation['combo_units'] ?? 0),
                        'line_total' => $lineTotal,
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
            'combo_discount' => round($comboDiscount, 2),
            'shipping_charge' => $shippingCharge,
            'grand_total' => round($grandTotal, 2),
            'cart_count' => (int) $cartItems->sum(fn ($item) => (int) $item->quantity),
            'quantities' => $this->simpleProductQuantities($cartItems),
            'product_id' => $request ? (int) $request->input('product_id', 0) ?: null : null,
            'combo_suggestions_html' => view('frontend.partials.cart-combo-suggestions', ['comboSuggestions' => $this->getComboSuggestions($cartItems, $comboSummary)])->render(),
            'combo_price_html' => view('frontend.partials.cart-combo-total', ['comboSummary' => $comboSummary])->render(),
        ];
    }

    private function simpleProductQuantities($cartItems): array
    {
        $map = [];

        foreach ($cartItems as $item) {
            $variationId = $item->variation_id
                ?? $item->product_variation_id
                ?? ($item->variation->id ?? null)
                ?? null;

            if (! empty($variationId)) {
                continue;
            }

            // Exclude combo items — they have their own add-to-cart logic.
            if (! empty($item->combo_id)) {
                continue;
            }

            $productId = (int) ($item->product_id ?? optional($item->product)->id ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $key = (string) $productId;
            $map[$key] = ($map[$key] ?? 0) + (int) $item->quantity;
        }

        return $map;
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
