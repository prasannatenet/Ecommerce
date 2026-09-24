<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Jobs\SendOrderInvoiceMail;
use App\Mail\OrderCreditNoteMail;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\PaymentProvider;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\ComboService;
use App\Services\CouponService;
use App\Services\OrderInventoryService;
use App\Services\ProductRecommendationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FrontendCheckoutController extends Controller
{
    public function __construct(
        private readonly OrderInventoryService $inventoryService,
        private readonly CouponService $couponService,
        private readonly ComboService $comboService,
        private readonly ProductRecommendationService $recommendations,
    ) {}

    public function index(): View|RedirectResponse
    {
        $cartItems = $this->loadUserCart();

        if ($cartItems->isEmpty()) {
            return redirect()->route('products.index')
                ->with('error', 'Your cart is empty. Please add products before checkout.');
        }

        $subtotal = $cartItems->sum(fn ($item) => $item->quantity * (float) $item->price);
        $totalQuantity = (int) $cartItems->sum(fn ($item) => (int) $item->quantity);

        // Combo price: when every product of an active combo is in the cart the
        // customer pays the combo price instead of the individual prices.
        $comboSummary = $this->comboService->summarize($cartItems);
        $hasAppliedCombo = $this->comboService->hasAppliedCombo($cartItems, $comboSummary);
        $appliedCoupon = $this->getAppliedCouponSummary($cartItems, $hasAppliedCombo);
        $discount = (float) ($appliedCoupon['discount'] ?? 0);
        $freeItems = collect($appliedCoupon['free_items'] ?? []);
        $comboDiscount = (float) ($comboSummary['discount'] ?? 0);

        $shippingCharge = (float) $subtotal >= 5000 ? 0.0 : 199.0;
        $orderTotal = max(0, (float) $subtotal - $comboDiscount - $discount) + $shippingCharge;
        $appliedCoins = $this->getAppliedCoinsSummary($orderTotal);
        $coinsDiscount = (float) ($appliedCoins['discount'] ?? 0);
        $coinsBalance = (int) ($appliedCoins['balance'] ?? 0);
        $grandTotal = max(0, $orderTotal - $coinsDiscount);
        $availableCoupons = Coupon::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(function (Coupon $coupon) use ($subtotal, $hasAppliedCombo) {
                $reason = $this->getCouponIneligibilityReason($coupon, (float) $subtotal, $hasAppliedCombo);
                $validityParts = [];

                if ($coupon->starts_at) {
                    $validityParts[] = 'From '.$coupon->starts_at->format('d M Y, h:i A');
                }
                if ($coupon->expires_at) {
                    $validityParts[] = 'Till '.$coupon->expires_at->format('d M Y, h:i A');
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

        $checkoutToken = (string) session('checkout_token', '');
        $checkoutTokenBelongsToOpenOrder = $checkoutToken !== '' && Order::query()
            ->where('user_id', Auth::id())
            ->where('checkout_token', $checkoutToken)
            ->whereIn('payment_status', ['pending', 'initiated', 'failed'])
            ->exists();

        if ($checkoutToken === '' || ! $checkoutTokenBelongsToOpenOrder) {
            $checkoutToken = (string) Str::uuid();
            session(['checkout_token' => $checkoutToken]);
        }

        $providers = PaymentProvider::whereIn('slug', ['cod', 'razorpay'])
            ->where('is_active', true)
            ->get()
            ->keyBy('slug');

        return view('frontend.checkout.index', [
            'cartItems' => $cartItems,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'comboDiscount' => $comboDiscount,
            'comboSummary' => $comboSummary,
            'hasAppliedCombo' => $hasAppliedCombo,
            'freeItems' => $freeItems,
            'shippingCharge' => $shippingCharge,
            'grandTotal' => $grandTotal,
            'appliedCoupon' => $appliedCoupon,
            'appliedCoins' => $appliedCoins,
            'coinsBalance' => $coinsBalance,
            'availableCoupons' => $availableCoupons,
            'codProvider' => $providers->get('cod'),
            'razorpayProvider' => $providers->get('razorpay'),
            'user' => Auth::user(),
            'checkoutToken' => $checkoutToken,
        ]);
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'coupon_code' => 'required|string|max:50',
        ]);

        $cartItems = $this->loadUserCart();
        if ($cartItems->isEmpty()) {
            return redirect()->route('products.index')->with('error', 'Your cart is empty.');
        }

        $subtotal = (float) $cartItems->sum(fn ($item) => $item->quantity * (float) $item->price);
        $comboSummary = $this->comboService->summarize($cartItems);
        $hasAppliedCombo = $this->comboService->hasAppliedCombo($cartItems, $comboSummary);
        if ($hasAppliedCombo) {
            session()->forget('checkout_coupon');
        }
        $normalizedCode = strtoupper(trim($data['coupon_code']));
        $coupon = Coupon::whereRaw('UPPER(TRIM(code)) = ?', [$normalizedCode])->first();

        if (! $coupon) {
            return back()->with('error', 'Coupon code not found.');
        }

        $ineligibilityReason = $this->getCouponIneligibilityReason($coupon, $subtotal, $hasAppliedCombo);
        if ($ineligibilityReason !== null) {
            return back()->with('error', $ineligibilityReason);
        }

        session([
            'checkout_coupon' => [
                'code' => $coupon->code,
            ],
        ]);

        return back()->with('success', 'Coupon applied successfully.');
    }

    public function removeCoupon(): RedirectResponse
    {
        session()->forget('checkout_coupon');

        return back()->with('success', 'Coupon removed.');
    }

    public function applyCoins(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'coins' => 'required|integer|min:1',
        ]);

        $cartItems = $this->loadUserCart();
        if ($cartItems->isEmpty()) {
            return redirect()->route('products.index')->with('error', 'Your cart is empty.');
        }

        $balance = (int) (Auth::user()->gehna_coins ?? 0);
        if ($balance < 1) {
            return back()->with('error', 'You do not have any Gehna Coins to redeem yet.');
        }

        $subtotal = (float) $cartItems->sum(fn ($item) => $item->quantity * (float) $item->price);
        $appliedCoupon = $this->getAppliedCouponSummary($cartItems);
        $discount = (float) ($appliedCoupon['discount'] ?? 0);
        $comboDiscount = (float) ($this->comboService->summarize($cartItems)['discount'] ?? 0);
        $shippingCharge = $subtotal >= 5000 ? 0.0 : 199.0;
        $orderTotal = max(0, $subtotal - $comboDiscount - $discount) + $shippingCharge;

        $maxUsable = max(0, min($balance, (int) floor($orderTotal)));
        if ($maxUsable < 1) {
            return back()->with('error', 'Gehna Coins cannot be applied to this order right now.');
        }

        $requested = (int) $data['coins'];
        $used = min($requested, $maxUsable);

        session(['checkout_coins' => ['coins' => $used]]);

        if ($used < $requested) {
            return back()->with('success', 'Gehna Coins applied: '.$used.' coins (limited by your coin balance and order total).');
        }

        return back()->with('success', 'Gehna Coins applied: '.$used.' coins = Rs '.number_format((float) $used, 2).' off your order.');
    }

    public function removeCoins(): RedirectResponse
    {
        session()->forget('checkout_coins');

        return back()->with('success', 'Gehna Coins removed.');
    }

    public function placeOrder(Request $request)
    {
        $data = $request->validate([
            'payment_method' => 'required|in:cod,razorpay',
            'checkout_token' => 'nullable|uuid',
            'billing_name' => 'required|string|max:100',
            'billing_email' => 'required|email|max:100',
            'billing_phone' => 'required|string|max:20',
            'billing_line1' => 'required|string|max:255',
            'billing_city' => 'required|string|max:100',
            'billing_state' => 'required|string|max:100',
            'billing_zip' => 'required|string|max:20',
            'billing_country' => 'required|string|max:100',
            'shipping_same_as_billing' => 'nullable|boolean',
            'shipping_name' => 'nullable|string|max:100',
            'shipping_phone' => 'nullable|string|max:20',
            'shipping_line1' => 'nullable|string|max:255',
            'shipping_city' => 'nullable|string|max:100',
            'shipping_state' => 'nullable|string|max:100',
            'shipping_zip' => 'nullable|string|max:20',
            'shipping_country' => 'nullable|string|max:100',
        ]);

        $checkoutToken = (string) ($data['checkout_token'] ?? session('checkout_token', ''));
        if ($checkoutToken === '') {
            $checkoutToken = (string) Str::uuid();
        }

        $existingOrder = Order::where('checkout_token', $checkoutToken)->first();
        if ($existingOrder && in_array($existingOrder->status, ['cancelled', 'refunded'], true)) {
            $checkoutToken = (string) Str::uuid();
            $existingOrder = null;
        }
        if ($existingOrder && (int) $existingOrder->user_id !== (int) Auth::id()) {
            $checkoutToken = (string) Str::uuid();
            $existingOrder = null;
        }
        session(['checkout_token' => $checkoutToken]);

        if ($existingOrder) {
            return $this->checkoutOrderResponse($existingOrder);
        }

        $provider = PaymentProvider::where('slug', $data['payment_method'])
            ->where('is_active', true)
            ->first();

        if (! $provider) {
            throw ValidationException::withMessages([
                'payment_method' => 'Selected payment method is not active right now.',
            ]);
        }

        $cartItems = $this->loadUserCart();
        if ($cartItems->isEmpty()) {
            return redirect()->route('products.index')->with('error', 'Your cart is empty.');
        }

        $subtotal = (float) $cartItems->sum(fn ($item) => $item->quantity * (float) $item->price);
        $comboSummary = $this->comboService->summarize($cartItems);
        $hasAppliedCombo = $this->comboService->hasAppliedCombo($cartItems, $comboSummary);
        $appliedCoupon = $this->getAppliedCouponSummary($cartItems, $hasAppliedCombo);
        $discount = (float) ($appliedCoupon['discount'] ?? 0);
        $comboDiscount = (float) ($comboSummary['discount'] ?? 0);

        $shippingCharge = $subtotal >= 5000 ? 0.0 : 199.0;
        $orderTotal = max(0, $subtotal - $comboDiscount - $discount) + $shippingCharge;
        $appliedCoins = $this->getAppliedCoinsSummary($orderTotal);
        $coinsUsed = (int) ($appliedCoins['coins'] ?? 0);
        $coinsDiscount = (float) $coinsUsed;
        $orderTotal = max(0, $orderTotal - $coinsDiscount);
        $shippingSame = $request->boolean('shipping_same_as_billing', true);

        if ($data['payment_method'] === 'razorpay' && $orderTotal < 1) {
            throw ValidationException::withMessages([
                'payment_method' => 'Gehna Coins cover the entire order amount. Please choose Cash on Delivery or reduce the coins used.',
            ]);
        }

        $billingAddress = [
            'name' => $data['billing_name'],
            'email' => $data['billing_email'],
            'phone' => $data['billing_phone'],
            'line1' => $data['billing_line1'],
            'city' => $data['billing_city'],
            'state' => $data['billing_state'],
            'zip' => $data['billing_zip'],
            'country' => $data['billing_country'],
        ];

        $shippingAddress = $shippingSame
            ? $billingAddress
            : [
                'name' => $request->input('shipping_name'),
                'phone' => $request->input('shipping_phone'),
                'line1' => $request->input('shipping_line1'),
                'city' => $request->input('shipping_city'),
                'state' => $request->input('shipping_state'),
                'zip' => $request->input('shipping_zip'),
                'country' => $request->input('shipping_country'),
            ];

        try {
            $order = DB::transaction(function () use ($provider, $subtotal, $discount, $comboDiscount, $comboSummary, $coinsUsed, $coinsDiscount, $shippingCharge, $orderTotal, $billingAddress, $shippingAddress, $data, $cartItems, $appliedCoupon, $checkoutToken) {
                $buyer = Auth::user() ? User::whereKey(Auth::id())->lockForUpdate()->first() : null;

                if ($coinsUsed > 0 && (! $buyer || (int) $buyer->gehna_coins < $coinsUsed)) {
                    throw ValidationException::withMessages([
                        'coins' => 'You do not have enough Gehna Coins to complete this order.',
                    ]);
                }

                $order = Order::create([
                    'user_id' => Auth::id(),
                    'checkout_token' => $checkoutToken,
                    'payment_provider_id' => $provider->id,
                    'status' => 'pending',
                    'payment_method' => $data['payment_method'],
                    'payment_status' => $data['payment_method'] === 'cod' ? 'pending' : 'initiated',
                    'refund_status' => 'none',
                    'total' => $orderTotal,
                    'refunded_total' => 0,
                    'billing_address' => $billingAddress,
                    'shipping_address' => $shippingAddress,
                    'payment_meta' => [
                        'pricing' => [
                            'subtotal' => $subtotal,
                            'combo_discount' => $comboDiscount,
                            'discount' => $discount,
                            'coins_used' => $coinsUsed,
                            'coins_discount' => $coinsDiscount,
                            'shipping_charge' => $shippingCharge,
                            'grand_total' => $orderTotal,
                        ],
                        'combos' => collect($comboSummary['combos'] ?? [])->map(fn ($applied) => [
                            'combo_id' => $applied['combo']->id,
                            'name' => $applied['combo']->name,
                            'sets' => (int) $applied['sets'],
                            'regular_total' => (float) $applied['regular_total'],
                            'combo_price' => (float) $applied['combo_price'],
                            'discount' => (float) $applied['discount'],
                        ])->values()->all(),
                        'coupon' => $appliedCoupon ? [
                            'id' => $appliedCoupon['id'],
                            'code' => $appliedCoupon['code'],
                            'type' => $appliedCoupon['type'],
                            'amount' => $appliedCoupon['amount'],
                            'buy_quantity' => $appliedCoupon['buy_quantity'],
                            'get_quantity' => $appliedCoupon['get_quantity'],
                            'reward_coins' => $appliedCoupon['reward_coins'],
                            'discount' => $appliedCoupon['discount'],
                            'free_items' => collect($appliedCoupon['free_items'] ?? [])->map(fn ($free) => [
                                'product_id' => $free['cart_item']->product_id,
                                'product_variation_id' => $free['cart_item']->product_variation_id,
                                'product_name' => optional($free['cart_item']->product)->name ?? 'Product',
                                'unit_price' => $free['price'],
                                'quantity' => $free['quantity'],
                                'free_quantity' => $free['free_quantity'],
                                'free_amount' => $free['free_amount'],
                            ])->values()->all(),
                        ] : null,
                    ],
                ]);

                if ($appliedCoupon) {
                    Coupon::whereKey($appliedCoupon['id'])->increment('used_count');

                    // Credit Gehna Coins when the order used a coins reward coupon.
                    if (($appliedCoupon['type'] ?? '') === 'gehna_coins' && (int) ($appliedCoupon['reward_coins'] ?? 0) > 0) {
                        User::whereKey(Auth::id())->increment('gehna_coins', (int) $appliedCoupon['reward_coins']);
                    }
                }

                // Debit the redeemed Gehna Coins from the buyer's balance.
                if ($coinsUsed > 0) {
                    $buyer->decrement('gehna_coins', $coinsUsed);
                }

                foreach ($cartItems as $cartItem) {
                    $variation = $cartItem->variation;
                    $variationLabel = collect($variation?->attributes ?? [])->map(fn ($val, $key) => ucfirst($key).': '.$val)->implode(' | ');
                    $productName = optional($cartItem->product)->name ?? 'Product';

                    if ($variationLabel !== '') {
                        $productName .= ' ('.$variationLabel.')';
                    }

                    // Lines unlocked by a combo are billed at their allocated combo
                    // price, so the order items add up to the discounted subtotal.
                    $lineAllocation = $comboSummary['line_allocations'][(string) $cartItem->id] ?? null;
                    $lineTotal = $this->comboService->chargedLineTotal($cartItem, $comboSummary);
                    $lineQuantity = max(1, (int) $cartItem->quantity);

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $cartItem->product_id,
                        'product_variation_id' => $cartItem->product_variation_id,
                        'product_name' => $productName,
                        'sku' => $variation?->sku ?? optional($cartItem->product)->sku,
                        'unit_price' => $lineAllocation ? round($lineTotal / $lineQuantity, 2) : (float) $cartItem->price,
                        'quantity' => (int) $cartItem->quantity,
                        'line_total' => $lineTotal,
                        'meta' => [
                            'from_cart_id' => $cartItem->id,
                            'variation_attributes' => $variation?->attributes,
                            'combo_names' => $lineAllocation['combo_names'] ?? [],
                            'combo_discount' => (float) ($lineAllocation['discount'] ?? 0),
                        ],
                    ]);
                }

                PaymentTransaction::create([
                    'order_id' => $order->id,
                    'payment_provider_id' => $provider->id,
                    'type' => 'payment',
                    'status' => $data['payment_method'] === 'cod' ? 'pending' : 'initiated',
                    'amount' => $orderTotal,
                    'currency' => 'INR',
                    'notes' => [
                        'method' => $data['payment_method'],
                    ],
                ]);

                return $order;
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            $existingOrder = Order::where('checkout_token', $checkoutToken)
                ->where('user_id', Auth::id())
                ->first();

            if ($existingOrder) {
                return $this->checkoutOrderResponse($existingOrder);
            }

            throw $exception;
        }

        session()->forget(['checkout_coupon', 'checkout_coins']);

        return $this->checkoutOrderResponse($order->fresh());
    }

    /**
     * Return the already-created order for an idempotent checkout submission.
     * COD completes immediately; online payments reuse the existing gateway order.
     */
    private function checkoutOrderResponse(Order $order): RedirectResponse|JsonResponse
    {
        if ($order->payment_method === 'cod') {
            $this->queueInvoiceMail($order);
            Cart::where('user_id', $order->user_id)->delete();
            session()->forget(['checkout_coupon', 'checkout_coins', 'checkout_token']);

            return redirect()->route('checkout.success', $order)
                ->with('success', 'Order placed successfully with Cash on Delivery.');
        }

        if ($order->payment_status === 'paid') {
            Cart::where('user_id', $order->user_id)->delete();
            session()->forget(['checkout_coupon', 'checkout_coins', 'checkout_token']);

            return response()->json([
                'message' => 'Payment already completed successfully.',
                'order' => ['id' => $order->id],
                'redirect_url' => route('checkout.success', $order),
            ]);
        }

        $provider = PaymentProvider::find($order->payment_provider_id);
        if (! $provider) {
            return response()->json([
                'message' => 'The payment provider is no longer available for this order.',
            ], 422);
        }

        $gatewayOrder = $this->ensureRazorpayOrder($order, $provider);
        $order->refresh();

        return response()->json([
            'order' => ['id' => $order->id],
            'razorpay' => [
                'key' => $provider->public_key,
                'order_id' => $gatewayOrder['id'],
                'amount' => (int) round((float) $order->total * 100),
                'currency' => 'INR',
            ],
        ]);
    }

    /**
     * Create the gateway order while holding the local order row lock. A repeated
     * browser submission therefore reuses one gateway order instead of creating
     * multiple payable attempts for the same local order.
     */
    private function ensureRazorpayOrder(Order $order, PaymentProvider $provider): array
    {
        return DB::transaction(function () use ($order, $provider): array {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->gateway_order_id) {
                return [
                    'id' => $lockedOrder->gateway_order_id,
                    'amount' => (int) round((float) $lockedOrder->total * 100),
                    'currency' => 'INR',
                ];
            }

            $gatewayOrder = $this->createRazorpayOrder($provider, $lockedOrder);
            $meta = $lockedOrder->payment_meta ?? [];
            $lockedOrder->update([
                'gateway_order_id' => $gatewayOrder['id'],
                'payment_meta' => array_merge($meta, [
                    'razorpay_order' => $gatewayOrder,
                ]),
            ]);

            $lockedOrder->paymentTransactions()
                ->where('type', 'payment')
                ->whereNull('gateway_order_id')
                ->latest('id')
                ->first()?->update([
                    'gateway_order_id' => $gatewayOrder['id'],
                    'payload' => $gatewayOrder,
                ]);

            return $gatewayOrder;
        });
    }

    public function paymentStatus(Order $order): JsonResponse
    {
        abort_if((int) $order->user_id !== (int) Auth::id(), 403);

        $transaction = $order->paymentTransactions()
            ->where('type', 'payment')
            ->latest('id')
            ->first();
        $state = match ($order->payment_method) {
            'cod' => 'cod_confirmed',
            default => match ($order->payment_status) {
                'paid' => 'paid',
                'failed' => 'failed',
                default => 'processing',
            },
        };

        return response()->json([
            'order_id' => $order->id,
            'state' => $state,
            'payment_status' => $order->payment_status,
            'transaction_status' => $transaction?->status,
            'message' => match ($state) {
                'paid' => 'Payment successful. Your order is confirmed.',
                'failed' => 'Payment was not completed. You can safely retry this order.',
                'cod_confirmed' => 'Order confirmed. Payment is due on delivery.',
                default => 'Payment is being confirmed. Please do not pay again.',
            },
            'redirect_url' => $state === 'paid' || $state === 'cod_confirmed'
                ? route('checkout.success', $order)
                : null,
        ])->header('Cache-Control', 'no-store');
    }

    public function verifyRazorpay(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $order = Order::where('id', $data['order_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $provider = PaymentProvider::find($order->payment_provider_id);
        if (! $provider || $provider->slug !== 'razorpay') {
            return response()->json([
                'message' => 'Invalid payment provider for this order.',
            ], 422);
        }

        if ($order->gateway_order_id && $order->gateway_order_id !== $data['razorpay_order_id']) {
            return response()->json([
                'message' => 'This payment does not belong to the selected order.',
            ], 422);
        }

        if ($order->payment_status === 'paid') {
            Cart::where('user_id', Auth::id())->delete();
            session()->forget(['checkout_coupon', 'checkout_coins', 'checkout_token']);

            return response()->json([
                'message' => 'Payment already completed successfully.',
                'redirect_url' => route('checkout.success', $order),
            ]);
        }

        $payload = $data['razorpay_order_id'].'|'.$data['razorpay_payment_id'];
        $expectedSignature = hash_hmac('sha256', $payload, (string) $provider->secret_key);

        if (! hash_equals($expectedSignature, $data['razorpay_signature'])) {
            $wasAlreadyPaid = DB::transaction(function () use ($order, $data): bool {
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                if ($lockedOrder->payment_status === 'paid') {
                    return true;
                }

                $lockedOrder->paymentTransactions()
                    ->where('type', 'payment')
                    ->where('gateway_order_id', $data['razorpay_order_id'])
                    ->latest('id')
                    ->first()?->update([
                        'status' => 'failed',
                        'signature' => $data['razorpay_signature'],
                    ]);

                $lockedOrder->update([
                    'payment_status' => 'failed',
                    'payment_meta' => array_merge($lockedOrder->payment_meta ?? [], [
                        'verification_error' => 'signature_mismatch',
                    ]),
                ]);

                return false;
            });

            if ($wasAlreadyPaid) {
                return response()->json([
                    'message' => 'Payment already completed successfully.',
                    'redirect_url' => route('checkout.success', $order),
                ]);
            }

            return response()->json([
                'message' => 'Payment verification failed. Signature mismatch.',
            ], 422);
        }

        $this->markOrderPaymentCaptured(
            $order,
            $data['razorpay_order_id'],
            $data['razorpay_payment_id'],
            $data['razorpay_signature'],
            ['source' => 'client_verification']
        );

        Cart::where('user_id', Auth::id())->delete();
        session()->forget(['checkout_coupon', 'checkout_coins', 'checkout_token']);

        return response()->json([
            'message' => 'Payment verified successfully.',
            'redirect_url' => route('checkout.success', $order),
        ]);
    }

    public function success(Order $order): View
    {
        abort_if($order->user_id !== Auth::id(), 403);

        return view('frontend.checkout.success', compact('order'));
    }

    public function razorpayWebhook(Request $request): JsonResponse
    {
        $provider = PaymentProvider::where('slug', 'razorpay')->first();
        if (! $provider) {
            return response()->json(['message' => 'Razorpay provider is not configured.'], 422);
        }

        $payload = $request->getContent();
        $receivedSignature = (string) $request->header('X-Razorpay-Signature');
        $webhookSecret = $provider->webhook_secret ?: $provider->secret_key;

        if (empty($webhookSecret)) {
            return response()->json(['message' => 'Webhook secret is not configured.'], 422);
        }

        $expected = hash_hmac('sha256', $payload, $webhookSecret);
        if (! hash_equals($expected, $receivedSignature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 422);
        }

        $event = $request->input('event');

        if ($event === 'payment.captured') {
            $entity = $request->input('payload.payment.entity', []);
            $this->syncCapturedPayment($entity);
        }

        if ($event === 'payment.failed') {
            $entity = $request->input('payload.payment.entity', []);
            $this->syncFailedPayment($entity);
        }

        if (in_array($event, ['refund.created', 'refund.processed'], true)) {
            $entity = $request->input('payload.refund.entity', []);
            $this->syncRefund($entity, $event === 'refund.processed');
        }

        return response()->json(['status' => 'ok']);
    }

    private function loadUserCart()
    {
        return Cart::with('product', 'variation')
            ->where('user_id', Auth::id())
            ->get()
            ->filter(fn ($item) => $item->product);
    }

    private function razorpayHttpClient(): \Illuminate\Http\Client\PendingRequest
    {
        $verifySsl = filter_var(env('RAZORPAY_SSL_VERIFY', app()->environment('production')), FILTER_VALIDATE_BOOLEAN);

        return Http::withOptions(['verify' => $verifySsl]);
    }

    private function createRazorpayOrder(PaymentProvider $provider, Order $order): array
    {
        if (empty($provider->public_key) || empty($provider->secret_key)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Razorpay is active but API keys are missing in admin settings.',
            ]);
        }

        $response = $this->razorpayHttpClient()
            ->withBasicAuth($provider->public_key, $provider->secret_key)
            ->timeout(20)
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => (int) round($order->total * 100),
                'currency' => 'INR',
                'receipt' => 'ord_'.$order->id,
                'notes' => [
                    'local_order_id' => (string) $order->id,
                    'user_id' => (string) $order->user_id,
                ],
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'payment_method' => 'Unable to create Razorpay order. Please check your API keys.',
            ]);
        }

        return $response->json();
    }

    private function markOrderPaymentCaptured(
        Order $order,
        string $gatewayOrderId,
        string $gatewayPaymentId,
        ?string $signature = null,
        array $payload = []
    ): bool {
        $captured = DB::transaction(function () use ($order, $gatewayOrderId, $gatewayPaymentId, $signature, $payload): bool {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            // Client verification and the gateway webhook can arrive together.
            // The first transaction to lock the order captures it; later calls are
            // successful no-ops and cannot deduct stock or queue mail twice.
            if ($lockedOrder->payment_status === 'paid') {
                return false;
            }

            $lockedOrder->update([
                'status' => in_array($lockedOrder->status, ['pending', 'failed'], true) ? 'processing' : $lockedOrder->status,
                'payment_status' => 'paid',
                'paid_at' => $lockedOrder->paid_at ?? now(),
                'transaction_id' => $gatewayPaymentId,
                'gateway_order_id' => $gatewayOrderId,
                'payment_meta' => array_merge($lockedOrder->payment_meta ?? [], [
                    'verification' => [
                        'status' => 'success',
                        'verified_at' => now()->toDateTimeString(),
                    ],
                ]),
            ]);

            $transaction = $lockedOrder->paymentTransactions()
                ->where('type', 'payment')
                ->where('gateway_order_id', $gatewayOrderId)
                ->latest('id')
                ->first();

            if ($transaction) {
                $transaction->update([
                    'status' => 'captured',
                    'gateway_payment_id' => $gatewayPaymentId,
                    'signature' => $signature,
                    'payload' => $payload,
                ]);
            }

            $this->inventoryService->deductForOrder($lockedOrder);

            return true;
        });

        if ($captured) {
            $order->refresh();
            $this->recommendations->forgetForOrder($order);
            $this->queueInvoiceMail($order);
        }

        return $captured;
    }

    private function syncCapturedPayment(array $entity): void
    {
        $gatewayOrderId = (string) ($entity['order_id'] ?? '');
        $gatewayPaymentId = (string) ($entity['id'] ?? '');

        if ($gatewayOrderId === '' || $gatewayPaymentId === '') {
            return;
        }

        $order = Order::where('gateway_order_id', $gatewayOrderId)->first();
        if (! $order) {
            return;
        }

        $this->markOrderPaymentCaptured($order, $gatewayOrderId, $gatewayPaymentId, null, ['source' => 'webhook', 'entity' => $entity]);
    }

    private function syncFailedPayment(array $entity): void
    {
        $gatewayOrderId = (string) ($entity['order_id'] ?? '');
        if ($gatewayOrderId === '') {
            return;
        }

        $order = Order::where('gateway_order_id', $gatewayOrderId)->first();
        if (! $order) {
            return;
        }

        DB::transaction(function () use ($order, $gatewayOrderId): void {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->payment_status === 'paid') {
                return;
            }

            $lockedOrder->update(['payment_status' => 'failed']);
            $lockedOrder->paymentTransactions()
                ->where('type', 'payment')
                ->where('gateway_order_id', $gatewayOrderId)
                ->latest('id')
                ->first()?->update([
                    'status' => 'failed',
                    'payload' => ['source' => 'webhook'],
                ]);
        });

        $this->recommendations->forgetForOrder($order->fresh());
    }

    private function syncRefund(array $entity, bool $processed): void
    {
        $gatewayPaymentId = (string) ($entity['payment_id'] ?? '');
        $gatewayRefundId = (string) ($entity['id'] ?? '');
        $amount = isset($entity['amount']) ? ((float) $entity['amount']) / 100 : null;

        if ($gatewayPaymentId === '' || $gatewayRefundId === '' || $amount === null) {
            return;
        }

        $paymentTxn = PaymentTransaction::where('type', 'payment')
            ->where('gateway_payment_id', $gatewayPaymentId)
            ->latest('id')
            ->first();

        if (! $paymentTxn || ! $paymentTxn->order) {
            return;
        }

        $order = $paymentTxn->order;

        $refundTxn = PaymentTransaction::firstOrCreate(
            [
                'order_id' => $order->id,
                'type' => 'refund',
                'gateway_refund_id' => $gatewayRefundId,
            ],
            [
                'payment_provider_id' => $order->payment_provider_id,
                'status' => $processed ? 'processed' : 'pending',
                'amount' => $amount,
                'currency' => 'INR',
                'gateway_payment_id' => $gatewayPaymentId,
                'payload' => ['source' => 'webhook', 'entity' => $entity],
            ]
        );

        $refundTxn->update([
            'status' => $processed ? 'processed' : $refundTxn->status,
            'payload' => ['source' => 'webhook', 'entity' => $entity],
        ]);

        $refund = OrderRefund::firstOrCreate(
            [
                'order_id' => $order->id,
                'gateway_refund_id' => $gatewayRefundId,
            ],
            [
                'payment_transaction_id' => $refundTxn->id,
                'amount' => $amount,
                'status' => $processed ? 'processed' : 'pending',
                'reason' => 'Webhook refund sync',
                'metadata' => ['source' => 'webhook', 'entity' => $entity],
                'processed_at' => $processed ? now() : null,
            ]
        );

        $refund->update([
            'status' => $processed ? 'processed' : $refund->status,
            'processed_at' => $processed ? now() : $refund->processed_at,
            'metadata' => ['source' => 'webhook', 'entity' => $entity],
        ]);

        if ($processed) {
            $totalRefunded = (float) $order->refunds()->where('status', 'processed')->sum('amount');
            $refundStatus = $totalRefunded >= (float) $order->total ? 'full' : 'partial';
            $order->update([
                'refunded_total' => $totalRefunded,
                'refund_status' => $refundStatus,
                'refunded_at' => now(),
            ]);

            if ($refundStatus === 'full') {
                $this->inventoryService->restockForOrder($order);
            }

            if (! $refund->emailed_at) {
                $this->sendCreditNoteMail($order, $refund);
            }

            $this->recommendations->forgetForOrder($order->fresh());
        }
    }

    private function queueInvoiceMail(Order $order): void
    {
        $meta = $order->payment_meta ?? [];
        if (! empty($meta['invoice_emailed_at']) || ! empty($meta['invoice_queued_at'])) {
            return;
        }

        $order->update([
            'payment_meta' => array_merge($meta, [
                'invoice_queued_at' => now()->toDateTimeString(),
            ]),
        ]);

        SendOrderInvoiceMail::dispatch($order->id);
    }

    private function sendCreditNoteMail(Order $order, OrderRefund $refund): void
    {
        $billingEmail = $order->billing_address['email'] ?? null;

        if (! $billingEmail) {
            if (! $order->user || empty($order->user->email)) {
                return;
            }

            $billingEmail = $order->user->email;
        }

        Mail::to($billingEmail)->send(new OrderCreditNoteMail($order, $refund));

        $refund->update([
            'emailed_at' => now(),
        ]);
    }

    private function getAppliedCouponSummary($cartItems, ?bool $hasAppliedCombo = null): ?array
    {
        $hasAppliedCombo ??= $this->comboService->hasAppliedCombo($cartItems);
        $stored = session('checkout_coupon');
        $code = strtoupper(trim((string) ($stored['code'] ?? '')));

        if ($hasAppliedCombo) {
            session()->forget('checkout_coupon');

            return null;
        }

        if ($code === '') {
            return null;
        }

        $coupon = Coupon::whereRaw('UPPER(TRIM(code)) = ?', [$code])->first();
        if (! $coupon || ! $this->isCouponApplicable($coupon, (float) collect($cartItems)->sum(fn ($item) => (int) $item->quantity * (float) $item->price), $hasAppliedCombo)) {
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

    private function getAppliedCoinsSummary(float $orderTotal): array
    {
        $balance = (int) (Auth::user()->gehna_coins ?? 0);
        $requested = (int) (session('checkout_coins.coins') ?? 0);
        $coins = max(0, min($requested, $balance, (int) floor(max(0.0, $orderTotal))));

        return [
            'requested' => $requested,
            'coins' => $coins,
            'discount' => (float) $coins,
            'balance' => $balance,
            'balance_after' => max(0, $balance - $coins),
        ];
    }

    private function isCouponApplicable(Coupon $coupon, float $subtotal, bool $hasAppliedCombo = false): bool
    {
        return $this->getCouponIneligibilityReason($coupon, $subtotal, $hasAppliedCombo) === null;
    }

    private function getCouponIneligibilityReason(Coupon $coupon, float $subtotal, bool $hasAppliedCombo = false): ?string
    {
        return $this->couponService->ineligibilityReason($coupon, $subtotal, $hasAppliedCombo);
    }

    private function calculateCouponDiscount(Coupon $coupon, $cartItems): float
    {
        return $this->couponService->calculateDiscount($coupon, collect($cartItems));
    }
}
