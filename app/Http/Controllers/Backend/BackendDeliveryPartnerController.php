<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\DeliveryPartner;
use Illuminate\Http\Request;

class BackendDeliveryPartnerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $partners = DeliveryPartner::orderBy('name')->paginate(15);
        return view('backend.delivery_partners.index', compact('partners'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('backend.delivery_partners.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['is_active'] = $request->boolean('is_active', true);
        DeliveryPartner::create($data);

        return redirect()->route('admin.delivery-partners.index')->with('success', 'Delivery partner created.');
    }

    /**
     * Display the specified resource.
     */
    public function show(DeliveryPartner $deliveryPartner)
    {
        return redirect()->route('admin.delivery-partners.edit', $deliveryPartner);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DeliveryPartner $deliveryPartner)
    {
        return view('backend.delivery_partners.edit', ['partner' => $deliveryPartner]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DeliveryPartner $deliveryPartner)
    {
        $data = $this->validateData($request, $deliveryPartner->id);
        $data['is_active'] = $request->boolean('is_active', false);
        $deliveryPartner->update($data);

        return redirect()->route('admin.delivery-partners.index')->with('success', 'Delivery partner updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeliveryPartner $deliveryPartner)
    {
        $deliveryPartner->delete();
        return redirect()->route('admin.delivery-partners.index')->with('success', 'Delivery partner deleted.');
    }

    private function validateData(Request $request, $id = null): array
    {
        $codeRule = 'required|string|max:50|unique:delivery_partners,code';
        if ($id) {
            $codeRule .= ',' . $id;
        }

        return $request->validate([
            'name' => 'required|string|max:100',
            'code' => $codeRule,
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);
    }
}
