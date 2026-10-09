<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Mail\ReturnRequestMail;
use App\Services\Delivery\DeliveryManager;
use App\Services\OrderInventoryService;
use App\Services\ProductRecommendationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

/**
 * The signed-in customer's order history.
 *
 *   GET /gehna/api/v1/orders
 *   GET /gehna/api/v1/orders/{id}
 *
 * Read-only on purpose. Placing an order in this project moves stock, calls a
 * payment gateway and books a delivery partner (see FrontendCheckoutController
 * and OrderInventoryService); exposing that over a token API without the
 * idempotency and gateway-webhook guarantees the Blade checkout has would let
 * a double-tapped React button create two real orders. Checkout stays on the
 * web routes; this endpoint is for showing the customer what already happened.
 */
class OrderController extends ApiController
{
    public function __construct(
        private readonly DeliveryManager $deliveryManager,
        private readonly OrderInventoryService $inventoryService,
        private readonly ProductRecommendationService $recommendations,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with(['items.product:id,name,slug,primary_image', 'returnRequests.items'])
            ->withCount('items')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->input('payment_status')))
            ->latest()
            ->paginate($this->perPage($request, 10))
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => OrderResource::collection($orders->items())->resolve(),
            'meta' => (object) [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::query()
            ->where('user_id', $request->user()->id)
            ->with(['items.product:id,name,slug,primary_image', 'shipments', 'returnRequests.items'])
            ->withCount('items')
            ->find($id);

        if (! $order) {
            return $this->fail('Order not found.', Response::HTTP_NOT_FOUND);
        }

        return $this->ok(new OrderResource($order));
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $order = DB::transaction(function () use ($request, $id, $data): ?Order {
            $order = Order::query()
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->find($id);

            if (! $order) {
                abort(Response::HTTP_NOT_FOUND, 'Order not found.');
            }

            if (! in_array($order->status, ['pending', 'processing'], true)) {
                return null;
            }

            $order->update([
                'status' => 'cancelled',
                'cancel_reason' => $data['reason'] ?? 'Cancelled by customer',
                'cancelled_at' => now(),
            ]);

            $meta = $order->payment_meta ?? [];
            $coinsUsed = (int) ($meta['pricing']['coins_used'] ?? 0);

            if ($coinsUsed > 0 && empty($meta['coins_restored_at'])) {
                $order->user()->increment('gehna_coins', $coinsUsed);
                $order->update([
                    'payment_meta' => array_merge($meta, [
                        'coins_restored_at' => now()->toDateTimeString(),
                    ]),
                ]);
            }

            $this->inventoryService->restockForOrder($order);

            return $order->fresh();
        });

        if (! $order) {
            return $this->fail('This order can no longer be cancelled.', Response::HTTP_CONFLICT);
        }

        $courierNote = $this->deliveryManager->cancelForOrder($order);

        $this->recommendations->forgetForOrder($order->fresh());

        if ($courierNote !== null) {
            Log::warning('Courier cancellation incomplete on API customer cancel', [
                'order_id' => $order->id,
                'note' => $courierNote,
            ]);
        }

        return $this->ok(
            new OrderResource($order->fresh()->load(['items.product:id,name,slug,primary_image'])->loadCount('items')),
            message: 'Order cancelled successfully. If payment was prepaid, refund will be initiated by admin.',
        );
    }

    public function storeReturnRequest(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'payout_method' => ['required', 'in:bank,upi,gehna_coins'],
            'account_holder' => ['required_if:payout_method,bank', 'nullable', 'string', 'max:120'],
            'account_number' => ['required_if:payout_method,bank', 'nullable', 'string', 'max:40'],
            'ifsc' => ['required_if:payout_method,bank', 'nullable', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'upi_id' => ['required_if:payout_method,upi', 'nullable', 'string', 'max:120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $returnRequest = DB::transaction(function () use ($request, $id, $data): ReturnRequest {
            $order = Order::query()
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->with('items')
                ->find($id);

            if (! $order) {
                abort(Response::HTTP_NOT_FOUND, 'Order not found.');
            }

            if ($order->status !== 'delivered') {
                abort(Response::HTTP_CONFLICT, 'Returns can be requested after the order is delivered.');
            }

            $alreadyRequested = $order->returnRequests()
                ->whereIn('status', ['requested', 'approved'])
                ->with('items')
                ->get()
                ->flatMap->items
                ->groupBy('order_item_id')
                ->map(fn ($items) => $items->sum('quantity'));

            $returnItems = collect($data['items'])->map(function (array $requested) use ($order, $alreadyRequested) {
                $item = $order->items->firstWhere('id', (int) $requested['order_item_id']);
                $quantity = (int) $requested['quantity'];
                $available = $item ? $item->quantity - (int) ($alreadyRequested[$item->id] ?? 0) : 0;

                if (! $item || $quantity > $available) {
                    throw ValidationException::withMessages([
                        'items' => ['One or more selected quantities are not available for return.'],
                    ]);
                }

                return ['item' => $item, 'quantity' => $quantity];
            });

            $returnRequest = ReturnRequest::create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'type' => 'return',
                'status' => 'requested',
                'reason' => $data['reason'],
                'payout_method' => $data['payout_method'],
                'payout_details' => [
                    'account_holder' => $data['account_holder'] ?? null,
                    'account_number' => $data['account_number'] ?? null,
                    'ifsc' => $data['ifsc'] ?? null,
                    'bank_name' => $data['bank_name'] ?? null,
                    'upi_id' => $data['upi_id'] ?? null,
                ],
                'amount' => $returnItems->sum(fn ($row) => $row['item']->unit_price * $row['quantity']),
            ]);

            foreach ($returnItems as $row) {
                $returnRequest->items()->create([
                    'order_item_id' => $row['item']->id,
                    'quantity' => $row['quantity'],
                    'amount' => round((float) $row['item']->unit_price * $row['quantity'], 2),
                ]);
            }

            return $returnRequest;
        });

        $order = $this->ownedOrder($request, $id);

        $notificationSent = true;
        if ($order->user?->email) {
            try {
                Mail::to($order->user->email)->send(new ReturnRequestMail($returnRequest, $order));
            } catch (Throwable $exception) {
                report($exception);
                $notificationSent = false;
            }
        }

        return $this->ok([
            'id' => $returnRequest->id,
            'status' => $returnRequest->status,
            'reason' => $returnRequest->reason,
            'amount' => (float) $returnRequest->amount,
            'notification_sent' => $notificationSent,
        ], message: $notificationSent
            ? 'Return request submitted for admin review.'
            : 'Return request submitted, but the notification email could not be sent.',
            status: Response::HTTP_CREATED);
    }

    private function ownedOrder(Request $request, int $id): Order
    {
        $order = Order::query()
            ->where('user_id', $request->user()->id)
            ->with('user')
            ->find($id);

        if (! $order) {
            abort(Response::HTTP_NOT_FOUND, 'Order not found.');
        }

        return $order;
    }
}
