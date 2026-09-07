<style>
    .frontend-site .site-header,
    .frontend-site .site-nav-row {
        background: #ffffff !important;
        border-color: #e2e8f0 !important;
    }

    .frontend-site .site-brand,
    .frontend-site .site-category-links a,
    .frontend-site .site-icon-group a {
        color: #0f172a !important;
    }

    .frontend-site .site-brand span {
        color: #d4af37 !important;
    }

    .frontend-site .site-category-links a:hover,
    .frontend-site .site-icon-group a:hover,
    .frontend-site .site-category-links a.active {
        color: #a67c00 !important;
    }

    .frontend-site .site-category-links a.active::after {
        background: #d4af37 !important;
    }

    .frontend-site .site-location-btn,
    .frontend-site .site-search-box input {
        border-color: #e2e8f0 !important;
        background: #ffffff !important;
        color: #0f172a !important;
    }

    .frontend-site .site-location-btn > i:first-child,
    .frontend-site .site-search-box i {
        color: #a67c00 !important;
    }

    .frontend-site .site-new-badge {
        /* background: #064e3b !important; */
        color: #ffffff !important;
    }

    .frontend-site .site-cart-count {
        background: #d4af37 !important;
        color: #0f172a !important;
    }

    .frontend-site .site-account-menu {
        min-width: 250px;
        margin-top: 12px !important;
        padding: 6px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 18px 44px rgba(15, 23, 42, 0.14);
    }

    .frontend-site .site-account-coins-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 10px;
        background: linear-gradient(95deg, rgba(212, 175, 55, 0.16), rgba(166, 124, 0, 0.05));
    }

    .frontend-site .site-account-coins-icon {
        width: 38px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #d4af37;
        color: #fff;
        font-size: 1.15rem;
        flex-shrink: 0;
    }

    .frontend-site .site-account-coins-label {
        flex: 1;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #64748b;
    }

    .frontend-site .site-account-coins-value {
        font-size: 1.15rem;
        font-weight: 800;
        color: #a67c00;
    }

    .frontend-site .site-account-menu .dropdown-item {
        padding: 9px 12px;
        border-radius: 8px;
        font-size: 0.88rem;
        font-weight: 600;
        color: #0f172a;
    }

    .frontend-site .site-account-menu .dropdown-item:hover,
    .frontend-site .site-account-menu .dropdown-item:focus {
        background: #faf3df;
        color: #a67c00;
    }

    .frontend-site .site-account-menu form {
        margin: 0;
    }

    .frontend-site .site-account-menu button.dropdown-item {
        width: 100%;
        border: 0;
        background: none;
        text-align: left;
        cursor: pointer;
    }

    /* ===== DELIVERY LOCATION (PINCODE) DROPDOWN ===== */
    .frontend-site .site-location-wrap {
        position: relative;
        flex: 0 0 auto;
    }

    .frontend-site .site-location-btn > i:last-child {
        transition: transform 0.2s ease;
    }

    .frontend-site .site-location-wrap.site-location-open .site-location-btn > i:last-child {
        transform: rotate(180deg);
    }

    .frontend-site .site-location-menu {
        position: absolute;
        top: calc(100% + 10px);
        left: 0;
        width: 320px;
        max-width: calc(100vw - 32px);
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 18px 44px rgba(15, 23, 42, 0.16);
        padding: 14px;
        z-index: 1400;
        display: none;
        text-align: left;
    }

    .frontend-site .site-location-menu.site-location-open {
        display: block;
    }

    .frontend-site .site-location-menu::before {
        content: '';
        position: absolute;
        top: -10px;
        left: 0;
        right: 0;
        height: 10px;
        background: transparent;
    }

    .frontend-site .site-location-menu-head h6 {
        margin: 0 0 2px;
        font-weight: 800;
        font-size: 0.95rem;
        color: #0f172a;
    }

    .frontend-site .site-location-menu-head p {
        margin: 0 0 10px;
        font-size: 0.75rem;
        color: #64748b;
    }

    .frontend-site .site-location-choice {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        margin-bottom: 8px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        text-align: left;
        cursor: pointer;
        transition: border-color 0.15s ease, background 0.15s ease;
    }

    .frontend-site .site-location-choice:hover {
        border-color: #d4af37;
        background: #fdf9ec;
    }

    .frontend-site .site-location-choice-icon {
        width: 38px;
        height: 38px;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(212, 175, 55, 0.14);
        color: #a67c00;
        font-size: 1.05rem;
    }

    .frontend-site .site-location-choice-text {
        flex: 1;
        min-width: 0;
    }

    .frontend-site .site-location-choice-text strong {
        display: block;
        font-size: 0.85rem;
        font-weight: 700;
        color: #0f172a;
    }

    .frontend-site .site-location-choice-text small {
        display: block;
        font-size: 0.72rem;
        color: #64748b;
    }

    .frontend-site .site-location-choice > i:last-child {
        color: #94a3b8;
        font-size: 0.8rem;
    }

    .frontend-site .site-location-manual .form-label {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.6px;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 6px;
    }

    .frontend-site .site-location-manual-row {
        display: flex;
        gap: 8px;
    }

    .frontend-site .site-location-manual-row .form-control {
        border-radius: 8px;
        border-color: #e2e8f0;
        font-weight: 700;
        letter-spacing: 2px;
    }

    .frontend-site .site-location-manual-row .form-control:focus {
        border-color: #d4af37;
        box-shadow: 0 0 0 0.2rem rgba(212, 175, 55, 0.15);
    }

    .frontend-site .site-location-apply-btn {
        border: 0;
        border-radius: 8px;
        padding: 0 16px;
        font-weight: 700;
        font-size: 0.82rem;
        cursor: pointer;
        background: #0f172a;
        color: #ffffff;
        white-space: nowrap;
    }

    .frontend-site .site-location-apply-btn:hover {
        background: #1e293b;
    }

    .frontend-site .site-location-apply-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .frontend-site .site-location-hint {
        margin: 6px 0 0;
        font-size: 0.72rem;
        color: #dc2626;
    }

    .frontend-site .site-location-back-btn {
        margin-top: 10px;
        border: 0;
        background: none;
        padding: 0;
        font-size: 0.75rem;
        font-weight: 700;
        color: #a67c00;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .frontend-site .site-location-back-btn:hover {
        color: #0f172a;
        text-decoration: underline;
    }

    .frontend-site .site-location-status {
        text-align: center;
        padding: 6px 2px 2px;
    }

    .frontend-site .site-location-status-icon {
        width: 42px;
        height: 42px;
        margin: 0 auto 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(212, 175, 55, 0.14);
        color: #a67c00;
        font-size: 1.15rem;
    }

    .frontend-site .site-location-status-icon.is-loading i {
        animation: siteLocationSpin 1s linear infinite;
    }

    .frontend-site .site-location-status-icon.is-error {
        background: #fee2e2;
        color: #dc2626;
    }

    .frontend-site .site-location-status-icon.is-success {
        background: #dcfce7;
        color: #16a34a;
    }

    @keyframes siteLocationSpin {
        to { transform: rotate(360deg); }
    }

    .frontend-site .site-location-status-msg {
        margin: 0 0 10px;
        font-size: 0.82rem;
        color: #334155;
    }

    .frontend-site .site-location-status-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .frontend-site .site-location-confirm-btn {
        border: 0;
        border-radius: 8px;
        padding: 9px 14px;
        font-weight: 700;
        font-size: 0.82rem;
        cursor: pointer;
        background: #0f172a;
        color: #ffffff;
    }

    .frontend-site .site-location-confirm-btn:hover {
        background: #1e293b;
    }

    .frontend-site .site-location-secondary-btn {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px 14px;
        font-weight: 700;
        font-size: 0.8rem;
        cursor: pointer;
        background: #ffffff;
        color: #0f172a;
    }

    .frontend-site .site-location-secondary-btn:hover {
        border-color: #d4af37;
        color: #a67c00;
    }

    .frontend-site .site-location-current {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 12px;
        padding: 9px 10px;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px dashed #e2e8f0;
        font-size: 0.76rem;
        color: #475569;
    }

    .frontend-site .site-location-current i {
        color: #16a34a;
        flex-shrink: 0;
    }

    .frontend-site .site-location-current span {
        flex: 1;
        min-width: 0;
    }

    .frontend-site .site-location-current strong {
        color: #0f172a;
    }

    .frontend-site .site-location-remove {
        border: 0;
        background: none;
        color: #94a3b8;
        cursor: pointer;
        padding: 2px;
        font-size: 0.75rem;
        line-height: 1;
        flex-shrink: 0;
    }

    .frontend-site .site-location-remove:hover {
        color: #dc2626;
    }
