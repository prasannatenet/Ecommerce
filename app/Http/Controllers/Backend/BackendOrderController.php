<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Mail\OrderCreditNoteMail;
use App\Mail\OrderInvoiceMail;
use App\Models\Order;
use App\Models\DeliveryPartner;
use App\Models\OrderRefund;
use App\Models\PaymentProvider;
use App\Models\PaymentTransaction;
use App\Services\OrderInventoryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class BackendOrderController extends Controller
{
	public function __construct(private readonly OrderInventoryService $inventoryService)
	{
	}

	public function index()
	{
		$query = $this->filteredOrdersQuery(request());

		$orders = $query->paginate(15)->withQueryString();
		return view('backend.orders.index', compact('orders'));
	}

	public function exportCsv(Request $request)
	{
		$orders = $this->filteredOrdersQuery($request)
			->with(['user'])
			->get();

		$filename = 'orders-export-' . now()->format('Ymd_His') . '.csv';

		$headers = [
			'Content-Type' => 'text/csv',
			'Content-Disposition' => 'attachment; filename="' . $filename . '"',
		];

		$callback = function () use ($orders): void {
			$output = fopen('php://output', 'w');

			fputcsv($output, [
				'Order ID',
				'Customer Name',
				'Customer Email',
				'Status',
				'Payment Method',
				'Payment Status',
				'Refund Status',
				'Total',
				'Refunded Total',
				'Date',
			]);

			foreach ($orders as $order) {
				fputcsv($output, [
					$order->id,
					optional($order->user)->name,
					optional($order->user)->email,
					$order->status,
					$order->payment_method,
					$order->payment_status,
					$order->refund_status,
					$order->total,
					$order->refunded_total,
					$order->created_at,
				]);
			}

			fclose($output);
		};

		return response()->stream($callback, 200, $headers);
	}

	public function show(Order $order)
	{
		$order->load(['user', 'paymentProvider', 'items.product', 'paymentTransactions', 'refunds', 'shipments.deliveryPartner']);
		$deliveryPartners = DeliveryPartner::where('is_active', true)->orderBy('name')->get();
		return view('backend.orders.show', compact('order', 'deliveryPartners'));
	}

	public function update(Request $request, Order $order)
	{
		$data = $request->validate([
			'status' => 'required|string|max:50',
			'payment_status' => 'nullable|in:pending,initiated,paid,failed',
		]);

		$previousStatus = $order->status;
		$previousPaymentStatus = $order->payment_status;

		$order->update([
			'status' => $data['status'],
			'payment_status' => $data['payment_status'] ?? $order->payment_status,
		]);

		if ($order->payment_status === 'paid' && $previousPaymentStatus !== 'paid') {
			$this->inventoryService->deductForOrder($order);
			$this->sendInvoiceMailIfNeeded($order);
		}

		if ($order->status === 'cancelled' && $previousStatus !== 'cancelled') {
			$this->inventoryService->restockForOrder($order);
		}

		if (
			$order->payment_method === 'cod'
			&& in_array($order->status, ['processing', 'shipped', 'delivered'], true)
			&& ! $order->stock_deducted
		) {
			$this->inventoryService->deductForOrder($order);
		}

		return redirect()->route('admin.orders.show', $order)->with('success', 'Order status updated.');
	}

	public function cancel(Request $request, Order $order)
	{
		$request->validate([
			'reason' => 'nullable|string|max:500',
		]);

		if (! in_array($order->status, ['pending', 'processing'], true)) {
			return redirect()->route('admin.orders.show', $order)->with('error', 'Order cannot be cancelled in its current state.');
		}

		$order->update([
			'status' => 'cancelled',
			'cancel_reason' => $request->input('reason', 'Cancelled by admin'),
			'cancelled_at' => now(),
		]);

		$this->inventoryService->restockForOrder($order);

		return redirect()->route('admin.orders.show', $order)->with('success', 'Order cancelled successfully.');
	}

	public function refund(Request $request, Order $order)
	{
		$data = $request->validate([
			'amount' => 'required|numeric|min:0.01',
			'reason' => 'nullable|string|max:500',
			'refund_method' => 'nullable|in:money,gehna_coins',
		]);

		if ($order->payment_status !== 'paid') {
			return redirect()->route('admin.orders.show', $order)->with('error', 'Only paid orders can be refunded.');
		}

		$amount = round((float) $data['amount'], 2);
		if ($amount > (float) $order->total) {
			return redirect()->route('admin.orders.show', $order)->with('error', 'Refund amount cannot exceed order total.');
		}

		$remainingRefundable = round((float) $order->total - (float) $order->refunded_total, 2);
		if ($amount > $remainingRefundable) {
			return redirect()->route('admin.orders.show', $order)->with('error', 'Refund amount exceeds the remaining refundable balance.');
		}

		$refundMethod = $data['refund_method'] ?? 'money';

		if ($refundMethod === 'gehna_coins') {
			$refund = DB::transaction(function () use ($order, $amount, $data) {
				$refund = OrderRefund::create([
					'order_id' => $order->id,
					'amount' => $amount,
					'status' => 'processed',
					'reason' => $data['reason'] ?? 'Admin issued Gehna Coins',
					'refund_method' => 'gehna_coins',
					'metadata' => ['source' => 'admin'],
					'processed_at' => now(),
				]);
				$order->user()->lockForUpdate()->first()->increment('gehna_coins', (int) round($amount));
				$totalRefunded = (float) $order->refunds()->where('status', 'processed')->sum('amount');
				$order->update([
					'refunded_total' => $totalRefunded,
					'refund_status' => $totalRefunded >= (float) $order->total ? 'full' : 'partial',
					'refunded_at' => now(),
				]);
				return $refund;
			});

			if ($refund && $order->user?->email) {
				Mail::to($order->user->email)->send(new OrderCreditNoteMail($order, $refund));
			}
			if ((float) $order->fresh()->refunded_total >= (float) $order->total) {
				$this->inventoryService->restockForOrder($order->fresh());
			}

			return redirect()->route('admin.orders.show', $order)->with('success', 'Gehna Coins credited successfully.');
		}

		$provider = PaymentProvider::find($order->payment_provider_id);
		if (! $provider || $provider->slug !== 'razorpay') {
			return redirect()->route('admin.orders.show', $order)->with('error', 'Automatic refunds are only enabled for Razorpay paid orders.');
		}

		if (empty($provider->public_key) || empty($provider->secret_key)) {
			throw ValidationException::withMessages([
				'amount' => 'Razorpay keys are missing. Configure payment provider credentials first.',
			]);
		}

		$paymentTxn = $order->paymentTransactions()
			->where('type', 'payment')
			->where('status', 'captured')
			->latest('id')
			->first();

		if (! $paymentTxn || empty($paymentTxn->gateway_payment_id)) {
			return redirect()->route('admin.orders.show', $order)->with('error', 'No captured payment transaction found for this order.');
		}

		$verifySsl = filter_var(env('RAZORPAY_SSL_VERIFY', app()->environment('production')), FILTER_VALIDATE_BOOLEAN);

		$response = Http::withOptions(['verify' => $verifySsl])
			->withBasicAuth($provider->public_key, $provider->secret_key)
			->timeout(20)
			->post("https://api.razorpay.com/v1/payments/{$paymentTxn->gateway_payment_id}/refund", [
				'amount' => (int) round($amount * 100),
				'notes' => [
					'order_id' => (string) $order->id,
					'reason' => $data['reason'] ?? 'Admin initiated refund',
				],
			]);

		if (! $response->successful()) {
			return redirect()->route('admin.orders.show', $order)->with('error', 'Razorpay refund request failed. Check credentials and retry.');
		}

		$refundPayload = $response->json();

		$refundTxn = PaymentTransaction::create([
			'order_id' => $order->id,
			'payment_provider_id' => $provider->id,
			'type' => 'refund',
			'status' => 'processed',
			'amount' => $amount,
			'currency' => 'INR',
			'gateway_payment_id' => $paymentTxn->gateway_payment_id,
			'gateway_refund_id' => $refundPayload['id'] ?? null,
			'payload' => $refundPayload,
			'notes' => [
				'reason' => $data['reason'] ?? null,
			],
		]);

		OrderRefund::create([
			'order_id' => $order->id,
			'payment_transaction_id' => $refundTxn->id,
			'amount' => $amount,
			'status' => 'processed',
			'reason' => $data['reason'] ?? 'Admin initiated refund',
			'gateway_refund_id' => $refundPayload['id'] ?? null,
			'metadata' => $refundPayload,
			'processed_at' => now(),
		]);

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

		$createdRefund = $order->refunds()->latest('id')->first();
		if ($createdRefund && ! $createdRefund->emailed_at) {
			$this->sendCreditNoteMail($order, $createdRefund);
		}

		return redirect()->route('admin.orders.show', $order)->with('success', 'Refund processed successfully.');
	}

	public function invoice(Order $order)
	{
		$order->load(['user', 'items', 'paymentProvider']);

		$pdf = Pdf::loadView('backend.orders.pdf.invoice', [
			'order' => $order,
		]);

		return $pdf->download('invoice-order-' . $order->id . '.pdf');
	}

	public function creditNote(Order $order, OrderRefund $refund)
	{
		abort_if($refund->order_id !== $order->id, 404);

		$order->load(['user', 'items', 'paymentProvider']);

		$pdf = Pdf::loadView('backend.orders.pdf.credit_note', [
			'order' => $order,
			'refund' => $refund,
		]);

		return $pdf->download('credit-note-order-' . $order->id . '-refund-' . $refund->id . '.pdf');
	}

	private function filteredOrdersQuery(Request $request)
	{
		$query = Order::with(['user', 'paymentProvider', 'items', 'shipments.deliveryPartner'])->orderByDesc('created_at');

		if ($request->filled('status')) {
			$query->where('status', $request->string('status')->toString());
		}

		if ($request->filled('payment_method')) {
			$query->where('payment_method', $request->string('payment_method')->toString());
		}

		if ($request->filled('payment_status')) {
			$query->where('payment_status', $request->string('payment_status')->toString());
		}

		if ($request->filled('refund_status')) {
			$query->where('refund_status', $request->string('refund_status')->toString());
		}

		if ($request->filled('date_from')) {
			$query->whereDate('created_at', '>=', $request->string('date_from')->toString());
		}

		if ($request->filled('date_to')) {
			$query->whereDate('created_at', '<=', $request->string('date_to')->toString());
		}

		return $query;
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
}
