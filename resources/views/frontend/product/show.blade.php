@extends('layouts.frontend')

@section('title', $product->name . ' | GEHNA')

@section('content')

    @php
        $activeVariations = $product->variations->where('is_active', true)->values();
        $isVariableProduct = $activeVariations->isNotEmpty();
        $defaultVariation = $isVariableProduct
            ? $activeVariations->first(fn($variation) => (int) ($variation->stock ?? 0) > 0) ??
                $activeVariations->first()
            : null;
        $firstVariationPrice = $defaultVariation ? (float) ($defaultVariation->price ?? 0) : null;
        $defaultVariationId = $defaultVariation?->id;
        $defaultVariationStock = $defaultVariation ? (int) ($defaultVariation->stock ?? 0) : 0;
        $defaultVariationAttrs = $defaultVariation ? $defaultVariation->attributes ?? [] : [];
        if (is_string($defaultVariationAttrs)) {
            $decodedDefaultAttrs = json_decode($defaultVariationAttrs, true);
            $defaultVariationAttrs = is_array($decodedDefaultAttrs) ? $decodedDefaultAttrs : [];
        }
        $defaultVariationLabel = $defaultVariation
            ? (collect($defaultVariationAttrs)
                ->map(fn($value, $key) => ucfirst($key) . ': ' . $value)
                ->implode(' | ') ?:
            'Variation #' . $defaultVariation->id)
            : '';
        $firstProductImage = $product->images->first();
        $firstProductImagePath = $firstProductImage->path ?? ($firstProductImage->image_path ?? null);
        $firstProductImageUrl = $firstProductImagePath ? asset('storage/' . ltrim($firstProductImagePath, '/')) : null;
    @endphp



    {{-- PAGE HEADER --}}
    <section class="product-detail-hero" style="background:linear-gradient(135deg,#013a3c 0%,#017075 60%,#02AAB1 100%); padding:48px 50px 40px;">
        <div class="container-fluid">

            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb mb-0" style="background:none; padding:0;">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"
                            style="color:rgba(255,255,255,0.7); text-decoration:none;">Home</a></li>
                    <li class="breadcrumb-item active" style="color:#fff;"><a href="{{ route('products.index') }}">Shop</a>
                    </li>
                    <li class="breadcrumb-item active" style="color:#fff;">{{ $product->name }}</li>

                </ol>
            </nav>
            <h1 style="color: gold !important;">{{ $product->name }}</h1>

        </div>
    </section>



    {{-- PRODUCT DETAIL --}}
    <section class="py-5" id="productDetailWrap">
        <div class="container-fluid">
            <div class="row g-5">

                {{-- GALLERY --}}
                <div class="col-lg-6">
                    <div class="product-gallery">
                        <div class="gallery-main">
                            @if ($firstProductImageUrl)
                                <img src="{{ $firstProductImageUrl }}" alt="{{ $product->name }}" id="mainProductImg">
                            @else
                                <img src="{{ asset('frontend/images/dumbbell.png') }}" alt="{{ $product->name }}"
                                    id="mainProductImg" class="main-product-img-placeholder">
                            @endif
                        </div>

                        @if ($product->images->count() > 1)
                            <div class="gallery-thumbs mt-3">
                                @foreach ($product->images as $image)
                                    @php
                                        $imagePath = $image->path ?? ($image->image_path ?? null);
                                        $imageUrl = $imagePath ? asset('storage/' . ltrim($imagePath, '/')) : null;
                                    @endphp
                                    @continue(!$imageUrl)
                                    <div class="gallery-thumb {{ $loop->first ? 'active' : '' }}"
                                        onclick="switchImage(this, '{{ $imageUrl }}')">
                                        <img src="{{ $imageUrl }}" alt="Thumbnail">
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- PRODUCT INFO --}}
                <div class="col-lg-6">
                    <div class="product-detail-info">

                        <span class="section-badge section-badge-teal mb-2">
                            {{ $product->brand ? $product->brand->name : 'GEHNA PREMIUM' }}
                        </span>

                        <h1 class="product-detail-title" id="pdTitle">{{ $product->name }}</h1>

                        <div class="product-rating mb-3">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-half"></i>
                            <span class="ms-2 text-muted" style="font-size:0.85rem;">(3 Customer Reviews)</span>
                        </div>

                        <div class="d-flex align-items-center gap-3 mb-4">
                            <span class="product-detail-price" id="product-price">
                                @if ($isVariableProduct)
                                    ₹{{ number_format($firstVariationPrice, 0) }}
                                @elseif($product->sale_price)
                                    ₹{{ number_format($product->sale_price, 0) }}
                                @else
                                    ₹{{ number_format($product->base_price, 0) }}
                                @endif
                            </span>
                            @if (!$isVariableProduct && $product->sale_price)
                                <span class="product-detail-price-old">₹{{ number_format($product->base_price, 0) }}</span>
                                @php $disc = round((($product->base_price - $product->sale_price) / $product->base_price) * 100); @endphp
                                <span class="badge-sale fs-6">{{ $disc }}% OFF</span>
                            @endif
                        </div>

                        <p class="text-muted mb-4">
                            {{ $product->short_description ?? 'Thoughtfully crafted jewellery designed to add a refined touch to every occasion.' }}
                        </p>

                        {{-- Variation selector as weight options --}}
                        @if ($isVariableProduct)
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="fw-bold mb-0">Choose Variant</h6>
                                    <small id="selected-variation-stock" class="text-muted">
                                        {{ $defaultVariationStock > 0 ? 'In stock' : 'Out of stock' }}
                                    </small>
                                </div>
                                <div class="weight-selector">
                                    @foreach ($activeVariations as $variation)
                                        @php
                                            $variationAttrs = $variation->attributes ?? [];
                                            if (is_string($variationAttrs)) {
                                                $decodedAttrs = json_decode($variationAttrs, true);
                                                $variationAttrs = is_array($decodedAttrs) ? $decodedAttrs : [];
                                            }
                                            $attrs = collect($variationAttrs)
                                                ->map(fn($value, $key) => ucfirst($key) . ': ' . $value)
                                                ->implode(' | ');
                                            $label = $attrs ?: 'Variation #' . $variation->id;
                                            $stock = (int) ($variation->stock ?? 0);
                                            $isOutOfStock = $stock <= 0;
                                        @endphp
                                        <button type="button"
                                            class="weight-option variation-option {{ $variation->id === $defaultVariationId ? 'active' : '' }}"
                                            data-variation-id="{{ $variation->id }}"
                                            data-price="{{ (float) $variation->price }}" data-stock="{{ $stock }}"
                                            data-label="{{ $label }}" onclick="selectVariation(this)">
                                            <span class="variation-option-title">{{ $label }}</span>
                                            <span class="variation-option-meta">
                                                <span
                                                    class="variation-option-price">&#8377;{{ number_format((float) $variation->price, 0) }}</span>
                                                <span class="variation-option-stock {{ $isOutOfStock ? 'out' : 'in' }}">
                                                    {{ $isOutOfStock ? 'Out of stock' : $stock . ' in stock' }}
                                                </span>
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                                {{-- <small class="text-muted d-block mt-2" id="selected-variation-label">
                                Selected: {{ $defaultVariationLabel }}
                            </small> --}}
                            </div>
                        @endif

                        {{-- Session messages --}}
                        @if (session('success'))
                            <div class="alert alert-success py-2">{{ session('success') }}</div>
                        @endif
                        @if (session('error'))
                            <div class="alert alert-danger py-2">{{ session('error') }}</div>
                        @endif

                        {{-- Add to Cart Form --}}
                        <form action="{{ route('cart.add') }}" method="POST" id="product-cart-form">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            @if ($isVariableProduct)
                                <input type="hidden" name="product_variation_id" id="product_variation_id"
                                    value="{{ $defaultVariationId }}">
                            @endif

                            <div class="d-flex align-items-center gap-3 mb-4">
                                <div class="qty-control">
                                    <button type="button" onclick="document.getElementById('pdQty').stepDown()">−</button>
                                    <input type="number" value="1" min="1" max="99" id="pdQty"
                                        name="quantity">
                                    <button type="button" onclick="document.getElementById('pdQty').stepUp()">+</button>
                                </div>
                                <button type="submit" class="btn-gehna btn-teal-gehna" id="addToCartBtn">
                                    <i class="bi bi-cart-plus"></i> Add to Cart
                                </button>
                                <a href="{{ route('checkout.index') }}" class="btn-gehna btn-dark-teal-gehna"
                                    id="buyNowBtn">
                                    <i class="bi bi-lightning"></i> Buy Now
                                </a>
                            </div>
                        </form>

                        @auth
                            <form action="{{ route('wishlist.toggle') }}" method="POST" class="mb-4">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                @if ($isVariableProduct)
                                    <input type="hidden" name="product_variation_id" id="wishlist_variation_id"
                                        value="{{ $defaultVariationId }}">
                                @endif
                                <button type="submit" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-heart me-2"></i>Add to Wishlist
                                </button>
                            </form>
                        @endauth

                        {{-- Trust Badges --}}
                        <div class="d-flex gap-4 pt-3 border-top flex-wrap">
                            <div class="text-center">
                                <i class="bi bi-truck fs-4 d-block text-primary mb-1"></i>
                                <small class="text-muted">Free Shipping</small>
                            </div>
                            <div class="text-center">
                                <i class="bi bi-shield-check fs-4 d-block text-success mb-1"></i>
                                <small class="text-muted">5-Year Warranty</small>
                            </div>
                            <div class="text-center">
                                <i class="bi bi-arrow-counterclockwise fs-4 d-block text-warning mb-1"></i>
                                <small class="text-muted">30-Day Returns</small>
                            </div>
                            <div class="text-center">
                                <i class="bi bi-patch-check fs-4 d-block text-info mb-1"></i>
                                <small class="text-muted">Genuine</small>
                            </div>
                        </div>

                        {{-- Meta --}}
                        <div class="mt-4 text-muted" style="font-size:0.85rem;">
                            <div><strong>SKU:</strong> {{ $product->sku }}</div>
                            @if ($product->category)
                                <div><strong>Category:</strong>
                                    <a href="{{ route('category.show', $product->category->id) }}"
                                        class="text-muted">{{ $product->category->name }}</a>
                                </div>
                            @endif
                            <div class="mt-2 d-flex gap-3 align-items-center">
                                <strong>Share:</strong>
                                <a href="#" class="text-muted"><i class="bi bi-facebook fs-5"></i></a>
                                <a href="#" class="text-muted"><i class="bi bi-twitter-x fs-5"></i></a>
                                <a href="#" class="text-muted"><i class="bi bi-whatsapp fs-5"></i></a>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            {{-- DESCRIPTION / SPECS / REVIEWS TABS --}}
            <div class="tabs-gehna mt-5 pt-4">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#tabDesc">Description</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tabSpecs">Specifications</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tabReviews">Reviews</a>
                    </li>
                </ul>

                <div class="tab-content pt-4">
                    {{-- Description Tab --}}
                    <div class="tab-pane fade show active" id="tabDesc">
                        <div class="row g-4">
                            <div class="col-md-8">
                                @if ($product->description)
                                    <div>{!! $product->description !!}</div>
                                @else
                                    <h5 class="fw-bold mb-3">Transform Your Workout</h5>
                                    <p class="text-muted">{{ $product->name }} is engineered for serious home fitness.
                                        High-quality materials and precision manufacturing ensure balanced, consistent
                                        performance every workout.</p>
                                    <h6 class="fw-bold mt-4 mb-2">What's in the Box:</h6>
                                    <ul class="text-muted">
                                        <li>1x {{ $product->name }}</li>
                                        <li>1x User Manual & Workout Guide</li>
                                        <li>1x Warranty Card</li>
                                    </ul>
                                @endif
                            </div>
                            <div class="col-md-4 text-center">
                                @if ($firstProductImageUrl)
                                    <img src="{{ $firstProductImageUrl }}" alt="{{ $product->name }}"
                                        style="max-height:250px; object-fit:contain; filter:drop-shadow(0 10px 20px rgba(0,0,0,0.15));">
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Specs Tab --}}
                    <div class="tab-pane fade" id="tabSpecs">
                        <table class="spec-table">
                            @if ($product->sku)
                                <tr>
                                    <td>SKU</td>
                                    <td>{{ $product->sku }}</td>
                                </tr>
                            @endif
                            @if ($product->category)
                                <tr>
                                    <td>Category</td>
                                    <td>{{ $product->category->name }}</td>
                                </tr>
                            @endif
                            @if ($product->brand)
                                <tr>
                                    <td>Brand</td>
                                    <td>{{ $product->brand->name }}</td>
                                </tr>
                            @endif
                            @if ($isVariableProduct)
                                @foreach ($activeVariations as $variation)
                                    @php
                                        $variationAttrs = $variation->attributes ?? [];
                                        if (is_string($variationAttrs)) {
                                            $decodedAttrs = json_decode($variationAttrs, true);
                                            $variationAttrs = is_array($decodedAttrs) ? $decodedAttrs : [];
                                        }
                                        $attrs = collect($variationAttrs)
                                            ->map(fn($val, $key) => ucfirst($key) . ': ' . $val)
                                            ->implode(' | ');
                                    @endphp
                                    <tr>
                                        <td>{{ $attrs ?: 'Variation #' . $variation->id }}</td>
                                        <td>₹{{ number_format((float) $variation->price, 0) }}</td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td>Price</td>
                                    <td>₹{{ number_format($product->sale_price ?? $product->base_price, 0) }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td>Warranty</td>
                                <td>5 Years</td>
                            </tr>
                            <tr>
                                <td>Return Policy</td>
                                <td>30 Days Easy Returns</td>
                            </tr>
                            <tr>
                                <td>Shipping</td>
                                <td>Free on orders above ₹5,000</td>
                            </tr>
                        </table>
                    </div>

                    {{-- Reviews Tab --}}
                    <div class="tab-pane fade" id="tabReviews">
                        <div class="review-card">
                            <div class="review-header">
                                <span class="review-author">Aditya M.</span>
                                <span class="review-date">March 15, 2026</span>
                            </div>
                            <div class="review-stars">
                                <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i
                                    class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i
                                    class="bi bi-star-fill"></i>
                            </div>
                            <p class="review-text">Absolute beast of a product! The quality is outstanding and it works
                                perfectly for my home gym setup. Highly recommended.</p>
                        </div>
                        <div class="review-card">
                            <div class="review-header">
                                <span class="review-author">Sneha R.</span>
                                <span class="review-date">March 10, 2026</span>
                            </div>
                            <div class="review-stars">
                                <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i
                                    class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i
                                    class="bi bi-star-half"></i>
                            </div>
                            <p class="review-text">Great quality and super space-saving. Delivery was faster than expected.
                                Very satisfied with the purchase.</p>
                        </div>
                        <div class="review-card">
                            <div class="review-header">
                                <span class="review-author">Vikram S.</span>
                                <span class="review-date">February 28, 2026</span>
                            </div>
                            <div class="review-stars">
                                <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i
                                    class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i
                                    class="bi bi-star-fill"></i>
                            </div>
                            <p class="review-text">As a certified trainer, I can confidently say this is one of the best
                                products in this price range. My clients love it.</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

@endsection

@push('styles')
    <style>
        .product-detail-hero {
            background: linear-gradient(135deg, #32030d 0%, #5b0b24 58%, #7b173d 100%) !important;
        }

        .product-detail-hero .breadcrumb-item a,
        .product-detail-hero .breadcrumb-item.active {
            color: rgba(255, 255, 255, 0.78) !important;
        }

        #productDetailWrap {
            background: #fbf7f3;
        }

        #productDetailWrap{
            padding: 0 50px;
        }

        .product-gallery .gallery-main {
            padding: clamp(14px, 2vw, 30px);
            min-height: clamp(280px, 42vw, 500px);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid #eadfd9;
            border-radius: 14px;
            background: #f4eee9;
            box-shadow: 0 10px 28px rgba(74, 22, 34, 0.06);
        }

        .product-gallery #mainProductImg {
            width: 100%;
            height: clamp(250px, 36vw, 450px);
            object-fit: contain;
            object-position: center;
            display: block;
        }

        .product-gallery .main-product-img-placeholder {
            opacity: 0.5;
        }

        .product-detail-info {
            padding: clamp(22px, 3vw, 40px);
            border: 1px solid #eadfd9;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 10px 28px rgba(74, 22, 34, 0.06);
        }

        .product-detail-info .section-badge-teal {
            color: #7b173d !important;
            background: #f9e7ed !important;
        }

        .product-detail-info .product-detail-title {
            color: #35121c;
        }

        .product-detail-info .product-rating i {
            color: #c88736;
        }

        .product-detail-price {
            color: #7b173d !important;
        }

        /* .product-detail-info .btn-teal-gehna {
            background: linear-gradient(95deg, #4f071b, #8b1e48) !important;
            border-color: transparent !important;
        } */

        /* .product-detail-info .btn-dark-teal-gehna {
            background: #35121c !important;
            border-color: #35121c !important;
        } */

        .product-detail-info .btn-outline-secondary {
            border-color: #dfcfd0;
            color: #7b173d;
        }

        .variation-option {
            border-color: #eadfd9 !important;
            background: #fffaf7 !important;
        }

        .variation-option:hover,
        .variation-option.active {
            border-color: #8b1e48 !important;
            background: #fdf1f4 !important;
            color: #7b173d !important;
            box-shadow: 0 0 0 1px rgba(139, 30, 72, 0.2) !important;
        }

        .variation-option-title,
        .variation-option-price {
            color: #5b0b24 !important;
        }

        .gallery-thumb:hover,
        .gallery-thumb.active {
            border-color: #8b1e48 !important;
            background: #fdf1f4;
        }

        .tabs-gehna .nav-tabs {
            border-bottom-color: #eadfd9;
        }

        .tabs-gehna .nav-link.active {
            color: #7b173d !important;
            border-color: #eadfd9 #eadfd9 #fbf7f3;
        }

        @media (max-width: 768px) {
            #productDetailWrap {
                padding: 0 16px;
            }

            .product-gallery .gallery-main {
                min-height: 260px;
            }

            .product-gallery #mainProductImg {
                height: 260px;
            }
        }

        .weight-selector {
            display: flex;
            flex-wrap: nowrap;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 6px;
            scrollbar-width: thin;
        }

        .variation-option {
            flex: 0 0 auto;
            min-width: 240px;
            border: 1px solid #d7e2e8;
            border-radius: 12px;
            background: #fff;
            padding: 12px 14px;
            text-align: left;
            transition: all 0.2s ease;
        }

        .variation-option:hover {
            border-color: #0f7c88;
            box-shadow: 0 4px 14px rgba(15, 124, 136, 0.12);
        }

        .variation-option.active {
            border-color: #0f7c88;
            background: #f2fbfd;
            box-shadow: 0 0 0 1px rgba(15, 124, 136, 0.25);
        }

        .variation-option-title {
            display: block;
            font-weight: 700;
            color: #132a33;
            margin-bottom: 6px;
        }

        .variation-option-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }

        .variation-option-price {
            color: #0f7c88;
            font-weight: 700;
        }

        .variation-option-stock {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 999px;
        }

        .variation-option-stock.in {
            color: #176f43;
            background: #e9f8ef;
        }

        .variation-option-stock.out {
            color: #9f2c2c;
            background: #fdecec;
        }
    </style>
