<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Mail\OrderCreditNoteMail;
use App\Models\OrderRefund;
use App\Models\PaymentTransaction;
use App\Models\PaymentProvider;
use App\Models\ReturnRequest;
use App\Services\OrderInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class BackendReturnRequestController extends Controller
{
    public function __construct(private readonly OrderInventoryService $inventoryService) {}

    public function index()
    {
        $requests = ReturnRequest::with(['user', 'order'])->latest()->paginate(20);
        return view('backend.return_requests.index', compact('requests'));
    }

    public function show(ReturnRequest $returnRequest)
    {
        $returnRequest->load(['user', 'order.items', 'items.orderItem']);
        return view('backend.return_requests.show', compact('returnRequest'));
    }

    public function update(Request $request, ReturnRequest $returnRequest)
    {
        $data = $request->validate([
            'decision' => 'required|in:approve,reject',
            'refund_method' => 'required_if:decision,approve|nullable|in:money,gehna_coins,bank_transfer',
            'payout_reference' => 'required_if:refund_method,bank_transfer|nullable|string|max:120',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        if ($returnRequest->status !== 'requested') {
            return back()->with('error', 'This request has already been processed.');
        }

        if ($data['decision'] === 'reject') {
            $returnRequest->update(['status' => 'rejected', 'admin_note' => $data['admin_note'] ?? null, 'processed_at' => now()]);
            return redirect()->route('admin.return-requests.show', $returnRequest)->with('success', 'Return request rejected.');
        }

        $returnRequest->load(['order.paymentTransactions', 'items.orderItem', 'user']);
        $amount = round((float) $returnRequest->items->sum('amount'), 2);
        $order = $returnRequest->order;
        $remainingRefundable = round((float) $order->total - (float) $order->refunded_total, 2);
        if ($amount > $remainingRefundable) {
            return back()->with('error', 'This request exceeds the remaining refundable order balance.');
        }
        $gatewayRefund = null;

        if ($data['refund_method'] === 'money') {
            $provider = PaymentProvider::find($order->payment_provider_id);
            $paymentTxn = $order->paymentTransactions->first(fn ($txn) => $txn->type === 'payment' && $txn->status === 'captured');
            if (! $provider || $provider->slug !== 'razorpay' || empty($provider->public_key) || empty($provider->secret_key) || ! $paymentTxn?->gateway_payment_id) {
                return back()->with('error', 'Money refunds require a captured Razorpay payment. Choose bank transfer or Gehna Coins for this request.');
            }

            $response = Http::withBasicAuth($provider->public_key, $provider->secret_key)
                ->timeout(20)
                ->post("https://api.razorpay.com/v1/payments/{$paymentTxn->gateway_payment_id}/refund", [
                    'amount' => (int) round($amount * 100),
                    'notes' => ['order_id' => (string) $order->id, 'return_request_id' => (string) $returnRequest->id],
                ]);
            if (! $response->successful()) {
                return back()->with('error', 'The gateway rejected the money refund. No changes were made.');
            }
            $gatewayRefund = [$provider, $paymentTxn, $response->json()];
        }

        DB::transaction(function () use ($data, $returnRequest, $amount, $order, $gatewayRefund) {
            $requestRow = ReturnRequest::whereKey($returnRequest->id)->lockForUpdate()->first();
            if ($requestRow->status !== 'requested') {
                throw ValidationException::withMessages(['decision' => 'This request was already processed.']);
            }

            $refundTxn = null;
            if ($gatewayRefund) {
                $refundTxn = PaymentTransaction::create([
                    'order_id' => $order->id,
                    'payment_provider_id' => $gatewayRefund[0]->id,
                    'type' => 'refund',
                    'status' => 'processed',
                    'amount' => $amount,
                    'currency' => 'INR',
                    'gateway_payment_id' => $gatewayRefund[1]->gateway_payment_id,
                    'gateway_refund_id' => $gatewayRefund[2]['id'] ?? null,
                    'payload' => $gatewayRefund[2],
                ]);
            }

            $refund = OrderRefund::create([
                'order_id' => $order->id,
                'payment_transaction_id' => $refundTxn?->id,
                'amount' => $amount,
                'status' => 'processed',
                'reason' => $requestRow->reason,
                'gateway_refund_id' => $gatewayRefund[2]['id'] ?? null,
                'metadata' => ['return_request_id' => $requestRow->id, 'gateway' => $gatewayRefund[2] ?? null],
                'processed_at' => now(),
                'refund_method' => $data['refund_method'],
                'payout_reference' => $data['payout_reference'] ?? null,
            ]);

            if ($data['refund_method'] === 'gehna_coins') {
                $requestRow->user()->lockForUpdate()->first()->increment('gehna_coins', (int) round($amount));
            }

            $requestRow->update([
                'status' => 'approved',
                'amount' => $amount,
                'refund_method' => $data['refund_method'],
                'payout_reference' => $data['payout_reference'] ?? null,
                'admin_note' => $data['admin_note'] ?? null,
                'processed_at' => now(),
            ]);

            $totalRefunded = (float) $order->refunds()->where('status', 'processed')->sum('amount');
            $order->update([
                'refunded_total' => $totalRefunded,
                'refund_status' => $totalRefunded >= (float) $order->total ? 'full' : 'partial',
                'refunded_at' => now(),
            ]);

            if ($totalRefunded >= (float) $order->total) {
                $this->inventoryService->restockForOrder($order);
            }

            if ($requestRow->user && $requestRow->user->email) {
                Mail::to($requestRow->user->email)->send(new OrderCreditNoteMail($order, $refund));
            }
        });

        return redirect()->route('admin.return-requests.show', $returnRequest)->with('success', 'Return approved and refund issued.');
    }
}
