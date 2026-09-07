@extends('layouts.app')
@section('content')

{{-- Page Header --}}
<div class="df-page-header">
    <h1 class="df-page-title">Add Product</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <a href="{{ route('admin.products.index') }}">Product</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <span class="current">Add Product</span>
    </nav>
</div>

@if($errors->any())
    <div class="df-alert df-alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div>
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<form action="{{ route('admin.products.store') }}" method="POST" enctype="multipart/form-data">
    @csrf

    <div class="row g-4">
        {{-- LEFT COLUMN --}}
        <div class="col-lg-8">

            {{-- Upload Section --}}
            <div class="df-card">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-image"></i> Upload Images</h5>
                </div>
                <div class="df-card-body">
                    <label class="df-form-label">Primary Image (Single)</label>
                    <div class="df-upload-zone" onclick="document.getElementById('primary-image').click()">
                        <div class="upload-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                        <p>Drop your primary image here or <span class="browse-link">click to browse</span></p>
                    </div>
                    <input type="file" id="primary-image" name="primary_image" accept="image/*"
                           style="display:none" onchange="previewPrimaryImage(this)">
                    <div id="primary-image-preview" class="d-flex flex-wrap gap-3 mt-3"></div>

                    <hr class="my-4">

                    <label class="df-form-label">Additional Images (Multiple)</label>
                    <div class="df-upload-zone" onclick="document.getElementById('product-images').click()">
                        <div class="upload-icon"><i class="bi bi-images"></i></div>
                        <p>Drop additional images here or <span class="browse-link">click to browse</span></p>
                    </div>
                    <input type="file" id="product-images" name="images[]" multiple accept="image/*"
                           style="display:none" onchange="previewGalleryImages(this)">
                    <div id="gallery-image-preview" class="d-flex flex-wrap gap-3 mt-3"></div>
                    <p class="df-form-hint mt-2">These images will be used as gallery images for the product.</p>
                </div>
            </div>

            {{-- Product Type --}}
            <div class="df-card">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-toggles"></i> Product Data</h5>
                </div>
                <div class="df-card-body">
                    <label class="df-form-label">Product Type <span class="text-danger">*</span></label>
                    <select name="product_type" id="productType" class="df-form-select" onchange="toggleProductType()">
                        <option value="simple" {{ old('product_type') == 'simple' ? 'selected' : '' }}>📦 Simple Product</option>
                        <option value="variable" {{ old('product_type') == 'variable' ? 'selected' : '' }}>🔀 Variable Product</option>
                    </select>
                    <p class="df-form-hint"><strong>Simple:</strong> Single product with one price/SKU. <strong>Variable:</strong> Product with variations (size, color, etc.)</p>
                </div>
            </div>

            {{-- General Info --}}
            <div class="df-card">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-info-circle"></i> General</h5>
                </div>
                <div class="df-card-body">
                    <div class="mb-3">
                        <label class="df-form-label">Product Name <span class="text-danger">*</span></label>
                           <input type="text" name="name" id="productName" class="df-form-control" placeholder="Enter product name"
                               value="{{ old('name') }}" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="df-form-label">Jewellery For <span class="text-danger">*</span></label>
                            <select name="audience" class="df-form-select" required>
                                <option value="">-- Select audience --</option>
                                <option value="men" {{ old('audience') === 'men' ? 'selected' : '' }}>Men</option>
                                <option value="women" {{ old('audience') === 'women' ? 'selected' : '' }}>Women</option>
                                <option value="both" {{ old('audience') === 'both' ? 'selected' : '' }}>Both</option>
                                <option value="girl" {{ old('audience') === 'girl' ? 'selected' : '' }}>Girl</option>
                                <option value="unisex" {{ old('audience') === 'unisex' ? 'selected' : '' }}>Unisex</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="df-form-label">Material Type <span class="text-danger">*</span></label>
                            <select name="material_type" class="df-form-select" required>
                                <option value="">-- Select material --</option>
                                <option value="gold" {{ old('material_type') === 'gold' ? 'selected' : '' }}>Gold</option>
                                <option value="silver" {{ old('material_type') === 'silver' ? 'selected' : '' }}>Silver</option>
                                <option value="diamond" {{ old('material_type') === 'diamond' ? 'selected' : '' }}>Diamond</option>
                                <option value="platinum" {{ old('material_type') === 'platinum' ? 'selected' : '' }}>Platinum</option>
                                <option value="rose_gold" {{ old('material_type') === 'rose_gold' ? 'selected' : '' }}>Rose Gold</option>
                                <option value="stainless_steel" {{ old('material_type') === 'stainless_steel' ? 'selected' : '' }}>Stainless Steel</option>
                                <option value="other" {{ old('material_type') === 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="df-form-label">Slug <span class="text-danger">*</span></label>
                           <input type="text" name="slug" id="productSlug" class="df-form-control" placeholder="Auto-generated from name"
                               value="{{ old('slug') }}" required>
                        <p class="df-form-hint">Auto-generated from product name. Used in URLs.</p>
                    </div>
                    <div class="mb-3">
                        <label class="df-form-label">Short Description</label>
                        <textarea name="short_description" class="df-form-control" rows="2"
                                  placeholder="Brief summary shown in product listings">{{ old('short_description') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="df-form-label">Description</label>
                        <textarea name="description" class="df-form-control" rows="5"
                                  placeholder="Full product description">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Pricing --}}
            <div class="df-card">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-currency-rupee"></i> Pricing</h5>
                </div>
                <div class="df-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="df-form-label">Regular Price (₹) <span class="text-danger">*</span></label>
                            <input type="number" name="base_price" id="basePrice" class="df-form-control" step="0.01"
                                   placeholder="0.00" value="{{ old('base_price') }}" required oninput="calculateSalePrice()">
                        </div>
                        <div class="col-md-3">
                            <label class="df-form-label">Discount Type</label>
                            <select name="discount_type" id="discountType" class="df-form-select" onchange="calculateSalePrice()">
                                <option value="">No discount</option>
                                <option value="percentage" {{ old('discount_type') === 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
                                <option value="fixed" {{ old('discount_type') === 'fixed' ? 'selected' : '' }}>Fixed amount (₹)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="df-form-label">Discount Value</label>
                            <input type="number" name="discount_value" id="discountValue" class="df-form-control" step="0.01" min="0"
                                   placeholder="0" value="{{ old('discount_value') }}" oninput="calculateSalePrice()" disabled>
                        </div>
                        <div class="col-12">
                            <label class="df-form-label">Calculated Sale Price (₹)</label>
                            <input type="number" name="sale_price" id="salePrice" class="df-form-control" step="0.01"
                                   placeholder="Calculated automatically" value="{{ old('sale_price') }}" readonly>
                            <p class="df-form-hint" id="discountSummary">Choose a discount type to calculate the sale price.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Inventory (Simple Only) --}}
            <div id="simpleFields" class="df-card">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-box-seam"></i> Inventory</h5>
                </div>
                <div class="df-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="df-form-label">SKU</label>
                            <input type="text" name="sku" class="df-form-control" placeholder="Stock Keeping Unit"
                                   value="{{ old('sku') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="df-form-label">Stock Quantity</label>
                            <input type="number" name="stock" class="df-form-control" placeholder="0"
                                   value="{{ old('stock', 0) }}">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <div class="df-form-check">
                                <input type="checkbox" name="manage_stock" value="1" id="manageStock"
                                       {{ old('manage_stock') ? 'checked' : '' }}>
                                <label for="manageStock">Manage Stock?</label>
                            </div>
                            <p class="df-form-hint">Enable stock management at product level</p>
                        </div>
                        <div class="col-md-6">
                            <label class="df-form-label">Weight (kg)</label>
                            <input type="number" name="weight" class="df-form-control" step="0.01" placeholder="0.00"
                                   value="{{ old('weight') }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Attributes (Variable Only) --}}
            <div id="variableFields" class="df-card" style="display:none;">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-sliders"></i> Attributes</h5>
                </div>
                <div class="df-card-body">
                    @foreach($attributes as $attribute)
                        <div class="mb-3">
                            <div class="df-form-check" style="background:var(--df-body-bg); padding:10px 14px; border-radius:10px;">
                                <input type="checkbox" name="attribute_ids[]" value="{{ $attribute->id }}"
                                       id="attr{{ $attribute->id }}"
                                       {{ in_array($attribute->id, old('attribute_ids', [])) ? 'checked' : '' }}>
                                <label for="attr{{ $attribute->id }}">{{ $attribute->name }}</label>
                            </div>
                        </div>
                    @endforeach
                    <div class="df-alert df-alert-info mt-3">
                        <i class="bi bi-info-circle"></i>
                        Select attribute names that apply to this variable product. You can set the values while creating each variation.
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT COLUMN --}}
        <div class="col-lg-4">

            {{-- Publish --}}
            <div class="df-card">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-send"></i> Publish</h5>
                </div>
                <div class="df-card-body">
                    <label class="df-form-label">Status</label>
                    <select name="is_active" class="df-form-select mb-3">
                        <option value="1" {{ old('is_active', 1) == 1 ? 'selected' : '' }}>✅ Active (Published)</option>
                        <option value="0" {{ old('is_active') == '0' ? 'selected' : '' }}>📝 Draft</option>
                    </select>
                    <button type="submit" class="df-btn df-btn-primary w-100">
                        <i class="bi bi-check2-circle"></i> Create Product
                    </button>
                </div>
            </div>

            {{-- Category --}}
            <div class="df-card">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-folder2-open"></i> Category</h5>
                </div>
                <div class="df-card-body">
                    <select name="category_id" class="df-form-select">
                        <option value="">— Select Category —</option>
                        @foreach($categories as $cat)
                            @include('backend.categories.partials.category-options', ['category' => $cat, 'level' => 0, 'selected' => old('category_id')])
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Brand --}}
            <div class="df-card">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-award"></i> Brand</h5>
                </div>
                <div class="df-card-body">
                    <select name="brand_id" class="df-form-select">
                        <option value="">— Select Brand —</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->id }}" {{ old('brand_id') == $brand->id ? 'selected' : '' }}>
                                {{ $brand->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Tags --}}
            <div class="df-card">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-bookmark-star"></i> Tags</h5>
                    <a href="{{ route('admin.tags.create') }}" class="df-action-btn" title="Add Tag">
                        <i class="bi bi-plus"></i>
                    </a>
                </div>
                <div class="df-card-body">
                    @if($tags->count())
                        <div class="df-tag-list">
                            @foreach($tags as $tag)
                                <div class="df-tag-item">
                                    <input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}"
                                           id="tag{{ $tag->id }}"
                                           {{ in_array($tag->id, old('tag_ids', [])) ? 'checked' : '' }}>
                                    <label for="tag{{ $tag->id }}">{{ $tag->name }}</label>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="df-form-hint text-center">No tags available. <a href="{{ route('admin.tags.create') }}">Create one</a></p>
                    @endif
                </div>
            </div>

        </div>
    </div>
