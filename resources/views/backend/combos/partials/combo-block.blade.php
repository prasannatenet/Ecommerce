{{-- One "combo" block inside the multi-combo builder. --}}
{{-- $i = block index, or '__IDX__' inside the JS clone template. --}}
{{-- $values = submitted/old values array, or null for a fresh form. --}}
@php
    $isTemplate   = ($i === '__IDX__');
    $blockName    = $values['name'] ?? '';
    $blockDesc    = $values['description'] ?? '';
    $selectedIds  = array_map('intval', (array) ($values['product_ids'] ?? []));
    $discountType = $values['discount_type'] ?? 'percent';
    $discountVal  = $values['discount_value'] ?? '';
    $startsAt     = $values['starts_at'] ?? '';
    $expiresAt    = $values['expires_at'] ?? '';
    $isActive     = $values === null ? true : ! empty($values['is_active']);
@endphp

<div class="df-card combo-block" data-block-idx="{{ $i }}">
    <div class="df-card-header">
        <h5 class="df-card-title combo-block-title"><i class="bi bi-gift"></i> {{ $isTemplate ? 'Combo #__NUM__' : 'Combo #' . ((int) $i + 1) }}</h5>
        <button type="button" class="df-btn df-btn-light btn-sm remove-combo-block" title="Remove this combo">
            <i class="bi bi-trash"></i> Remove
        </button>
    </div>
    <div class="df-card-body">

        @if(! $isTemplate && $errors->any())
            @php $blockErrors = []; @endphp
            @foreach($errors->toArray() as $key => $msgs)
                @if(str_starts_with((string) $key, "combos.{$i}."))
                    @foreach((array) $msgs as $msg)
                        @php $blockErrors[] = $msg; @endphp
                    @endforeach
                @endif
            @endforeach
            @if(! empty($blockErrors))
                <div class="df-alert df-alert-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <ul class="mb-0 ps-3">
                        @foreach($blockErrors as $msg) <li>{{ $msg }}</li> @endforeach
                    </ul>
                </div>
            @endif
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <label class="df-form-label">Combo Name <span class="text-danger">*</span></label>
                <input type="text" name="combos[{{ $i }}][name]" class="df-form-control combo-name-input"
                       value="{{ $blockName }}" placeholder="e.g. Festive Necklace Set Combo" required>
            </div>
            <div class="col-md-6">
                <label class="df-form-label">Short Description</label>
                <input type="text" name="combos[{{ $i }}][description]" class="df-form-control"
                       value="{{ $blockDesc }}" placeholder="Shown with the combo offer (optional)">
            </div>
        </div>

        <div class="mt-3">
            <label class="df-form-label">
                Select Products <span class="text-danger">*</span>
                <span class="df-badge df-badge-info combo-selected-count ms-2">0 selected</span>
            </label>
            <p class="df-form-hint">Pick 2, 3 or more products to bundle together in this combo.</p>
            <input type="text" class="df-form-control combo-product-search mb-2" placeholder="Search products by name...">
            @error('combos.' . $i . '.product_ids')
                <div class="text-danger small mb-1">{{ $message }}</div>
            @enderror
            <div class="combo-product-list">
                @forelse($products as $product)
                    @php
                        $price = (float) ($product->picker_price ?? $product->display_price);
                        $thumb = $product->primary_image ?? optional($product->images->first())->path;
                    @endphp
                    <label class="combo-product-item {{ in_array($product->id, $selectedIds) ? 'selected' : '' }}"
                           data-name="{{ mb_strtolower($product->name) }}" data-price="{{ $price }}">
                        <input type="checkbox" name="combos[{{ $i }}][product_ids][]" value="{{ $product->id }}"
                               {{ in_array($product->id, $selectedIds) ? 'checked' : '' }}>
                        <span class="cpi-thumb">
                            @if($thumb)
                                <img src="{{ asset('storage/' . $thumb) }}" alt="{{ $product->name }}">
                            @else
                                <i class="bi bi-box-seam"></i>
                            @endif
                        </span>
                        <span class="cpi-info">
                            <span class="cpi-name">{{ $product->name }}</span>
                            <span class="cpi-price">₹{{ number_format($price, 2) }}</span>
                        </span>
                        <i class="bi bi-check2 cpi-check"></i>
                    </label>
                @empty
                    <p class="df-form-hint">No active products found. Add products first to build combos.</p>
                @endforelse
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <label class="df-form-label">Special Discount Type <span class="text-danger">*</span></label>
                <select name="combos[{{ $i }}][discount_type]" class="df-form-select combo-discount-type" required>
                    <option value="percent" {{ $discountType === 'percent' ? 'selected' : '' }}>Percent (%)</option>
                    <option value="fixed" {{ $discountType === 'fixed' ? 'selected' : '' }}>Fixed (₹)</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="df-form-label">Special Discount Value <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="number" step="0.01" min="0.01" name="combos[{{ $i }}][discount_value]"
                           class="df-form-control combo-discount-value" value="{{ $discountVal }}"
                           placeholder="e.g. 10" required>
                    <span class="input-group-text combo-discount-suffix">{{ $discountType === 'percent' ? '%' : '₹' }}</span>
                </div>
                @error('combos.' . $i . '.discount_value')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4 d-flex align-items-center">
                <div style="margin-top:22px;">
                    <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                        <input type="checkbox" name="combos[{{ $i }}][is_active]" value="1" {{ $isActive ? 'checked' : '' }}
                               style="width:18px; height:18px; accent-color:var(--df-primary);">
                        <span class="df-form-label mb-0">Active (show offer to customers)</span>
                    </label>
                </div>
            </div>
            <div class="col-md-6">
                <label class="df-form-label">Starts At</label>
                <input type="datetime-local" name="combos[{{ $i }}][starts_at]" class="df-form-control" value="{{ $startsAt }}">
            </div>
            <div class="col-md-6">
                <label class="df-form-label">Expires At</label>
                <input type="datetime-local" name="combos[{{ $i }}][expires_at]" class="df-form-control" value="{{ $expiresAt }}">
                @error('combos.' . $i . '.expires_at')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- Combo offer summary — visible below all the selected products --}}
        <div class="combo-summary mt-3">
            <div class="combo-summary-title"><i class="bi bi-tags-fill"></i> Combo Offer (applies below each selected product)</div>
            <div class="combo-summary-products cs-products">No products selected yet.</div>
            <div class="row g-2 mt-2">
                <div class="col-6 col-md-3">
                    <div class="cs-box">
                        <span class="cs-label">Original Total</span>
                        <span class="cs-total">₹0.00</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="cs-box">
                        <span class="cs-label">Special Discount</span>
                        <span class="cs-discount">₹0.00</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="cs-box cs-box-primary">
                        <span class="cs-label">Combo Price</span>
                        <span class="cs-price">₹0.00</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="cs-box">
                        <span class="cs-label">Customer Saves</span>
                        <span class="cs-save">₹0.00 (0%)</span>
                    </div>
                </div>
            </div>
            <div class="cs-save-note">Select at least 2 products to activate this combo offer.</div>
        </div>

    </div>
</div>
