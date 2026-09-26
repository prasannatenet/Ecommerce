@extends('layouts.frontend')

@section('title', $product->name . ' | GEHNA')

@section('content')

    @php
        $activeVariations = $product->variations->where('is_active', true)->values();
        $hasVariations = $product->variations->isNotEmpty();
        $isVariableProduct = $activeVariations->isNotEmpty();
        // Resolve the quoted variation through the model so this page and the
        // product cards always show the exact same price.
        $defaultVariation = $isVariableProduct
            ? $product->defaultVariation()
            : null;
        // Price of the initially selected variation (or of the product itself).
        $selectedRegularPrice = $defaultVariation
            ? (float) $defaultVariation->price
            : (float) ($product->base_price ?? 0);
        $selectedEffectivePrice = $defaultVariation
            ? $defaultVariation->effectivePrice()
            : (float) ($product->sale_price ?? $product->base_price ?? 0);
        $selectedDiscountPercentage = ($selectedRegularPrice > 0 && $selectedEffectivePrice < $selectedRegularPrice)
            ? round((($selectedRegularPrice - $selectedEffectivePrice) / $selectedRegularPrice) * 100)
            : null;
        $defaultVariationDescription = $defaultVariation ? trim((string) ($defaultVariation->description ?? '')) : '';
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
        $toGalleryImageUrl = fn ($path) => filled($path)
            ? asset('storage/' . ltrim($path, '/'))
            : null;

        $productGalleryImages = $product->images
            ->map(fn ($image) => $toGalleryImageUrl($image->path ?? ($image->image_path ?? null)))
            ->filter()
            ->values();

        $variationGalleryImages = $activeVariations->mapWithKeys(fn ($variation) => [
            $variation->id => $variation->images
                ->sortByDesc(fn ($image) => (int) $image->is_primary)
                ->map(fn ($image) => $toGalleryImageUrl($image->path))
                ->filter()
                ->values(),
        ]);

        // A product that has variations shows only the selected variation's
        // images, description and price — the parent product's media is never
        // mixed in, not even as a fallback.
        $initialGalleryImages = ($hasVariations
            ? $variationGalleryImages->get($defaultVariationId, collect())
            : $productGalleryImages)->unique()->values();
        $firstProductImageUrl = $initialGalleryImages->first();
        $descriptionImageUrl = $firstProductImageUrl;

        // Videos belong to the simple-product gallery only.
        $productVideos = $hasVariations
            ? collect()
            : $product->videos->sortByDesc('is_primary')->values();
        $primaryVideo = $productVideos->first();
        $primaryVideoUrl = $primaryVideo?->stream_url;

        // Encoded without JSON_HEX_QUOT so the browser can JSON.parse the value
        // straight from the data attribute (Blade escapes the quotes for HTML).
        $galleryJson = fn ($images) => json_encode($images, JSON_UNESCAPED_SLASHES);
        $productGalleryImagesJson = $hasVariations ? '[]' : $galleryJson($productGalleryImages->all());
        $variationGalleryImagesJson = $galleryJson($variationGalleryImages->all());
    @endphp



    {{-- PAGE HEADER --}}
    <!-- <section class="product-detail-hero" style="background:linear-gradient(135deg,#013a3c 0%,#017075 60%,#02AAB1 100%); padding:48px 50px 40px;">
        <div class="container-fluid">
            <h1 style="color: gold !important;">{{ $product->name }}</h1>
        </div>
    </section> -->

    <section style="border: solid 1px #ccc; box-shadow: rgba(50, 50, 93, 0.25) 0px 2px 5px -1px, rgba(0, 0, 0, 0.3) 0px 1px 3px -1px; margin-bottom: 3px;">
    <div class="container">
        <nav aria-label="breadcrumb" class="py-2">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Shop</a>
                </li>
                <li class="breadcrumb-item active">{{ $product->name }}</li>

            </ol>
        </nav>
    </div>
    </section>


    {{-- PRODUCT DETAIL --}}
    <section class="py-5" id="productDetailWrap">
        <div class="container">
            <div class="row g-5">

                {{-- GALLERY --}}
                <div class="col-lg-6">
                    <div class="product-gallery" id="productGallery"
                        data-product-images="{{ $productGalleryImagesJson }}"
                        data-variation-images="{{ $variationGalleryImagesJson }}"
                        data-placeholder-image="{{ asset('frontend/images/dumbbell.png') }}">
                        <div class="gallery-main product-zoom-area" data-zoom-src="{{ $firstProductImageUrl ?? asset('frontend/images/dumbbell.png') }}">
                            @if ($firstProductImageUrl)
                                <img src="{{ $firstProductImageUrl }}" alt="{{ $product->name }}" id="mainProductImg" data-zoom-src="{{ $firstProductImageUrl }}">
                            @else
                                <img src="{{ asset('frontend/images/dumbbell.png') }}" alt="{{ $product->name }}"
                                    id="mainProductImg" class="main-product-img-placeholder"
                                    style="{{ $primaryVideoUrl ? 'display:none;' : '' }}"
                                    data-zoom-src="{{ asset('frontend/images/dumbbell.png') }}">
                            @endif

                            @if ($primaryVideoUrl)
                                {{-- Video-only products show the clip straight away; otherwise it waits behind a thumb. --}}
                                <video id="mainProductVideo" class="main-product-video" src="{{ $primaryVideoUrl }}"
                                    controls playsinline preload="metadata"
                                    style="{{ $firstProductImageUrl ? 'display:none;' : '' }}"></video>
                            @endif

                            <div id="productZoomPreview" class="product-zoom-preview" aria-hidden="true">
                                <div class="product-zoom-preview-image"></div>
                            </div>
                        </div>

                        @if ($initialGalleryImages->count() > 1 || $productVideos->isNotEmpty())
                            <div class="gallery-thumbs mt-3" id="productGalleryThumbs">
                                @foreach ($initialGalleryImages as $imageUrl)
                                    <div class="gallery-thumb gallery-thumb-image {{ $loop->first ? 'active' : '' }}"
                                        data-gallery-image="{{ $imageUrl }}"
                                        onclick="switchImage(this, this.dataset.galleryImage)">
                                        <img src="{{ $imageUrl }}" alt="{{ $product->name }} image thumbnail">
                                    </div>
                                @endforeach

                                @foreach ($productVideos as $video)
                                    @php
                                        // stream_url serves the file in byte ranges so playback starts at once.
                                        $videoUrl = $video->stream_url;
                                    @endphp
                                    @continue(!$videoUrl)
                                    <div class="gallery-thumb gallery-thumb-video {{ $loop->first && !$firstProductImageUrl ? 'active' : '' }}"
                                        title="Play video"
                                        onclick="switchToVideo(this, '{{ $videoUrl }}')">
                                        {{-- The #t=0.1 media fragment makes the browser paint a real frame instead of a black box. --}}
                                        <video src="{{ $videoUrl }}#t=0.1" muted preload="metadata" playsinline></video>
                                        <span class="thumb-play-badge"><i class="bi bi-play-fill"></i></span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- PRODUCT INFO --}}
                <div class="col-lg-6">
                    <div class="product-detail-info product_detail_info">

                        <h1 class="product-detail-title" id="pdTitle">{{ $product->name }}</h1>

                        <div class="product-rating mb-3" id="productRatingSummary" role="button" title="Read customer reviews">
                            <span class="pd-rating-stars">
                                @for ($i = 1; $i <= 5; $i++)
                                    <i class="bi {{ $averageRating >= $i ? 'bi-star-fill' : ($averageRating >= $i - 0.5 ? 'bi-star-half' : 'bi-star') }}"></i>
                                @endfor
                            </span>
                            <span class="ms-2 text-muted pd-rating-label" style="font-size:0.85rem;">
                                @if ($reviewsCount > 0)
                                    ({{ number_format($averageRating, 1) }} &#9733; &middot; {{ $reviewsCount }} Customer Review{{ $reviewsCount === 1 ? '' : 's' }})
                                @else
                                    (No reviews yet &middot; Be the first to review)
                                @endif
                            </span>
                        </div>

                        <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
                            {{-- 2 decimals, matching the product cards, quick view and the
                                 cart, so a price never changes shape between pages. --}}
                            <span class="product-detail-price" id="product-price">₹{{ number_format($selectedEffectivePrice, 2) }}</span>
                            <span class="product-detail-price-old" id="product-price-old"
                                style="{{ $selectedDiscountPercentage === null ? 'display:none;' : '' }}">
                                ₹{{ number_format($selectedRegularPrice, 2) }}
                            </span>
                            <span class="badge-sale fs-6" id="product-price-discount"
                                style="{{ $selectedDiscountPercentage === null ? 'display:none;' : '' }}">@if ($selectedDiscountPercentage !== null){{ $selectedDiscountPercentage }}% OFF @endif</span>
                        </div>

                        {{-- A product with variations describes only the selected variation. --}}
                        <p class="text-muted mb-4" id="product-short-description">
                            @if ($hasVariations)
                                {{ $defaultVariationDescription !== ''
                                    ? Str::limit(strip_tags($defaultVariationDescription), 180)
                                    : 'Select an option to see the details of this piece.' }}
                            @else
                                {{ $product->short_description ?? 'Thoughtfully crafted jewellery designed to add a refined touch to every occasion.' }}
                            @endif
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
                                            data-price="{{ $variation->effectivePrice() }}" data-stock="{{ $stock }}"
                                            data-regular-price="{{ (float) $variation->price }}"
                                            data-discount="{{ $variation->discountPercentage() ?? 0 }}"
                                            data-label="{{ $label }}" data-description="{{ $variation->description }}"
                                            onclick="selectVariation(this)">
                                            <span class="variation-option-title">{{ $label }}</span>
                                            <span class="variation-option-meta">
                                                <span
                                                    class="variation-option-price">&#8377;{{ number_format($variation->effectivePrice(), 2) }}</span>
                                                @if ($variation->discountPercentage())
                                                    <span class="variation-option-price-old text-decoration-line-through text-muted"
                                                        style="font-size:0.78rem;">&#8377;{{ number_format((float) $variation->price, 0) }}</span>
                                                @endif
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

                            <div class="d-flex flex-column flex-md-row align-items-center gap-3 mb-4">
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

                        @php $pdpWishlisted = $inWishlist ?? false; @endphp

{{-- Share + Wishlist row --}}
<div class="d-flex gap-2 mb-4">
    <div class="flex-fill">
        @auth
            <form action="{{ route('wishlist.toggle') }}" method="POST" id="wishlistToggleForm">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                @if ($isVariableProduct)
                    <input type="hidden" name="product_variation_id" id="wishlist_variation_id"
                        value="{{ $defaultVariationId }}">
                @endif
                <button type="submit" class="btn btn-outline-secondary w-100 {{ $pdpWishlisted ? 'active' : '' }}" id="wishlistToggleBtn">
                    <i class="bi {{ $pdpWishlisted ? 'bi-heart-fill' : 'bi-heart' }} me-2" @if($pdpWishlisted) style="color:#dc3545;" @endif></i><span>{{ $pdpWishlisted ? 'Wishlisted' : 'Add to Wishlist' }}</span>
                </button>
            </form>
        @else
            <form action="{{ route('wishlist.toggle') }}" method="POST" id="wishlistToggleForm">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                @if ($isVariableProduct)
                    <input type="hidden" name="product_variation_id" id="wishlist_variation_id"
                        value="{{ $defaultVariationId }}">
                @endif
                <button type="submit" class="btn btn-outline-secondary w-100 {{ $pdpWishlisted ? 'active' : '' }}" id="wishlistToggleBtn">
                    <i class="bi {{ $pdpWishlisted ? 'bi-heart-fill' : 'bi-heart' }} me-2" @if($pdpWishlisted) style="color:#dc3545;" @endif></i><span>{{ $pdpWishlisted ? 'Wishlisted' : 'Add to Wishlist' }}</span>
                </button>
            </form>
        @endauth
    </div>

    {{-- Share button: opens the OS share sheet on mobile (Web Share API),
         falls back to the share modal on desktop --}}
    <button type="button" class="btn btn-outline-secondary product-share-btn"
        data-share-url="{{ url()->current() }}"
        data-share-title="{{ $product->name }}"
        title="Share this product" aria-label="Share this product">
        <i class="bi bi-share me-2"></i><span>Share</span>
    </button>
</div>

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
                            {{-- <div class="mt-2 d-flex gap-3 align-items-center">
                                <strong>Share this product:</strong>
                                <a href="#" class="text-muted"><i class="bi bi-facebook fs-5"></i></a>
                                <a href="#" class="text-muted"><i class="bi bi-twitter-x fs-5"></i></a>
                                <a href="#" class="text-muted"><i class="bi bi-whatsapp fs-5"></i></a>
                            </div> --}}
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
                        <a class="nav-link" data-bs-toggle="tab" href="#tabReviews">Reviews <span
                                class="text-muted">(<span id="reviewsTabCount">{{ $reviewsCount }}</span>)</span></a>
                    </li>
                </ul>

                <div class="tab-content pt-4">
                    {{-- Description Tab --}}
                    <div class="tab-pane fade show active" id="tabDesc">
                        <div class="row g-4">
                            <div class="col-md-8">
                                <div id="product-description" class="product-description-content">
                                    @if ($hasVariations)
                                        @if ($defaultVariationDescription !== '')
                                            <div>{!! $defaultVariationDescription !!}</div>
                                        @else
                                            <p class="text-muted mb-0">No description is available for this variation.</p>
                                        @endif
                                    @elseif ($product->description)
                                        <div>{!! $product->description !!}</div>
                                    @else
                                        <h5 class="fw-bold mb-3">Product Details</h5>
                                        <p class="text-muted">{{ $product->name }} is ready to explore.</p>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                @if ($descriptionImageUrl)
                                    <img id="description-product-image" src="{{ $descriptionImageUrl }}" alt="{{ $product->name }}"
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
                                        <td>₹{{ number_format($variation->effectivePrice(), 2) }}</td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td>Price</td>
                                    <td>₹{{ number_format($product->sale_price ?? $product->base_price, 2) }}</td>
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
                        {{-- Review summary --}}
                        <div class="review-summary-bar mb-4 d-flex flex-wrap align-items-center gap-3">
                            <div class="review-summary-rating">
                                <span class="review-summary-score">{{ number_format($averageRating, 1) }}</span>
                                <span class="review-summary-stars">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <i class="bi {{ $averageRating >= $i ? 'bi-star-fill' : ($averageRating >= $i - 0.5 ? 'bi-star-half' : 'bi-star') }}"></i>
                                    @endfor
                                </span>
                                <span class="review-summary-count text-muted">
                                    ({{ $reviewsCount }} review{{ $reviewsCount === 1 ? '' : 's' }})
                                </span>
                            </div>
                        </div>

                        {{-- Review submission form --}}
                        <div class="review-form-wrap mb-4">
                            @auth
                                @if ($canReview)
                                <form id="reviewForm" method="POST" action="{{ route('products.reviews.store', $product) }}" enctype="multipart/form-data">
                                    @csrf
                                    <h6 class="review-form-title fw-bold mb-2" id="reviewFormTitle">Write a Review</h6>
                                    <div class="rating-input mb-2" id="ratingInput">
                                        <span class="rating-input-label text-muted me-2">Your rating:</span>
                                        <span class="star-rating" id="starRating">
                                            @for ($i = 1; $i <= 5; $i++)
                                                <i class="bi bi-star rating-star" data-value="{{ $i }}"></i>
                                            @endfor
                                        </span>
                                        <input type="hidden" name="rating" id="ratingValue" value="">
                                        <span class="rating-hint text-muted small ms-2" id="ratingHint"></span>
                                    </div>
                                    @error('rating')
                                        <div class="text-danger small mb-2">{{ $message }}</div>
                                    @enderror
                                    <textarea name="comment" id="reviewComment" class="form-control" rows="3"
                                        placeholder="Share your experience with this product..." maxlength="2000"></textarea>
                                    @error('comment')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                    <div class="review-image-field mt-3">
                                        <label for="reviewImageInput" class="form-label small fw-semibold mb-1">Add photos <span class="text-muted fw-normal">(optional)</span></label>
                                        <input type="file" id="reviewImageInput" name="images[]" class="form-control form-control-sm"
                                            accept="image/jpeg,image/png,image/webp" multiple>
                                        <div class="form-text">Up to 3 JPG, PNG, or WebP images. Maximum 2 MB each.</div>
                                        @error('images')
                                            <div class="text-danger small mt-1">{{ $message }}</div>
                                        @enderror
                                        @error('images.0')
                                            <div class="text-danger small mt-1">{{ $message }}</div>
                                        @enderror
                                        @error('images.1')
                                            <div class="text-danger small mt-1">{{ $message }}</div>
                                        @enderror
                                        @error('images.2')
                                            <div class="text-danger small mt-1">{{ $message }}</div>
                                        @enderror
                                        <div id="reviewImageError" class="text-danger small mt-1" role="alert"></div>
                                        <div id="reviewExistingImages" class="review-image-previews mt-2"></div>
                                        <div id="reviewSelectedImages" class="review-image-previews mt-2"></div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 mt-3">
                                        <button type="submit" class="btn btn-primary-gehna btn-sm" id="reviewSubmitBtn">
                                            <i class="bi bi-send me-1"></i>Submit Review
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm d-none"
                                            id="reviewCancelEditBtn">
                                            <i class="bi bi-x-lg me-1"></i>Cancel
                                        </button>
                                        <span class="small text-muted"><span id="commentCharCount">0</span>/2000</span>
                                    </div>
                                </form>
                                @else
                                    <div class="review-login-prompt p-3 rounded-3">
                                        <i class="bi bi-bag-check me-2"></i>
                                        You can review this product after purchasing it.
                                        <a href="{{ route('orders.index') }}" class="fw-semibold">View your orders</a>
                                    </div>
                                @endif
                            @else
                                <div class="review-login-prompt p-3 rounded-3">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>
                                    <a href="{{ route('login') }}" class="fw-semibold">Log in</a> or
                                    <a href="{{ route('register') }}" class="fw-semibold">create an account</a>
                                    to write a review.
                                </div>
                            @endauth
                        </div>

                        {{-- Review list --}}
                        <div id="reviewList">
                            @include('frontend.product.review-list', ['reviews' => $reviews])
                        </div>

                        <div class="modal fade" id="reviewImageModal" tabindex="-1" aria-labelledby="reviewImageModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="reviewImageModalLabel">Customer Photo</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 text-center bg-dark">
                                        <img id="reviewImageModalImage" src="" alt="Customer review photo" class="img-fluid">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    {{-- Active combo offers including this product — shown below the product --}}
    @include('frontend.partials.combo-offers', ['product' => $product, 'combos' => $combos ?? collect()])

    {{-- Products commonly purchased together in valid paid orders --}}
    @include('frontend.partials.frequently-bought-together', [
        'frequentlyBoughtTogether' => $frequentlyBoughtTogether ?? collect(),
        'frequentlyBoughtTogetherWishlistIds' => $frequentlyBoughtTogetherWishlistIds ?? [],
    ])

    {{-- Suggestions: other active products from the same category --}}
    @include('frontend.partials.related-products', [
        'relatedProducts' => $relatedProducts ?? collect(),
        'wishlistProductIds' => $relatedWishlistIds ?? [],
    ])

    {{-- Product share modal --}}
    @include('frontend.partials.share-modal', [
        'modalId'  => 'product',
        'shareUrl' => url()->current(),
        'shareTitle' => $product->name,
    ])

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
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: visible;
            border: 1px solid #eadfd9;
            border-radius: 14px;
            background: #f4eee9;
            cursor: crosshair;
            height: 730px;
        }

        .product-gallery #mainProductImg {
            width: 100%;
            aspect-ratio: 1/1;
            object-fit: cover;
            display: block;
            transition: transform 0.25s ease;
            transform-origin: center center;
            height: 100%;
        }

        .product-zoom-preview {
            position: absolute;
            top: 0;
            left: calc(100% + 24px);
            width: min(420px, 48vw);
            border: 1px solid #eadfd9;
            border-radius: 4px;
            background: #fff;
            box-shadow: 0 12px 30px rgba(32, 15, 19, 0.16);
            pointer-events: none;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.15s ease, visibility 0.15s ease;
            z-index: 20;
            overflow: hidden;
            aspect-ratio: 1/1;
        }

        .product-zoom-preview-image {
            width: 100%;
            height: 100%;
            background-repeat: no-repeat;
            background-color: #fff;
        }

        .product-zoom-preview.visible {
            opacity: 1;
            visibility: visible;
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
            color: var(--primary) !important;
            background: #f9e7ed !important;
        }

        .product-detail-info .product-detail-title {
            color: #35121c;
        }

        .product-detail-info .product-rating i {
            color: #c88736;
        }

        .product-detail-price {
            color: var(--primary) !important;
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

        .product-gallery .main-product-video {
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #000;
            border-radius: 14px;
            display: block;
        }

        .gallery-thumb.gallery-thumb-video {
            position: relative;
            overflow: hidden;
            padding: 0;
        }

        .gallery-thumb.gallery-thumb-video video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            border-radius: 6px;
        }

        .gallery-thumb .thumb-play-badge {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.4rem;
            background: rgba(0, 0, 0, 0.28);
            pointer-events: none;
        }

        .tabs-gehna .nav-tabs {
            border-bottom-color: #eadfd9;
        }

        .tabs-gehna .nav-link.active {
            color: var(--primary) !important;
            border-color: #eadfd9 #eadfd9 #fbf7f3;
        }

        @media (max-width: 768px) {
            #productDetailWrap {
                padding: 0 16px;
            }

            .product-gallery .gallery-main {
                height: auto;
            }

            .product-gallery #mainProductImg {
                height: 260px;
            }

            .product-gallery .main-product-video {
                height: 260px;
            }

            .product-zoom-preview {
                display: none;
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

        /* ===== REVIEWS ===== */
        .review-summary-rating {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .review-summary-score {
            font-size: 1.6rem;
            font-weight: 800;
            color: #a67c00;
        }

        .review-summary-stars {
            color: var(--gold);
            font-size: 1.05rem;
        }

        .review-summary-stars i,
        .review-summary-stars span,
        .review-stars i {
            color: var(--gold);
        }

        .review-summary-count {
            font-size: 0.85rem;
        }

        .review-form-wrap {
            border: 1px solid #e7ded6;
            border-radius: 14px;
            background: #fff;
            padding: 18px 20px;
        }

        .review-login-prompt {
            background: #f8f3ee;
            border: 1px dashed #d8cbb9;
            color: #5a4a3a;
            font-size: 0.92rem;
        }

        .review-login-prompt a {
            color: #a67c00;
            text-decoration: none;
        }

        .review-login-prompt a:hover {
            text-decoration: underline;
        }

        .star-rating {
            display: inline-flex;
            gap: 3px;
            font-size: 1.45rem;
            cursor: pointer;
            color: #d1c1ac;
        }

        .star-rating .rating-star {
            transition: color 0.15s ease, transform 0.15s ease;
        }

        .star-rating .rating-star:hover {
            transform: scale(1.15);
        }

        .star-rating .rating-star.filled,
        .star-rating .rating-star.selected {
            color: var(--gold);
        }

        .review-form-wrap .form-control {
            border-color: #e2dcd3;
        }

        .review-form-wrap .form-control:focus {
            border-color: #a67c00;
            box-shadow: 0 0 0 0.18rem rgba(166, 124, 0, 0.12);
        }

        .review-empty {
            font-style: italic;
        }

        .review-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .review-actions .btn {
            font-size: 0.82rem;
            padding: 2px 10px;
            border-radius: 999px;
            background: #f8f3ee;
            border: 1px solid #e2dcd3;
        }

        .review-edit-btn {
            color: #a67c00;
        }

        .review-edit-btn:hover {
            background: #faf3df;
            color: #8a5d00;
        }

        .review-delete-btn {
            color: #b02a2a;
        }

        .review-delete-btn:hover {
            background: #fdecec;
            color: #8c1d1d;
        }

        .review-image-gallery,
        .review-image-previews {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .review-image-trigger,
        .review-image-preview {
            position: relative;
            width: 76px;
            height: 76px;
            padding: 0;
            border: 1px solid #e2dcd3;
            border-radius: 10px;
            background: #f8f3ee;
            overflow: hidden;
        }

        .review-image-trigger img,
        .review-image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .review-image-remove {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 24px;
            height: 24px;
            padding: 0;
            border: 0;
            border-radius: 50%;
            background: rgba(32, 15, 19, 0.78);
            color: #fff;
            line-height: 24px;
        }

        #reviewImageModal img {
            max-height: 78vh;
            object-fit: contain;
        }
    </style>
@endpush

@push('scripts')
    <script>
        // Gallery thumbnail switcher — images
        function switchImage(thumb, src) {
            const mainImage = document.getElementById('mainProductImg');
            const mainVideo = document.getElementById('mainProductVideo');

            // Stop and hide the video when an image thumb is picked.
            if (mainVideo) {
                mainVideo.pause();
                mainVideo.style.display = 'none';
            }

            if (mainImage) {
                mainImage.style.display = '';
                mainImage.onload = function() {
                    if (typeof window.refreshProductZoom === 'function') {
                        window.refreshProductZoom(src);
                    }
                };
                mainImage.src = src;
                mainImage.dataset.zoomSrc = src;
            }
            document.querySelectorAll('.gallery-thumb').forEach(function(t) {
                t.classList.remove('active');
            });
            if (thumb) {
                thumb.classList.add('active');
            }
            if (typeof window.refreshProductZoom === 'function') {
                window.refreshProductZoom(src);
            }
        }

        // Gallery thumbnail switcher — short videos
        function switchToVideo(thumb, src) {
            const mainImage = document.getElementById('mainProductImg');
            const mainVideo = document.getElementById('mainProductVideo');
            if (!mainVideo) return;

            if (mainImage) {
                mainImage.style.display = 'none';
            }

            if (mainVideo.getAttribute('src') !== src) {
                mainVideo.setAttribute('src', src);
            }
            mainVideo.style.display = 'block';

            const preview = document.getElementById('productZoomPreview');
            if (preview) {
                preview.classList.remove('visible');
                preview.setAttribute('aria-hidden', 'true');
            }

            const playPromise = mainVideo.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                // Browsers may block autoplay with sound — the controls remain available.
                playPromise.catch(function() {});
            }

            document.querySelectorAll('.gallery-thumb').forEach(function(t) {
                t.classList.remove('active');
            });
            thumb.classList.add('active');
        }

        function initProductZoom() {
            const zoomArea = document.querySelector('.product-zoom-area');
            const mainImage = document.getElementById('mainProductImg');
            const preview = document.getElementById('productZoomPreview');
            const previewImage = preview ? preview.querySelector('.product-zoom-preview-image') : null;

            if (!zoomArea || !mainImage || !preview || !previewImage) return;

            let lastPoint = null;
            const zoomFactor = 2.2;

            function refreshZoomImage(src) {
                previewImage.style.backgroundImage = "url('" + src + "')";
                if (lastPoint && zoomArea.matches(':hover')) {
                    moveLens(lastPoint);
                }
            }

            function moveLens(event) {
                // Zoom only applies to the image — skip while a video is showing.
                if (mainImage.style.display === 'none') {
                    preview.classList.remove('visible');
                    return;
                }

                const rect = zoomArea.getBoundingClientRect();
                const x = event.clientX - rect.left;
                const y = event.clientY - rect.top;
                lastPoint = event;

                if (x < 0 || y < 0 || x > rect.width || y > rect.height) {
                    preview.classList.remove('visible');
                    return;
                }

                const previewWidth = preview.clientWidth;
                const previewHeight = preview.clientHeight;
                const backgroundWidth = rect.width * zoomFactor;
                const backgroundHeight = rect.height * zoomFactor;
                const backgroundX = (x / rect.width) * backgroundWidth;
                const backgroundY = (y / rect.height) * backgroundHeight;

                previewImage.style.backgroundSize = backgroundWidth + 'px ' + backgroundHeight + 'px';
                previewImage.style.backgroundPosition =
                    (previewWidth / 2 - backgroundX) + 'px ' +
                    (previewHeight / 2 - backgroundY) + 'px';
                preview.classList.add('visible');
            }

            window.refreshProductZoom = refreshZoomImage;
            refreshZoomImage(mainImage.dataset.zoomSrc || mainImage.src);

            zoomArea.addEventListener('mouseenter', function(event) {
                moveLens(event);
            });

            zoomArea.addEventListener('mousemove', moveLens);

            zoomArea.addEventListener('mouseleave', function() {
                lastPoint = null;
                preview.classList.remove('visible');
            });
        }

        function readGalleryData(gallery, key, fallback) {
            try {
                const data = JSON.parse(gallery.dataset[key] || '');
                return data && typeof data === 'object' ? data : fallback;
            } catch (error) {
                return fallback;
            }
        }

        // Show only the selected variation's photos. A product that has
        // variations never borrows the parent product's gallery.
        function renderGalleryForVariation(variationId) {
            const gallery = document.getElementById('productGallery');
            const mainImage = document.getElementById('mainProductImg');
            if (!gallery || !mainImage) return;

            const productImages = readGalleryData(gallery, 'productImages', []);
            const variationImages = readGalleryData(gallery, 'variationImages', {});
            const selectedImages = Array.isArray(variationImages[String(variationId)])
                ? variationImages[String(variationId)]
                : [];
            const images = (selectedImages.length ? selectedImages : productImages).filter(
                (src) => typeof src === 'string' && src.length > 0
            );
            const placeholder = gallery.dataset.placeholderImage || mainImage.src;

            let thumbs = document.getElementById('productGalleryThumbs');
            if (!thumbs && images.length > 1) {
                thumbs = document.createElement('div');
                thumbs.id = 'productGalleryThumbs';
                thumbs.className = 'gallery-thumbs mt-3';
                gallery.appendChild(thumbs);
            }

            if (thumbs) {
                thumbs.querySelectorAll('.gallery-thumb-image').forEach(function(thumb) {
                    thumb.remove();
                });

                const fragment = document.createDocumentFragment();
                images.forEach(function(src) {
                    const thumb = document.createElement('div');
                    thumb.className = 'gallery-thumb gallery-thumb-image';
                    thumb.dataset.galleryImage = src;
                    thumb.setAttribute('onclick', 'switchImage(this, this.dataset.galleryImage)');

                    const image = document.createElement('img');
                    image.src = src;
                    image.alt = 'Product image thumbnail';
                    thumb.appendChild(image);
                    fragment.appendChild(thumb);
                });

                const firstVideoThumb = thumbs.querySelector('.gallery-thumb-video');
                thumbs.insertBefore(fragment, firstVideoThumb);
            }

            if (images.length) {
                switchImage(thumbs ? thumbs.querySelector('.gallery-thumb-image') : null, images[0]);
                return;
            }

            // A variation without photos must not leave another variation's
            // photo on screen; show the neutral placeholder instead.
            mainImage.classList.add('main-product-img-placeholder');
            mainImage.style.display = '';
            mainImage.src = placeholder;
            mainImage.dataset.zoomSrc = placeholder;
            if (typeof window.refreshProductZoom === 'function') {
                window.refreshProductZoom(placeholder);
            }
        }

        // Variation selector
        function selectVariation(el) {
            document.querySelectorAll('.weight-option').forEach(function(opt) {
                opt.classList.remove('active');
            });
            el.classList.add('active');

            const price = parseFloat(el.dataset.price || 0);
            const regularPrice = parseFloat(el.dataset.regularPrice || '0');
            const discount = parseFloat(el.dataset.discount || '0');
            const varId = el.dataset.variationId;
            const stock = parseInt(el.dataset.stock || '0', 10);
            const label = el.dataset.label || 'Variant';
            const hasDiscount = discount > 0 && regularPrice > price;

            const priceEl = document.getElementById('product-price');
            if (priceEl) {
                priceEl.textContent = '\u20B9' + price.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            // The struck-through regular price and the % OFF badge follow the
            // selected variation (both hidden when it is not discounted).
            const oldPriceEl = document.getElementById('product-price-old');
            if (oldPriceEl) {
                oldPriceEl.textContent = '\u20B9' + regularPrice.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                oldPriceEl.style.display = hasDiscount ? '' : 'none';
            }

            const discountEl = document.getElementById('product-price-discount');
            if (discountEl) {
                discountEl.textContent = Math.round(discount) + '% OFF';
                discountEl.style.display = hasDiscount ? '' : 'none';
            }

            const varInput = document.getElementById('product_variation_id');
            if (varInput) varInput.value = varId;

            const wishlistVarInput = document.getElementById('wishlist_variation_id');
            if (wishlistVarInput) wishlistVarInput.value = varId;

            const selectedLabel = document.getElementById('selected-variation-label');
            if (selectedLabel) selectedLabel.textContent = 'Selected: ' + label;

            const productDescription = document.getElementById('product-description');
            if (productDescription) {
                const variationDescription = el.dataset.description || '';
                productDescription.innerHTML = variationDescription.trim()
                    ? variationDescription
                    : '<p class="text-muted mb-0">No description is available for this variation.</p>';
            }

            const shortDescription = document.getElementById('product-short-description');
            if (shortDescription) {
                const shortText = (el.dataset.description || '')
                    .replace(/<[^>]*>/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim();
                shortDescription.textContent = shortText
                    ? (shortText.length > 180 ? shortText.slice(0, 177) + '...' : shortText)
                    : 'Select an option to see the details of this piece.';
            }

            renderGalleryForVariation(varId);

            const descriptionImage = document.getElementById('description-product-image');
            const variationImages = readGalleryData(
                document.getElementById('productGallery') || document.createElement('div'),
                'variationImages',
                {}
            );
            if (descriptionImage) {
                const firstVariationImage = Array.isArray(variationImages[String(varId)])
                    ? variationImages[String(varId)][0]
                    : '';
                if (firstVariationImage) {
                    descriptionImage.src = firstVariationImage;
                    descriptionImage.hidden = false;
                } else {
                    descriptionImage.hidden = true;
                }
            }

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
            initProductZoom();
        });

        // ===== REVIEWS =====
        (function() {
            // Clicking the rating summary scrolls to the Reviews tab.
            const ratingSummary = document.getElementById('productRatingSummary');
            if (ratingSummary) {
                ratingSummary.addEventListener('click', function() {
                    const tabLink = document.querySelector('a[href="#tabReviews"]');
                    if (tabLink) {
                        const tab = bootstrap.Tab.getOrCreateInstance(tabLink);
                        tab.show();
                        const reviewsPane = document.getElementById('tabReviews');
                        if (reviewsPane) reviewsPane.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            }

            const reviewImageModal = document.getElementById('reviewImageModal');
            if (reviewImageModal) {
                reviewImageModal.addEventListener('show.bs.modal', function(event) {
                    const trigger = event.relatedTarget;
                    const image = document.getElementById('reviewImageModalImage');
                    if (trigger && trigger.dataset.reviewImageSrc && image) {
                        image.src = trigger.dataset.reviewImageSrc;
                        image.alt = 'Customer review photo';
                    }
                });
            }

            const form = document.getElementById('reviewForm');
            if (!form) return;

            const starContainer = document.getElementById('starRating');
            const ratingValue = document.getElementById('ratingValue');
            const ratingHint = document.getElementById('ratingHint');
            const comment = document.getElementById('reviewComment');
            const charCount = document.getElementById('commentCharCount');
            const submitBtn = document.getElementById('reviewSubmitBtn');
            const formTitle = document.getElementById('reviewFormTitle');
            const cancelEditBtn = document.getElementById('reviewCancelEditBtn');
            const imageInput = document.getElementById('reviewImageInput');
            const imageError = document.getElementById('reviewImageError');
            const existingImages = document.getElementById('reviewExistingImages');
            const selectedImages = document.getElementById('reviewSelectedImages');

            // Route URL templates; the __ID__ placeholder is swapped for the
            // real review id at run time when editing or deleting a review.
            const updateReviewUrlTemplate = '{{ route('products.reviews.update', [$product->id, '__ID__']) }}';
            const deleteReviewUrlTemplate = '{{ route('products.reviews.destroy', [$product->id, '__ID__']) }}';

            // null = create mode, a review id = edit that review.
            let editingReviewId = null;
            const storeUrl = form.action;

            const hints = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
            let previewObjectUrls = [];

            function clearSelectedImagePreviews() {
                previewObjectUrls.forEach(function(url) { URL.revokeObjectURL(url); });
                previewObjectUrls = [];
                if (selectedImages) selectedImages.innerHTML = '';
            }

            function currentReviewImageCount() {
                const existingCount = existingImages
                    ? existingImages.querySelectorAll('.review-image-preview').length
                    : 0;
                return existingCount + (imageInput ? imageInput.files.length : 0);
            }

            function updateReviewImageError() {
                if (!imageError) return;
                imageError.textContent = currentReviewImageCount() > 3
                    ? 'A review can contain a maximum of 3 images.'
                    : '';
            }

            function buildImagePreview(url, removable, onRemove) {
                const wrapper = document.createElement('div');
                wrapper.className = 'review-image-preview';
                const image = document.createElement('img');
                image.src = url;
                image.alt = 'Review photo preview';
                wrapper.appendChild(image);

                if (removable) {
                    const removeButton = document.createElement('button');
                    removeButton.type = 'button';
                    removeButton.className = 'review-image-remove';
                    removeButton.setAttribute('aria-label', 'Remove photo');
                    removeButton.textContent = '×';
                    removeButton.addEventListener('click', onRemove);
                    wrapper.appendChild(removeButton);
                }

                return wrapper;
            }

            function renderSelectedImagePreviews() {
                clearSelectedImagePreviews();
                if (!imageInput || !selectedImages) return;

                const files = Array.from(imageInput.files || []);
                const invalidType = files.some(function(file) {
                    return !['image/jpeg', 'image/png', 'image/webp'].includes(file.type);
                });
                const oversized = files.some(function(file) { return file.size > 2 * 1024 * 1024; });

                if (invalidType || oversized) {
                    imageInput.value = '';
                    if (imageError) {
                        imageError.textContent = invalidType
                            ? 'Only JPG, PNG, and WebP images are allowed.'
                            : 'Each review image must be 2 MB or smaller.';
                    }
                    return;
                }

                if (currentReviewImageCount() > 3) {
                    imageInput.value = '';
                    if (imageError) imageError.textContent = 'A review can contain a maximum of 3 images.';
                    return;
                }

                files.forEach(function(file) {
                    const objectUrl = URL.createObjectURL(file);
                    previewObjectUrls.push(objectUrl);
                    selectedImages.appendChild(buildImagePreview(objectUrl, false));
                });
                updateReviewImageError();
            }

            function paintStars(count) {
                if (!starContainer) return;
                starContainer.querySelectorAll('.rating-star').forEach(function(star) {
                    const val = parseInt(star.dataset.value, 10);
                    const isFilled = val <= count;
                    star.classList.toggle('filled', isFilled);
                    // `bi-star` is an outline-only glyph (hollow center), so
                    // recoloring it alone never fills the star. Swap to the
                    // solid `bi-star-fill` glyph for fully filled stars.
                    if (isFilled) {
                        star.classList.remove('bi-star');
                        star.classList.add('bi-star-fill');
                    } else {
                        star.classList.remove('bi-star-fill');
                        star.classList.add('bi-star');
                    }
                });
                starContainer.classList.add('active');
            }

            function resetReviewForm() {
                editingReviewId = null;
                form.action = storeUrl;
                const methodField = form.querySelector('input[name="_method"]');
                if (methodField) methodField.remove();
                form.classList.remove('editing');
                if (formTitle) formTitle.textContent = 'Write a Review';
                if (submitBtn) submitBtn.innerHTML = '<i class="bi bi-send me-1"></i>Submit Review';
                if (cancelEditBtn) cancelEditBtn.classList.add('d-none');
                form.reset();
                clearSelectedImagePreviews();
                if (imageInput) imageInput.value = '';
                if (imageError) imageError.textContent = '';
                if (existingImages) existingImages.innerHTML = '';
                form.querySelectorAll('input[name="remove_image_ids[]"]').forEach(function(input) { input.remove(); });
                ratingValue.value = '';
                if (ratingHint) ratingHint.textContent = '';
                if (charCount) charCount.textContent = '0';
                if (starContainer) {
                    starContainer.classList.remove('active');
                    starContainer.querySelectorAll('.rating-star').forEach(function(star) {
                        star.classList.remove('filled', 'selected');
                        star.classList.remove('bi-star-fill');
                        star.classList.add('bi-star');
                    });
                }
            }

            function startReviewEdit(reviewId, rating, commentText, imageData) {
                editingReviewId = reviewId;
                form.action = updateReviewUrlTemplate.replace('__ID__', reviewId);

                // Method spoofing so a plain POST (no-JS fallback) also hits the
                // update (PUT) route.
                let methodField = form.querySelector('input[name="_method"]');
                if (!methodField) {
                    methodField = document.createElement('input');
                    methodField.type = 'hidden';
                    methodField.name = '_method';
                    form.appendChild(methodField);
                }
                methodField.value = 'PUT';
                form.classList.add('editing');
                if (formTitle) formTitle.textContent = 'Edit Your Review';
                if (submitBtn) submitBtn.innerHTML = '<i class="bi bi-check2 me-1"></i>Update Review';
                if (cancelEditBtn) cancelEditBtn.classList.remove('d-none');

                if (starContainer && ratingValue) {
                    ratingValue.value = rating;
                    paintStars(rating);
                    starContainer.querySelectorAll('.rating-star').forEach(function(s) {
                        s.classList.toggle('selected', parseInt(s.dataset.value, 10) <= rating);
                    });
                    if (ratingHint) ratingHint.textContent = hints[rating] || '';
                }
                if (comment) {
                    comment.value = commentText;
                    if (charCount) charCount.textContent = commentText.length;
                }
                clearSelectedImagePreviews();
                if (imageInput) imageInput.value = '';
                if (imageError) imageError.textContent = '';
                if (existingImages) {
                    existingImages.innerHTML = '';
                    form.querySelectorAll('input[name="remove_image_ids[]"]').forEach(function(input) { input.remove(); });
                    let images = [];
                    try { images = JSON.parse(imageData || '[]'); } catch (error) { images = []; }
                    images.forEach(function(image) {
                        const wrapper = buildImagePreview(image.url, true, function() {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'remove_image_ids[]';
                            input.value = image.id;
                            form.appendChild(input);
                            wrapper.remove();
                            updateReviewImageError();
                        });
                        existingImages.appendChild(wrapper);
                    });
                }
                updateReviewImageError();
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            function applyReviewListData(data) {
                if (!data.success) return;
                const list = document.getElementById('reviewList');
                if (list) {
                    list.innerHTML = data.reviews_html;
                    bindReviewActions();
                }
                const tabCount = document.getElementById('reviewsTabCount');
                if (tabCount) tabCount.textContent = data.reviews_count;

                // Update the top rating summary.
                const summaryStars = document.querySelector('#productRatingSummary .pd-rating-stars');
                const summaryLabel = document.querySelector('#productRatingSummary .pd-rating-label');
                if (summaryStars) {
                    const avg = data.average_rating;
                    let html = '';
                    for (let i = 1; i <= 5; i++) {
                        if (avg >= i) html += '<i class="bi bi-star-fill"></i>';
                        else if (avg >= i - 0.5) html += '<i class="bi bi-star-half"></i>';
                        else html += '<i class="bi bi-star"></i>';
                    }
                    summaryStars.innerHTML = html;
                }
                if (summaryLabel) {
                    const countLabel = data.reviews_count + (data.reviews_count === 1 ? ' Customer Review' : ' Customer Reviews');
                    summaryLabel.innerHTML = '(' + data.average_rating.toFixed(1) + ' &#9733; &middot; ' + countLabel + ')';
                }

                // Update the in-tab summary.
                const tabSummaryScore = document.querySelector('.review-summary-score');
                const tabSummaryCount = document.querySelector('.review-summary-count');
                if (tabSummaryScore) tabSummaryScore.textContent = data.average_rating.toFixed(1);
                if (tabSummaryCount) {
                    tabSummaryCount.textContent = '(' + data.reviews_count + (data.reviews_count === 1 ? ' review' : ' reviews') + ')';
                }

                if (window.showToast) showToast(data.message);
            }

            if (starContainer && ratingValue) {
                starContainer.querySelectorAll('.rating-star').forEach(function(star) {
                    star.addEventListener('mouseenter', function() {
                        paintStars(parseInt(this.dataset.value, 10));
                    });
                    star.addEventListener('click', function() {
                        const val = parseInt(this.dataset.value, 10);
                        ratingValue.value = val;
                        paintStars(val);
                        starContainer.querySelectorAll('.rating-star').forEach(function(s) {
                            s.classList.toggle('selected', parseInt(s.dataset.value, 10) <= val);
                        });
                        if (ratingHint) ratingHint.textContent = hints[val] || '';
                    });
                });
                starContainer.addEventListener('mouseleave', function() {
                    const val = parseInt(ratingValue.value, 10) || 0;
                    if (val > 0) {
                        paintStars(val);
                    } else {
                        starContainer.querySelectorAll('.rating-star').forEach(function(star) {
                            star.classList.remove('filled');
                            star.classList.remove('bi-star-fill');
                            star.classList.add('bi-star');
                        });
                    }
                });
            }

            if (comment && charCount) {
                comment.addEventListener('input', function() {
                    charCount.textContent = comment.value.length;
                });
            }

            if (imageInput) {
                imageInput.addEventListener('change', renderSelectedImagePreviews);
            }

            function bindReviewActions() {
                document.querySelectorAll('.review-edit-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        startReviewEdit(
                            parseInt(this.dataset.reviewId, 10),
                            parseInt(this.dataset.rating, 10),
                            this.dataset.comment || '',
                            this.dataset.images || '[]'
                        );
                    });
                });

                document.querySelectorAll('.review-delete-form').forEach(function(deleteForm) {
                    deleteForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const reviewId = parseInt(this.dataset.reviewId, 10);
                        if (!window.confirm('Delete your review? This cannot be undone.')) return;

                        const btn = this.querySelector('button[type="submit"]');
                        if (btn) btn.disabled = true;

                        fetch(deleteReviewUrlTemplate.replace('__ID__', reviewId), {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        })
                        .then(function(res) {
                            if (!res.ok) throw new Error('Bad response');
                            return res.json();
                        })
                        .then(function(data) {
                            applyReviewListData(data);
                        })
                        .catch(function() {
                            if (window.showToast) showToast('Something went wrong. Please try again.');
                        })
                        .finally(function() {
                            if (btn) btn.disabled = false;
                        });
                    });
                });
            }

            if (cancelEditBtn) {
                cancelEditBtn.addEventListener('click', resetReviewForm);
            }

form.addEventListener('submit', function(e) {
                if (!ratingValue.value) {
                    e.preventDefault();
                    if (ratingHint) ratingHint.textContent = 'Please select a star rating.';
                    return;
                }
                if (!comment.value.trim()) {
                    e.preventDefault();
                    comment.focus();
                    return;
                }
                if (currentReviewImageCount() > 3) {
                    e.preventDefault();
                    updateReviewImageError();
                    return;
                }

                // Submit via AJAX so the result appears instantly without a
                // page reload. In edit mode the form posts to the update route
                // with a `_method` of PUT (which also covers the no-JS path).
                e.preventDefault();
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
                }

                const payload = new FormData(form);
                const methodField = form.querySelector('input[name="_method"]');
                const method = methodField && methodField.value ? methodField.value.toUpperCase() : 'POST';

                fetch(form.action, {
                    method: method,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: payload,
                })
                .then(function(res) {
                    return res.json().then(function(data) {
                        if (!res.ok) {
                            const messages = data.errors ? Object.values(data.errors).flat() : [];
                            throw new Error(messages[0] || 'Your review could not be saved.');
                        }
                        return data;
                    });
                })
                .then(function(data) {
                    if (data.success) {
                        applyReviewListData(data);
                        resetReviewForm();
                    }
                })
                .catch(function(error) {
                    if (window.showToast) showToast(error.message || 'Something went wrong. Please try again.');
                })
                .finally(function() {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="bi bi-send me-1"></i>' + (editingReviewId ? 'Update Review' : 'Submit Review');
                    }
                });
            });

            bindReviewActions();
        })();

        // Product share behaviour lives in /frontend/js/product-share.js
        // (loaded globally in the layout) so product cards on the shop and
        // category pages can share too.
    </script>
@endpush
