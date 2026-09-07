<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\PaymentProvider;
use Illuminate\Http\Request;

class BackendPaymentProviderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $providers = PaymentProvider::orderBy('name')->paginate(15);
        return view('backend.payment_providers.index', compact('providers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('backend.payment_providers.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['is_active'] = $request->boolean('is_active', true);
        PaymentProvider::create($data);

        return redirect()->route('admin.payment-providers.index')->with('success', 'Payment provider created.');
    }

    /**
     * Display the specified resource.
     */
    public function show(PaymentProvider $paymentProvider)
    {
        return redirect()->route('admin.payment-providers.edit', $paymentProvider);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PaymentProvider $paymentProvider)
    {
        return view('backend.payment_providers.edit', ['provider' => $paymentProvider]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PaymentProvider $paymentProvider)
    {
        $data = $this->validateData($request, $paymentProvider->id);
        $data['is_active'] = $request->boolean('is_active', false);
        $paymentProvider->update($data);

        return redirect()->route('admin.payment-providers.index')->with('success', 'Payment provider updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentProvider $paymentProvider)
    {
        $paymentProvider->delete();
        return redirect()->route('admin.payment-providers.index')->with('success', 'Payment provider deleted.');
    }

    public function toggleStatus(PaymentProvider $paymentProvider)
    {
        $paymentProvider->update([
            'is_active' => ! $paymentProvider->is_active,
        ]);

        $state = $paymentProvider->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.payment-providers.index')
            ->with('success', "{$paymentProvider->name} {$state} successfully.");
    }

    public function settings()
    {
        $providers = PaymentProvider::whereIn('slug', ['cod', 'razorpay'])
            ->orderBy('name')
            ->get()
            ->keyBy('slug');

        return view('backend.payment_providers.settings', [
            'cod' => $providers->get('cod'),
            'razorpay' => $providers->get('razorpay'),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'cod_active' => 'nullable|boolean',
            'razorpay_active' => 'nullable|boolean',
            'razorpay_public_key' => 'nullable|string|max:255',
            'razorpay_secret_key' => 'nullable|string|max:255',
            'razorpay_webhook_secret' => 'nullable|string|max:255',
        ]);

        $cod = PaymentProvider::firstOrCreate(
            ['slug' => 'cod'],
            ['name' => 'Cash on Delivery', 'is_active' => true]
        );

        $cod->update([
            'is_active' => (bool) ($data['cod_active'] ?? false),
        ]);

        $razorpay = PaymentProvider::firstOrCreate(
            ['slug' => 'razorpay'],
            ['name' => 'Razorpay', 'is_active' => false]
        );

        $razorpay->update([
            'is_active' => (bool) ($data['razorpay_active'] ?? false),
            'public_key' => $data['razorpay_public_key'] ?? null,
            'secret_key' => $data['razorpay_secret_key'] ?? null,
            'webhook_secret' => $data['razorpay_webhook_secret'] ?? null,
        ]);

        return redirect()->route('admin.payment-providers.settings')
            ->with('success', 'Payment settings saved successfully.');
    }

    private function validateData(Request $request, $id = null): array
    {
        $slugRule = 'required|string|max:100|unique:payment_providers,slug';
        if ($id) {
            $slugRule .= ',' . $id;
        }

        return $request->validate([
            'name' => 'required|string|max:100',
            'slug' => $slugRule,
            'public_key' => 'nullable|string|max:255',
            'secret_key' => 'nullable|string|max:255',
            'webhook_secret' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);
    }
}
