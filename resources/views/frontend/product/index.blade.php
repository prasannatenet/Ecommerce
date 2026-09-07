@extends('layouts.frontend')

@section('title', 'Shop All Products | GEHNA')

@section('content')

    {{-- PAGE HEADER --}}
    <section class="shop-page-hero" style="background:linear-gradient(135deg,#013a3c 0%,#017075 60%,#02AAB1 100%); padding:48px 50px 40px;">
        <div class="container-fluid px-4">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb mb-0" style="background:none; padding:0;">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}" style="color:rgba(255,255,255,0.7); text-decoration:none;">Home</a></li>
                    <li class="breadcrumb-item active" style="color:#fff;">Shop</li>
                </ol>
            </nav>
            <h1 style="color:#fff !important; font-size:2.2rem; font-weight:800; margin:0 0 6px;">
                Shop All <span style="color:#00e5ff;">Products</span>
            </h1>
            <p style="color:rgba(255,255,255,0.75); margin:0; font-size:0.95rem;">
                {{ $products->total() }} products found
            </p>
        </div>
    </section>

    {{-- PRODUCT LISTING --}}
    <section class="shop-listing-section" style="padding:40px 50px 60px; background:#f8f9fa !important;">
        <div class="container-fluid px-4">
            <div class="row g-4">

                {{-- ===== FILTER SIDEBAR ===== --}}
                <div class="col-lg-3">
                    <div class="shop-filter-panel" style="background:#fff; border-radius:12px; padding:24px; border:1px solid #e9ecef; position:sticky; top:20px;">
                        <h6 style="font-weight:800; font-size:0.85rem; text-transform:uppercase; letter-spacing:1.5px; color:#017075; margin:0 0 20px; padding-bottom:12px; border-bottom:2px solid #f0f0f0;">
                            <i class="bi bi-funnel me-2"></i>Filters
                        </h6>

                        <form method="GET" action="{{ route('products.index') }}" id="filterForm">

                            {{-- Category --}}
                            <div style="margin-bottom:24px;">
                                <p style="font-size:0.78rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#6C757D; margin:0 0 12px;">Category</p>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="category" value="" id="catAll"
                                           {{ !request('category') ? 'checked' : '' }}
                                           onchange="this.form.submit()"
                                           style="accent-color:#017075;">
                                    <label class="form-check-label" for="catAll" style="font-size:0.88rem; cursor:pointer;">All Categories</label>
                                </div>
                                @foreach($filterCategories ?? [] as $cat)
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="category"
                                               value="{{ $cat->id }}" id="cat{{ $cat->id }}"
                                               {{ request('category') == $cat->id ? 'checked' : '' }}
                                               onchange="this.form.submit()"
                                               style="accent-color:#017075;">
                                        <label class="form-check-label" for="cat{{ $cat->id }}" style="font-size:0.88rem; cursor:pointer;">{{ $cat->name }}</label>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Price Range (slider bounds = lowest/highest product prices) --}}
                            @php
                                $priceStep = max(1, (int) ceil(($maxProductPrice - $minProductPrice) / 100));
                            @endphp
                            <div style="margin-bottom:24px;">
                                <p style="font-size:0.78rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#6C757D; margin:0 0 12px;">Max Price</p>
                                <input type="range" name="max_price"
                                       min="{{ $minProductPrice }}" max="{{ $maxProductPrice }}"
                                       value="{{ request('max_price', $maxProductPrice) }}"
                                       step="{{ $priceStep }}"
                                       style="width:100%; accent-color:#017075;"
                                       oninput="document.getElementById('priceValue').textContent = '₹' + parseInt(this.value).toLocaleString('en-US')">
                                <div class="d-flex justify-content-between mt-1">
                                    <small style="color:#aaa;">₹{{ number_format($minProductPrice, 0, '.', ',') }}</small>
                                    <small style="font-weight:700; color:#017075;" id="priceValue">₹{{ number_format(request('max_price', $maxProductPrice), 0, '.', ',') }}</small>
                                </div>
                            </div>

                            <button type="submit"
                                    style="width:100%; padding:11px; border-radius:8px; border:none; background: linear-gradient(135deg, #a67c00 0%, #d4af37 50%, #e7c96b 100%) !important ; color:#fff !important; font-size:0.88rem; font-weight:700; cursor:pointer;">
                                <i class="bi bi-funnel me-1"></i> Apply Filters
                            </button>

                            @if(request()->hasAny(['category','max_price','sort','search']))
                                <a href="{{ route('products.index') }}"
                                   style="display:block; margin-top:10px; padding:10px; text-align:center; border-radius:8px; border:1.5px solid #dee2e6; color:#6C757D; font-size:0.85rem; font-weight:600; text-decoration:none;">
                                    <i class="bi bi-x-circle me-1"></i> Clear Filters
                                </a>
                            @endif
                        </form>
                    </div>
                </div>

                {{-- ===== PRODUCTS GRID ===== --}}
                <div class="col-lg-9">

                    {{-- Top bar: results count + sort --}}
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                        <p style="margin:0; color:#6C757D; font-size:0.9rem;">
                            Showing <strong style="color:#0D0D0D;">{{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }}</strong>
                            of <strong style="color:#0D0D0D;">{{ $products->total() }}</strong> results
                        </p>
                        <form method="GET" action="{{ route('products.index') }}" class="d-flex align-items-center gap-2">
                            @foreach(request()->except('sort') as $key => $val)
                                <input type="hidden" name="{{ $key }}" value="{{ $val }}">
                            @endforeach
                            <select name="sort" onchange="this.form.submit()"
                                    style="padding:8px 14px; border-radius:8px; border:1.5px solid #dee2e6; font-size:0.85rem; font-weight:600; color:#495057; background:#fff; cursor:pointer; outline:none;">
                                <option value="default"    {{ request('sort','default') == 'default'    ? 'selected' : '' }}>Sort: Default</option>
                                <option value="price-low"  {{ request('sort') == 'price-low'  ? 'selected' : '' }}>Price: Low → High</option>
                                <option value="price-high" {{ request('sort') == 'price-high' ? 'selected' : '' }}>Price: High → Low</option>
                                <option value="newest"     {{ request('sort') == 'newest'     ? 'selected' : '' }}>Newest First</option>
                                <option value="popularity" {{ request('sort') == 'popularity' ? 'selected' : '' }}>Most Popular</option>
                            </select>
                        </form>
                    </div>

                    {{-- Alerts --}}
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    {{-- Product grid (first page SSR, subsequent pages via infinite scroll) --}}
                    <div class="row g-3" id="shopGrid">
                        @if($products->isEmpty())
                            <div class="col-12 text-center py-5">
                                <i class="bi bi-search" style="font-size:4rem; color:#ccc;"></i>
                                <h4 class="mt-3" style="color:#6C757D;">No products found</h4>
                                <p style="color:#aaa;">Try adjusting your filters or
                                    <a href="{{ route('products.index') }}" style="color:#017075;">view all products</a>.
                                </p>
                            </div>
                        @else
                            @include('frontend.partials.product-cards', ['products' => $products])
                        @endif
                    </div>

                    {{-- Infinite scroll sentinel --}}
                    <div id="scrollSentinel" style="height:60px; display:flex; align-items:center; justify-content:center; margin-top:24px;">
                        <div id="scrollSpinner" style="display:none;">
                            <div class="spinner-border spinner-border-sm me-2" style="color:#017075;" role="status"></div>
                            <span style="color:#6C757D; font-size:0.9rem;">Loading more products…</span>
                        </div>
                        <p id="scrollEnd" style="display:none; color:#aaa; font-size:0.85rem; margin:0;">
                            <i class="bi bi-check-circle me-1" style="color:#017075;"></i> All products loaded
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </section>

