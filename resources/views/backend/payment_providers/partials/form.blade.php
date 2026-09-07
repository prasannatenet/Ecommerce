<div class="row g-3">
    <div class="col-md-4">
        <label class="df-form-label">Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="df-form-control" value="{{ old('name', optional($provider)->name) }}"
               placeholder="e.g. Stripe, Razorpay" required>
    </div>

    <div class="col-md-4">
        <label class="df-form-label">Slug <span class="text-danger">*</span></label>
        <input type="text" name="slug" class="df-form-control" value="{{ old('slug', optional($provider)->slug) }}"
               placeholder="e.g. stripe, razorpay" required>
        <p class="df-form-hint">Used internally to select provider.</p>
    </div>

    <div class="col-md-4 d-flex align-items-center">
        <div style="margin-top:28px;">
            <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                <input type="checkbox" name="is_active" value="1"
                       {{ old('is_active', optional($provider)->is_active ?? true) ? 'checked' : '' }}
                       style="width:18px; height:18px; accent-color:var(--df-primary);">
                <span class="df-form-label mb-0">Active</span>
            </label>
        </div>
    </div>

    <div class="col-md-6">
        <label class="df-form-label">Public Key</label>
        <input type="text" name="public_key" class="df-form-control" value="{{ old('public_key', optional($provider)->public_key) }}"
               placeholder="pk_live_...">
    </div>

    <div class="col-md-6">
        <label class="df-form-label">Secret Key</label>
        <input type="text" name="secret_key" class="df-form-control" value="{{ old('secret_key', optional($provider)->secret_key) }}"
               placeholder="sk_live_...">
    </div>

    <div class="col-md-6">
        <label class="df-form-label">Webhook Secret</label>
        <input type="text" name="webhook_secret" class="df-form-control" value="{{ old('webhook_secret', optional($provider)->webhook_secret) }}"
               placeholder="whsec_... (Optional)">
    </div>
</div>
