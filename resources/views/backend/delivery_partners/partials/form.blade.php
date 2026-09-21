@php
    $partner = $partner ?? null;
    $driver = old('driver', optional($partner)->driver ?? 'manual');
    $integrated = in_array($driver, ['delhivery'], true);
    $statusLabels = \App\Services\Delivery\DeliveryStatus::labels();
    $autoBookEnabled = (bool) old('auto_book_enabled', optional($partner)->autoBookEnabled() ?? false);
    $autoBookTrigger = old('auto_book_on', optional($partner)->auto_book_on ?? 'processing');
    if ($autoBookTrigger === 'manual' && $autoBookEnabled) {
        $autoBookTrigger = 'processing';
    }
    $notifyEvents = old('notify_events', optional($partner)->notifyEventList() ?? \App\Models\DeliveryPartner::DEFAULT_NOTIFY_EVENTS);
    $notifyEvents = is_array($notifyEvents) ? $notifyEvents : [];
@endphp

{{-- ============================ DELIVERY PARTNER ============================ --}}
<div class="df-card mb-4">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-truck"></i> Delivery Partner</h5>
        @if($integrated)
            <span class="df-badge df-badge-primary">Delhivery One</span>
        @endif
    </div>
    <div class="df-card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="df-form-label">Delivery Partner Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="df-form-control"
                       value="{{ old('name', optional($partner)->name) }}"
                       placeholder="e.g. Delhivery" required>
            </div>
            <div class="col-md-6">
                <label class="df-form-label">Partner Code <span class="text-danger">*</span></label>
                <input type="text" name="code" class="df-form-control"
                       value="{{ old('code', optional($partner)->code) }}"
                       placeholder="e.g. DELHIVERY" required>
                <p class="df-form-hint">Unique code used in the webhook URL below. Keep it URL friendly.</p>
            </div>
            <div class="col-md-6">
                <label class="df-form-label">Delivery Partner API</label>
                <select name="driver" class="df-form-select" id="driverSelect">
                    @foreach(($driverOptions ?? ['manual' => 'Manual (no API)']) as $key => $label)
                        <option value="{{ $key }}" {{ $driver === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="df-form-hint">Choose "Manual" for local pickup or hand-off; select the courier for automatic booking.</p>
            </div>
            <div class="col-md-6">
                <label class="df-form-label">Contact Email</label>
                <input type="email" name="contact_email" class="df-form-control"
                       value="{{ old('contact_email', optional($partner)->contact_email) }}"
                       placeholder="partner@example.com">
            </div>
            <div class="col-md-6">
                <label class="df-form-label">Contact Phone</label>
                <input type="text" name="contact_phone" class="df-form-control"
                       value="{{ old('contact_phone', optional($partner)->contact_phone) }}"
                       placeholder="+91 98765 43210">
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <div class="d-flex flex-wrap gap-4">
                    <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1"
                               {{ old('is_active', optional($partner)->is_active ?? true) ? 'checked' : '' }}
                               style="width:18px; height:18px; accent-color:var(--df-primary);">
                        <span class="df-form-label mb-0">Active</span>
                    </label>
                    <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                        <input type="hidden" name="is_default" value="0">
                        <input type="checkbox" name="is_default" value="1"
                               {{ old('is_default', optional($partner)->is_default ?? false) ? 'checked' : '' }}
                               style="width:18px; height:18px; accent-color:var(--df-primary);">
                        <span class="df-form-label mb-0">Default partner</span>
                    </label>
                </div>
            </div>
            <div class="col-12">
                <p class="df-form-hint mb-0">
                    <i class="bi bi-info-circle"></i>
                    The default active partner is used for automatic bookings and scheduled tracking updates.
                </p>
            </div>
        </div>
    </div>
</div>
{{-- ============================ API & ENVIRONMENT ============================ --}}
<div class="df-card mb-4" id="apiSettingsCard" style="{{ $integrated ? '' : 'display:none;' }}">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-shield-lock"></i> API &amp; Environment</h5>
        @if($partner && $partner->hasStoredApiKey())
            <span class="df-badge df-badge-success">Token stored</span>
        @elseif($integrated)
            <span class="df-badge df-badge-warning">Token not set</span>
        @endif
    </div>
    <div class="df-card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="df-form-label">Environment</label>
                @php $sandbox = (bool) old('is_sandbox', optional($partner)->is_sandbox ?? true); @endphp
                <div class="d-flex gap-2">
                    <label class="d-flex align-items-center justify-content-center gap-2 flex-fill df-btn {{ $sandbox ? 'df-btn-outline' : 'df-btn-primary' }} mb-0" style="cursor:pointer;">
                        <input type="radio" name="is_sandbox" value="0" class="env-radio" {{ $sandbox ? '' : 'checked' }} style="display:none;">
                        <i class="bi bi-broadcast"></i> Production
                    </label>
                    <label class="d-flex align-items-center justify-content-center gap-2 flex-fill df-btn {{ $sandbox ? 'df-btn-primary' : 'df-btn-outline' }} mb-0" style="cursor:pointer;">
                        <input type="radio" name="is_sandbox" value="1" class="env-radio" {{ $sandbox ? 'checked' : '' }} style="display:none;">
                        <i class="bi bi-beaker"></i> Sandbox
                    </label>
                </div>
                <p class="df-form-hint">Production access must be enabled by Delhivery before live bookings.</p>
            </div>
            <div class="col-md-6">
                <label class="df-form-label">API Base URL</label>
                <input type="url" name="base_url" class="df-form-control"
                       value="{{ old('base_url', optional($partner)->base_url) }}"
                       placeholder="{{ config('delhivery.old_api.sandbox_base_url') }}">
                <p class="df-form-hint">
                    Leave blank for the default.
                    Sandbox: <code style="font-size:0.75rem;">{{ config('delhivery.old_api.sandbox_base_url') }}</code>
                    · Production: <code style="font-size:0.75rem;">{{ config('delhivery.old_api.production_base_url') }}</code>
                </p>
            </div>
            <div class="col-md-6">
                <label class="df-form-label">API Token</label>
                <input type="text" name="api_key" class="df-form-control" value="{{ old('api_key') }}"
                       autocomplete="new-password"
                       placeholder="{{ $partner && $partner->hasStoredApiKey() ? 'Leave blank to keep the stored token' : 'Paste the token from Delhivery' }}">
                @if($partner && $partner->hasStoredApiKey())
                    <p class="df-form-hint mb-0">
                        <span class="df-badge df-badge-success"><i class="bi bi-check2"></i> Stored</span>
                        <code style="font-size:0.8rem;">{{ $partner->maskedApiKey() }}</code>
                    </p>
                    <p class="df-form-hint">Leave blank to keep the current token. The full token is never shown again.</p>
                @else
                    <p class="df-form-hint">Stored encrypted; the full token is never displayed after saving.</p>
                @endif
            </div>
<div class="col-md-6">
                <label class="df-form-label">Client ID / Login</label>
                <input type="text" name="client_id" class="df-form-control"
                       value="{{ old('client_id', optional($partner)->client_id) }}"
                       placeholder="e.g. ucp-service-cli">
                <p class="df-form-hint">Used for the Delhivery One (B2C) OAuth2 client-credentials flow.</p>
            </div>
            <div class="col-md-6">
                <label class="df-form-label">Client Secret</label>
                <input type="text" name="client_secret" class="df-form-control" value="{{ old('client_secret') }}"
                       autocomplete="new-password"
                       placeholder="{{ $partner && $partner->hasStoredClientSecret() ? 'Leave blank to keep the stored secret' : 'OAuth2 client secret' }}">
                @if($partner && $partner->hasStoredClientSecret())
                    <p class="df-form-hint mb-0">
                        <span class="df-badge df-badge-success"><i class="bi bi-check2"></i> Stored</span>
                        <code style="font-size:0.8rem;">{{ $partner->maskedClientSecret() }}</code>
                    </p>
                @else
                    <p class="df-form-hint">Stored encrypted. Falls back to DELHIVERY_B2C_CLIENT_SECRET when blank.</p>
                @endif
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                    <input type="hidden" name="use_b2c_one" value="0">
                    <input type="checkbox" name="use_b2c_one" value="1"
                           {{ old('use_b2c_one', optional($partner)->use_b2c_one ?? false) ? 'checked' : '' }}
                           style="width:18px; height:18px; accent-color:var(--df-primary);">
                    <span class="df-form-label mb-0">Use Delhivery One (B2C / OAuth2)</span>
                </label>
            </div>
            <div class="col-12">
                <p class="df-form-hint mb-0">
                    <i class="bi bi-exclamation-triangle"></i>
                    Order creation still requires the Express API token; Delhivery One credentials power tracking
                    and serviceability for this account.
                </p>
            </div>
        </div>
    </div>
</div>
{{-- ========================= AUTOMATIC SHIPPING RULES ========================= --}}
<div class="df-card mb-4">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-magic"></i> Automatic shipping rules</h5>
        <span class="df-badge df-badge-muted">Runs without manual effort</span>
    </div>
    <div class="df-card-body">
        <div class="row g-4">
            <div class="col-12">
                <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                    <input type="hidden" name="auto_book_enabled" value="0">
                    <input type="checkbox" name="auto_book_enabled" value="1" id="autoBookToggle"
                           {{ $autoBookEnabled ? 'checked' : '' }}
                           style="width:20px; height:20px; accent-color:var(--df-primary);">
                    <span class="df-form-label mb-0">Auto-book eligible orders</span>
                    @if($autoBookEnabled)
                        <span class="df-badge df-badge-success">Enabled</span>
                    @else
                        <span class="df-badge df-badge-muted">Disabled</span>
                    @endif
                </label>
                <p class="df-form-hint mb-2">
                    Laravel creates the shipment and requests the AWB from the courier automatically - no manual
                    AWB entry required.
                </p>
                <div class="row g-3" id="autoBookTriggerRow" style="{{ $autoBookEnabled ? '' : 'display:none;' }}">
                    <div class="col-md-6">
                        <label class="df-form-label">Book orders when they become</label>
                        <select name="auto_book_on" class="df-form-select">
                            <option value="processing" {{ $autoBookTrigger === 'processing' ? 'selected' : '' }}>Processing (after payment confirmation / admin approval)</option>
                            <option value="shipped" {{ $autoBookTrigger === 'shipped' ? 'selected' : '' }}>Shipped</option>
                            <option value="both" {{ $autoBookTrigger === 'both' ? 'selected' : '' }}>Both Processing and Shipped</option>
                            <option value="manual" {{ $autoBookTrigger === 'manual' ? 'selected' : '' }}>Only when booked manually</option>
                        </select>
                        <p class="df-form-hint">Configurable trigger - nothing is hardcoded.</p>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                    <input type="hidden" name="auto_sync_tracking" value="0">
                    <input type="checkbox" name="auto_sync_tracking" value="1"
                           {{ old('auto_sync_tracking', optional($partner)->auto_sync_tracking ?? true) ? 'checked' : '' }}
                           style="width:20px; height:20px; accent-color:var(--df-primary);">
                    <span class="df-form-label mb-0">Automatic tracking updates</span>
                </label>
                <p class="df-form-hint mb-0">
                    A scheduled job polls the courier for shipment events (and reconciles any missed webhook
                    callback) so the admin and customer views always show the latest status.
                </p>
            </div>
            <div class="col-12">
                <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                    <input type="hidden" name="auto_notify_customer" value="0">
                    <input type="checkbox" name="auto_notify_customer" value="1"
                           {{ old('auto_notify_customer', optional($partner)->auto_notify_customer ?? true) ? 'checked' : '' }}
                           style="width:20px; height:20px; accent-color:var(--df-primary);">
                    <span class="df-form-label mb-0">Automatic customer notifications</span>
                </label>
                <p class="df-form-hint">Email the customer when the shipment status changes:</p>
                <div class="d-flex flex-wrap gap-3">
                    @foreach($statusLabels as $statusKey => $statusLabel)
                        <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                            <input type="checkbox" name="notify_events[]" value="{{ $statusKey }}"
                                   {{ in_array($statusKey, $notifyEvents, true) ? 'checked' : '' }}
                                   style="width:16px; height:16px; accent-color:var(--df-primary);">
                            <span style="font-size:0.85rem;">{{ $statusLabel }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@if($integrated)
{{-- ========================= CONNECTION & WEBHOOK ========================= --}}
<div class="df-card mb-4">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-plug"></i> Connection &amp; Webhook</h5>
        @if($partner && $partner->last_connection_test_ok === true)
            <span class="df-badge df-badge-success">Last test passed</span>
        @elseif($partner && $partner->last_connection_test_ok === false)
            <span class="df-badge df-badge-danger">Last test failed</span>
        @else
            <span class="df-badge df-badge-muted">Not tested yet</span>
        @endif
    </div>
    <div class="df-card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="df-form-label">Webhook URL (configure in the Delhivery dashboard)</label>
                <input type="text" class="df-form-control" readonly
                       value="{{ url('/webhooks/delivery/' . (old('code', optional($partner)->code) ?? 'CODE')) }}">
                <p class="df-form-hint">Delhivery pushes shipment status events here; the app updates the shipment instantly.</p>
            </div>
            <div class="col-md-6">
                <label class="df-form-label">Webhook Secret</label>
                <input type="text" name="webhook_secret" class="df-form-control" value="{{ old('webhook_secret') }}"
                       autocomplete="new-password"
                       placeholder="{{ $partner && $partner->webhook_secret ? 'Leave blank to keep the stored secret' : 'HMAC secret (optional)' }}">
                <p class="df-form-hint">Verifies the X-Delhivery-Signature header on incoming events. Leave blank to keep it.</p>
            </div>
            <div class="col-md-6">
                <label class="df-form-label">Tracking URL Template</label>
                <input type="text" name="tracking_url_template" class="df-form-control"
                       value="{{ old('tracking_url_template', optional($partner)->tracking_url_template) }}"
                       placeholder="https://www.delhivery.com/track/package/{awb}">
                <p class="df-form-hint">Use {awb} or {tracking_number} as the placeholder.</p>
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                    <input type="hidden" name="auto_update_order_status" value="0">
                    <input type="checkbox" name="auto_update_order_status" value="1"
                           {{ old('auto_update_order_status', optional($partner)->auto_update_order_status ?? true) ? 'checked' : '' }}
                           style="width:18px; height:18px; accent-color:var(--df-primary);">
                    <span class="df-form-label mb-0">Auto-update order status from tracking</span>
                </label>
            </div>
            @if($partner && $partner->exists)
                <div class="col-12"><hr class="my-2"></div>
                <div class="col-md-6">
                    <label class="df-form-label">Serviceability check</label>
                    <div class="d-flex gap-2">
                        <input type="text" name="pincode" class="df-form-control" value="{{ old('pincode') }}"
                               placeholder="Destination pincode e.g. 600001">
                        <button type="submit" class="df-btn df-btn-outline text-nowrap"
                                formaction="{{ route('admin.delivery-partners.test-serviceability', $partner) }}"
                                formmethod="post">
                            <i class="bi bi-geo-alt"></i> Check
                        </button>
                    </div>
                    <p class="df-form-hint">Verifies the courier can deliver to a pincode and surfaces warehouse/configuration errors.</p>
                </div>
                <div class="col-md-6">
                    <label class="df-form-label">Courier connection</label>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('admin.delivery-partners.test-connection', $partner) }}" class="df-btn df-btn-outline">
                            <i class="bi bi-wifi"></i> Test Connection
                        </a>
                        <button type="submit" class="df-btn df-btn-light"
                                formaction="{{ route('admin.delivery-partners.sync-now', $partner) }}"
                                formmethod="post">
                            <i class="bi bi-arrow-repeat"></i> Sync now
                        </button>
                    </div>
                    <p class="df-form-hint mb-0">
                        @if($partner->last_connection_test_at)
                            Last test: {{ $partner->last_connection_test_at->format('M d, Y h:i A') }}
                            — {{ $partner->last_connection_test_ok ? 'reachable' : 'failed' }}
                        @else
                            Connection has not been tested yet.
                        @endif
                    </p>
                </div>
                @if($partner->last_error)
                    <div class="col-12">
                        <div class="df-alert df-alert-danger mb-0">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div>
                                <strong>Last courier error</strong>
                                <div style="font-size:0.85rem;">{{ $partner->last_error }}</div>
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
@endif
{{-- ============================== ADVANCED ============================== --}}
<details class="df-card mb-4" style="padding:0;">
    <summary style="cursor:pointer; padding:16px 20px; font-weight:700;">
        <i class="bi bi-sliders"></i> Advanced — warehouse / pickup &amp; package defaults
    </summary>
    <div class="df-card-body" style="border-top:1px solid var(--df-border,#e9ecef);">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="df-form-label">Pickup / Warehouse Name</label>
                <input type="text" name="config_pickup_name" class="df-form-control"
                       value="{{ old('config_pickup_name', optional($partner)->configValue('pickup_name')) }}"
                       placeholder="Registered warehouse name in Delhivery">
            </div>
            <div class="col-md-6">
                <label class="df-form-label">Pickup Pincode</label>
                <input type="text" name="config_pickup_pin" class="df-form-control"
                       value="{{ old('config_pickup_pin', optional($partner)->configValue('pickup_pin')) }}"
                       placeholder="600001">
            </div>
            <div class="col-md-6">
                <label class="df-form-label">Pickup Phone</label>
                <input type="text" name="config_pickup_phone" class="df-form-control"
                       value="{{ old('config_pickup_phone', optional($partner)->configValue('pickup_phone')) }}">
            </div>
            <div class="col-md-6">
                <label class="df-form-label">Pickup City</label>
                <input type="text" name="config_pickup_city" class="df-form-control"
                       value="{{ old('config_pickup_city', optional($partner)->configValue('pickup_city')) }}">
            </div>
            <div class="col-md-6">
                <label class="df-form-label">Pickup State</label>
                <input type="text" name="config_pickup_state" class="df-form-control"
                       value="{{ old('config_pickup_state', optional($partner)->configValue('pickup_state')) }}">
            </div>
            <div class="col-md-6">
                <label class="df-form-label">Pickup Address</label>
                <input type="text" name="config_pickup_address_line1" class="df-form-control"
                       value="{{ old('config_pickup_address_line1', optional($partner)->configValue('pickup_address_line1')) }}">
            </div>
            <div class="col-md-3">
                <label class="df-form-label">Default Weight (g)</label>
                <input type="number" name="config_default_weight" class="df-form-control" min="1"
                       value="{{ old('config_default_weight', optional($partner)->configValue('default_weight', 500)) }}">
            </div>
            <div class="col-md-3">
                <label class="df-form-label">Length (cm)</label>
                <input type="number" name="config_default_length" class="df-form-control" min="1"
                       value="{{ old('config_default_length', optional($partner)->configValue('default_length', 10)) }}">
            </div>
            <div class="col-md-3">
                <label class="df-form-label">Breadth (cm)</label>
                <input type="number" name="config_default_breadth" class="df-form-control" min="1"
                       value="{{ old('config_default_breadth', optional($partner)->configValue('default_breadth', 10)) }}">
            </div>
            <div class="col-md-3">
                <label class="df-form-label">Height (cm)</label>
                <input type="number" name="config_default_height" class="df-form-control" min="1"
                       value="{{ old('config_default_height', optional($partner)->configValue('default_height', 10)) }}">
            </div>
            <div class="col-12">
                <p class="df-form-hint mb-0">
                    <i class="bi bi-info-circle"></i>
                    Warehouse details must also exist and be active in your Delhivery account — booking can fail with
                    error 1005 if the pickup warehouse is not registered.
                </p>
            </div>
        </div>
    </div>
</details>
{{-- ============================ CUSTOM API SETTINGS ============================ --}}
<div class="df-card mb-4" id="customApiSettingsCard" style="display:none;">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-code-slash"></i> Custom API Configuration</h5>
    </div>
    <div class="df-card-body">
        <div class="row g-3">
            <div class="col-12"><h6 class="mb-0">Booking API</h6></div>
            <div class="col-md-3">
                <label class="df-form-label">Method</label>
                <select name="config_booking_method" class="df-form-select">
                    <option value="POST" {{ old('config_booking_method', optional($partner)->configValue('booking_method')) === 'POST' ? 'selected' : '' }}>POST</option>
                    <option value="GET" {{ old('config_booking_method', optional($partner)->configValue('booking_method')) === 'GET' ? 'selected' : '' }}>GET</option>
                </select>
            </div>
            <div class="col-md-9">
                <label class="df-form-label">Booking Endpoint</label>
                <input type="text" name="config_booking_endpoint" class="df-form-control"
                       value="{{ old('config_booking_endpoint', optional($partner)->configValue('booking_endpoint')) }}"
                       placeholder="/api/v1/shipments/create">
            </div>
            <div class="col-md-12">
                <label class="df-form-label">Headers (one per line: Key: Value)</label>
                <textarea name="config_booking_headers" class="df-form-control" rows="2"
                          placeholder="X-Custom-Auth: abc123&#10;Accept-Language: en">{{ old('config_booking_headers', optional($partner)->configValue('booking_headers')) }}</textarea>
            </div>
            <div class="col-md-12">
                <label class="df-form-label">JSON Payload Template</label>
                <textarea name="config_booking_payload_template" class="df-form-control" rows="5"
                          placeholder='{"order_id": "@{{order_id}}", "name": "@{{customer_name}}"}'>{{ old('config_booking_payload_template', optional($partner)->configValue('booking_payload_template')) }}</textarea>
                <p class="df-form-hint">Available vars: @{{order_id}}, @{{customer_name}}, @{{address}}, @{{city}}, @{{state}}, @{{country}}, @{{phone}}, @{{pincode}}, @{{payment_mode}}, @{{cod_amount}}, @{{total_amount}}, @{{weight}}</p>
            </div>
            <div class="col-md-12">
                <label class="df-form-label">AWB JSON Path in Response</label>
                <input type="text" name="config_booking_awb_path" class="df-form-control"
                       value="{{ old('config_booking_awb_path', optional($partner)->configValue('booking_awb_path')) }}"
                       placeholder="data.awb_number">
            </div>

            <div class="col-12 mt-4"><h6 class="mb-0">Tracking API</h6></div>
            <div class="col-md-3">
                <label class="df-form-label">Method</label>
                <select name="config_tracking_method" class="df-form-select">
                    <option value="GET" {{ old('config_tracking_method', optional($partner)->configValue('tracking_method')) === 'GET' ? 'selected' : '' }}>GET</option>
                    <option value="POST" {{ old('config_tracking_method', optional($partner)->configValue('tracking_method')) === 'POST' ? 'selected' : '' }}>POST</option>
                </select>
            </div>
            <div class="col-md-9">
                <label class="df-form-label">Tracking Endpoint</label>
                <input type="text" name="config_tracking_endpoint" class="df-form-control"
                       value="{{ old('config_tracking_endpoint', optional($partner)->configValue('tracking_endpoint')) }}"
                       placeholder="/api/v1/track/@{{waybill}}">
                <p class="df-form-hint">Use @{{waybill}} as placeholder for the tracking number.</p>
            </div>
            <div class="col-md-12">
                <label class="df-form-label">Headers (one per line)</label>
                <textarea name="config_tracking_headers" class="df-form-control" rows="2">{{ old('config_tracking_headers', optional($partner)->configValue('tracking_headers')) }}</textarea>
            </div>
            <div class="col-md-4">
                <label class="df-form-label">Events Array Path</label>
                <input type="text" name="config_tracking_events_path" class="df-form-control"
                       value="{{ old('config_tracking_events_path', optional($partner)->configValue('tracking_events_path')) }}"
                       placeholder="data.tracking_history">
            </div>
            <div class="col-md-4">
                <label class="df-form-label">Status Field Path</label>
                <input type="text" name="config_tracking_status_path" class="df-form-control"
                       value="{{ old('config_tracking_status_path', optional($partner)->configValue('tracking_status_path')) }}"
                       placeholder="status">
            </div>
            <div class="col-md-4">
                <label class="df-form-label">Timestamp Field Path</label>
                <input type="text" name="config_tracking_timestamp_path" class="df-form-control"
                       value="{{ old('config_tracking_timestamp_path', optional($partner)->configValue('tracking_timestamp_path')) }}"
                       placeholder="created_at">
            </div>
        </div>
    </div>
</div>
<details class="df-card mb-4" style="padding:0;">
    <summary style="cursor:pointer; padding:16px 20px; font-weight:700;">
        <i class="bi bi-cloud"></i> Advanced — Delhivery One (B2C) endpoints &amp; status mapping
    </summary>
    <div class="df-card-body" style="border-top:1px solid var(--df-border,#e9ecef);">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="df-form-label">B2C Realm</label>
                <input type="text" name="config_b2c_realm" class="df-form-control"
                       value="{{ old('config_b2c_realm', optional($partner)->configValue('b2c_realm')) }}"
                       placeholder="{{ config('delhivery.b2c_one.auth.realm') }}">
            </div>
            <div class="col-md-6">
                <label class="df-form-label">B2C CMS Client ID</label>
                <input type="text" name="config_b2c_cms" class="df-form-control"
                       value="{{ old('config_b2c_cms', optional($partner)->configValue('b2c_cms')) }}"
                       placeholder="{{ config('delhivery.b2c_one.cms') }}">
            </div>
            <div class="col-md-6">
                <label class="df-form-label">B2C User Email</label>
                <input type="text" name="config_b2c_user_email" class="df-form-control"
                       value="{{ old('config_b2c_user_email', optional($partner)->configValue('b2c_user_email')) }}"
                       placeholder="{{ config('delhivery.b2c_one.user_email') }}">
            </div>
            <div class="col-md-6">
                <label class="df-form-label">B2C Auth URL</label>
                <input type="text" name="config_b2c_auth_url" class="df-form-control"
                       value="{{ old('config_b2c_auth_url', optional($partner)->configValue('b2c_auth_url')) }}"
                       placeholder="{{ config('delhivery.b2c_one.auth.auth_url') }}">
            </div>
            <div class="col-md-6">
                <label class="df-form-label">B2C Token URL (optional)</label>
                <input type="text" name="config_b2c_token_url" class="df-form-control"
                       value="{{ old('config_b2c_token_url', optional($partner)->configValue('b2c_token_url')) }}">
            </div>
            <div class="col-md-6">
                <label class="df-form-label">B2C MCP URL</label>
                <input type="text" name="config_b2c_mcp_url" class="df-form-control"
                       value="{{ old('config_b2c_mcp_url', optional($partner)->configValue('b2c_mcp_url')) }}"
                       placeholder="{{ config('delhivery.b2c_one.mcp_url') }}">
            </div>
            <div class="col-12">
                <p class="df-form-hint mb-0">Blank fields fall back to the DELHIVERY_B2C_* values in .env.</p>
            </div>
        </div>
        @php
            $statusMapRows = old('status_map');
            if (! is_array($statusMapRows)) {
                $statusMapRows = [];
                foreach ((array) optional($partner)->status_map as $providerCode => $internalStatus) {
                    $statusMapRows[] = ['provider' => $providerCode, 'internal' => $internalStatus];
                }
            }
            $statusMapRows[] = ['provider' => '', 'internal' => ''];
        @endphp
        <hr class="my-4">
        <label class="df-form-label">Courier status mapping (optional override)</label>
        <p class="df-form-hint">Map courier scan codes to internal statuses. Leave blank to use the Delhivery defaults.</p>
        <div class="table-responsive">
            <table class="df-table">
                <thead>
                    <tr>
                        <th>Courier status code</th>
                        <th>Internal status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($statusMapRows as $index => $row)
                        <tr>
                            <td>
                                <input type="text" name="status_map[{{ $index }}][provider]" class="df-form-control"
                                       value="{{ $row['provider'] ?? '' }}" placeholder="e.g. IN TRANSIT">
                            </td>
                            <td>
                                <select name="status_map[{{ $index }}][internal]" class="df-form-select">
                                    <option value="">—</option>
                                    @foreach($statusLabels as $statusKey => $statusLabel)
                                        <option value="{{ $statusKey }}" {{ ($row['internal'] ?? '') === $statusKey ? 'selected' : '' }}>{{ $statusLabel }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</details>

<div class="d-flex flex-wrap gap-2 mt-4">
    <button type="submit" class="df-btn df-btn-primary">
        <i class="bi bi-check2-circle"></i> Save Delivery Partner
    </button>
    <a href="{{ route('admin.delivery-partners.index') }}" class="df-btn df-btn-light">
        <i class="bi bi-arrow-left"></i> Cancel
    </a>
</div>
@push('scripts')
<script>
    (function () {
        var driverSelect = document.getElementById('driverSelect');
        var apiCard = document.getElementById('apiSettingsCard');
        var customApiCard = document.getElementById('customApiSettingsCard');
        var autoBookToggle = document.getElementById('autoBookToggle');
        var triggerRow = document.getElementById('autoBookTriggerRow');

        function syncDriver() {
            if (!driverSelect) { return; }
            if (apiCard) { apiCard.style.display = driverSelect.value === 'manual' ? 'none' : ''; }
            if (customApiCard) { customApiCard.style.display = driverSelect.value === 'custom_api' ? '' : 'none'; }
        }

        function syncAutoBook() {
            if (!autoBookToggle || !triggerRow) { return; }
            triggerRow.style.display = autoBookToggle.checked ? '' : 'none';
        }

        if (driverSelect) { driverSelect.addEventListener('change', syncDriver); }
        if (autoBookToggle) { autoBookToggle.addEventListener('change', syncAutoBook); }

        syncDriver();
    })();
</script>
@endpush