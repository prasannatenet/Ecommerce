@extends('layouts.app')
@section('content')

{{-- Page Header --}}
<div class="df-page-header">
    <h1 class="df-page-title">Product Details</h1>
    <div class="d-flex align-items-center gap-3">
        <nav class="df-breadcrumb d-none d-md-flex">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="{{ route('admin.products.index') }}">Product</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">{{ Str::limit($product->name, 30) }}</span>
        </nav>
        <a href="{{ route('admin.products.edit', $product) }}" class="df-btn df-btn-primary df-btn-sm">
            <i class="bi bi-pencil"></i> Edit Product
        </a>
    </div>
</div>

@if(session('success'))
    <div class="df-alert df-alert-success">
        <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
    </div>
@endif

<div class="row g-4">
    {{-- LEFT COLUMN --}}
    <div class="col-lg-8">

        {{-- Images --}}
        <div class="df-card">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-images"></i> Product Images</h5>
            </div>
            <div class="df-card-body">
                @if($product->images->count())
                    <div class="d-flex flex-wrap gap-3">
                        @foreach($product->images as $image)
                            <img src="{{ asset('storage/' . $image->path) }}" alt="{{ $product->name }}"
                                 style="width:140px;height:140px;object-fit:cover;border-radius:14px;border:1px solid var(--df-border-color);">
                        @endforeach
                    </div>
                @else
                    <div class="df-empty-state py-4">
                        <div class="empty-icon"><i class="bi bi-image"></i></div>
                        <p>No images uploaded</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- General Info --}}
        <div class="df-card">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-info-circle"></i> General Information</h5>
            </div>
            <div class="df-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="df-form-label">Product Name</div>
                        <span style="font-size:1.05rem; font-weight:600;">{{ $product->name }}</span>
                    </div>
                    <div class="col-md-6">
                        <div class="df-form-label">Slug</div>
                        <code style="font-size:0.9rem;">{{ $product->slug }}</code>
                    </div>
                </div>
                @if($product->short_description)
                    <div class="mt-3">
                        <div class="df-form-label">Short Description</div>
                        <p class="mb-0" style="color:var(--df-text-secondary);">{{ $product->short_description }}</p>
                    </div>
                @endif
                @if($product->description)
                    <div class="mt-3">
                        <div class="df-form-label">Full Description</div>
                        <p class="mb-0" style="color:var(--df-text-secondary);">{!! nl2br(e($product->description)) !!}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Pricing --}}
        <div class="df-card">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-currency-rupee"></i> Pricing</h5>
            </div>
            <div class="df-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="df-form-label">Regular Price</div>
                        <span class="df-price-regular" style="font-size:1.2rem;">₹{{ number_format($product->base_price, 2) }}</span>
                    </div>
                    @if($product->sale_price)
                        <div class="col-md-4">
                            <div class="df-form-label">Sale Price</div>
                            <span class="df-price-sale" style="font-size:1.2rem;">₹{{ number_format($product->sale_price, 2) }}</span>
                        </div>
                        <div class="col-md-4">
                            <div class="df-form-label">Discount</div>
                            <span class="df-badge df-badge-danger" style="font-size:0.85rem;">
                                {{ round(100 - ($product->sale_price / $product->base_price * 100)) }}% OFF
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Inventory (Simple) --}}
        @if($product->product_type === 'simple')
            <div class="df-card">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-box-seam"></i> Inventory</h5>
                </div>
                <div class="df-card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="df-form-label">SKU</div>
                            <span style="font-weight:600;">{{ $product->sku ?? '—' }}</span>
                        </div>
                        <div class="col-md-3">
                            <div class="df-form-label">Stock</div>
                            @php $stock = $product->stock @endphp
                            <span class="df-badge {{ $stock > 10 ? 'df-badge-success' : ($stock > 0 ? 'df-badge-warning' : 'df-badge-danger') }}">
                                {{ $stock }} units
                            </span>
                        </div>
                        <div class="col-md-3">
                            <div class="df-form-label">Weight</div>
                            <span>{{ $product->weight ? $product->weight . ' kg' : '—' }}</span>
                        </div>
                        <div class="col-md-3">
                            <div class="df-form-label">Manage Stock</div>
                            <span class="df-badge {{ $product->manage_stock ? 'df-badge-success' : 'df-badge-muted' }}">
                                {{ $product->manage_stock ? 'Yes' : 'No' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Variations (Variable) --}}
        @if($product->product_type === 'variable')
            <div class="df-card">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-sliders"></i> Variations</h5>
                    <div class="d-flex align-items-center gap-2">
                        <span class="df-badge df-badge-purple">{{ $product->variations->count() }} variations</span>
                        <button class="df-btn df-btn-primary df-btn-sm" type="button" onclick="document.getElementById('addVariationForm').style.display = document.getElementById('addVariationForm').style.display === 'none' ? 'block' : 'none'">
                            <i class="bi bi-plus-lg"></i> Add Variation
                        </button>
                    </div>
                </div>

                {{-- Add New Variation Form --}}
                <div id="addVariationForm" style="display:none; border-bottom:1px solid var(--df-border-color);">
                    <div class="df-card-body" style="background:var(--df-primary-light);">
                        <h6 style="font-weight:700; margin-bottom:16px;"><i class="bi bi-plus-circle"></i> New Variation</h6>
                        <form action="{{ route('admin.products.variations.store', $product) }}" method="POST">
                            @csrf

                            {{-- Attribute Dropdowns --}}
                            @if($product->attributes->count())
                                <div class="row g-3 mb-3">
                                    @foreach($product->attributes as $attribute)
                                        <div class="col-md-4">
                                            <label class="df-form-label">{{ $attribute->name }} <span class="text-danger">*</span></label>
                                            <select name="attributes[{{ $attribute->name }}]" class="df-form-select" required>
                                                <option value="">Select {{ $attribute->name }}</option>
                                                @foreach($attribute->values as $value)
                                                    <option value="{{ $value->value }}">{{ $value->value }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="row g-3 mb-3">
                                    <div class="col-12">
                                        <div class="df-alert df-alert-warning" style="margin:0;">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            No attributes assigned to this product. <a href="{{ route('admin.products.edit', $product) }}">Edit product</a> to add attributes first, or add custom attributes below.
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="df-form-label">Attribute Name</label>
                                        <input type="text" id="customAttrName" class="df-form-control" placeholder="e.g. Color">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="df-form-label">Attribute Value</label>
                                        <input type="text" id="customAttrValue" class="df-form-control" placeholder="e.g. Red">
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <button type="button" class="df-btn df-btn-light" onclick="addCustomAttribute()">
                                            <i class="bi bi-plus"></i> Add
                                        </button>
                                    </div>
                                    <div id="customAttributesContainer" class="col-12"></div>
                                </div>
                            @endif

                            {{-- SKU / Price / Stock --}}
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="df-form-label">SKU <span class="text-danger">*</span></label>
                                    <input type="text" name="sku" class="df-form-control" placeholder="e.g. PROD-RED-L" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="df-form-label">Price (₹) <span class="text-danger">*</span></label>
                                    <input type="number" name="price" class="df-form-control" step="0.01" min="0" placeholder="0.00" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="df-form-label">Stock <span class="text-danger">*</span></label>
                                    <input type="number" name="stock" class="df-form-control" min="0" placeholder="0" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="df-form-check">
                                    <input type="checkbox" name="is_active" value="1" id="varActive" checked>
                                    <label for="varActive">Active</label>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="df-btn df-btn-primary">
                                    <i class="bi bi-check2-circle"></i> Create Variation
                                </button>
                                <button type="button" class="df-btn df-btn-light" onclick="document.getElementById('addVariationForm').style.display='none'">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Existing Variations List --}}
                @if($product->variations->count())
                    <div class="df-card-body-flush">
                        @foreach($product->variations as $index => $variation)
                            <div class="variation-panel" style="border-bottom:1px solid var(--df-border-color);">
                                {{-- Variation Header (clickable to expand) --}}
                                <div class="d-flex align-items-center justify-content-between"
                                     style="padding:14px 20px; cursor:pointer; transition:background 0.2s;"
                                     onmouseover="this.style.background='var(--df-body-bg)'"
                                     onmouseout="this.style.background='transparent'"
                                     onclick="toggleVariation({{ $variation->id }})">
                                    <div class="d-flex align-items-center gap-3">
                                        <span style="font-weight:700; color:var(--df-primary); font-size:0.85rem;">#{{ $index + 1 }}</span>
                                        <div class="d-flex flex-wrap gap-1">
                                            @if($variation->attributes && is_array($variation->attributes))
                                                @foreach($variation->attributes as $key => $val)
                                                    <span class="df-badge df-badge-info">{{ $key }}: {{ $val }}</span>
                                                @endforeach
                                            @endif
                                        </div>
                                        <code style="font-size:0.82rem; color:var(--df-text-secondary);">{{ $variation->sku ?? '—' }}</code>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="df-price-regular">₹{{ number_format($variation->price, 2) }}</span>
                                        <span class="df-badge {{ $variation->stock > 0 ? 'df-badge-success' : 'df-badge-danger' }}">
                                            {{ $variation->stock }} in stock
                                        </span>
                                        <span class="df-badge {{ $variation->is_active ? 'df-badge-success' : 'df-badge-muted' }}">
                                            {{ $variation->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                        <i class="bi bi-chevron-down" id="chevron-{{ $variation->id }}" style="transition:transform 0.3s; color:var(--df-text-muted);"></i>
                                    </div>
                                </div>

                                {{-- Variation Edit Form (collapsible) --}}
                                <div id="variation-{{ $variation->id }}" style="display:none; padding:16px 20px; background:var(--df-body-bg); border-top:1px solid var(--df-border-color);">
                                    <form action="{{ route('admin.variations.update', $variation) }}" method="POST">
                                        @csrf
                                        @method('PUT')

                                        {{-- Attribute fields --}}
                                        <div class="row g-3 mb-3">
                                            @if($variation->attributes && is_array($variation->attributes))
                                                @foreach($variation->attributes as $attrName => $attrVal)
                                                    <div class="col-md-4">
                                                        <label class="df-form-label">{{ $attrName }}</label>
                                                        <input type="text" name="attributes[{{ $attrName }}]" class="df-form-control" value="{{ $attrVal }}">
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>

                                        <div class="row g-3 mb-3">
                                            <div class="col-md-3">
                                                <label class="df-form-label">SKU</label>
                                                <input type="text" name="sku" class="df-form-control" value="{{ $variation->sku }}" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="df-form-label">Price (₹)</label>
                                                <input type="number" name="price" class="df-form-control" step="0.01" value="{{ $variation->price }}" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="df-form-label">Stock</label>
                                                <input type="number" name="stock" class="df-form-control" value="{{ $variation->stock }}" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="df-form-label">Status</label>
                                                <select name="is_active" class="df-form-select">
                                                    <option value="1" {{ $variation->is_active ? 'selected' : '' }}>Active</option>
                                                    <option value="0" {{ !$variation->is_active ? 'selected' : '' }}>Inactive</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="d-flex gap-2">
                                            <button type="submit" class="df-btn df-btn-primary df-btn-sm">
                                                <i class="bi bi-check2"></i> Save Changes
                                            </button>
                                            <button type="button" class="df-btn df-btn-light df-btn-sm" onclick="toggleVariation({{ $variation->id }})">
                                                Cancel
                                            </button>
                                        </div>
                                    </form>

                                    {{-- Delete --}}
                                    <div style="margin-top:12px; padding-top:12px; border-top:1px dashed var(--df-border-color);">
                                        <form action="{{ route('admin.variations.destroy', $variation) }}" method="POST"
                                              onsubmit="return confirm('Delete this variation? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="df-btn df-btn-sm" style="color:var(--df-danger); background:var(--df-danger-light); border:none; font-size:0.82rem;">
                                                <i class="bi bi-trash"></i> Delete Variation
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="df-card-body">
                        <div class="df-empty-state py-4">
                            <div class="empty-icon"><i class="bi bi-sliders"></i></div>
                            <p>No variations yet. Click <strong>"Add Variation"</strong> to create one.</p>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- RIGHT COLUMN --}}
    <div class="col-lg-4">

        {{-- Status --}}
        <div class="df-card">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-send"></i> Status</h5>
            </div>
            <div class="df-card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="df-form-label mb-0">Product Type</span>
                    <span class="df-badge {{ $product->product_type === 'simple' ? 'df-badge-info' : 'df-badge-purple' }}">
                        {{ ucfirst($product->product_type) }}
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="df-form-label mb-0">Status</span>
                    <span class="df-badge {{ $product->is_active ? 'df-badge-success' : 'df-badge-danger' }}">
                        {{ $product->is_active ? 'Active' : 'Draft' }}
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="df-form-label mb-0">Created</span>
                    <span style="font-size:0.85rem; color:var(--df-text-secondary);">{{ $product->created_at->format('M d, Y') }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="df-form-label mb-0">Updated</span>
                    <span style="font-size:0.85rem; color:var(--df-text-secondary);">{{ $product->updated_at->format('M d, Y') }}</span>
                </div>
            </div>
        </div>

        {{-- Category & Brand --}}
        <div class="df-card">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-folder2-open"></i> Organization</h5>
            </div>
            <div class="df-card-body">
                <div class="mb-3">
                    <div class="df-form-label">Category</div>
                    @if($product->category)
                        <span class="df-badge df-badge-primary">{{ $product->category->name }}</span>
                    @else
                        <span style="color:var(--df-text-muted);">Uncategorized</span>
                    @endif
                </div>
                <div>
                    <div class="df-form-label">Brand</div>
                    @if($product->brand)
                        <span class="df-badge df-badge-info">{{ $product->brand->name }}</span>
                    @else
                        <span style="color:var(--df-text-muted);">No brand</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Tags --}}
        <div class="df-card">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-bookmark-star"></i> Tags</h5>
            </div>
            <div class="df-card-body">
                @if($product->tags->count())
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($product->tags as $tag)
                            <span class="df-badge df-badge-primary">{{ $tag->name }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="df-form-hint text-center mb-0">No tags assigned</p>
                @endif
            </div>
        </div>

        {{-- Actions --}}
        <div class="df-card">
            <div class="df-card-body">
                <a href="{{ route('admin.products.edit', $product) }}" class="df-btn df-btn-primary w-100 mb-2" style="justify-content:center;">
                    <i class="bi bi-pencil"></i> Edit Product
                </a>
                <a href="{{ route('admin.products.index') }}" class="df-btn df-btn-light w-100" style="justify-content:center;">
                    <i class="bi bi-arrow-left"></i> Back to Products
                </a>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function toggleVariation(id) {
    const panel = document.getElementById('variation-' + id);
    const chevron = document.getElementById('chevron-' + id);
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        if (chevron) chevron.style.transform = 'rotate(180deg)';
    } else {
        panel.style.display = 'none';
        if (chevron) chevron.style.transform = 'rotate(0deg)';
    }
}

function addCustomAttribute() {
    const nameInput = document.getElementById('customAttrName');
    const valueInput = document.getElementById('customAttrValue');
    const container = document.getElementById('customAttributesContainer');

    if (!nameInput.value.trim() || !valueInput.value.trim()) {
        alert('Please enter both attribute name and value');
        return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'd-flex align-items-center gap-2 mb-2';
    wrapper.innerHTML = `
        <span class="df-badge df-badge-info">${nameInput.value}: ${valueInput.value}</span>
        <input type="hidden" name="attributes[${nameInput.value}]" value="${valueInput.value}">
        <button type="button" class="df-action-btn danger" onclick="this.parentElement.remove()" style="width:24px;height:24px;font-size:0.7rem;">
            <i class="bi bi-x"></i>
        </button>
    `;
    container.appendChild(wrapper);

    nameInput.value = '';
    valueInput.value = '';
    nameInput.focus();
}
</script>
@endpush