@endpush

@push('scripts')
    <script>
        // Gallery thumbnail switcher
        function switchImage(thumb, src) {
            document.getElementById('mainProductImg').src = src;
            document.querySelectorAll('.gallery-thumb').forEach(function(t) {
                t.classList.remove('active');
            });
            thumb.classList.add('active');
        }

        // Variation selector
        function selectVariation(el) {
            document.querySelectorAll('.weight-option').forEach(function(opt) {
                opt.classList.remove('active');
            });
            el.classList.add('active');

            const price = parseFloat(el.dataset.price || 0);
            const varId = el.dataset.variationId;
            const stock = parseInt(el.dataset.stock || '0', 10);
            const label = el.dataset.label || 'Variant';

            const priceEl = document.getElementById('product-price');
            if (priceEl) {
                priceEl.textContent = '₹' + price.toLocaleString('en-IN');
            }

            const varInput = document.getElementById('product_variation_id');
            if (varInput) varInput.value = varId;

            const wishlistVarInput = document.getElementById('wishlist_variation_id');
            if (wishlistVarInput) wishlistVarInput.value = varId;

            const selectedLabel = document.getElementById('selected-variation-label');
            if (selectedLabel) selectedLabel.textContent = 'Selected: ' + label;

            const stockLabel = document.getElementById('selected-variation-stock');
            if (stockLabel) stockLabel.textContent = stock > 0 ? 'In stock' : 'Out of stock';

            const addToCartBtn = document.getElementById('addToCartBtn');
            if (addToCartBtn) addToCartBtn.disabled = stock <= 0;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const selected = document.querySelector('.weight-option.active');
            if (selected) {
                selectVariation(selected);
            }
        });
    </script>
@endpush
