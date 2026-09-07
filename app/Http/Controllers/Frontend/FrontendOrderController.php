<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Services\OrderInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class FrontendOrderController extends Controller
{
    public function __construct(private readonly OrderInventoryService $inventoryService)
    {
    }

    public function index(): View
    {
        $orders = Order::with(['items', 'refunds'])
            ->where('user_id', Auth::id())
            ->latest('id')
            ->paginate(10);

        return view('frontend.order.index', compact('orders'));
    }

    public function show(Order $order): View
    {
        abort_if($order->user_id !== Auth::id(), 403);

        $order->load(['items', 'paymentProvider', 'paymentTransactions', 'refunds', 'returnRequests.items']);

        return view('frontend.order.show', compact('order'));
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        abort_if($order->user_id !== Auth::id(), 403);

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        if (! in_array($order->status, ['pending', 'processing'], true)) {
            return redirect()->route('orders.show', $order)
                ->with('error', 'This order can no longer be cancelled.');
        }

        $order->update([
            'status' => 'cancelled',
            'cancel_reason' => $request->input('reason', 'Cancelled by customer'),
            'cancelled_at' => now(),
        ]);

        $this->inventoryService->restockForOrder($order);

        return redirect()->route('orders.show', $order)
            ->with('success', 'Order cancelled successfully. If payment was prepaid, refund will be initiated by admin.');
    }

    public function storeReturnRequest(Request $request, Order $order): RedirectResponse
    {
        abort_if($order->user_id !== Auth::id(), 403);

        $data = $request->validate([
            'reason' => 'required|string|max:500',
            'items' => 'required|array|min:1',
            'items.*' => 'integer|distinct',
            'quantities' => 'required|array',
            'quantities.*' => 'integer|min:1',
        ]);

        if ($order->status !== 'delivered') {
            return back()->with('error', 'Returns can be requested after the order is delivered.');
        }

        $order->load('items');
        $requestedItems = collect($data['items'])->mapWithKeys(fn ($id) => [(int) $id => (int) ($data['quantities'][$id] ?? 0)]);
        $alreadyRequested = $order->returnRequests()->whereIn('status', ['requested', 'approved'])->with('items')->get()
            ->flatMap->items->groupBy('order_item_id')->map(fn ($items) => $items->sum('quantity'));
        $returnItems = collect();

        foreach ($requestedItems as $itemId => $quantity) {
            $item = $order->items->firstWhere('id', $itemId);
            $available = $item ? $item->quantity - (int) ($alreadyRequested[$itemId] ?? 0) : 0;
            if (! $item || $quantity < 1 || $quantity > $available) {
                return back()->with('error', 'One or more selected quantities are not available for return.');
            }
            $returnItems->push(['item' => $item, 'quantity' => $quantity]);
        }

        DB::transaction(function () use ($order, $data, $returnItems) {
            $returnRequest = ReturnRequest::create([
                'order_id' => $order->id,
                'user_id' => Auth::id(),
                'type' => 'return',
                'status' => 'requested',
                'reason' => $data['reason'],
                'amount' => $returnItems->sum(fn ($row) => $row['item']->unit_price * $row['quantity']),
            ]);

            foreach ($returnItems as $row) {
                $returnRequest->items()->create([
                    'order_item_id' => $row['item']->id,
                    'quantity' => $row['quantity'],
                    'amount' => round((float) $row['item']->unit_price * $row['quantity'], 2),
                ]);
            }
        });

        return redirect()->route('orders.show', $order)->with('success', 'Return request submitted for admin review.');
    }
}
