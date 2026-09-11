@extends('layouts.frontend')

@section('title', $category->name . ' | GEHNA')

@section('content')

    {{-- PAGE HEADER --}}
    <section class="py-4" style="background:linear-gradient(135deg,#013a3c 0%,#017075 60%,#000 100%); position:relative; overflow:hidden;">
        @if($category->image_path)
            <div style="position:absolute; inset:0; z-index:0;">
                <img src="{{ Storage::url($category->image_path) }}"
                    style="width:100%; height:100%; object-fit:cover; opacity:0.12;" alt="{{ $category->name }}">
            </div>
        @endif
        <div class="container" style="position:relative; z-index:1;">
            <h1 class="title-heading-h1">{{ $category->name }}</h1>
            @if($category->description)
                <p style="color:rgba(255,255,255,0.7); font-size:0.92rem; margin:0 0 10px; max-width:560px;">
                    {{ strip_tags($category->description) }}</p>
            @endif
        </div>
    </section>

    <section style="border: solid 1px #ccc; box-shadow: rgba(50, 50, 93, 0.25) 0px 2px 5px -1px, rgba(0, 0, 0, 0.3) 0px 1px 3px -1px; margin-bottom: 3px;">
        <div class="container">
            <nav aria-label="breadcrumb" class="py-2">
                <ol class="breadcrumb mb-0" style="background:none; padding:0;">
                    <li class="breadcrumb-item">
                        <a href="{{ route('home') }}" >Home</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('categories.index') }}">Categories</a>
                    </li>
                    <li class="breadcrumb-item active">{{ $category->name }}</li>
                </ol>
            </nav>
        </div>
    </section>

    {{-- PRODUCTS --}}
    <section style="background:#f8f9fa; padding:40px 0 70px;">
        <div class="container">

            {{-- Toolbar --}}
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <p>
                    Showing <strong style="color:#0D0D0D;">{{ $category->products->count() }}</strong>
                    product(s) in <strong style="color:#017075;">{{ $category->name }}</strong>
                </p>
                <a href="{{ route('categories.index') }}"
                    style="display:inline-flex; align-items:center; gap:6px; padding:8px 18px; border-radius:8px; border:1.5px solid #017075; color:#017075; font-size:0.85rem; font-weight:700; text-decoration:none; transition:all 0.2s;"
                    onmouseover="this.style.background='#017075'; this.style.color='#fff';"
                    onmouseout="this.style.background='transparent'; this.style.color='#017075';">
                    <i class="bi bi-grid-3x3-gap"></i> All Categories
                </a>
            </div>

            {{-- Product Grid --}}
            @if($category->products->isEmpty())
                <div
                    style="background:#fff; border-radius:16px; padding:80px 40px; text-align:center; border:1px solid #e9ecef;">
                    <i class="bi bi-box-seam" style="font-size:4rem; color:#dee2e6;"></i>
                    <h4 class="mt-3" style="color:#6C757D;">No products in this category</h4>
                    <p style="color:#aaa; margin-bottom:24px;">Check back soon or browse other categories.</p>
                    <a href="{{ route('products.index') }}"
                        style="display:inline-flex; align-items:center; gap:8px; padding:12px 28px; border-radius:8px; border:none; background:linear-gradient(95deg,#017075,#02AAB1); color:#fff; font-weight:700; text-decoration:none;">
                        <i class="bi bi-grid"></i> Browse All Products
                    </a>
                </div>
            @else
                <div class="row g-3">
                    @foreach($category->products as $product)
                        @php
                            $effPrice = $product->sale_price ?? $product->base_price;
                            $hasImages = $product->images->isNotEmpty();
                            $hasVars = $product->variations->isNotEmpty();
                            $discount = $product->sale_price
                                ? round((($product->base_price - $product->sale_price) / $product->base_price) * 100)
                                : 0;
                        @endphp
                        <div class="col-md-4 col-lg-3">
                            <div class="shop-card">

                                {{-- Image --}}
                                <a href="{{ route('product.show', $product->slug) }}" class="shop-card-img-wrap">
                                    @if($hasImages)
                                        @php
                                            $firstImage = $product->images->first();
                                            $imagePath = $firstImage->path ?? $firstImage->image_path ?? null;
                                        @endphp
                                        <img src="{{ $imagePath ? asset('storage/' . ltrim($imagePath, '/')) : asset('frontend/images/dumbbell.png') }}"
                                            alt="{{ $product->name }}" loading="lazy">
                                    @else
                                        <div class="shop-card-no-img">
                                            <i class="bi bi-image"></i>
                                            <span>No Image</span>
                                        </div>
                                    @endif

                                    @if($product->created_at->diffInDays(now()) <= 30)
                                        <span class="shop-badge shop-badge-new">NEW</span>
                                    @endif
                                    @if($discount > 0)
                                        <span class="shop-badge shop-badge-sale">-{{ $discount }}%</span>
                                    @endif
                                </a>

                                {{-- Info --}}
                                <div class="shop-card-body">
                                    <p class="shop-card-brand">
                                        {{ optional($product->brand)->name ?? $category->name }}
                                    </p>

                                    <a href="{{ route('product.show', $product->slug) }}" class="shop-card-title">
                                        {{ $product->name }}
                                    </a>

                                    <div class="shop-card-price-row">
                                        <span class="shop-card-price">Rs {{ number_format($effPrice, 2) }}</span>
                                        @if($product->sale_price)
                                            <span class="shop-card-price-old">Rs {{ number_format($product->base_price, 2) }}</span>
                                        @endif
                                    </div>

                                    <div class="shop-card-actions">
                                        {{-- Add to Cart --}}
                                        <form action="{{ route('cart.add') }}" method="POST" style="flex:1;">
                                            @csrf
                                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                                            <input type="hidden" name="quantity" value="1">
                                            <button type="submit" class="shop-btn-cart w-100"
                                                onclick="if({{ $hasVars ? 'true' : 'false' }}){ event.preventDefault(); window.location.href='{{ route('product.show', $product->slug) }}'; }">
                                                <i class="bi bi-cart-plus"></i>
                                                {{ $hasVars ? 'Options' : 'Add to Cart' }}
                                            </button>
                                        </form>

                                        {{-- Wishlist --}}
                                        <form action="{{ route('wishlist.toggle') }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                                            <button type="submit" class="shop-btn-icon" title="Wishlist">
                                                <i class="bi bi-heart"></i>
                                            </button>
                                        </form>

                                        {{-- Quick View --}}
                                        <button type="button" class="shop-btn-icon" title="Quick View"
                                            onclick="openQuickView({{ $product->id }}, '{{ addslashes($product->name) }}')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
    </section>