</form>

@push('scripts')
<script>
function toggleProductType() {
    const type = document.getElementById('productType').value;
    document.getElementById('simpleFields').style.display   = type === 'simple' ? 'block' : 'none';
    document.getElementById('variableFields').style.display  = type === 'variable' ? 'block' : 'none';
}

function calculateSalePrice() {
    const basePrice = parseFloat(document.getElementById('basePrice')?.value) || 0;
    const type = document.getElementById('discountType')?.value || '';
    const discountInput = document.getElementById('discountValue');
    const salePrice = document.getElementById('salePrice');
    const summary = document.getElementById('discountSummary');

    discountInput.disabled = !type;

    if (!type || basePrice <= 0 || !discountInput.value) {
        salePrice.value = '';
        summary.textContent = type ? 'Enter a discount value to calculate the sale price.' : 'Choose a discount type to calculate the sale price.';
        return;
    }

    const discount = parseFloat(discountInput.value) || 0;
    const discountAmount = type === 'percentage' ? basePrice * discount / 100 : discount;
    salePrice.value = Math.max(0, basePrice - discountAmount).toFixed(2);
    summary.textContent = type === 'percentage'
        ? `${discount}% discount saves ₹${discountAmount.toFixed(2)}`
        : `Fixed discount saves ₹${discountAmount.toFixed(2)}`;
}