@endsection

@push('styles')
<style>
    .shop-page-hero {
        background: linear-gradient(135deg, #32030d 0%, #5b0b24 58%, #7b173d 100%) !important;
    }

    .shop-page-hero h1 span {
        color: #f4c5cf !important;
    }

    .shop-listing-section {
        background: #fbf7f3 !important;
    }

    .shop-filter-panel {
        border-color: #eadfd9 !important;
        box-shadow: 0 10px 28px rgba(74, 22, 34, 0.06);
    }

    .shop-filter-panel h6 {
        color: #5b0b24 !important;
        border-bottom-color: #eadfd9 !important;
    }

    .shop-filter-panel .form-check-input {
        accent-color: #7b173d !important;
    }

    .shop-filter-panel input[type="range"] {
        accent-color: #7b173d !important;
    }

    .shop-filter-panel button {
        background: linear-gradient(95deg, #4f071b, #8b1e48) !important;
    }

    .shop-filter-panel a {
        border-color: #dfcfd0 !important;
        color: #6b5960 !important;
    }

    #shopGrid .shop-card {
        border-color: #eadfd9;
        box-shadow: 0 5px 18px rgba(74, 22, 34, 0.04);
    }

    #shopGrid .shop-card:hover {
        box-shadow: 0 12px 30px rgba(91, 11, 36, 0.14);
    }

    #shopGrid .shop-card-img-wrap {
        background: #F7F7F7;
    }

    #shopGrid .shop-card-brand,
    #shopGrid .shop-card-price,
    #shopGrid .shop-card-title:hover {
        color: #7b173d;
    }

    #shopGrid .shop-btn-cart {
        background: linear-gradient(95deg, #4f071b, #8b1e48);
    }

    #shopGrid .shop-btn-cart:hover {
        box-shadow: 0 4px 14px rgba(91, 11, 36, 0.35);
    }

    #shopGrid .shop-btn-icon:hover {
        border-color: #8b1e48;
        color: #7b173d;
        background: #fdf1f4;
    }

    #shopGrid .shop-badge-new {
        background: #7b173d;
    }

    .shop-listing-section select {
        border-color: #dfcfd0 !important;
        color: #5b0b24 !important;
    }

    .shop-listing-section .text-muted,
    .shop-listing-section p[style*="#6C757D"] {
        color: #76686d !important;
    }

    .shop-listing-section strong {
        color: #35121c !important;
    }

    #scrollSpinner .spinner-border {
        color: #7b173d !important;
    }

    /* ===== SHOP PAGE PRODUCT CARDS ===== */
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
        box-shadow: 0 8px 32px rgba(1,112,117,0.15);
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
        object-fit: contain;
        padding: 16px;
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
    .shop-card-no-img i { font-size: 2.5rem; }
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
    .shop-badge-new  { left: 10px;  background: #017075; }
    .shop-badge-sale { right: 10px; background: #dc3545; }

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
    .shop-card-title:hover { color: #017075; }
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
        transition: all 0.2s;
        padding: 0 12px;
    }
    .shop-btn-cart:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 14px rgba(1,112,117,0.4);
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
        background: rgba(1,112,117,0.06);
    }
    .shop-btn-icon.is-wishlisted {
        border-color: #dc3545;
        color: #dc3545;
        background: rgba(220,53,69,0.10);
    }
    .shop-btn-icon.is-wishlisted:hover {
        border-color: #c82333;
        color: #c82333;
        background: rgba(220,53,69,0.16);
    }
</style>
@endpush

@push('scripts')
<script>
    // ===== INFINITE SCROLL =====
    (function () {
        const grid     = document.getElementById('shopGrid');
        const sentinel = document.getElementById('scrollSentinel');
        const spinner  = document.getElementById('scrollSpinner');
        const endMsg   = document.getElementById('scrollEnd');

        // Build base URL from current filters (everything except page)
        const baseParams = new URLSearchParams(window.location.search);
        baseParams.delete('page');

        let nextPage  = {{ $products->currentPage() < $products->lastPage() ? $products->currentPage() + 1 : 'null' }};
        let loading   = false;

        function loadMore() {
            if (!nextPage || loading) return;
            loading = true;
            spinner.style.display = 'flex';

            const params = new URLSearchParams(baseParams);
            params.set('page', nextPage);

            fetch('{{ route('products.index') }}?' + params.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                // Append new cards
                const tmp = document.createElement('div');
                tmp.innerHTML = data.html;
                while (tmp.firstChild) grid.appendChild(tmp.firstChild);

                nextPage = (data.current_page < data.last_page) ? data.current_page + 1 : null;
                loading  = false;
                spinner.style.display = 'none';

                if (!nextPage) {
                    endMsg.style.display = 'block';
                    observer.disconnect();
                }
            })
            .catch(function () {
                loading = false;
                spinner.style.display = 'none';
            });
        }

        // Only observe if there are more pages
        @if($products->lastPage() > 1)
        const observer = new IntersectionObserver(function (entries) {
            if (entries[0].isIntersecting) loadMore();
        }, { rootMargin: '200px' });

        observer.observe(sentinel);
        @else
        if ({{ $products->total() }} > 0) {
            endMsg.style.display = 'block';
        }
        @endif
    })();

    // ===== QUICK VIEW =====
    function openQuickView(productId, productName) {
        const modal = document.getElementById('quickViewModal');
        const title = document.getElementById('quickViewTitle');
        const body  = document.getElementById('quickViewBody');
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
