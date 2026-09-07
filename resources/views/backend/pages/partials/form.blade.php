<div class="row g-3">
    <div class="col-md-8">
        <label class="df-form-label">Title <span class="text-danger">*</span></label>
        <input type="text" name="title" class="df-form-control" value="{{ old('title', optional($page)->title) }}"
               placeholder="e.g. About Us, Contact, Privacy Policy" required>
    </div>
    <div class="col-md-4">
        <label class="df-form-label">Slug</label>
        <input type="text" name="slug" class="df-form-control" value="{{ old('slug', optional($page)->slug) }}"
               placeholder="Auto-generated if empty">
        <p class="df-form-hint">Leave blank to auto-generate from title.</p>
    </div>
    <div class="col-12">
        <label class="df-form-label">Content</label>
        <textarea name="content" class="df-form-control" rows="10"
                  placeholder="Write your page content here...">{{ old('content', optional($page)->content) }}</textarea>
    </div>
    <div class="col-md-4">
        <label class="df-form-label">Meta Title</label>
        <input type="text" name="meta_title" class="df-form-control" value="{{ old('meta_title', optional($page)->meta_title) }}"
               placeholder="SEO page title">
    </div>
    <div class="col-md-4">
        <label class="df-form-label">Meta Keywords</label>
        <input type="text" name="meta_keywords" class="df-form-control" value="{{ old('meta_keywords', optional($page)->meta_keywords) }}"
               placeholder="comma, separated, keywords">
    </div>
    <div class="col-md-4">
        <label class="df-form-label">Meta Description</label>
        <textarea name="meta_description" class="df-form-control" rows="2"
                  placeholder="Short description for search engines">{{ old('meta_description', optional($page)->meta_description) }}</textarea>
    </div>
    <div class="col-md-3 d-flex align-items-center">
        <div style="margin-top:8px;">
            <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                <input type="checkbox" name="is_active" value="1"
                       {{ old('is_active', optional($page)->is_active ?? true) ? 'checked' : '' }}
                       style="width:18px; height:18px; accent-color:var(--df-primary);">
                <span class="df-form-label mb-0">Active</span>
            </label>
        </div>
    </div>
</div>