let slugWasEdited = Boolean(document.getElementById('productSlug')?.value);

function generateProductSlug() {
    if (slugWasEdited) return;

    const name = document.getElementById('productName')?.value || '';
    const slug = name
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/[\s-]+/g, '-')
        .replace(/^-+|-+$/g, '');

    const slugInput = document.getElementById('productSlug');
    if (slugInput) slugInput.value = slug;
}

function previewPrimaryImage(input) {
    const preview = document.getElementById('primary-image-preview');
    preview.innerHTML = '';

    if (!input.files.length) return;

    const reader = new FileReader();
    reader.onload = e => {
        const img = document.createElement('img');
        img.src = e.target.result;
        img.style.cssText = 'width:100px;height:100px;object-fit:cover;border-radius:12px;border:1px solid var(--df-border-color);';
        preview.appendChild(img);
    };
    reader.readAsDataURL(input.files[0]);
}

function previewGalleryImages(input) {
    const preview = document.getElementById('gallery-image-preview');
    preview.innerHTML = '';
    Array.from(input.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.cssText = 'width:100px;height:100px;object-fit:cover;border-radius:12px;border:1px solid var(--df-border-color);';
            preview.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    toggleProductType();
    calculateSalePrice();

    document.getElementById('productName')?.addEventListener('input', generateProductSlug);
    document.getElementById('productSlug')?.addEventListener('input', () => {
        slugWasEdited = true;
    });
    generateProductSlug();
});
</script>
@endpush

@endsection
