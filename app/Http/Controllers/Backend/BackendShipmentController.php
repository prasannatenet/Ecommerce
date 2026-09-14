<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\Order;
use App\Services\Delivery\DeliveryManager;
use Illuminate\Http\Request;

class BackendShipmentController extends Controller
{
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

