<div class="row g-3">
    <div class="col-md-6">
        <label class="df-form-label">Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="df-form-control" value="{{ old('name', optional($partner)->name) }}"
               placeholder="e.g. Blue Dart, Delhivery" required>
    </div>
    <div class="col-md-6">
        <label class="df-form-label">Code <span class="text-danger">*</span></label>
        <input type="text" name="code" class="df-form-control" value="{{ old('code', optional($partner)->code) }}"
               placeholder="e.g. BLUEDART, DLVRY" required>
        <p class="df-form-hint">Unique identifier for this partner.</p>
    </div>
    <div class="col-md-6">
        <label class="df-form-label">Contact Email</label>
        <input type="email" name="contact_email" class="df-form-control" value="{{ old('contact_email', optional($partner)->contact_email) }}"
               placeholder="partner@example.com">
    </div>
    <div class="col-md-6">
        <label class="df-form-label">Contact Phone</label>
        <input type="text" name="contact_phone" class="df-form-control" value="{{ old('contact_phone', optional($partner)->contact_phone) }}"
               placeholder="+91 98765 43210">
    </div>
                <div class="col-md-6">
            <label class="df-form-label">Courier API</label>
            <select name="driver" class="df-form-select" id="driverSelect">
                @foreach(($driverOptions ?? ['manual' => 'Manual (no API)']) as $key => $label)
                    <option value="{{ $key }}" {{ old('driver', optional($partner)->driver ?? 'manual') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <p class="df-form-hint">Choose "Manual" for local pickup or hand-off; select your courier for auto-booking.</p>
        </div>
        <div class="col-md-3 d-flex align-items-center">
            <div style="margin-top:8px;">
                <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                    <input type="checkbox" name="is_active" value="1"
                           {{ old('is_active', optional($partner)->is_active ?? true) ? 'checked' : '' }}
                           style="width:18px; height:18px; accent-color:var(--df-primary);">
                    <span class="df-form-label mb-0">Active</span>
                </label>
            </div>
        </div>
</div>

{{-- Integration section: only show for non-manual drivers --}}
@if(in_array(old('driver', optional($partner)->driver ?? 'manual'), ['delhivery']))
<hr class="my-4">
<div class="df-card">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-gear"></i> API Integration</h5>
    </div>
    <div class="df-card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="df-form-label">Courier API</label>
                <input type="text" name="driver" class="df-form-control" value="{{ old('driver', optional($partner)->driver ?? 'delhivery') }}" readonly>
            </div>
            <div class="col-md-6">
                <label class="df-form-label">Environment</label>
                <select name="is_sandbox" class="df-form-select">
                    <option value="0" {{ (old('is_sandbox', optional($partner)->is_sandbox ?? true) ? '1' : '0') === '0' ? 'selected' : '' }}>Production</option>
                    <option value="1" {{ old('is_sandbox', optional($partner)->is_sandbox ?? true) ? 'selected' : '' }}>Sandbox / Testing</option>
                </select>
                <p class="df-form-hint">Use Sandbox while testing, switch to Production when live.</p>
            </div>
            <div class="col-md-6">
                <label class="df-form-label">API Key <span class="text-danger">*</span></label>
                <input type="text" name="api_key" class="df-form-control" value="{{ old('api_key') }}"
                       placeholder="64-char hex key from courier">
                <p class="df-form-hint">Leave blank to keep existing key unchanged.</p>
            </div>
            <div class="col-md-6">
                <label class="df-form-label">Client ID / Login</label>
                <input type="text" name="client_id" class="df-form-control" value="{{ old('client_id', optional($partner)->client_id) }}">
            </div>
            <div class="col-12">
                <label class="df-form-label">Base URL (optional)</label>
                <input type="url" name="base_url" class="df-form-control" value="{{ old('base_url', optional($partner)->base_url) }}"
                       placeholder="Custom API endpoint (leave blank for default)">
            </div>
            <div class="col-md-6">
                <label class="df-form-label">Webhook Secret</label>
                <input type="text" name="webhook_secret" class="df-form-control" value="{{ old('webhook_secret') }}">
                <p class="df-form-hint">HMAC secret for verifying webhook signatures.</p>
            </div>
            <div class="col-md-6">
                <label class="df-form-label">Tracking URL Template</label>
                <input type="text" name="tracking_url_template" class="df-form-control"
                       value="{{ old('tracking_url_template', optional($partner)->tracking_url_template) }}"
                       placeholder="https://tracking.delhivery.com/tracking/{awb}">
                <p class="df-form-hint">Use {awb} or {tracking_number} as the placeholder.</p>
            </div>
            <div class="col-12">
                <label class="df-form-label">Webhook URL (configure in courier dashboard)</label>
                <input type="text" class="df-form-control" readonly
                       value="{{ url('/webhooks/delivery/' . (old('code', optional($partner)->code) ?? 'CODE')) }}">
            </div>
            <div class="col-12">
                <label class="df-form-label">Auto-update order status from tracking</label>
                <label class="d-flex align-items-center gap-2" style="cursor:pointer; margin-bottom:0;">
                    <input type="checkbox" name="auto_update_order_status" value="1"
                           {{ old('auto_update_order_status', optional($partner)->auto_update_order_status) ? 'checked' : '' }}
                           style="width:18px; height:18px; accent-color:var(--df-primary);">
                    <span class="mb-0">Yes — mark order delivered automatically when courier confirms delivery</span>
                </label>
                        </div>
        </div>
    </div>

@endif

    <div class="col-12">
        <label class="df-form-label">Auto-book trigger</label>
        <select name="auto_book_on" class="df-form-select">
            <option value="manual"   {{ old('auto_book_on', optional($partner)->auto_book_on) === 'manual'   ? 'selected' : '' }}>Manual — do not auto-book</option>
            <option value="processing" {{ old('auto_book_on', optional($partner)->auto_book_on) === 'processing' ? 'selected' : '' }}>When order becomes Processing</option>
            <option value="shipped"  {{ old('auto_book_on', optional($partner)->auto_book_on) === 'shipped'  ? 'selected' : '' }}>When order becomes Shipped</option>
            <option value="both"     {{ old('auto_book_on', optional($partner)->auto_book_on) === 'both'     ? 'selected' : '' }}>Both Processing and Shipped</option>
        </select>
        <p class="df-form-hint">When should the system automatically create and book a shipment for this partner?</p>
    </div>

    <div class="d-flex gap-2 mt-4">
        @if($partner && $partner->isIntegrated())
            <a href="{{ route('admin.delivery-partners.test-connection', $partner) }}" class="df-btn df-btn-outline">
                <i class="bi bi-wifi"></i> Test Connection
            </a>
        @endif
        <button type="submit" class="df-btn df-btn-primary">
            <i class="bi bi-check2-circle"></i> Save Partner
        </button>
        <a href="{{ route('admin.delivery-partners.index') }}" class="df-btn df-btn-light">
            <i class="bi bi-arrow-left"></i> Cancel
        </a>
    </div>

