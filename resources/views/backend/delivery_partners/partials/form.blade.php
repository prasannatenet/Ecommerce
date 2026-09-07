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
