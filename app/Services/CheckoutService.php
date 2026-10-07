<?php

namespace App\Services;

use App\Jobs\SendOrderInvoiceMail;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentProvider;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The checkout core, shared by the Blade checkout and the JSON API.
 *
 * Only the transport lives in the controllers: the web controller owns
 * sessions, redirects and views, the API controller owns the response
 * envelope. Everything that decides *what the customer is charged* lives
 * here - pricing, idempotent order placement and Razorpay verification are
 * one code path, so the website and the React storefront can never drift
 * apart on money.
 *
 * The two callers differ in where the coupon and the Gehna Coins come from:
 * the Blade checkout keeps them in the session, a bearer-token client has no
 * session, so both are passed explicitly to quote() instead of being read
 * from ambient state.
 */
class CheckoutService
{
    public function __construct(
        private readonly OrderInventoryService $inventoryService,
        private readonly CouponService $couponService,
        private readonly ComboService $comboService,
        private readonly ProductRecommendationService $recommendations,
    ) {}

    /**
     * The customer's cart with combo pricing applied.
     *
     * Lines whose product has since been deleted are dropped: an order row
     * cannot be built without one.
     *
     * @return Collection<int, Cart>
     */
    public function loadCart(User $user): Collection
    {
        $cartItems = Cart::with('product', 'variation')
            ->where('user_id', $user->id)
            ->get()
            ->filter(fn ($item) => $item->product);

        return $this->comboService->applyExplicitComboPrices($cartItems);
    }

    /**
     * Price the cart exactly the way place() will charge it.
     *
     * Every figure the checkout page shows and every figure the order stores
     * comes out of this one method: subtotal, bundle discount, coupon,
     * shipping (free at or above the threshold), Gehna Coins, and the grand
     * total rounded to whole rupees. The cart rows ride along in `cart` so
     * place() cannot be given a quote built from a different cart than the
     * one it writes.
     *
     * `coupon_error` explains why a requested coupon was dropped, so the API
     * can reject a code the client explicitly asked for instead of quietly
     * charging more than the customer was quoted.
     *
     * @param  Collection<int, Cart>|array  $cartItems
     * @return array{
     *     cart: Collection<int, Cart>,
     *     subtotal: float,
     *     combo_summary: array<string, mixed>,
     *     has_applied_combo: bool,
     *     combo_discount: float,
     *     coupon: array<string, mixed>|null,
     *     coupon_error: string|null,
     *     discount: float,
     *     shipping_charge: float,
     *     coins: array{requested: int, used: int, discount: float, balance: int, balance_after: int},
     *     total: float
     * }
     */
    public function quote(User $user, $cartItems, ?string $couponCode = null, int $coinsRequested = 0): array
    {
        $cartItems = collect($cartItems);

        $subtotal = (float) $cartItems->sum(fn ($item) => (int) $item->quantity * (float) $item->price);
        $comboSummary = $this->comboService->summarize($cartItems);
        $hasAppliedCombo = $this->comboService->hasAppliedCombo($cartItems, $comboSummary);
        $appliedCoupon = $this->resolveCoupon($couponCode, $cartItems, $subtotal, $hasAppliedCombo);

        $discount = (float) ($appliedCoupon['discount'] ?? 0);
        $comboDiscount = (float) ($comboSummary['discount'] ?? 0);

        // An empty cart quotes zero shipping: placement itself rejects an
        // empty cart, so this branch only ever feeds the summary endpoint and
        // must not advertise a delivery charge for nothing.
        $shippingCharge = $cartItems->isEmpty() || $subtotal >= 5000 ? 0.0 : 199.0;
        $orderTotal = max(0, $subtotal - $comboDiscount - $discount) + $shippingCharge;

        // Coins are clamped to the balance and to the payable amount, so a
        // stale request can never drive the total negative.
        $balance = (int) ($user->gehna_coins ?? 0);
        $coinsUsed = max(0, min($coinsRequested, $balance, (int) floor(max(0.0, $orderTotal))));

        return [
            'cart' => $cartItems,
            'subtotal' => $subtotal,
            'combo_summary' => $comboSummary,
            'has_applied_combo' => $hasAppliedCombo,
            'combo_discount' => $comboDiscount,
            'coupon' => $appliedCoupon,
            'coupon_error' => $this->couponErrorMessage($couponCode, $appliedCoupon, $subtotal, $hasAppliedCombo),
            'discount' => $discount,
            'shipping_charge' => $shippingCharge,
            'coins' => [
                'requested' => $coinsRequested,
                'used' => $coinsUsed,
                'discount' => (float) $coinsUsed,
                'balance' => $balance,
                'balance_after' => max(0, $balance - $coinsUsed),
            ],
            // Round the final amount, after coins. This same value is stored
            // on the order, shown on the page and sent to the gateway, so the
            // customer is never quoted a fraction they are not charged.
            'total' => Order::roundAmount($orderTotal - $coinsUsed),
        ];
    }

