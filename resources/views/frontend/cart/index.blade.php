@extends('layouts.frontend')

@section('title', 'Shopping Cart | GEHNA')

@section('content')

{{-- PAGE HEADER --}}
<div class="hero-dark-wrapper" style="padding-bottom: 0;">
    <section class="page-header page-header-teal" style="padding-bottom:30px;">
        <div class="container-fluid px-4 position-relative" style="z-index:2">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-gehna">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Shop</a></li>
                    <li class="breadcrumb-item active">Shopping Cart</li>
                </ol>
            </nav>
            <h1 class="text-white fw-bold" style="font-size:2.5rem;">
                Shopping <span style="color:#00e5ff;">Cart</span>
            </h1>
        </div>
    </section>
</div>

{{-- CART CONTENT --}}
<section class="py-5" id="cartPageWrap">
    <div class="container-fluid px-4">

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if($cartItems->isEmpty())
            {{-- Empty Cart --}}
            <div class="text-center py-5">
                <i class="bi bi-bag-x" style="font-size:5rem; color:#ccc;"></i>
                <h3 class="mt-4 fw-bold">Your Cart is Empty</h3>
                <p class="text-muted mb-4">Looks like you haven't added any equipment yet. Start building your home gym!</p>
                <a href="{{ route('products.index') }}" class="btn-gehna btn-teal-gehna">
                    <i class="bi bi-bag-plus"></i> Start Shopping
                </a>
            </div>
        @else
            <div class="row g-5 cart-page-wrap" >
                @php
                    $freeItemsByCartId = $freeItems->keyBy(fn ($free) => $free['cart_item']->id);
                @endphp

                {{-- Cart Items --}}
                <div class="col-lg-8">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Cart Items ({{ $cartItems->count() }})</h5>
                        <a href="{{ route('products.index') }}" class="text-decoration-none" style="color:var(--primary); font-weight:600;">
                            <i class="bi bi-arrow-left me-1"></i> Continue Shopping
                        </a>
                    </div>  

                    <div class="table-responsive">
                        <table class="cart-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Name</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($cartItems as $item)
                                    <tr>
                                        {{-- Product Image --}}
                                        <td>
                                            <a href="{{ route('product.show', $item->product->slug) }}">
                                                @if($item->product->images->count() > 0)
                                                    @php
                                                        $firstImage = $item->product->images->first();
                                                        $imagePath = $firstImage->path ?? $firstImage->image_path ?? null;
                                                    @endphp
                                                    <img src="{{ $imagePath ? asset('storage/' . ltrim($imagePath, '/')) : asset('frontend/images/dumbbell.png') }}"
                                                         alt="{{ $item->product->name }}" class="cart-item-img">
                                                @else
                                                    <img src="{{ asset('frontend/images/dumbbell.png') }}"
                                                         alt="{{ $item->product->name }}" class="cart-item-img" style="opacity:0.5;">
                                                @endif
                                            </a>
                                        </td>

                                        {{-- Name + Variant --}}
                                        <td>
                                            <a href="{{ route('product.show', $item->product->slug) }}" class="cart-item-name d-block text-dark text-decoration-none">
                                                {{ $item->product->name }}
                                            </a>
                                            <span id="cart-free-badge-{{ $item->id }}" class="badge {{ (int) ($freeItemsByCartId[$item->id]['free_quantity'] ?? 0) > 0 ? '' : 'd-none' }}" style="background:#198754; color:#fff; font-size:0.72rem; font-weight:700; margin-top:4px;">
                                                <i class="bi bi-gift me-1"></i>{{ $freeItemsByCartId[$item->id]['free_quantity'] ?? 0 }} FREE with Buy {{ $appliedCoupon['buy_quantity'] ?? 1 }} Get {{ $appliedCoupon['get_quantity'] ?? 1 }}
                                            </span>
                                            @if($item->variation)
                                                @php
                                                    $variationAttrs = $item->variation->attributes ?? [];
                                                    if (is_string($variationAttrs)) {
                                                        $decodedAttrs = json_decode($variationAttrs, true);
                                                        $variationAttrs = is_array($decodedAttrs) ? $decodedAttrs : [];
                                                    }
                                                    $attrs = collect($variationAttrs)->map(fn($val, $key) => ucfirst($key) . ': ' . $val)->implode(' | ');
                                                @endphp
                                                <div class="cart-item-variant">{{ $attrs ?: ('Variation #' . $item->variation->id) }}</div>
                                            @endif
                                        </td>

                                        {{-- Price --}}
                                        <td>₹{{ number_format($item->price, 0) }}</td>

                                        {{-- Quantity --}}
                                        <td>
                                            <form action="{{ route('cart.update', $item->id) }}" method="POST" class="d-inline-flex align-items-center gap-2 js-cart-qty-form">
                                                @csrf
                                                @method('PATCH')
                                                <div class="qty-control" style="width:auto;">
                                                    <button type="button" data-cart-qty-step="-1" aria-label="Decrease quantity">−</button>
                                                    <input type="number" name="quantity" min="1" max="99" value="{{ $item->quantity }}"
                                                           data-cart-qty-input data-cart-item-id="{{ $item->id }}" data-qty-current="{{ $item->quantity }}"
                                                           style="width:50px;" aria-label="Quantity">
                                                    <button type="button" data-cart-qty-step="1" aria-label="Increase quantity">+</button>
                                                </div>
                                                <span class="spinner-border spinner-border-sm text-primary d-none js-cart-qty-spinner" role="status" aria-hidden="true"></span>
                                            </form>
                                        </td>

                                        {{-- Line Total --}}
                                        <td class="fw-bold" id="cart-line-total-{{ $item->id }}">
                                            @php
                                                $freeQty = !empty($freeItemsByCartId[$item->id]) ? (int) $freeItemsByCartId[$item->id]['free_quantity'] : 0;
                                                $chargedQty = max(0, (int) $item->quantity - $freeQty);
                                            @endphp
                                            @if($freeQty > 0)
                                                <s class="text-muted" style="font-weight:400;">₹{{ number_format($item->quantity * $item->price, 0) }}</s>
                                                <span style="color:#198754;">₹{{ number_format($chargedQty * $item->price, 0) }}</span>
                                                <div style="font-size:0.72rem; color:#198754; font-weight:700;">{{ $freeQty }} FREE</div>
                                            @else
                                                ₹{{ number_format($item->quantity * $item->price, 0) }}
                                            @endif
                                        </td>

                                        {{-- Remove --}}
                                        <td>
                                            <form id="cart-remove-form-{{ $item->id }}" action="{{ route('cart.destroy', $item->id) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="button"
                                                    class="cart-remove-btn js-cart-remove-choice"
                                                    data-remove-form="cart-remove-form-{{ $item->id }}"
                                                    data-wishlist-form="cart-wishlist-form-{{ $item->id }}"
                                                    data-product-name="{{ $item->product->name }}"
                                                    title="Remove"
                                                >
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                            <form id="cart-wishlist-form-{{ $item->id }}" action="{{ route('cart.move-to-wishlist', $item->id) }}" method="POST" class="d-none">
                                                @csrf
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Order Summary --}}
                <div class="col-lg-4">
                    <div class="cart-summary" style="position: sticky; top: 100px;">
                        <h5>Order Summary</h5>

                        {{-- Coupon --}}
                        <div class="mb-4">
                            <form action="{{ route('checkout.coupon.apply') }}" method="POST" style="display:flex; gap:8px;">
                                @csrf
                                <input type="text" name="coupon_code" value="{{ old('coupon_code', $appliedCoupon['code'] ?? '') }}"
                                       placeholder="Coupon code"
                                       style="flex:1; border:1.5px solid #DEE2E6; border-radius:8px; padding:9px 14px; font-size:0.88rem; background:#f9f9f9; outline:none; text-transform:uppercase;"
                                       onfocus="this.style.borderColor='#017075'; this.style.background='#fff';"
                                       onblur="this.style.borderColor='#DEE2E6'; this.style.background='#f9f9f9';">
                                <button type="submit"
                                        style="padding:9px 16px; border-radius:8px; border:1.5px solid #017075; background:#fff; color:#017075; font-size:0.82rem; font-weight:700; cursor:pointer; white-space:nowrap; transition:all 0.2s;"
                                        onmouseover="this.style.background='#017075'; this.style.color='#fff';"
                                        onmouseout="this.style.background='#fff'; this.style.color='#017075';">
                                    Apply
                                </button>
                            </form>

                            @if($appliedCoupon)
                                <div style="margin-top:10px; display:flex; align-items:center; justify-content:space-between;">
                                    <span style="color:#198754; font-size:0.82rem; font-weight:600; text-transform:uppercase;">
                                        <i class="bi bi-tag-fill me-1"></i> {{ $appliedCoupon['code'] }} applied
                                    </span>
                                    <form action="{{ route('checkout.coupon.remove') }}" method="POST" style="margin:0;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" style="background:none; border:none; color:#dc3545; font-size:0.82rem; font-weight:600; cursor:pointer; padding:0;">Remove</button>
                                    </form>
                                </div>
                            @endif

                            @if(!empty($availableCoupons) && $availableCoupons->count())
                                <div style="margin-top:12px;">
                                    <button type="button" id="toggle-coupons-btn"
                                            style="padding:8px 12px; border-radius:8px; border:1.5px solid #017075; background:#fff; color:#017075; font-size:0.8rem; font-weight:700; cursor:pointer; transition:all 0.2s;"
                                            onmouseover="this.style.background='#017075'; this.style.color='#fff';"
                                            onmouseout="this.style.background='#fff'; this.style.color='#017075';">
                                        View Coupons
                                    </button>

                                    <div id="available-coupons-wrap" style="display:none; margin-top:10px;">
                                        <div style="font-size:0.78rem; font-weight:700; letter-spacing:1px; color:#6C757D; text-transform:uppercase; margin-bottom:8px;">
                                            Available Coupons
                                        </div>
                                        <div style="display:flex; flex-direction:column; gap:8px;">
                                            @foreach($availableCoupons as $couponItem)
                                                <div style="border:1px dashed #cfd8dc; border-radius:8px; padding:10px 12px; background:#fcfdfd;">
                                                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                                                        <div>
                                                            <div style="font-size:0.84rem; font-weight:800; color:#0D0D0D;">
                                                                {{ strtoupper($couponItem['code']) }}
                                                            </div>
                                                            <div style="font-size:0.79rem; color:#495057;">
                                                                @if($couponItem['type'] === 'percent')
                                                                    {{ rtrim(rtrim(number_format($couponItem['amount'], 2), '0'), '.') }}% OFF
                                                                @elseif($couponItem['type'] === 'fixed')
                                                                    Rs {{ number_format($couponItem['amount'], 2) }} OFF
                                                                @elseif($couponItem['type'] === 'buy_get')
                                                                    Buy {{ $couponItem['buy_quantity'] }}, Get {{ $couponItem['get_quantity'] }} Free
                                                                @else
                                                                    {{ number_format($couponItem['reward_coins']) }} Gehna Coins
                                                                @endif
                                                                @if($couponItem['min_order_amount'] > 0)
                                                                    | Min order Rs {{ number_format($couponItem['min_order_amount'], 2) }}
                                                                @endif
                                                            </div>
                                                            <div style="font-size:0.74rem; color:#6C757D; margin-top:2px;">
                                                                {{ $couponItem['validity_text'] }}
                                                            </div>
                                                            @if(!$couponItem['is_applicable'] && !empty($couponItem['ineligible_reason']))
                                                                <div style="font-size:0.74rem; color:#dc3545; margin-top:3px;">
                                                                    {{ $couponItem['ineligible_reason'] }}
                                                                </div>
                                                            @endif
                                                        </div>

                                                        <form action="{{ route('checkout.coupon.apply') }}" method="POST" style="margin:0;">
                                                            @csrf
                                                            <input type="hidden" name="coupon_code" value="{{ $couponItem['code'] }}">
                                                            <button type="submit"
                                                                    {{ $couponItem['is_applicable'] ? '' : 'disabled' }}
                                                                    style="padding:6px 10px; border-radius:7px; border:1px solid #017075; background:{{ $couponItem['is_applicable'] ? '#fff' : '#f1f3f5' }}; color:{{ $couponItem['is_applicable'] ? '#017075' : '#adb5bd' }}; font-size:0.75rem; font-weight:700; cursor:{{ $couponItem['is_applicable'] ? 'pointer' : 'not-allowed' }}; white-space:nowrap;">
                                                                Apply
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="cart-summary-row">
                            <span>Subtotal</span>
                            <span id="cart-summary-subtotal">₹{{ number_format($subtotal, 0) }}</span>
                        </div>
                        <div class="cart-summary-row {{ $discount > 0 ? '' : 'd-none' }}" id="cart-summary-discount-row" style="color:var(--success);">
                            <span>Discount</span>
                            <span id="cart-summary-discount">- ₹{{ number_format($discount, 0) }}</span>
                        </div>
                        <div class="cart-summary-row {{ ($appliedCoupon && $appliedCoupon['type'] === 'buy_get' && $freeItems->isNotEmpty()) ? '' : 'd-none' }}" id="cart-summary-freegifts-row" style="flex-wrap:wrap; gap:2px 8px; font-size:0.8rem; color:var(--success);">
                            <span>FREE gifts (Buy {{ $appliedCoupon['buy_quantity'] ?? 1 }} Get {{ $appliedCoupon['get_quantity'] ?? 1 }})</span>
                            <span style="text-align:right;" id="cart-summary-freegifts">
                                @foreach($freeItems as $free)
                                    {{ optional($free['cart_item']->product)->name }} × {{ $free['free_quantity'] }}@if(!$loop->last)<br>@endif
                                @endforeach
                            </span>
                        </div>
                        <div class="cart-summary-row">
                            <span>Shipping</span>
                            <span id="cart-summary-shipping">{{ $shippingCharge <= 0 ? 'FREE' : '₹' . number_format($shippingCharge, 0) }}</span>
                        </div>
                        <div class="cart-summary-row total">
                            <span>Total</span>
                            <span id="cart-summary-total">₹{{ number_format($grandTotal, 0) }}</span>
                        </div>

                        <a href="{{ route('checkout.index') }}" class="btn-gehna btn-teal-gehna w-100 mt-4 justify-content-center" style="border-radius:8px;">
                            <i class="bi bi-lock me-2"></i> Proceed to Checkout
                        </a>

                        <div class="text-center mt-3">
                            <small class="text-muted"><i class="bi bi-shield-lock me-1"></i> Secure SSL Encrypted Checkout</small>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-around text-center">
                            <div>
                                <i class="bi bi-truck d-block fs-5 text-primary mb-1"></i>
                                <small class="text-muted" style="font-size:0.75rem;">Free Shipping<br>Above ₹5,000</small>
                            </div>
                            <div>
                                <i class="bi bi-arrow-counterclockwise d-block fs-5 text-warning mb-1"></i>
                                <small class="text-muted" style="font-size:0.75rem;">30-Day<br>Returns</small>
                            </div>
                            <div>
                                <i class="bi bi-shield-check d-block fs-5 text-success mb-1"></i>
                                <small class="text-muted" style="font-size:0.75rem;">Genuine<br>Products</small>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        @endif
    </div>
