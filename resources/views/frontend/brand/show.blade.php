@extends('layouts.frontend')

@section('title', 'GEHNA ')

@section('content')

    {{-- Brand Banner --}}
    <div class="page-header-teal" style="min-height:180px;">
        <div class="container">
            <div style="display:flex; align-items:center; gap:24px; flex-wrap:wrap;">
                <div
                    style="width:80px; height:80px; border-radius:12px; background:rgba(255,255,255,0.12); display:flex; align-items:center; justify-content:center; flex-shrink:0; overflow:hidden; border:2px solid rgba(0,229,255,0.3);">
                    @php
                        $brandLogoPath = is_string($brand->logo_path) ? trim($brand->logo_path, " \t\n\r\0\x0B/") : null;
                        if (is_string($brandLogoPath) && str_starts_with($brandLogoPath, 'public/')) {
                            $brandLogoPath = substr($brandLogoPath, 7);
                        }
                    @endphp
                    @if (filled($brandLogoPath))
                        <img src="{{ asset('storage/' . $brandLogoPath) }}" alt="{{ $brand->name }}"
                            style="max-width:100%; max-height:100%; object-fit:contain;">
                    @else
                        <span
                            style="font-size:1.1rem; font-weight:900; color:#fff; text-transform:uppercase; text-align:center; padding:8px;">{{ $brand->name }}</span>
                    @endif
                </div>
                <div>
                    <p
                        style="color:#00e5ff; font-size:0.75rem; letter-spacing:3px; text-transform:uppercase; font-weight:600; margin-bottom:4px;">
                        Brand Store</p>
                    <h1 class="page-header-title" style="margin-bottom:4px;">{{ $brand->name }}</h1>
                    <p style="color:rgba(255,255,255,0.65); font-size:0.9rem; margin:0;">Premium equipment from
                        {{ $brand->name }}</p>
                </div>
            </div>
            <nav aria-label="breadcrumb" class="mt-3">
                <ol class="breadcrumb-gehna mb-0">
                    <li><a href="{{ route('home') }}">Home</a></li>
                    <li><a href="{{ route('brands.index') }}">Brands</a></li>
                </ol>
            </nav>
        </div>
    </div>



    <section style="background:#f5f5f5; padding: 50px 0; min-height: 55vh;">
        <div class="container">

            <div
                style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:28px;">
                <p style="color:#6C757D; font-size:0.9rem; margin:0;">Showing {{ $brand->products->count() }} product(s)
                    from <strong style="color:#017075;">{{ $brand->name }}</strong></p>
                <a href="{{ route('brands.index') }}"
                    style="color:#017075; font-size:0.85rem; font-weight:600; text-decoration:none; padding:7px 16px; border:1.5px solid #017075; border-radius:6px; transition:all 0.2s;"
                    onmouseover="this.style.background='#017075'; this.style.color='#fff';"
                    onmouseout="this.style.background='transparent'; this.style.color='#017075';">
                    <i class="bi bi-arrow-left me-1"></i> All Brands
                </a>
            </div>

            @forelse($brand->products as $product)
                @if ($loop->first)
                    <div class="row g-4">
                @endif
                <div class="col-sm-6 col-lg-3">
                    <div class="product-card">
                        <div class="product-img-wrap">

                            <a href="{{ route('product.show', $product->slug) }}">
                                @php
                                    $imagePath = is_string($product->primary_image)
                                        ? trim($product->primary_image)
                                        : null;
                                    if (blank($imagePath) && $product->images->isNotEmpty()) {
                                        $imagePath =
                                            $product->images->first()->image_path ??
                                            ($product->images->first()->path ?? null);
                                    }
                                    $imagePath = is_string($imagePath) ? trim($imagePath, " \t\n\r\0\x0B/") : null;
                                    if (is_string($imagePath) && str_starts_with($imagePath, 'public/')) {
                                        $imagePath = substr($imagePath, 7);
                                    }
                                @endphp
                                @if (filled($imagePath))
                                    <img src="{{ asset('storage/' . $imagePath) }}" alt="{{ $product->name }}"
                                        class="product-img">
                                @else
                                    <div
                                        style="width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#aaa;">
                                        <i class="bi bi-image" style="font-size:2rem; margin-bottom:6px;"></i>
                                        <span style="font-size:0.8rem;">No Image</span>
                                    </div>
                                @endif
                            </a>
                            <div class="product-actions-overlay">
                                <form action="{{ route('cart.add') }}" method="POST" id="quick-cart-{{ $product->id }}">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                    <input type="hidden" name="quantity" value="1">
                                </form>
                                <button type="button"
                                    onclick="document.getElementById('quick-cart-{{ $product->id }}').submit()"
                                    class="btn-cart-gehna" title="Add to Cart">
                                    <i class="bi bi-cart-plus"></i>
                                </button>
                                <a href="{{ route('product.show', $product->slug) }}" class="btn-cart-gehna"
                                    title="View Product">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </div>
                        </div>
                        <div class="product-info">
                            @if ($product->category)
                                <div class="product-brand">{{ $product->category->name }}</div>
                            @endif
                            <a href="{{ route('product.show', $product->slug) }}"
                                class="product-name">{{ $product->name }}</a>
                            <div class="product-price">
                                @if ($product->sale_price)
                                    <span class="price-sale">Rs {{ number_format($product->sale_price, 2) }}</span>
                                    <span class="price-original">Rs {{ number_format($product->base_price, 2) }}</span>
                                @else
                                    <span class="price-sale">Rs {{ number_format($product->base_price, 2) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @if ($loop->last)
        </div>
        @endif
    @empty
        <div
            style="background:#fff; border-radius:12px; padding:70px 40px; text-align:center; box-shadow:0 4px 20px rgba(0,0,0,0.06);">
            <i class="bi bi-tags" style="font-size:3rem; color:#dee2e6; display:block; margin-bottom:16px;"></i>
            <h3 style="font-size:1.2rem; font-weight:800; color:#0D0D0D; margin-bottom:8px; text-transform:uppercase;">No
                Products Found</h3>
            <p style="color:#6C757D; font-size:0.9rem; margin-bottom:24px;">There are currently no products available from
                this brand.</p>
            <a href="{{ route('products.index') }}" class="btn-gehna btn-teal-gehna">
                <i class="bi bi-grid me-2"></i> Browse All Products
            </a>
        </div>
        @endforelse

        </div>
    </section>

@endsection
