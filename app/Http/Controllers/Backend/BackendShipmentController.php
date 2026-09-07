<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\Order;
use Illuminate\Http\Request;

class BackendShipmentController extends Controller
{
    public function store(Request $request, Order $order)
    {
        $data = $this->validateData($request);
        $data['order_id'] = $order->id;

        Shipment::create($data);

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

    private function validateData(Request $request, $id = null): array
    {
        return $request->validate([
            'delivery_partner_id' => 'nullable|exists:delivery_partners,id',
            'tracking_number' => 'nullable|string|max:255',
            'status' => 'required|string|max:50',
            'shipped_at' => 'nullable|date',
            'delivered_at' => 'nullable|date|after_or_equal:shipped_at',
        ]);
    }
}