    /**
     * Resolve a coupon code against the cart, or null when it cannot be used.
     *
     * A bundle offer and a coupon never stack: while a combo applies, no
     * coupon is eligible.
     *
     * @param  Collection<int, Cart>|array  $cartItems
     */
    public function resolveCoupon(?string $couponCode, $cartItems, float $subtotal, bool $hasAppliedCombo): ?array
    {
        $code = strtoupper(trim((string) $couponCode));

        if ($hasAppliedCombo || $code === '') {
            return null;
        }

        $coupon = Coupon::whereRaw('UPPER(TRIM(code)) = ?', [$code])->first();

        if (! $coupon || $this->couponService->ineligibilityReason($coupon, $subtotal, $hasAppliedCombo) !== null) {
            return null;
        }

        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'amount' => (float) $coupon->amount,
            'buy_quantity' => (int) ($coupon->buy_quantity ?? 0),
            'get_quantity' => (int) ($coupon->get_quantity ?? 0),
            'reward_coins' => (int) ($coupon->reward_coins ?? 0),
            'discount' => $this->couponService->calculateDiscount($coupon, collect($cartItems)),
            'free_items' => $this->couponService->freeItemsBreakdown($coupon, collect($cartItems)),
        ];
    }

    /**
     * Create the order idempotently.
     *
     * `$checkoutToken` identifies one checkout attempt: a double-tapped
     * button, a retried request or a resubmitted form carrying the same token
     * returns the order that attempt already created instead of building a
     * second one (the column has a unique index, so a race lands in the same
     * place through the QueryException below).
     *
     * @param  array<string, mixed>  $quote  result of quote() for this cart
     * @return array{order: Order, replayed: bool, checkout_token: string}
     */
    public function place(
        User $user,
        array $data,
        string $checkoutToken,
        array $quote,
        array $billingAddress,
        array $shippingAddress,
    ): array {
        $provider = PaymentProvider::where('slug', $data['payment_method'])
            ->where('is_active', true)
            ->first();

        if (! $provider) {
            throw ValidationException::withMessages([
                'payment_method' => 'Selected payment method is not active right now.',
            ]);
        }

        // A token whose order was cancelled or refunded, or that belongs to
        // another account, starts a fresh attempt rather than resurrecting or
        // hijacking the old one. The column is globally unique, so the rotated
        // token is what this attempt writes.
        $existingOrder = Order::where('checkout_token', $checkoutToken)->first();

        if ($existingOrder) {
            $reusable = (int) $existingOrder->user_id === (int) $user->id
                && ! in_array($existingOrder->status, ['cancelled', 'refunded'], true);

            if ($reusable) {
                return ['order' => $existingOrder, 'replayed' => true, 'checkout_token' => $checkoutToken];
            }

            $checkoutToken = (string) Str::uuid();
        }

        $cartItems = $quote['cart'];
        $comboSummary = $quote['combo_summary'];
        $appliedCoupon = $quote['coupon'];
        $subtotal = (float) $quote['subtotal'];
        $comboDiscount = (float) $quote['combo_discount'];
        $discount = (float) $quote['discount'];
        $shippingCharge = (float) $quote['shipping_charge'];
        $coinsUsed = (int) $quote['coins']['used'];
        $coinsDiscount = (float) $quote['coins']['discount'];
        $orderTotal = (float) $quote['total'];

        try {
            $order = DB::transaction(function () use (
                $user, $data, $provider, $checkoutToken, $cartItems, $comboSummary,
                $appliedCoupon, $subtotal, $comboDiscount, $discount, $shippingCharge,
                $coinsUsed, $coinsDiscount, $orderTotal, $billingAddress, $shippingAddress
            ) {
                // The buyer row is locked so two concurrent checkouts cannot
                // spend the same Gehna Coins balance.
                $buyer = User::whereKey($user->id)->lockForUpdate()->first();

                if ($coinsUsed > 0 && (! $buyer || (int) $buyer->gehna_coins < $coinsUsed)) {
                    throw ValidationException::withMessages([
                        'coins' => 'You do not have enough Gehna Coins to complete this order.',
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

                    // Remember which customer burned this code so the coupon is
                    // disabled for them from now on. firstOrCreate keeps a
                    // retried checkout from tripping the one-per-customer index.
                    CouponUse::firstOrCreate(
                        [
                            'coupon_id' => $appliedCoupon['id'],
                            'user_id' => $user->id,
                        ],
                        ['order_id' => $order->id],
                    );

                    // Credit Gehna Coins when the order used a coins reward coupon.
                    if (($appliedCoupon['type'] ?? '') === 'gehna_coins' && (int) ($appliedCoupon['reward_coins'] ?? 0) > 0) {
                        User::whereKey($user->id)->increment('gehna_coins', (int) $appliedCoupon['reward_coins']);
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
            // Unique checkout_token: another request for the same attempt won
            // the race, so return the order it created instead of failing.
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            $existingOrder = Order::where('checkout_token', $checkoutToken)
                ->where('user_id', $user->id)
                ->first();

            if ($existingOrder) {
                return ['order' => $existingOrder, 'replayed' => true, 'checkout_token' => $checkoutToken];
            }

            throw $exception;
        }

        return ['order' => $order->fresh(), 'replayed' => false, 'checkout_token' => $checkoutToken];
    }

    /**
     * The shared tail of a checkout submission, for a new or a replayed order.
     *
     * COD completes immediately (invoice queued, cart emptied), an order that
     * is already paid just empties the cart, and an online payment gets one
     * gateway order attached under a row lock so repeated submissions reuse it
     * instead of creating several payable attempts.
     *
     * @return array{state: 'cod'|'paid'|'pending_payment'|'provider_unavailable', order: Order, razorpay: array<string, mixed>|null}
     */
    public function finalize(Order $order): array
    {
        if ($order->payment_method === 'cod') {
            $this->queueInvoiceMail($order);
            Cart::where('user_id', $order->user_id)->delete();

            return ['state' => 'cod', 'order' => $order, 'razorpay' => null];
        }

        if ($order->payment_status === 'paid') {
            Cart::where('user_id', $order->user_id)->delete();

            return ['state' => 'paid', 'order' => $order, 'razorpay' => null];
        }

        $provider = PaymentProvider::find($order->payment_provider_id);
        if (! $provider) {
            return ['state' => 'provider_unavailable', 'order' => $order, 'razorpay' => null];
        }

        $gatewayOrder = $this->ensureRazorpayOrder($order, $provider);
        $order->refresh();

        return [
            'state' => 'pending_payment',
            'order' => $order,
            'razorpay' => [
                'key' => $provider->public_key,
                'order_id' => $gatewayOrder['id'],
                'amount' => (int) round((float) $order->total * 100),
                'currency' => 'INR',
            ],
        ];
    }

    /**
     * Create the gateway order while holding the local order row lock. A repeated
     * submission therefore reuses one gateway order instead of creating multiple
     * payable attempts for the same local order.
     *
     * @return array<string, mixed>
     */
    public function ensureRazorpayOrder(Order $order, PaymentProvider $provider): array
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

    /**
     * Verify the checkout.js callback against the provider secret and capture.
     *
     * Statuses are mapped to responses by the caller: `provider_invalid` and
     * `order_mismatch` are rejected requests, `mismatch` is a failed
     * signature (recorded on the order so a retry is honest about it), and
     * `already_paid` / `paid` are successes.
     *
     * @param  array{razorpay_order_id: string, razorpay_payment_id: string, razorpay_signature: string}  $data
     * @return array{status: 'provider_invalid'|'order_mismatch'|'already_paid'|'mismatch'|'paid', order: Order}
     */
    public function verifyRazorpayPayment(User $user, Order $order, array $data): array
    {
        $provider = PaymentProvider::find($order->payment_provider_id);
        if (! $provider || $provider->slug !== 'razorpay') {
            return ['status' => 'provider_invalid', 'order' => $order];
        }

        if ($order->gateway_order_id && $order->gateway_order_id !== $data['razorpay_order_id']) {
            return ['status' => 'order_mismatch', 'order' => $order];
        }

        if ($order->payment_status === 'paid') {
            Cart::where('user_id', $user->id)->delete();

            return ['status' => 'already_paid', 'order' => $order];
        }

        $payload = $data['razorpay_order_id'].'|'.$data['razorpay_payment_id'];
        $expectedSignature = hash_hmac('sha256', $payload, (string) $provider->secret_key);

        if (! hash_equals($expectedSignature, $data['razorpay_signature'])) {
            // Client verification and the gateway webhook can arrive together;
            // if the webhook captured the payment while we checked, this is a
            // success rather than a mismatch.
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
                Cart::where('user_id', $user->id)->delete();

                return ['status' => 'already_paid', 'order' => $order->fresh()];
            }

            return ['status' => 'mismatch', 'order' => $order->fresh()];
        }

        $this->capturePayment(
            $order,
            $data['razorpay_order_id'],
            $data['razorpay_payment_id'],
            $data['razorpay_signature'],
            ['source' => 'client_verification']
        );

        Cart::where('user_id', $user->id)->delete();

        return ['status' => 'paid', 'order' => $order->fresh()];
    }

    /**
     * Mark an order paid after a verified payment (or a signed webhook).
     *
     * The first transaction to lock the order captures it; later calls are
     * successful no-ops and cannot deduct stock or queue mail twice. Returns
     * whether this call was the one that captured.
     */
    public function capturePayment(
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

    /** Queue the invoice mail once, ever, for an order. */
    public function queueInvoiceMail(Order $order): void
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

    /**
     * Why a requested coupon is not being applied, or null when it is.
     */
    private function couponErrorMessage(?string $couponCode, ?array $appliedCoupon, float $subtotal, bool $hasAppliedCombo): ?string
    {
        $code = strtoupper(trim((string) $couponCode));

        if ($code === '' || $appliedCoupon !== null) {
            return null;
        }

        $coupon = Coupon::whereRaw('UPPER(TRIM(code)) = ?', [$code])->first();

        if (! $coupon) {
            return 'Coupon code not found.';
        }

        return $this->couponService->ineligibilityReason($coupon, $subtotal, $hasAppliedCombo)
            ?? 'This coupon cannot be applied to this order.';
    }

    private function razorpayHttpClient(): PendingRequest
    {
        $verifySsl = filter_var(env('RAZORPAY_SSL_VERIFY', app()->environment('production')), FILTER_VALIDATE_BOOLEAN);

        return Http::withOptions(['verify' => $verifySsl]);
    }

    /**
     * Ask Razorpay for the payable order. Failures surface as a validation
     * error on `payment_method`, because that is the field the customer is
     * being asked to change.
     *
     * @return array<string, mixed>
     */
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
}
