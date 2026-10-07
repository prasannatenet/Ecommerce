<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\OrderResource;
use App\Jobs\SendOrderInvoiceMail;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentProvider;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\ComboService;
use App\Services\CouponService;
use App\Services\OrderInventoryService;
use App\Services\ProductRecommendationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Storefront REST API for Checkout, Coupon, Coins, Order Placement & Payments.
 *
 * Consumed by React.js SPA & Mobile Apps.
 */
class CheckoutController extends ApiController
{
    public function __construct(
        private readonly OrderInventoryService $inventoryService,
        private readonly CouponService $couponService,
        private readonly ComboService $comboService,
        private readonly ProductRecommendationService $recommendations,
    ) {}

    /**
     * GET /api/v1/checkout/summary
     * Get checkout breakdown, cart calculation, available coupons, user coins balance & payment options.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $cartItems = $this->loadUserCart($user->id);

        if ($cartItems->isEmpty()) {
            return $this->fail('Your cart is empty. Please add products before checkout.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $subtotal = (float) $cartItems->sum(fn ($item) => $item->quantity * (float) $item->price);
        $totalQuantity = (int) $cartItems->sum(fn ($item) => (int) $item->quantity);

        $comboSummary = $this->comboService->summarize($cartItems);
        $hasAppliedCombo = $this->comboService->hasAppliedCombo($cartItems, $comboSummary);

        $couponCode = $request->input('coupon_code', session('checkout_coupon.code'));
        $appliedCoupon = $this->getAppliedCouponSummary($cartItems, $user, $couponCode, $hasAppliedCombo);
        $discount = (float) ($appliedCoupon['discount'] ?? 0);
        $freeItems = collect($appliedCoupon['free_items'] ?? []);
        $comboDiscount = (float) ($comboSummary['discount'] ?? 0);

        $shippingCharge = $subtotal >= 5000 ? 0.0 : 199.0;
        $orderTotal = max(0, $subtotal - $comboDiscount - $discount) + $shippingCharge;

        $requestedCoins = (int) $request->input('coins', session('checkout_coins.coins', 0));
        $appliedCoins = $this->getAppliedCoinsSummary($user, $orderTotal, $requestedCoins);
        $coinsDiscount = (float) ($appliedCoins['discount'] ?? 0);
        $coinsBalance = (int) ($user->gehna_coins ?? 0);
        $grandTotal = Order::roundAmount((float) $orderTotal - $coinsDiscount);

        $availableCoupons = Coupon::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function (Coupon $coupon) use ($subtotal, $hasAppliedCombo) {
                $reason = $this->couponService->ineligibilityReason($coupon, $subtotal, $hasAppliedCombo);
                $validityParts = [];
                if ($coupon->starts_at) {
                    $validityParts[] = 'From '.$coupon->starts_at->format('d M Y, h:i A');
                }
                if ($coupon->expires_at) {
                    $validityParts[] = 'Till '.$coupon->expires_at->format('d M Y, h:i A');
                }

                return [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'type' => $coupon->type,
                    'amount' => (float) $coupon->amount,
                    'buy_quantity' => (int) ($coupon->buy_quantity ?? 0),
                    'get_quantity' => (int) ($coupon->get_quantity ?? 0),
                    'reward_coins' => (int) ($coupon->reward_coins ?? 0),
                    'min_order_amount' => (float) ($coupon->min_order_amount ?? 0),
                    'validity_text' => empty($validityParts) ? 'No date restriction' : implode(' | ', $validityParts),
                    'is_applicable' => $reason === null,
                    'ineligible_reason' => $reason,
                ];
            })
            ->values();

        $providers = PaymentProvider::whereIn('slug', ['cod', 'razorpay'])
            ->where('is_active', true)
            ->get()
            ->keyBy('slug');

        $checkoutToken = (string) Str::uuid();

        return $this->ok([
            'pricing' => [
                'subtotal' => $subtotal,
                'total_quantity' => $totalQuantity,
                'combo_discount' => $comboDiscount,
                'coupon_discount' => $discount,
                'coins_discount' => $coinsDiscount,
                'coins_used' => (int) ($appliedCoins['coins'] ?? 0),
                'shipping_charge' => $shippingCharge,
                'grand_total' => $grandTotal,
            ],
            'combo_summary' => [
                'has_applied_combo' => $hasAppliedCombo,
                'discount' => $comboDiscount,
            ],
            'applied_coupon' => $appliedCoupon,
            'applied_coins' => $appliedCoins,
            'user_coins' => [
                'balance' => $coinsBalance,
                'balance_after' => max(0, $coinsBalance - (int) ($appliedCoins['coins'] ?? 0)),
            ],
            'available_coupons' => $availableCoupons,
            'payment_providers' => [
                'cod' => isset($providers['cod']) ? [
                    'enabled' => true,
                    'name' => $providers['cod']->name,
                ] : ['enabled' => false],
                'razorpay' => isset($providers['razorpay']) ? [
                    'enabled' => true,
                    'name' => $providers['razorpay']->name,
                    'key' => $providers['razorpay']->public_key,
                ] : ['enabled' => false],
            ],
            'checkout_token' => $checkoutToken,
        ]);
    }

    /**
     * POST /api/v1/checkout/apply-coupon
     */
    public function applyCoupon(Request $request): JsonResponse
    {
        $data = $request->validate([
            'coupon_code' => 'required|string|max:50',
        ]);

        $user = $request->user();
        $cartItems = $this->loadUserCart($user->id);

        if ($cartItems->isEmpty()) {
            return $this->fail('Your cart is empty.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $subtotal = (float) $cartItems->sum(fn ($item) => $item->quantity * (float) $item->price);
        $comboSummary = $this->comboService->summarize($cartItems);
        $hasAppliedCombo = $this->comboService->hasAppliedCombo($cartItems, $comboSummary);

        if ($hasAppliedCombo) {
            return $this->fail('Coupons cannot be combined with active bundle combo offers.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $normalizedCode = strtoupper(trim($data['coupon_code']));
        $coupon = Coupon::whereRaw('UPPER(TRIM(code)) = ?', [$normalizedCode])->first();

        if (! $coupon) {
            return $this->fail('Coupon code not found.', Response::HTTP_NOT_FOUND);
        }

        $ineligibilityReason = $this->couponService->ineligibilityReason($coupon, $subtotal, $hasAppliedCombo);
        if ($ineligibilityReason !== null) {
            return $this->fail($ineligibilityReason, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $discount = $this->couponService->calculateDiscount($coupon, collect($cartItems));
        $freeItems = $this->couponService->freeItemsBreakdown($coupon, collect($cartItems));

        session(['checkout_coupon' => ['code' => $coupon->code]]);

        return $this->ok([
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->type,
                'amount' => (float) $coupon->amount,
                'discount' => $discount,
                'free_items' => $freeItems,
            ],
        ], [], 'Coupon applied successfully.');
    }

    /**
     * POST /api/v1/checkout/remove-coupon
     */
    public function removeCoupon(Request $request): JsonResponse
    {
        session()->forget('checkout_coupon');

        return $this->ok(null, [], 'Coupon removed.');
    }

    /**
     * POST /api/v1/checkout/apply-coins
     */
    public function applyCoins(Request $request): JsonResponse
    {
        $data = $request->validate([
            'coins' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $cartItems = $this->loadUserCart($user->id);

        if ($cartItems->isEmpty()) {
            return $this->fail('Your cart is empty.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $balance = (int) ($user->gehna_coins ?? 0);
        if ($balance < 1) {
            return $this->fail('You do not have any Gehna Coins to redeem yet.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $subtotal = (float) $cartItems->sum(fn ($item) => $item->quantity * (float) $item->price);
        $comboSummary = $this->comboService->summarize($cartItems);
        $hasAppliedCombo = $this->comboService->hasAppliedCombo($cartItems, $comboSummary);
        $appliedCoupon = $this->getAppliedCouponSummary($cartItems, $user, session('checkout_coupon.code'), $hasAppliedCombo);

        $discount = (float) ($appliedCoupon['discount'] ?? 0);
        $comboDiscount = (float) ($comboSummary['discount'] ?? 0);
        $shippingCharge = $subtotal >= 5000 ? 0.0 : 199.0;
        $orderTotal = max(0, $subtotal - $comboDiscount - $discount) + $shippingCharge;

        $maxUsable = max(0, min($balance, (int) floor($orderTotal)));
        if ($maxUsable < 1) {
            return $this->fail('Gehna Coins cannot be applied to this order right now.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $requested = (int) $data['coins'];
        $used = min($requested, $maxUsable);

        session(['checkout_coins' => ['coins' => $used]]);

        return $this->ok([
            'coins_used' => $used,
            'discount' => (float) $used,
            'balance' => $balance,
            'balance_after' => max(0, $balance - $used),
        ], [], 'Gehna Coins applied: '.$used.' coins = Rs '.number_format((float) $used, 2).' off your order.');
    }

    /**
     * POST /api/v1/checkout/remove-coins
     */
    public function removeCoins(Request $request): JsonResponse
    {
        session()->forget('checkout_coins');

        return $this->ok(null, [], 'Gehna Coins removed.');
    }

    /**
     * POST /api/v1/checkout/place-order
     * Place order via API. Returns Order object for COD, or Razorpay payload for Razorpay online payment.
     */
    public function placeOrder(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'payment_method' => 'required|in:cod,razorpay',
            'checkout_token' => 'nullable|uuid',
            'coupon_code' => 'nullable|string|max:50',
            'coins' => 'nullable|integer|min:0',
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

        $checkoutToken = (string) ($data['checkout_token'] ?? Str::uuid());

        // Idempotent retry check
        $existingOrder = Order::where('checkout_token', $checkoutToken)->first();
        if ($existingOrder && in_array($existingOrder->status, ['cancelled', 'refunded'], true)) {
            $checkoutToken = (string) Str::uuid();
            $existingOrder = null;
        }
        if ($existingOrder && (int) $existingOrder->user_id !== (int) $user->id) {
            $checkoutToken = (string) Str::uuid();
            $existingOrder = null;
        }

        $provider = PaymentProvider::where('slug', $data['payment_method'])
            ->where('is_active', true)
            ->first();

        if ($existingOrder) {
            if ($existingOrder->payment_status !== 'paid' && $existingOrder->payment_method !== $data['payment_method']) {
                $existingOrder->update([
                    'payment_method' => $data['payment_method'],
                    'payment_status' => $data['payment_method'] === 'cod' ? 'pending' : 'initiated',
                    'payment_provider_id' => $provider ? $provider->id : $existingOrder->payment_provider_id,
                ]);
                $existingOrder->paymentTransactions()->where('type', 'payment')->latest('id')->first()?->update([
                    'status' => $data['payment_method'] === 'cod' ? 'pending' : 'initiated',
                    'notes' => ['method' => $data['payment_method']],
                ]);
            }

            return $this->checkoutOrderApiResponse($existingOrder, $user);
        }

        if (! $provider) {
            throw ValidationException::withMessages([
                'payment_method' => ['Selected payment method is not active right now.'],
            ]);
        }

        $cartItems = $this->loadUserCart($user->id);
        if ($cartItems->isEmpty()) {
            return $this->fail('Your cart is empty.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $subtotal = (float) $cartItems->sum(fn ($item) => $item->quantity * (float) $item->price);
        $comboSummary = $this->comboService->summarize($cartItems);
        $hasAppliedCombo = $this->comboService->hasAppliedCombo($cartItems, $comboSummary);

        $couponCode = $data['coupon_code'] ?? session('checkout_coupon.code');
        $appliedCoupon = $this->getAppliedCouponSummary($cartItems, $user, $couponCode, $hasAppliedCombo);
        $discount = (float) ($appliedCoupon['discount'] ?? 0);
        $comboDiscount = (float) ($comboSummary['discount'] ?? 0);

        $shippingCharge = $subtotal >= 5000 ? 0.0 : 199.0;
        $orderTotal = max(0, $subtotal - $comboDiscount - $discount) + $shippingCharge;

        $requestedCoins = isset($data['coins']) ? (int) $data['coins'] : (int) session('checkout_coins.coins', 0);
        $appliedCoins = $this->getAppliedCoinsSummary($user, $orderTotal, $requestedCoins);
        $coinsUsed = (int) ($appliedCoins['coins'] ?? 0);
        $coinsDiscount = (float) $coinsUsed;
        $orderTotal = Order::roundAmount((float) $orderTotal - $coinsDiscount);

        if ($data['payment_method'] === 'razorpay' && $orderTotal < 1) {
            throw ValidationException::withMessages([
                'payment_method' => ['Gehna Coins cover the entire order amount. Please choose Cash on Delivery or reduce the coins used.'],
            ]);
        }

        $shippingSame = $request->boolean('shipping_same_as_billing', false);

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
                'name' => $data['shipping_name'] ?? $data['billing_name'],
                'phone' => $data['shipping_phone'] ?? $data['billing_phone'],
                'line1' => $data['shipping_line1'] ?? $data['billing_line1'],
                'city' => $data['shipping_city'] ?? $data['billing_city'],
                'state' => $data['shipping_state'] ?? $data['billing_state'],
                'zip' => $data['shipping_zip'] ?? $data['billing_zip'],
                'country' => $data['shipping_country'] ?? $data['billing_country'],
            ];

        try {
            $order = DB::transaction(function () use ($user, $provider, $subtotal, $discount, $comboDiscount, $comboSummary, $coinsUsed, $coinsDiscount, $shippingCharge, $orderTotal, $billingAddress, $shippingAddress, $data, $cartItems, $appliedCoupon, $checkoutToken) {
                $buyer = User::whereKey($user->id)->lockForUpdate()->first();

                if ($coinsUsed > 0 && (! $buyer || (int) $buyer->gehna_coins < $coinsUsed)) {
                    throw ValidationException::withMessages([
                        'coins' => ['You do not have enough Gehna Coins to complete this order.'],
                    ]);
                }

                $order = Order::create([
                    'user_id' => $user->id,
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
                            'discount' => $appliedCoupon['discount'],
                        ] : null,
                    ],
                ]);

                if ($appliedCoupon) {
                    Coupon::whereKey($appliedCoupon['id'])->increment('used_count');
                    CouponUse::firstOrCreate(
                        ['coupon_id' => $appliedCoupon['id'], 'user_id' => $user->id],
                        ['order_id' => $order->id]
                    );

                    if (($appliedCoupon['type'] ?? '') === 'gehna_coins' && (int) ($appliedCoupon['reward_coins'] ?? 0) > 0) {
                        User::whereKey($user->id)->increment('gehna_coins', (int) $appliedCoupon['reward_coins']);
                    }
                }

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
                    'notes' => ['method' => $data['payment_method']],
                ]);

                return $order;
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            $existingOrder = Order::where('checkout_token', $checkoutToken)
                ->where('user_id', $user->id)
                ->first();

            if ($existingOrder) {
                return $this->checkoutOrderApiResponse($existingOrder, $user);
            }

            throw $exception;
        }

        session()->forget(['checkout_coupon', 'checkout_coins']);

        return $this->checkoutOrderApiResponse($order->fresh(), $user);
    }

    /**
     * POST /api/v1/checkout/verify-razorpay
     * Verify payment signature returned by Razorpay SDK popup in React.
     */
    public function verifyRazorpay(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $order = Order::where('id', $data['order_id'])
            ->where('user_id', $user->id)
            ->first();

        if (! $order) {
            return $this->fail('Order not found.', Response::HTTP_NOT_FOUND);
        }

        $provider = PaymentProvider::find($order->payment_provider_id);
        if (! $provider || $provider->slug !== 'razorpay') {
            return $this->fail('Invalid payment provider for this order.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($order->gateway_order_id && $order->gateway_order_id !== $data['razorpay_order_id']) {
            return $this->fail('This payment does not belong to the selected order.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($order->payment_status === 'paid') {
            Cart::where('user_id', $user->id)->delete();
            session()->forget(['checkout_coupon', 'checkout_coins']);

            return $this->ok([
                'order' => new OrderResource($order),
                'payment_status' => 'paid',
            ], [], 'Payment already completed successfully.');
        }

        $payload = $data['razorpay_order_id'].'|'.$data['razorpay_payment_id'];
        $expectedSignature = hash_hmac('sha256', $payload, (string) $provider->secret_key);

        if (! hash_equals($expectedSignature, $data['razorpay_signature'])) {
            DB::transaction(function () use ($order, $data) {
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->first();
                if ($lockedOrder && $lockedOrder->payment_status !== 'paid') {
                    $lockedOrder->update(['payment_status' => 'failed']);
                    $lockedOrder->paymentTransactions()
                        ->where('type', 'payment')
                        ->where('gateway_order_id', $data['razorpay_order_id'])
                        ->latest('id')
                        ->first()?->update(['status' => 'failed', 'signature' => $data['razorpay_signature']]);
                }
            });

            return $this->fail('Payment verification failed. Signature mismatch.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->markOrderPaymentCaptured(
            $order,
            $data['razorpay_order_id'],
            $data['razorpay_payment_id'],
            $data['razorpay_signature'],
            ['source' => 'api_verification']
        );

        Cart::where('user_id', $user->id)->delete();
        session()->forget(['checkout_coupon', 'checkout_coins']);

        return $this->ok([
            'order' => new OrderResource($order->fresh()),
            'payment_status' => 'paid',
        ], [], 'Payment verified successfully. Order confirmed.');
    }

    /**
     * GET /api/v1/checkout/payment-status/{order}
     */
    public function paymentStatus(Request $request, Order $order): JsonResponse
    {
        if ((int) $order->user_id !== (int) $request->user()->id) {
            return $this->fail('Unauthorized access to order status.', Response::HTTP_FORBIDDEN);
        }

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

        return $this->ok([
            'order_id' => $order->id,
            'state' => $state,
            'payment_status' => $order->payment_status,
            'transaction_status' => $transaction?->status,
        ], [], match ($state) {
            'paid' => 'Payment successful. Your order is confirmed.',
            'failed' => 'Payment was not completed. You can safely retry this order.',
            'cod_confirmed' => 'Order confirmed. Payment is due on delivery.',
            default => 'Payment is being confirmed. Please wait.',
        });
    }

    /* -------------------------------------------------------------------------- */
    /*  Internal Helpers                                                          */
    /* -------------------------------------------------------------------------- */

    private function checkoutOrderApiResponse(Order $order, User $user): JsonResponse
    {
        if ($order->payment_method === 'cod') {
            $this->queueInvoiceMail($order);
            Cart::where('user_id', $user->id)->delete();
            session()->forget(['checkout_coupon', 'checkout_coins']);

            return $this->ok([
                'order' => new OrderResource($order),
                'payment_method' => 'cod',
                'is_paid' => false,
            ], [], 'Order placed successfully with Cash on Delivery.', Response::HTTP_CREATED);
        }

        if ($order->payment_status === 'paid') {
            Cart::where('user_id', $user->id)->delete();
            session()->forget(['checkout_coupon', 'checkout_coins']);

            return $this->ok([
                'order' => new OrderResource($order),
                'payment_method' => 'razorpay',
                'is_paid' => true,
            ], [], 'Payment already completed successfully.');
        }

        $provider = PaymentProvider::find($order->payment_provider_id);
        if (! $provider) {
            return $this->fail('The payment provider is no longer available.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $gatewayOrder = $this->ensureRazorpayOrder($order, $provider);
        $order->refresh();

        return $this->ok([
            'order' => new OrderResource($order),
            'payment_method' => 'razorpay',
            'is_paid' => false,
            'razorpay' => [
                'key' => $provider->public_key,
                'order_id' => $gatewayOrder['id'],
                'amount' => (int) round((float) $order->total * 100),
                'currency' => 'INR',
                'name' => config('app.name', 'Gehna'),
                'description' => 'Order #'.$order->id,
                'prefill' => [
                    'name' => $order->billing_address['name'] ?? $user->name,
                    'email' => $order->billing_address['email'] ?? $user->email,
                    'contact' => $order->billing_address['phone'] ?? $user->phone ?? '',
                ],
            ],
        ], [], 'Order created. Please complete Razorpay payment.', Response::HTTP_CREATED);
    }

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

    private function createRazorpayOrder(PaymentProvider $provider, Order $order): array
    {
        if (empty($provider->public_key) || empty($provider->secret_key)) {
            throw ValidationException::withMessages([
                'payment_method' => ['Razorpay is active but API keys are missing in admin settings.'],
            ]);
        }

        $verifySsl = filter_var(env('RAZORPAY_SSL_VERIFY', app()->environment('production')), FILTER_VALIDATE_BOOLEAN);

        $response = Http::withOptions(['verify' => $verifySsl])
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
                'payment_method' => ['Unable to create Razorpay order. Please check API credentials.'],
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

    private function loadUserCart(int $userId)
    {
        $cartItems = Cart::with('product', 'variation')
            ->where('user_id', $userId)
            ->get()
            ->filter(fn ($item) => $item->product);

        return $this->comboService->applyExplicitComboPrices($cartItems);
    }

    private function getAppliedCouponSummary($cartItems, User $user, ?string $code, bool $hasAppliedCombo): ?array
    {
        if ($hasAppliedCombo || empty($code)) {
            return null;
        }

        $normalizedCode = strtoupper(trim($code));
        $coupon = Coupon::whereRaw('UPPER(TRIM(code)) = ?', [$normalizedCode])->first();
        if (! $coupon) {
            return null;
        }

        $subtotal = (float) collect($cartItems)->sum(fn ($item) => (int) $item->quantity * (float) $item->price);
        if ($this->couponService->ineligibilityReason($coupon, $subtotal, $hasAppliedCombo) !== null) {
            return null;
        }

        $discount = $this->couponService->calculateDiscount($coupon, collect($cartItems));

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

    private function getAppliedCoinsSummary(User $user, float $orderTotal, int $requested): array
    {
        $balance = (int) ($user->gehna_coins ?? 0);
        $coins = max(0, min($requested, $balance, (int) floor(max(0.0, $orderTotal))));

        return [
            'requested' => $requested,
            'coins' => $coins,
            'discount' => (float) $coins,
            'balance' => $balance,
            'balance_after' => max(0, $balance - $coins),
        ];
    }
}