@endsection

@push('styles')
    <style>
        .shop-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
            transition: box-shadow 0.25s, transform 0.25s;
        }

        .shop-card:hover {
            box-shadow: 0 8px 32px rgba(1, 112, 117, 0.15);
            transform: translateY(-4px);
        }

        .shop-card-img-wrap {
            display: block;
            background: #f8f9fa;
            position: relative;
            aspect-ratio: 1 / 1;
            overflow: hidden;
            text-decoration: none;
        }

        .shop-card-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .shop-card:hover .shop-card-img-wrap img {
            transform: scale(1.05);
        }

        .shop-card-no-img {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #ccc;
            font-size: 0.78rem;
            gap: 6px;
        }

        .shop-card-no-img i {
            font-size: 2.5rem;
        }

        .shop-badge {
            position: absolute;
            top: 10px;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 4px;
            letter-spacing: 0.5px;
            color: #fff;
        }

        .shop-badge-new {
            left: 10px;
            background: #017075;
        }

        .shop-badge-sale {
            right: 10px;
            background: #dc3545;
        }

        .shop-card-body {
            padding: 14px 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .shop-card-brand {
            color: #017075;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            font-weight: 700;
            margin: 0 0 4px;
        }

        .shop-card-title {
            font-size: 0.88rem;
            font-weight: 700;
            color: #0D0D0D;
            text-decoration: none;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
            margin-bottom: 10px;
            flex: 1;
        }

        .shop-card-title:hover {
            color: #017075;
        }

        .shop-card-price-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
        }

        .shop-card-price {
            font-size: 1rem;
            font-weight: 800;
            color: #017075;
        }

        .shop-card-price-old {
            font-size: 0.8rem;
            color: #aaa;
            text-decoration: line-through;
        }

        .shop-card-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .shop-btn-cart {
            height: 40px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(95deg, #017075, #02AAB1);
            color: #fff;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 0 12px;
            transition: all 0.2s;
        }

        .shop-btn-cart:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(1, 112, 117, 0.4);
            color: #fff;
        }

        .shop-btn-icon {
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            border-radius: 8px;
            border: 1.5px solid #dee2e6;
            background: #fff;
            color: #6C757D;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .shop-btn-icon:hover {
            border-color: #017075;
            color: #017075;
            background: rgba(1, 112, 117, 0.06);
        }
    </style>
@endpush

@push('scripts')
    <script>
        function openQuickView(productId, productName) {
            const modal = document.getElementById('quickViewModal');
            const title = document.getElementById('quickViewTitle');
            const body = document.getElementById('quickViewBody');
            if (!modal) return;

            title.textContent = productName;
            body.innerHTML = '<div class="text-center py-5"><div class="spinner-border" style="color:#017075;" role="status"></div><p class="mt-3 text-muted">Loading...</p></div>';

            bootstrap.Modal.getOrCreateInstance(modal).show();

            fetch('/product/' + productId + '/quick-view', {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
            })
                .then(function (res) {
                    if (!res.ok) throw new Error('Failed');
                    return res.text();
                })
                .then(function (html) { body.innerHTML = html; })
                .catch(function () {
                    body.innerHTML = '<div class="text-center py-4"><p class="text-muted">Could not load. <a href="/product/' + productId + '" style="color:#017075;">View full page →</a></p></div>';
                });
        }
    </script>
@endpush