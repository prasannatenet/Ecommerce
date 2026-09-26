<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with(['items.product:id,name,slug,primary_image'])
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
            ->with(['items.product:id,name,slug,primary_image', 'shipments'])
            ->withCount('items')
            ->find($id);

        if (! $order) {
            return $this->fail('Order not found.', Response::HTTP_NOT_FOUND);
        }

        return $this->ok(new OrderResource($order));
    }
}
