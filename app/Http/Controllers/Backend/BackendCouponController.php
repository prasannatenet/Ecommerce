<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;

class BackendCouponController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $coupons = Coupon::orderByDesc('created_at')->paginate(15);
        return view('backend.coupons.index', compact('coupons'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('backend.coupons.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['is_active'] = $request->boolean('is_active', true);
        Coupon::create($data);

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Coupon $coupon)
    {
        return redirect()->route('admin.coupons.edit', $coupon);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Coupon $coupon)
    {
        return view('backend.coupons.edit', compact('coupon'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Coupon $coupon)
    {
        $data = $this->validateData($request, $coupon->id);
        $data['is_active'] = $request->boolean('is_active', false);
        $coupon->update($data);

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Coupon $coupon)
    {
        $coupon->delete();
        return redirect()->route('admin.coupons.index')->with('success', 'Coupon deleted successfully.');
    }

    private function validateData(Request $request, $id = null): array
    {
        $codeRule = 'required|string|max:50|unique:coupons,code';
        if ($id) {
            $codeRule .= ',' . $id;
        }

        return $request->validate([
            'code' => $codeRule,
            'type' => 'required|in:percent,fixed,buy_get,gehna_coins',
            'amount' => 'nullable|numeric|min:0.01',
            'buy_quantity' => 'required_if:type,buy_get|nullable|integer|min:1',
            'get_quantity' => 'required_if:type,buy_get|nullable|integer|min:1',
            'reward_coins' => 'required_if:type,gehna_coins|nullable|integer|min:1',
            'max_uses' => 'nullable|integer|min:1',
            'min_order_amount' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'nullable|boolean',
        ]);
    }
}