</section>

<div class="modal fade" id="cartActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content cart-action-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-cart-x me-2 text-danger"></i>Choose Action
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="mb-2 text-muted">What would you like to do with:</p>
                <div class="cart-action-product-name" id="cartActionProductName"></div>
            </div>
            <div class="modal-footer border-0 pt-0 d-flex gap-2">
                <button type="button" class="btn btn-light flex-fill" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-danger flex-fill" id="cartActionRemoveBtn">
                    <i class="bi bi-trash3 me-1"></i>Remove
                </button>
                <button type="button" class="btn btn-primary-gehna flex-fill" id="cartActionWishlistBtn">
                    <i class="bi bi-heart me-1"></i>Move to Wishlist
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    .cart-action-modal {
        border: 0;
        border-radius: 16px;
        box-shadow: 0 22px 60px rgba(0, 0, 0, 0.25);
        overflow: hidden;
    }

    .cart-action-modal .modal-header {
        background: linear-gradient(135deg, #f6f9fc 0%, #eef4ff 100%);
        padding: 1rem 1.1rem 0.7rem;
    }

    .cart-action-modal .modal-body {
        padding: 0.8rem 1.1rem 0.9rem;
    }

    .cart-action-modal .modal-footer {
        padding: 0 1.1rem 1.1rem;
    }

    .cart-action-product-name {
        background: #f8fafc;
        border: 1px solid #e9eef5;
        border-radius: 10px;
        padding: 0.7rem 0.8rem;
        font-weight: 600;
        color: #212529;
        word-break: break-word;
    }
</style>
@endpush

@push('scripts')
<script>
    const cartActionModalEl = document.getElementById('cartActionModal');
    const cartActionProductName = document.getElementById('cartActionProductName');
    const cartActionRemoveBtn = document.getElementById('cartActionRemoveBtn');
    const cartActionWishlistBtn = document.getElementById('cartActionWishlistBtn');
    const cartActionModal = cartActionModalEl ? new bootstrap.Modal(cartActionModalEl) : null;

    let activeRemoveForm = null;
    let activeWishlistForm = null;

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('.js-cart-remove-choice');
        if (!trigger || !cartActionModal) return;

        const removeFormId = trigger.getAttribute('data-remove-form');
        const wishlistFormId = trigger.getAttribute('data-wishlist-form');
        const productName = trigger.getAttribute('data-product-name') || 'this item';

        activeRemoveForm = document.getElementById(removeFormId);
        activeWishlistForm = document.getElementById(wishlistFormId);

        if (!activeRemoveForm || !activeWishlistForm) return;

        cartActionProductName.textContent = productName;
        cartActionModal.show();
    });

    if (cartActionWishlistBtn) {
        cartActionWishlistBtn.addEventListener('click', function () {
            if (!activeWishlistForm) return;
            activeWishlistForm.submit();
        });
    }

    if (cartActionRemoveBtn) {
        cartActionRemoveBtn.addEventListener('click', function () {
            if (!activeRemoveForm) return;
            activeRemoveForm.submit();
        });
    }

    const toggleCouponsBtn = document.getElementById('toggle-coupons-btn');
    const couponsWrap = document.getElementById('available-coupons-wrap');
    if (toggleCouponsBtn && couponsWrap) {
        toggleCouponsBtn.addEventListener('click', function () {
            const isHidden = couponsWrap.style.display === 'none';
            couponsWrap.style.display = isHidden ? 'block' : 'none';
            toggleCouponsBtn.textContent = isHidden ? 'Hide Coupons' : 'View Coupons';
        });
    }

    // ===== AJAX quantity updates (no page reload) =====
    function formatCartMoney(value) {
        return Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 0 });
    }

    function cartToast(message) {
        if (typeof window.showToast === 'function') {
            window.showToast(message);
        }
    }

    function clampCartQty(input) {
        const max = parseInt(input.max || '99', 10) || 99;
        let value = parseInt(input.value || '1', 10);
        if (isNaN(value) || value < 1) value = 1;
        if (value > max) value = max;
        input.value = value;
        return value;
    }

    const cartQtyQueues = new Map();

    function queueCartQtyUpdate(form, qty) {
        const input = form.querySelector('[data-cart-qty-input]');

        // Skip useless requests when the value matches what the server already has.
        if (input && parseInt(input.dataset.qtyCurrent || '0', 10) === qty) {
            return;
        }

        const pending = cartQtyQueues.get(form) || Promise.resolve();
        const next = pending.then(() => sendCartQtyUpdate(form, qty)).catch(() => {});
        cartQtyQueues.set(form, next);
    }

    async function sendCartQtyUpdate(form, qty) {
        const spinner = form.querySelector('.js-cart-qty-spinner');
        const input = form.querySelector('[data-cart-qty-input]');
        const token = document.querySelector('meta[name="csrf-token"]');

        if (spinner) spinner.classList.remove('d-none');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    quantity: qty,
                    _method: 'PATCH',
                }),
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Could not update quantity.');
            }

            applyCartSummary(data);
            cartToast(data.message || 'Cart quantity updated.');
        } catch (error) {
            // Revert to the last known server value; no page reload needed.
            if (input) input.value = input.dataset.qtyCurrent || 1;
            cartToast(error.message || 'Could not update quantity. Please try again.');
        } finally {
            if (spinner) spinner.classList.add('d-none');
        }
    }
    // ===== cart qty summary applier =====
    function applyCartSummary(data) {
        (data.items || []).forEach(function (item) {
            const input = document.querySelector('[data-cart-qty-input][data-cart-item-id="' + item.id + '"]');
            if (input) {
                input.value = item.quantity;
                input.dataset.qtyCurrent = item.quantity;
            }

            const lineTotal = document.getElementById('cart-line-total-' + item.id);
            if (lineTotal) {
                if (item.free_quantity > 0) {
                    lineTotal.innerHTML =
                        '<s class="text-muted" style="font-weight:400;">₹' + formatCartMoney(item.line_gross_total) + '</s> ' +
                        '<span style="color:#198754;">₹' + formatCartMoney(item.line_total) + '</span>' +
                        '<div style="font-size:0.72rem; color:#198754; font-weight:700;">' + item.free_quantity + ' FREE</div>';
                } else {
                    lineTotal.textContent = '₹' + formatCartMoney(item.line_total);
                }
            }

            const badge = document.getElementById('cart-free-badge-' + item.id);
            if (badge) {
                if (item.free_quantity > 0 && data.coupon && data.coupon.type === 'buy_get') {
                    badge.classList.remove('d-none');
                    badge.innerHTML = '<i class="bi bi-gift me-1"></i>' + item.free_quantity +
                        ' FREE with Buy ' + (data.coupon.buy_quantity || 1) +
                        ' Get ' + (data.coupon.get_quantity || 1);
                } else {
                    badge.classList.add('d-none');
                }
            }
        });

        const setSummary = function (id, text) {
            const el = document.getElementById(id);
            if (el) el.textContent = text;
        };

        setSummary('cart-summary-subtotal', '₹' + formatCartMoney(data.subtotal));

        const discountRow = document.getElementById('cart-summary-discount-row');
        if (discountRow) {
            discountRow.classList.toggle('d-none', !(data.discount > 0));
            setSummary('cart-summary-discount', '- ₹' + formatCartMoney(data.discount));
        }

        const freeRow = document.getElementById('cart-summary-freegifts-row');
        const hasFreeItems = Array.isArray(data.free_items) && data.free_items.length > 0;
        if (freeRow) {
            freeRow.classList.toggle('d-none', !hasFreeItems);
            setSummary('cart-summary-freegifts', hasFreeItems
                ? data.free_items.map(function (free) { return free.name + ' × ' + free.free_quantity; }).join('<br>')
                : '');
        }

        setSummary('cart-summary-shipping', data.shipping_charge > 0 ? '₹' + formatCartMoney(data.shipping_charge) : 'FREE');
        setSummary('cart-summary-total', '₹' + formatCartMoney(data.grand_total));

        // Header badge(s) — keep in sync like the wishlist AJAX handlers do.
        document.querySelectorAll('.site-cart-link .site-cart-count, .nav-cart-count, .cart-count').forEach(function (badge) {
            badge.textContent = data.cart_count;
        });
    }
    // ===== cart qty bindings =====
    document.querySelectorAll('.js-cart-qty-form').forEach(function (form) {
        const input = form.querySelector('[data-cart-qty-input]');
        let debounceTimer = null;

        form.querySelectorAll('[data-cart-qty-step]').forEach(function (button) {
            button.addEventListener('click', function () {
                const qtyInput = form.querySelector('[data-cart-qty-input]');
                const delta = parseInt(button.dataset.cartQtyStep, 10) || 0;
                qtyInput.value = (parseInt(qtyInput.value || '1', 10) || 1) + delta;
                const qty = clampCartQty(qtyInput);
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => queueCartQtyUpdate(form, qty), 150);
            });
        });

        if (input) {
            input.addEventListener('change', function () {
                const qty = clampCartQty(input);
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => queueCartQtyUpdate(form, qty), 250);
            });
        }

        // Progressive enhancement fallback: never do a full page reload.
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (input) queueCartQtyUpdate(form, clampCartQty(input));
        });
    });
</script>
@endpush