</style>

@php
    $deliveryPincode = session('delivery_pincode');
    $deliveryArea = trim((string) collect([session('delivery_city'), session('delivery_state')])
        ->filter()->unique()->implode(', '));
    $headerWishlistCount = Auth::check()
        ? (int) \App\Models\Wishlist::where('user_id', Auth::id())->count()
        : count((array) session('guest_wishlist', []));
    $headerCartCount = Auth::check()
        ? (int) \App\Models\Cart::where('user_id', Auth::id())->sum('quantity')
        : collect((array) session('guest_cart', []))->sum(fn ($e) => (int) ($e['quantity'] ?? 1));
    $headerGehnaCoins = Auth::check() ? (int) (Auth::user()->gehna_coins ?? 0) : 0;
@endphp

<header class="site-header">
    <div class="site-header-inner container-fluid px-4 px-xl-5">
        <div class="site-brand-wrap">
            <a class="site-brand" href="{{ route('home') }}">
                GEHNA
            </a>
        </div>

        <div class="site-search-wrap">
            <div class="site-location-wrap" id="siteLocationWrap">
                <button class="site-location-btn" type="button" id="siteLocationBtn"
                    aria-haspopup="true" aria-expanded="false" aria-controls="siteLocationMenu">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>
                        <strong id="siteLocationTitle">{{ $deliveryPincode ? 'Deliver to ' . $deliveryPincode : 'Where to Deliver?' }}</strong>
                        <small id="siteLocationSubtitle">{{ $deliveryPincode ? ($deliveryArea !== '' ? $deliveryArea : 'Update Delivery Pincode') : 'Update Delivery Pincode' }}</small>
                    </span>
                    <i class="bi bi-chevron-down"></i>
                </button>

                {{-- Delivery location dropdown: auto-detect or manual entry --}}
                <div class="site-location-menu" id="siteLocationMenu" aria-hidden="true">
                    <div class="site-location-menu-head">
                        <h6>Select Delivery Location</h6>
                        <p>Auto-detect your pincode or enter it manually.</p>
                    </div>

                    <div class="site-location-choices" id="siteLocationChoices">
                        <button type="button" class="site-location-choice" data-location-choice="auto">
                            <span class="site-location-choice-icon"><i class="bi bi-crosshair2"></i></span>
                            <span class="site-location-choice-text">
                                <strong>Detect automatically</strong>
                                <small>Use my current location (GPS)</small>
                            </span>
                            <i class="bi bi-chevron-right"></i>
                        </button>
                        <button type="button" class="site-location-choice" data-location-choice="manual">
                            <span class="site-location-choice-icon"><i class="bi bi-pencil-square"></i></span>
                            <span class="site-location-choice-text">
                                <strong>Enter pincode manually</strong>
                                <small>Type your 6-digit delivery pincode</small>
                            </span>
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>

                    <div class="site-location-manual d-none" id="siteLocationManual">
                        <form id="siteLocationManualForm" autocomplete="off" novalidate>
                            <label class="form-label" for="siteLocationPincodeInput">Delivery Pincode</label>
                            <div class="site-location-manual-row">
                                <input type="text" id="siteLocationPincodeInput" class="form-control"
                                    inputmode="numeric" maxlength="6" placeholder="e.g. 110001"
                                    aria-label="Delivery pincode">
                                <button type="submit" class="site-location-apply-btn" id="siteLocationManualSubmit">Apply</button>
                            </div>
                            <p class="site-location-hint d-none" id="siteLocationPincodeError">Please enter a valid 6-digit pincode.</p>
                            <button type="button" class="site-location-back-btn" data-location-back>
                                <i class="bi bi-arrow-left"></i> Back to options
                            </button>
                        </form>
                    </div>

                    <div class="site-location-status d-none" id="siteLocationStatus">
                        <div class="site-location-status-icon" id="siteLocationStatusIcon"><i class="bi bi-geo-alt"></i></div>
                        <p class="site-location-status-msg" id="siteLocationStatusMsg"></p>
                        <div class="site-location-status-actions" id="siteLocationStatusActions"></div>
                    </div>

                    <div class="site-location-current {{ empty($deliveryPincode) ? 'd-none' : '' }}" id="siteLocationCurrent">
                        <i class="bi bi-check-circle-fill"></i>
                        <span id="siteLocationCurrentText">Currently delivering to <strong>{{ $deliveryPincode }}</strong>{{ $deliveryArea !== '' ? ' – ' . $deliveryArea : '' }}</span>
                        <button type="button" class="site-location-remove" id="siteLocationRemove" title="Remove pincode">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="site-search-box" id="siteSearchBox">
                <form action="{{ route('products.index') }}" method="GET" autocomplete="off" role="search" id="siteSearchForm">
                    <input type="text" name="search" id="siteSearchInput"
                        placeholder="Search products, categories..." aria-label="Search"
                        autocomplete="off" value="{{ request('search') }}">
                    <button type="submit" class="site-search-btn" aria-label="Search"><i class="bi bi-search"></i></button>
                </form>
                <div class="site-search-results" id="siteSearchResults"></div>
            </div>
        </div>

        <div class="site-header-actions">
            <div class="site-icon-group">
                <!-- <a href="#" aria-label="Stores">
                    <i class="bi bi-shop"></i>
                    <span>STORES</span>
                </a> -->
                @if (Auth::check())
                    <div class="dropdown site-account-dropdown">
                        <a href="#" class="site-account-trigger" role="button" id="siteAccountDropdown"
                           data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account">
                            <i class="bi bi-person"></i>
                            <span>ACCOUNT</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end site-account-menu" aria-labelledby="siteAccountDropdown">
                            <div class="site-account-coins-row">
                                <span class="site-account-coins-icon"><i class="bi bi-coin"></i></span>
                                <span class="site-account-coins-label">Gehna Coins</span>
                                <span class="site-account-coins-value">{{ number_format($headerGehnaCoins) }}</span>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="{{ route('account.index') }}">
                                <i class="bi bi-person-gear me-2"></i>My Account
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" aria-label="Account">
                        <i class="bi bi-person"></i>
                        <span>ACCOUNT</span>
                    </a>
                @endif
                <a href="#wishlistSidebar"
                    aria-label="Wishlist"
                    data-bs-toggle="offcanvas">
                    <i class="bi bi-heart"></i>
                    <span class="site-cart-count nav-wishlist-count {{ ($headerWishlistCount ?? 0) > 0 ? '' : 'd-none' }}">{{ $headerWishlistCount ?? 0 }}</span>
                    <span>WISHLIST</span>
                </a>
                <a href="{{ route('cart.index') }}" aria-label="Cart" class="site-cart-link">
                    <i class="bi bi-cart3"></i>
                    <span class="site-cart-count">{{ $headerCartCount ?? 0 }}</span>
                    <span>CART</span>
                </a>
            </div>
        </div>
    </div>

    <div class="site-nav-row container-fluid px-4 px-xl-5">
        @php
            $navCategories = \App\Models\Category::orderBy('position')
                ->withCount('products')
                ->get();
        @endphp

        <div class="site-category-links">
            <div class="site-cat-dropdown" id="siteCatDropdown">
                <a href="{{ route('categories.index') }}" class="site-cat-dropdown-trigger" id="siteCatTrigger"
                    aria-haspopup="true" aria-expanded="false">
                    Shop by Category <i class="bi bi-chevron-down"></i>
                </a>
                <div class="site-cat-dropdown-menu" id="siteCatMenu">
                    @forelse($navCategories as $cat)
                        <a class="site-cat-link" href="{{ route('category.show', $cat->slug ?? $cat->id) }}">
                            <span class="site-cat-link-name">{{ $cat->name }}</span>
                            @if(($cat->products_count ?? 0) > 0)
                                <span class="site-cat-link-count">{{ $cat->products_count }}</span>
                            @endif
                        </a>
                    @empty
                        <span class="site-cat-empty">No categories yet.</span>
                    @endforelse
                    <a class="site-cat-all" href="{{ route('categories.index') }}">
                        <i class="bi bi-grid-3x3-gap-fill"></i> All Categories
                        <i class="bi bi-arrow-right ms-auto"></i>
                    </a>
                </div>
            </div>
            
            <a href="{{ route('products.index') }}">Gifts for Him</a>
            <a href="{{ route('products.index') }}">Gifts for Her</a>
            <a href="{{ route('products.index') }}">GEHNA Gift Card</a>
            <a href="{{ route('products.index') }}">Gift Store <i class="bi bi-chevron-down"></i></a>
            <a href="{{ route('products.index') }}">Exclusive Collections <i class="bi bi-chevron-down"></i></a>
            <a href="{{ route('products.index') }}">Smart Purchase Plan <span class="site-new-badge">New</span></a>
            <a href="{{ route('products.index') }}">More at GEHNA <i class="bi bi-chevron-down"></i></a>
        </div>
    </div>
</header>
