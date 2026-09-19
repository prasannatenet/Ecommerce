<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\DeliveryPartner;
use App\Services\Delivery\DeliveryManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;

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
        return view('backend.delivery_partners.create', ['driverOptions' => $this->driverOptions()]);
    }

    /**
     * Store a newly created resource in storage.
     */
        public function store(Request $request)
    {
        $data = $this->validateData($request);
        DeliveryPartner::create($this->normalize($request, $data));

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
        return view('backend.delivery_partners.edit', [
            'partner' => $deliveryPartner,
            'driverOptions' => $this->driverOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DeliveryPartner $deliveryPartner)
    {
        $data = $this->validateData($request, $deliveryPartner->id);
        $deliveryPartner->update($this->normalize($request, $data, $deliveryPartner->id));

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

    public function testConnection(DeliveryPartner $deliveryPartner, DeliveryManager $manager)
    {
        $result = $manager->testConnection($deliveryPartner);

        return redirect()->route('admin.delivery-partners.edit', $deliveryPartner)
            ->with($result['ok'] ? 'success' : 'delivery_error', $result['message']);
    }

    /**
     * Run the tracking sync for a single partner (the "Sync now" button).
     */
    public function syncNow(DeliveryPartner $deliveryPartner)
    {
        $exitCode = Artisan::call('delivery:sync-tracking', [
            '--partner' => $deliveryPartner->id,
        ]);

        $output = trim(Artisan::output());

        return redirect()->route('admin.delivery-partners.edit', $deliveryPartner)
            ->with(
                $exitCode === 0 ? 'success' : 'delivery_error',
                $output !== '' ? $output : 'Tracking sync finished.'
            );
    }

    /**
     * Probe a destination pincode so the operator can verify serviceability
     * (and surface courier errors such as a mis-configured warehouse).
     */
    public function testServiceability(Request $request, DeliveryPartner $deliveryPartner, DeliveryManager $manager)
    {
        $data = $request->validate([
            'pincode' => 'required|string|max:10',
        ]);

        $result = $manager->checkServiceability($deliveryPartner, trim((string) $data['pincode']));

        return redirect()->route('admin.delivery-partners.edit', $deliveryPartner)
            ->with(
                ($result['ok'] && $result['serviceable']) ? 'success' : 'delivery_error',
                $result['message']
            );
    }

        private function driverOptions(): array
    {
        return ['manual' => 'Manual (no API)', 'delhivery' => 'Delhivery'];
    }

    private function normalize(Request $request, array $data, ?int $id = null): array
    {
        $data['is_active'] = $request->boolean('is_active');
        $data['is_sandbox'] = $request->boolean('is_sandbox');
        $data['is_default'] = $request->boolean('is_default');
        $data['auto_update_order_status'] = $request->boolean('auto_update_order_status');
        $data['use_b2c_one'] = $request->boolean('use_b2c_one');

        // Automatic shipping rules.
        $data['auto_sync_tracking'] = $request->boolean('auto_sync_tracking');
        $data['auto_notify_customer'] = $request->boolean('auto_notify_customer');

        if (! $request->boolean('auto_book_enabled')) {
            // Toggle off = never auto-book. Existing trigger choices are preserved
            // by the form whenever the rule is switched back on.
            $data['auto_book_on'] = 'manual';
        } elseif (($data['auto_book_on'] ?? 'manual') === 'manual') {
            // Toggle on but no trigger chosen yet: default to auto-booking when
            // the order becomes Processing.
            $data['auto_book_on'] = 'processing';
        }

        $notifyEvents = array_values(array_filter(array_map(
            fn ($value) => trim((string) $value),
            (array) $request->input('notify_events', [])
        )));
        $data['notify_events'] = $notifyEvents ?: null;

        if (empty($data['api_key'])) {
            unset($data['api_key']);
        }

        if (empty($data['client_secret'])) {
            unset($data['client_secret']);
        }

        if (empty($data['webhook_secret'])) {
            unset($data['webhook_secret']);
        }

        $data['config'] = array_filter([
            'pickup_name' => $request->input('config_pickup_name'),
            'pickup_pin' => $request->input('config_pickup_pin'),
            'pickup_phone' => $request->input('config_pickup_phone'),
            'pickup_address_line1' => $request->input('config_pickup_address_line1'),
            'pickup_city' => $request->input('config_pickup_city'),
            'pickup_state' => $request->input('config_pickup_state'),
            'default_weight' => $request->input('config_default_weight'),
            'default_length' => $request->input('config_default_length'),
            'default_breadth' => $request->input('config_default_breadth'),
            'default_height' => $request->input('config_default_height'),
            'b2c_realm' => $request->input('config_b2c_realm'),
            'b2c_cms' => $request->input('config_b2c_cms'),
            'b2c_user_email' => $request->input('config_b2c_user_email'),
            'b2c_auth_url' => $request->input('config_b2c_auth_url'),
            'b2c_token_url' => $request->input('config_b2c_token_url'),
            'b2c_mcp_url' => $request->input('config_b2c_mcp_url'),
        ], fn ($v) => $v !== null && $v !== '');

        $map = [];
        foreach ((array) $request->input('status_map', []) as $row) {
            $provider = strtoupper(trim((string) ($row['provider'] ?? '')));
            $internal = trim((string) ($row['internal'] ?? ''));
            if ($provider !== '' && $internal !== '') {
                $map[$provider] = $internal;
            }
        }
        $data['status_map'] = $map ?: null;

        if (! empty($data['is_default'])) {
            DeliveryPartner::where('id', '!=', (int) ($id ?? 0))
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        return $data;
    }

    private function validateData(Request $request, $id = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:100',
            'code' => ['required', 'string', 'max:50', Rule::unique('delivery_partners', 'code')->ignore($id)],
            'driver' => 'required|string|in:manual,delhivery',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'api_key' => 'nullable|string|max:2000',
            'client_id' => 'nullable|string|max:255',
            'client_secret' => 'nullable|string|max:2000',
            'webhook_secret' => 'nullable|string|max:2000',
            'base_url' => 'nullable|url|max:500',
            'is_sandbox' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'auto_book_on' => 'nullable|string|in:manual,processing,shipped,both',
            'auto_book_enabled' => 'nullable|boolean',
            'auto_update_order_status' => 'nullable|boolean',
            'auto_sync_tracking' => 'nullable|boolean',
            'auto_notify_customer' => 'nullable|boolean',
            'notify_events' => 'nullable|array',
            'notify_events.*' => 'nullable|string|in:booked,shipped,in_transit,out_for_delivery,delivered,undelivered,rto,cancelled',
            'use_b2c_one' => 'nullable|boolean',
            'tracking_url_template' => 'nullable|string|max:500',
            'config_pickup_name' => 'nullable|string|max:255',
            'config_pickup_pin' => 'nullable|string|max:20',
            'config_pickup_phone' => 'nullable|string|max:50',
            'config_pickup_address_line1' => 'nullable|string|max:500',
            'config_pickup_city' => 'nullable|string|max:100',
            'config_pickup_state' => 'nullable|string|max:100',
            'config_default_weight' => 'nullable|integer|min:1|max:50000',
            'config_default_length' => 'nullable|integer|min:1|max:200',
            'config_default_breadth' => 'nullable|integer|min:1|max:200',
            'config_default_height' => 'nullable|integer|min:1|max:200',
            'config_b2c_realm' => 'nullable|string|max:255',
            'config_b2c_cms' => 'nullable|string|max:255',
            'config_b2c_user_email' => 'nullable|string|max:255',
            'config_b2c_auth_url' => 'nullable|url|max:500',
            'config_b2c_token_url' => 'nullable|url|max:500',
            'config_b2c_mcp_url' => 'nullable|url|max:500',
            'status_map' => 'nullable|array',
            'status_map.*.provider' => 'nullable|string|max:100',
            'status_map.*.internal' => 'nullable|string|max:50',
        ]);
    }
}
