<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\DeliveryPartner;
use App\Models\Shipment;
use App\Models\Order;
use App\Services\Delivery\DeliveryManager;
use App\Services\Delivery\DeliveryStatus;
use Illuminate\Http\Request;

class BackendShipmentController extends Controller
{
    /**
     * Shipment Management dashboard: totals across all orders, with filters.
     */
    public function index(Request $request)
    {
        $status = trim((string) $request->query('status', ''));
        $partnerId = $request->query('partner');
        $search = trim((string) $request->query('q', ''));
        $perPage = (int) $request->query('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 15;

        $stats = [
            'total' => Shipment::query()->count(),
            'in_transit' => Shipment::query()->inTransit()->count(),
            'delivered' => Shipment::query()->delivered()->count(),
            'exceptions' => Shipment::query()->exceptions()->count(),
        ];

        $shipments = Shipment::query()
            ->with(['deliveryPartner:id,name,driver', 'trackingEvents', 'order:id,user_id,status,total,payment_method,payment_status,shipping_address'])
            ->when($status !== '', function ($query) use ($status) {
                if ($status === 'exceptions') {
                    $query->exceptions();

                    return;
                }

                if ($status === 'in_transit') {
                    $query->inTransit();

                    return;
                }

                $query->where('status', $status);
            })
            ->when($partnerId, fn ($query) => $query->where('delivery_partner_id', $partnerId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('tracking_number', 'like', '%' . $search . '%')
                        ->orWhere('provider_shipment_id', 'like', '%' . $search . '%');

                    if (ctype_digit($search)) {
                        $inner->orWhere('order_id', (int) $search);
                    }
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('backend.shipments.index', [
            'shipments' => $shipments,
            'stats' => $stats,
            'partners' => DeliveryPartner::orderBy('name')->get(['id', 'name', 'driver', 'is_active']),
            'statusOptions' => DeliveryStatus::labels(),
            'filters' => [
                'status' => $status,
                'partner' => $partnerId,
                'q' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(Request $request, Order $order, DeliveryManager $manager)
    {
        $data = $this->validateData($request);
        $data['order_id'] = $order->id;

        $shipment = Shipment::create($data);

        if ($request->boolean('book_now')) {
            $result = $manager->book($shipment->fresh());

            return redirect()->route('admin.orders.show', $order)->with(
                $result->success ? 'success' : 'delivery_error',
                $result->success
                    ? 'Shipment created and booked. AWB: ' . $result->waybill
                    : 'Shipment created, but booking failed: ' . $result->error
            );
        }

        return redirect()->route('admin.orders.show', $order)->with('success', 'Shipment created.');
    }

    public function update(Request $request, Shipment $shipment)
    {
        $data = $this->validateData($request, $shipment->id);
        $shipment->update($data);

        return redirect()->route('admin.orders.show', $shipment->order_id)->with('success', 'Shipment updated.');
    }

    public function destroy(Shipment $shipment)
    {
        $orderId = $shipment->order_id;
        $shipment->delete();
        return redirect()->route('admin.orders.show', $orderId)->with('success', 'Shipment deleted.');
    }

    public function book(Request $request, Shipment $shipment, DeliveryManager $manager)
    {
        $result = $manager->book($shipment);

        return redirect()->route('admin.orders.show', $shipment->order_id)->with(
            $result->success ? 'success' : 'delivery_error',
            $result->success
                ? 'Shipment booked. AWB: ' . $result->waybill
                : 'Booking failed: ' . $result->error
        );
    }

    public function sync(Request $request, Shipment $shipment, DeliveryManager $manager)
    {
        $result = $manager->sync($shipment);

        return redirect()->route('admin.orders.show', $shipment->order_id)->with(
            $result ? 'success' : 'delivery_error',
            $result ? 'Tracking synced. Latest status: ' . $shipment->fresh()->status : 'Sync failed: ' . ($shipment->fresh()->last_sync_error ?? 'unknown error')
        );
    }

    public function label(Shipment $shipment, DeliveryManager $manager)
    {
        $url = $manager->label($shipment);

        if (! $url) {
            return redirect()->route('admin.orders.show', $shipment->order_id)
                ->with('delivery_error', 'Could not fetch the courier label.');
        }

        return redirect($url);
    }

    private function validateData(Request $request, $id = null): array
    {
        return $request->validate([
            'delivery_partner_id' => 'nullable|exists:delivery_partners,id',
            'tracking_number' => 'nullable|string|max:255',
            'tracking_url' => 'nullable|url|max:1000',
            'provider_shipment_id' => 'nullable|string|max:255',
            'label_url' => 'nullable|string|max:1000',
            'status' => 'required|string|max:50',
            'shipped_at' => 'nullable|date',
            'delivered_at' => 'nullable|date|after_or_equal:shipped_at',
            'booking_requested_at' => 'nullable|date',
            'booked_at' => 'nullable|date',
            'last_synced_at' => 'nullable|date',
            'last_sync_error' => 'nullable|string',
            'meta' => 'nullable|array',
        ]);
    }
}

