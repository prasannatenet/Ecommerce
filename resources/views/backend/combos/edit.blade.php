@extends('layouts.app')
@section('content')

@php
    $selectedIds = old('product_ids', $combo->products->pluck('id')->map(fn ($id) => (int) $id)->all());
    $selDiscountType = old('discount_type', $combo->discount_type);
@endphp

<div class="df-page-header">
    <h1 class="df-page-title">Edit Combo</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <a href="{{ route('admin.combos.index') }}">Combo Offers</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <span class="current">{{ $combo->name }}</span>
    </nav>
</div>

@if($errors->any())
    <div class="df-alert df-alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <ul class="mb-0">
            @foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-9">
        <form action="{{ route('admin.combos.update', $combo) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="df-card combo-block">
                <div class="df-card-header">
                    <h5 class="df-card-title combo-block-title"><i class="bi bi-gift"></i> Combo Details</h5>
                    <span class="df-badge df-badge-muted">Slug: {{ $combo->slug }}</span>
                </div>
                <div class="df-card-body">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="df-form-label">Combo Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="df-form-control combo-name-input"
                                   value="{{ old('name', $combo->name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="df-form-label">Short Description</label>
                            <input type="text" name="description" class="df-form-control"
                                   value="{{ old('description', $combo->description) }}" placeholder="Shown with the combo offer (optional)">
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="df-form-label">
                            Select Products <span class="text-danger">*</span>
                            <span class="df-badge df-badge-info combo-selected-count ms-2">0 selected</span>
                        </label>
                        <p class="df-form-hint">Pick 2, 3 or more products to bundle together in this combo.</p>
                        <input type="text" class="df-form-control combo-product-search mb-2" placeholder="Search products by name...">
                        @error('product_ids')
                            <div class="text-danger small mb-1">{{ $message }}</div>
                        @enderror
                        <div class="combo-product-list">
                            @forelse($products as $product)
                                @php
                                    $price = (float) ($product->picker_price ?? $product->display_price);
                                    $thumb = $product->primary_image ?? optional($product->images->first())->path;
                                    $isSelected = in_array((int) $product->id, array_map('intval', (array) $selectedIds));
                                @endphp
                                <label class="combo-product-item {{ $isSelected ? 'selected' : '' }}"
                                       data-name="{{ mb_strtolower($product->name) }}" data-price="{{ $price }}">
                                    <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" {{ $isSelected ? 'checked' : '' }}>
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
                                <p class="df-form-hint">No active products found.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label class="df-form-label">Special Discount Type <span class="text-danger">*</span></label>
                            <select name="discount_type" class="df-form-select combo-discount-type" required>
                                <option value="percent" {{ $selDiscountType === 'percent' ? 'selected' : '' }}>Percent (%)</option>
                                <option value="fixed" {{ $selDiscountType === 'fixed' ? 'selected' : '' }}>Fixed (₹)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="df-form-label">Special Discount Value <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0.01" name="discount_value"
                                       class="df-form-control combo-discount-value"
                                       value="{{ old('discount_value', $combo->discount_value) }}" required>
                                <span class="input-group-text combo-discount-suffix">{{ $selDiscountType === 'percent' ? '%' : '₹' }}</span>
                            </div>
                            @error('discount_value')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4 d-flex align-items-center">
                            <div style="margin-top:22px;">
                                <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                                    <input type="checkbox" name="is_active" value="1"
                                           {{ old('is_active', $combo->is_active) ? 'checked' : '' }}
                                           style="width:18px; height:18px; accent-color:var(--df-primary);">
                                    <span class="df-form-label mb-0">Active (show offer to customers)</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="df-form-label">Starts At</label>
                            <input type="datetime-local" name="starts_at" class="df-form-control"
                                   value="{{ old('starts_at', optional($combo->starts_at)->format('Y-m-d\TH:i')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="df-form-label">Expires At</label>
                            <input type="datetime-local" name="expires_at" class="df-form-control"
                                   value="{{ old('expires_at', optional($combo->expires_at)->format('Y-m-d\TH:i')) }}">
                            @error('expires_at')
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

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="df-btn df-btn-primary">
                    <i class="bi bi-check2-circle"></i> Update Combo
                </button>
                <a href="{{ route('admin.combos.index') }}" class="df-btn df-btn-light">
                    <i class="bi bi-arrow-left"></i> Cancel
                </a>
            </div>
        </form>
    </div>
    <div class="col-lg-3">
        <div class="df-card">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-lightbulb"></i> Combo Offer</h5>
            </div>
            <div class="df-card-body">
                <p style="color:var(--df-text-secondary); font-size:0.85rem; margin-bottom:10px;">
                    The combo offer is shown <strong>below every product selected in this combo</strong> wherever that product is viewed in the store.
                </p>
                <p style="color:var(--df-text-secondary); font-size:0.85rem; margin-bottom:10px;">
                    The summary panel recalculates live as you change products or the special discount.
                </p>
                <p style="color:var(--df-text-secondary); font-size:0.85rem; margin:0;">
                    Uncheck <em>Active</em> to hide the offer without deleting the combo.
                </p>
            </div>
        </div>
    </div>
</div>

@include('backend.combos.partials.combo-styles')
@include('backend.combos.partials.combo-script')

@endsection
