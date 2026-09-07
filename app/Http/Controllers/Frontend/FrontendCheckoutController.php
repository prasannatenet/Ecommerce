<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Mail\OrderCreditNoteMail;
use App\Mail\OrderInvoiceMail;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\PaymentProvider;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\CouponService;
use App\Services\OrderInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FrontendCheckoutController extends Controller
{
	public function __construct(
		private readonly OrderInventoryService $inventoryService,
		private readonly CouponService $couponService,
	) {
	}

	public function index(): View|RedirectResponse
	{
		$cartItems = $this->loadUserCart();

		if ($cartItems->isEmpty()) {
			return redirect()->route('products.index')
				->with('error', 'Your cart is empty. Please add products before checkout.');
		}

		$subtotal = $cartItems->sum(fn ($item) => $item->quantity * (float) $item->price);
		$totalQuantity = (int) $cartItems->sum(fn ($item) => (int) $item->quantity);
		$appliedCoupon = $this->getAppliedCouponSummary($cartItems);
		$discount = (float) ($appliedCoupon['discount'] ?? 0);
		$freeItems = collect($appliedCoupon['free_items'] ?? []);
		$shippingCharge = (float) $subtotal >= 5000 ? 0.0 : 199.0;
		$grandTotal = max(0, (float) $subtotal - $discount) + $shippingCharge;
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

		$providers = PaymentProvider::whereIn('slug', ['cod', 'razorpay'])
			->where('is_active', true)
			->get()
			->keyBy('slug');

		return view('frontend.checkout.index', [
			'cartItems' => $cartItems,
			'subtotal' => $subtotal,
			'discount' => $discount,
			'freeItems' => $freeItems,
			'shippingCharge' => $shippingCharge,
			'grandTotal' => $grandTotal,
			'appliedCoupon' => $appliedCoupon,
			'availableCoupons' => $availableCoupons,
			'codProvider' => $providers->get('cod'),
			'razorpayProvider' => $providers->get('razorpay'),
			'user' => Auth::user(),
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
		$normalizedCode = strtoupper(trim($data['coupon_code']));
		$coupon = Coupon::whereRaw('UPPER(TRIM(code)) = ?', [$normalizedCode])->first();

		if (! $coupon) {
			return back()->with('error', 'Coupon code not found.');
		}

		$ineligibilityReason = $this->getCouponIneligibilityReason($coupon, $subtotal);
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

	public function placeOrder(Request $request)
	{
		$data = $request->validate([
			'payment_method' => 'required|in:cod,razorpay',
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
		$totalQuantity = (int) $cartItems->sum(fn ($item) => (int) $item->quantity);
		$appliedCoupon = $this->getAppliedCouponSummary($cartItems);
		$discount = (float) ($appliedCoupon['discount'] ?? 0);
		$shippingCharge = $subtotal >= 5000 ? 0.0 : 199.0;
		$orderTotal = max(0, $subtotal - $discount) + $shippingCharge;
		$shippingSame = $request->boolean('shipping_same_as_billing', true);

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

		$order = DB::transaction(function () use ($provider, $subtotal, $discount, $shippingCharge, $orderTotal, $billingAddress, $shippingAddress, $data, $cartItems, $appliedCoupon) {
			$order = Order::create([
				'user_id' => Auth::id(),
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
						'discount' => $discount,
						'shipping_charge' => $shippingCharge,
						'grand_total' => $orderTotal,
					],
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

			foreach ($cartItems as $cartItem) {
				$variation = $cartItem->variation;
				$variationLabel = collect($variation?->attributes ?? [])->map(fn ($val, $key) => ucfirst($key) . ': ' . $val)->implode(' | ');
				$productName = optional($cartItem->product)->name ?? 'Product';

				if ($variationLabel !== '') {
					$productName .= ' (' . $variationLabel . ')';
				}

				OrderItem::create([
					'order_id' => $order->id,
					'product_id' => $cartItem->product_id,
					'product_variation_id' => $cartItem->product_variation_id,
					'product_name' => $productName,
					'sku' => $variation?->sku ?? optional($cartItem->product)->sku,
					'unit_price' => (float) $cartItem->price,
					'quantity' => (int) $cartItem->quantity,
					'line_total' => (float) $cartItem->quantity * (float) $cartItem->price,
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
				'notes' => [
					'method' => $data['payment_method'],
				],
			]);

			return $order;
		});

		if ($data['payment_method'] === 'cod') {
			$this->sendInvoiceMailIfNeeded($order);
			Cart::where('user_id', Auth::id())->delete();
			session()->forget('checkout_coupon');

			return redirect()->route('checkout.success', $order)
				->with('success', 'Order placed successfully with Cash on Delivery.');
		}

		session()->forget('checkout_coupon');

		$razorpayOrder = $this->createRazorpayOrder($provider, $order);

		$order->update([
			'gateway_order_id' => $razorpayOrder['id'],
			'payment_meta' => array_merge($order->payment_meta ?? [], [
				'razorpay_order' => $razorpayOrder,
			]),
		]);

		$order->paymentTransactions()->where('type', 'payment')->latest('id')->first()?->update([
			'gateway_order_id' => $razorpayOrder['id'],
			'payload' => $razorpayOrder,
		]);

		return response()->json([
			'order' => [
				'id' => $order->id,
			],
			'razorpay' => [
				'key' => $provider->public_key,
				'order_id' => $razorpayOrder['id'],
				'amount' => (int) ($order->total * 100),
				'currency' => 'INR',
			],
		]);
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

		$payload = $data['razorpay_order_id'] . '|' . $data['razorpay_payment_id'];
		$expectedSignature = hash_hmac('sha256', $payload, (string) $provider->secret_key);

		if (! hash_equals($expectedSignature, $data['razorpay_signature'])) {
			$order->paymentTransactions()
				->where('type', 'payment')
				->where('gateway_order_id', $data['razorpay_order_id'])
				->latest('id')
				->first()?->update([
					'status' => 'failed',
					'signature' => $data['razorpay_signature'],
				]);

			$order->update([
				'payment_status' => 'failed',
				'payment_meta' => array_merge($order->payment_meta ?? [], [
					'verification_error' => 'signature_mismatch',
				]),
			]);

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
				'receipt' => 'ord_' . $order->id,
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
	): void {
		$order->update([
			'status' => in_array($order->status, ['pending', 'failed'], true) ? 'processing' : $order->status,
			'payment_status' => 'paid',
			'transaction_id' => $gatewayPaymentId,
			'gateway_order_id' => $gatewayOrderId,
			'payment_meta' => array_merge($order->payment_meta ?? [], [
				'verification' => [
					'status' => 'success',
					'verified_at' => now()->toDateTimeString(),
				],
			]),
		]);

		$order->paymentTransactions()
			->where('type', 'payment')
			->where('gateway_order_id', $gatewayOrderId)
			->latest('id')
			->first()?->update([
				'status' => 'captured',
				'gateway_payment_id' => $gatewayPaymentId,
				'signature' => $signature,
				'payload' => $payload,
			]);

		$this->inventoryService->deductForOrder($order);
		$this->sendInvoiceMailIfNeeded($order);
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

		$order->update([
			'payment_status' => 'failed',
		]);

		$order->paymentTransactions()
			->where('type', 'payment')
			->where('gateway_order_id', $gatewayOrderId)
			->latest('id')
			->first()?->update([
				'status' => 'failed',
				'payload' => ['source' => 'webhook', 'entity' => $entity],
			]);
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
		}
	}

	private function sendInvoiceMailIfNeeded(Order $order): void
	{
		if (! $order->user || empty($order->user->email)) {
			return;
		}

		$meta = $order->payment_meta ?? [];
		if (! empty($meta['invoice_emailed_at'])) {
			return;
		}

		Mail::to($order->user->email)->send(new OrderInvoiceMail($order));

		$order->update([
			'payment_meta' => array_merge($meta, [
				'invoice_emailed_at' => now()->toDateTimeString(),
			]),
		]);
	}

	private function sendCreditNoteMail(Order $order, OrderRefund $refund): void
	{
		if (! $order->user || empty($order->user->email)) {
			return;
		}

		Mail::to($order->user->email)->send(new OrderCreditNoteMail($order, $refund));

		$refund->update([
			'emailed_at' => now(),
		]);
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
