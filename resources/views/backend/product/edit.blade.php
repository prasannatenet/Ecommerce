@extends('layouts.app')
@section('content')

{{-- Page Header --}}
<div class="df-page-header">
    <h1 class="df-page-title">Edit Product</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <a href="{{ route('admin.products.index') }}">Product</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <span class="current">Edit</span>
    </nav>
</div>

@if(session('success'))
    <div class="df-alert df-alert-success">
        <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="df-alert df-alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div>
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </div>
    </div>
@endif

<form action="{{ route('admin.products.update', $product) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="row g-4">
        {{-- LEFT COLUMN --}}
        <div class="col-lg-8">

            {{-- Current Primary Image --}}
            @if($product->primary_image)
                @php
                    $primaryImageRecord = $product->images->firstWhere('is_primary', true);
                @endphp
                <div class="df-card">
                    <div class="df-card-header">
                        <h5 class="df-card-title"><i class="bi bi-image"></i> Current Primary Image</h5>
                    </div>
                    <div class="df-card-body">
                        <div class="position-relative d-inline-block">
                            <img src="{{ asset('storage/' . $product->primary_image) }}" alt=""
                                 style="width:120px;height:120px;object-fit:cover;border-radius:12px;border:1px solid var(--df-border-color);">
                            @if($primaryImageRecord)
                                <button type="button"
                                        class="btn btn-sm btn-danger position-absolute"
                                        style="top:6px;right:6px;line-height:1;"
                                        onclick="deleteProductImage('{{ route('admin.products.images.destroy', [$product, $primaryImageRecord]) }}', 'primary image')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Current Images --}}
            @php $galleryImages = $product->images->where('is_primary', false); @endphp
            @if($galleryImages->count())
                <div class="df-card">
                    <div class="df-card-header">
                        <h5 class="df-card-title"><i class="bi bi-images"></i> Current Images</h5>
                    </div>
                    <div class="df-card-body">
                        <div class="d-flex flex-wrap gap-3">
                            @foreach($galleryImages as $image)
                                <div class="position-relative">
                                    <img src="{{ asset('storage/' . $image->path) }}" alt=""
                                         style="width:100px;height:100px;object-fit:cover;border-radius:12px;border:1px solid var(--df-border-color);">
                                    <button type="button"
                                            class="btn btn-sm btn-danger position-absolute"
                                            style="top:6px;right:6px;line-height:1;"
                                            onclick="deleteProductImage('{{ route('admin.products.images.destroy', [$product, $image]) }}', 'this image')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- Upload New --}}
            <div class="df-card">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-cloud-arrow-up"></i> Upload New Images</h5>
                </div>
                <div class="df-card-body">
                    <label class="df-form-label">Primary Image (Single)</label>
                    <div class="df-upload-zone" onclick="document.getElementById('primary-image').click()">
                        <div class="upload-icon"><i class="bi bi-image"></i></div>
                        <p>Replace primary image or <span class="browse-link">click to browse</span></p>
                    </div>
                    <input type="file" id="primary-image" name="primary_image" accept="image/*"
                           style="display:none" onchange="previewPrimaryImage(this)">
                    <div id="primary-image-preview" class="d-flex flex-wrap gap-3 mt-3"></div>

                    <hr class="my-4">

                    <label class="df-form-label">Additional Images (Multiple)</label>
                    <div class="df-upload-zone" onclick="document.getElementById('product-images').click()">
                        <div class="upload-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                        <p>Drop additional images here or <span class="browse-link">click to browse</span></p>
                    </div>
                    <input type="file" id="product-images" name="images[]" multiple accept="image/*"
                           style="display:none" onchange="previewGalleryImages(this)">
                    <div id="gallery-image-preview" class="d-flex flex-wrap gap-3 mt-3"></div>
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
                        <option value="simple" {{ old('product_type', $product->product_type) == 'simple' ? 'selected' : '' }}>📦 Simple Product</option>
                        <option value="variable" {{ old('product_type', $product->product_type) == 'variable' ? 'selected' : '' }}>🔀 Variable Product</option>
                    </select>
                </div>
            </div>

            {{-- General --}}
            <div class="df-card">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-info-circle"></i> General</h5>
                </div>
                <div class="df-card-body">
                    <div class="mb-3">
                        <label class="df-form-label">Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="df-form-control"
                               value="{{ old('name', $product->name) }}" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="df-form-label">Jewellery For <span class="text-danger">*</span></label>
                            <select name="audience" class="df-form-select" required>
                                <option value="men" {{ old('audience', $product->audience ?? 'unisex') === 'men' ? 'selected' : '' }}>Men</option>
                                <option value="women" {{ old('audience', $product->audience ?? 'unisex') === 'women' ? 'selected' : '' }}>Women</option>
                                <option value="both" {{ old('audience', $product->audience ?? 'unisex') === 'both' ? 'selected' : '' }}>Both</option>
                                <option value="girl" {{ old('audience', $product->audience ?? 'unisex') === 'girl' ? 'selected' : '' }}>Girl</option>
                                <option value="unisex" {{ old('audience', $product->audience ?? 'unisex') === 'unisex' ? 'selected' : '' }}>Unisex</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="df-form-label">Material Type <span class="text-danger">*</span></label>
                            <select name="material_type" class="df-form-select" required>
                                <option value="gold" {{ old('material_type', $product->material_type ?? 'other') === 'gold' ? 'selected' : '' }}>Gold</option>
                                <option value="silver" {{ old('material_type', $product->material_type ?? 'other') === 'silver' ? 'selected' : '' }}>Silver</option>
                                <option value="diamond" {{ old('material_type', $product->material_type ?? 'other') === 'diamond' ? 'selected' : '' }}>Diamond</option>
                                <option value="platinum" {{ old('material_type', $product->material_type ?? 'other') === 'platinum' ? 'selected' : '' }}>Platinum</option>
                                <option value="rose_gold" {{ old('material_type', $product->material_type ?? 'other') === 'rose_gold' ? 'selected' : '' }}>Rose Gold</option>
                                <option value="stainless_steel" {{ old('material_type', $product->material_type ?? 'other') === 'stainless_steel' ? 'selected' : '' }}>Stainless Steel</option>
                                <option value="other" {{ old('material_type', $product->material_type ?? 'other') === 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="df-form-label">Slug <span class="text-danger">*</span></label>
                        <input type="text" name="slug" class="df-form-control"
                               value="{{ old('slug', $product->slug) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="df-form-label">Short Description</label>
                        <textarea name="short_description" class="df-form-control" rows="2">{{ old('short_description', $product->short_description) }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="df-form-label">Description</label>
                        <textarea name="description" class="df-form-control" rows="5">{{ old('description', $product->description) }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Pricing --}}
            <div class="df-card">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-currency-rupee"></i> Pricing</h5>
                </div>
                <div class="df-card-body">
                    @php
                        $savedDiscountType = old('discount_type', $product->discount_type);
                        $savedDiscountValue = old('discount_value', $product->discount_value);
                        if (!$savedDiscountType && $product->sale_price && $product->base_price > 0 && $product->sale_price < $product->base_price) {
                            $savedDiscountType = 'percentage';
                            $savedDiscountValue = round((($product->base_price - $product->sale_price) / $product->base_price) * 100, 2);
                        }
                    @endphp
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="df-form-label">Regular Price (₹) <span class="text-danger">*</span></label>
                            <input type="number" name="base_price" id="basePrice" class="df-form-control" step="0.01"
                                   value="{{ old('base_price', $product->base_price) }}" required oninput="calculateSalePrice()">
                        </div>
                        <div class="col-md-3">
                            <label class="df-form-label">Discount Type</label>
                            <select name="discount_type" id="discountType" class="df-form-select" onchange="calculateSalePrice()">
                                <option value="">No discount</option>
                                <option value="percentage" {{ $savedDiscountType === 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
                                <option value="fixed" {{ $savedDiscountType === 'fixed' ? 'selected' : '' }}>Fixed amount (₹)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="df-form-label">Discount Value</label>
                            <input type="number" name="discount_value" id="discountValue" class="df-form-control" step="0.01" min="0"
                                   placeholder="0" value="{{ $savedDiscountValue }}" disabled>
                        </div>
                        <div class="col-12">
                            <label class="df-form-label">Calculated Sale Price (₹)</label>
                            <input type="number" name="sale_price" id="salePrice" class="df-form-control" step="0.01"
                                   value="{{ old('sale_price', $product->sale_price) }}" placeholder="Calculated automatically">
                            <p class="df-form-hint" id="discountSummary">Choose a discount type to calculate the sale price. Leave the existing sale price unchanged if no discount is selected.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Inventory (Simple) --}}
            <div id="simpleFields" class="df-card">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-box-seam"></i> Inventory</h5>
                </div>
                <div class="df-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="df-form-label">SKU</label>
                            <input type="text" name="sku" class="df-form-control"
                                   value="{{ old('sku', $product->sku) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="df-form-label">Stock Quantity</label>
                            <input type="number" name="stock" class="df-form-control"
                                   value="{{ old('stock', $product->stock) }}">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <div class="df-form-check">
                                <input type="checkbox" name="manage_stock" value="1" id="manageStock"
                                       {{ old('manage_stock', $product->manage_stock) ? 'checked' : '' }}>
                                <label for="manageStock">Manage Stock?</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="df-form-label">Weight (kg)</label>
                            <input type="number" name="weight" class="df-form-control" step="0.01"
                                   value="{{ old('weight', $product->weight) }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Attributes (Variable) --}}
            <div id="variableFields" class="df-card" style="display:none;">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-sliders"></i> Attributes</h5>
                </div>
                <div class="df-card-body">
                    @php $selectedAttributeIds = old('attribute_ids', $product->attributes->pluck('id')->toArray()); @endphp
                    @foreach($attributes as $attribute)
                        <div class="mb-3">
                            <div class="df-form-check" style="background:var(--df-body-bg); padding:10px 14px; border-radius:10px;">
                                <input type="checkbox" name="attribute_ids[]" value="{{ $attribute->id }}"
                                       id="attr{{ $attribute->id }}"
                                       {{ in_array($attribute->id, $selectedAttributeIds) ? 'checked' : '' }}>
                                <label for="attr{{ $attribute->id }}">{{ $attribute->name }}</label>
                            </div>
                        </div>
                    @endforeach
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
                        <option value="1" {{ old('is_active', $product->is_active) == 1 ? 'selected' : '' }}>✅ Active</option>
                        <option value="0" {{ old('is_active', $product->is_active) == 0 ? 'selected' : '' }}>📝 Draft</option>
                    </select>
                    <button type="submit" class="df-btn df-btn-primary w-100">
                        <i class="bi bi-check2-circle"></i> Update Product
                    </button>
                    <a href="{{ route('admin.products.index') }}" class="df-btn df-btn-light w-100 mt-2" style="justify-content: center;">
                        <i class="bi bi-arrow-left"></i> Back to Products
                    </a>
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
                            @include('backend.categories.partials.category-options', ['category' => $cat, 'level' => 0, 'selected' => old('category_id', $product->category_id)])
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
                            <option value="{{ $brand->id }}" {{ old('brand_id', $product->brand_id) == $brand->id ? 'selected' : '' }}>
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
                </div>
                <div class="df-card-body">
                    @php $selectedTags = old('tag_ids', $product->tags->pluck('id')->toArray()); @endphp
                    @if($tags->count())
                        <div class="df-tag-list">
                            @foreach($tags as $tag)
                                <div class="df-tag-item">
                                    <input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}"
                                           id="tag{{ $tag->id }}"
                                           {{ in_array($tag->id, $selectedTags) ? 'checked' : '' }}>
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
        summary.textContent = type
            ? 'Enter a discount value to calculate the sale price.'
            : 'Choose a discount type to calculate the sale price. Leave the existing sale price unchanged if no discount is selected.';
        return;
    }

    const discount = parseFloat(discountInput.value) || 0;
    const discountAmount = type === 'percentage' ? basePrice * discount / 100 : discount;
    salePrice.value = Math.max(0, basePrice - discountAmount).toFixed(2);
    summary.textContent = type === 'percentage'
        ? `${discount}% discount saves ₹${discountAmount.toFixed(2)}`
        : `Fixed discount saves ₹${discountAmount.toFixed(2)}`;
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

function deleteProductImage(url, label) {
    if (!confirm(`Are you sure you want to delete ${label}?`)) {
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;

    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = '{{ csrf_token() }}';
    form.appendChild(csrf);

    const method = document.createElement('input');
    method.type = 'hidden';
    method.name = '_method';
    method.value = 'DELETE';
    form.appendChild(method);

    document.body.appendChild(form);
    form.submit();
}

document.addEventListener('DOMContentLoaded', () => {
    toggleProductType();
    calculateSalePrice();
});
</script>
@endpush

@endsection
