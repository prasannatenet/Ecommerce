@extends('layouts.frontend')

@section('title', 'Checkout | GEHNA')

@section('content')

{{-- Page Header --}}
<div class="page-header-teal" style="color: white">
    <div class="container-fluid">
        <h1 class="page-header-title">Checkout</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb breadcrumb-gehna">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('cart.index') }}">Cart</a></li>
                <li class="breadcrumb-item active" aria-current="page">Checkout</li>
            </ol>
        </nav>
    </div>
</div>

<section style="background:#f5f5f5; padding: 50px 50px;">
    <div class="container-fluid">
        <div class="row g-4">

            {{-- Left: Forms --}}
            <div class="col-lg-8">

                @if(session('error'))
                    <div class="alert alert-danger py-2 mb-4" style="font-size:0.875rem; border-radius:8px;">
                        <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
                    </div>
                @endif
                @if(session('success'))
                    <div class="alert alert-success py-2 mb-4" style="font-size:0.875rem; border-radius:8px;">
                        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                    </div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger py-2 mb-4" style="font-size:0.875rem; border-radius:8px;">
                        @foreach($errors->all() as $error)
                            <div><i class="bi bi-dot"></i> {{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                @if(!$codProvider && !$razorpayProvider)
                    <div class="alert alert-warning" style="border-radius:8px;">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        No payment method is active. Please contact admin.
                    </div>
                @else
                    <form id="checkout-form" action="{{ route('checkout.place') }}" method="POST">
                        @csrf

                        {{-- Billing Address --}}
                        <div style="background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.06); overflow:hidden; margin-bottom:20px;">
                            <div style="padding:18px 28px; border-bottom:1px solid #f0f0f0; background:linear-gradient(135deg, #022C2B 0%, #017075 100%);">
                                <h3 style="color:#fff; font-size:1rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; margin:0;">
                                    <i class="bi bi-geo-alt me-2" style="color:#00e5ff;"></i> Billing Address
                                </h3>
                            </div>
                            <div style="padding:28px;">
                                <div class="row g-3">
                                    @php
                                    $inputStyle = "border:1.5px solid #DEE2E6; border-radius:8px; padding:11px 16px; font-size:0.93rem; background:#f9f9f9; width:100%; outline:none; transition:border-color 0.3s;";
                                    $focusAttr = "onfocus=\"this.style.borderColor='#017075'; this.style.background='#fff';\" onblur=\"this.style.borderColor='#DEE2E6'; this.style.background='#f9f9f9';\"";
                                    @endphp
                                    <div class="col-md-6">
                                        <label style="font-size:0.73rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;" class="form-label">Full Name</label>
                                        <input type="text" name="billing_name" value="{{ old('billing_name', $user->name) }}" placeholder="Full Name" style="{{ $inputStyle }}" {!! $focusAttr !!} required>
                                    </div>
                                    <div class="col-md-6">
                                        <label style="font-size:0.73rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;" class="form-label">Email</label>
                                        <input type="email" name="billing_email" value="{{ old('billing_email', $user->email) }}" placeholder="Email" style="{{ $inputStyle }}" {!! $focusAttr !!} required>
                                    </div>
                                    <div class="col-md-6">
                                        <label style="font-size:0.73rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;" class="form-label">Phone</label>
                                        <input type="text" name="billing_phone" value="{{ old('billing_phone') }}" placeholder="Phone" style="{{ $inputStyle }}" {!! $focusAttr !!} required>
                                    </div>
                                    <div class="col-md-6">
                                        <label style="font-size:0.73rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;" class="form-label">Address Line</label>
                                        <input type="text" name="billing_line1" value="{{ old('billing_line1') }}" placeholder="Address Line" style="{{ $inputStyle }}" {!! $focusAttr !!} required>
                                    </div>
                                    <div class="col-md-6">
                                        <label style="font-size:0.73rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;" class="form-label">City</label>
                                        <input type="text" name="billing_city" value="{{ old('billing_city') }}" placeholder="City" style="{{ $inputStyle }}" {!! $focusAttr !!} required>
                                    </div>
                                    <div class="col-md-6">
                                        <label style="font-size:0.73rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;" class="form-label">State</label>
                                        <input type="text" name="billing_state" value="{{ old('billing_state') }}" placeholder="State" style="{{ $inputStyle }}" {!! $focusAttr !!} required>
                                    </div>
                                    <div class="col-md-6">
                                        <label style="font-size:0.73rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;" class="form-label">ZIP Code</label>
                                        <input type="text" name="billing_zip" value="{{ old('billing_zip') }}" placeholder="ZIP Code" style="{{ $inputStyle }}" {!! $focusAttr !!} required>
                                    </div>
                                    <div class="col-md-6">
                                        <label style="font-size:0.73rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;" class="form-label">Country</label>
                                        <input type="text" name="billing_country" value="{{ old('billing_country', 'India') }}" placeholder="Country" style="{{ $inputStyle }}" {!! $focusAttr !!} required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Shipping Address --}}
                        <div style="background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.06); overflow:hidden; margin-bottom:20px;">
                            <div style="padding:18px 28px; border-bottom:1px solid #f0f0f0; background:linear-gradient(135deg, #022C2B 0%, #017075 100%); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                                <h3 style="color:#fff; font-size:1rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; margin:0;">
                                    <i class="bi bi-truck me-2" style="color:#00e5ff;"></i> Shipping Address
                                </h3>
                                <label style="display:flex; align-items:center; gap:8px; color:rgba(255,255,255,0.85); font-size:0.85rem; cursor:pointer; margin:0;">
                                    <input id="shipping_same_as_billing" type="checkbox" name="shipping_same_as_billing" value="1" checked
                                           style="accent-color:#00e5ff; width:15px; height:15px;">
                                    Same as billing
                                </label>
                            </div>
                            <div id="shipping-fields" style="padding:28px; display:none;">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label style="font-size:0.73rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;" class="form-label">Full Name</label>
                                        <input type="text" name="shipping_name" value="{{ old('shipping_name') }}" placeholder="Full Name" style="{{ $inputStyle }}" {!! $focusAttr !!}>
                                    </div>
                                    <div class="col-md-6">
                                        <label style="font-size:0.73rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;" class="form-label">Phone</label>
                                        <input type="text" name="shipping_phone" value="{{ old('shipping_phone') }}" placeholder="Phone" style="{{ $inputStyle }}" {!! $focusAttr !!}>
                                    </div>
                                    <div class="col-md-6">
                                        <label style="font-size:0.73rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;" class="form-label">Address Line</label>
                                        <input type="text" name="shipping_line1" value="{{ old('shipping_line1') }}" placeholder="Address Line" style="{{ $inputStyle }}" {!! $focusAttr !!}>
                                    </div>
                                    <div class="col-md-6">
                                        <label style="font-size:0.73rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;" class="form-label">City</label>
                                        <input type="text" name="shipping_city" value="{{ old('shipping_city') }}" placeholder="City" style="{{ $inputStyle }}" {!! $focusAttr !!}>
                                    </div>
                                    <div class="col-md-6">
                                        <label style="font-size:0.73rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;" class="form-label">State</label>
                                        <input type="text" name="shipping_state" value="{{ old('shipping_state') }}" placeholder="State" style="{{ $inputStyle }}" {!! $focusAttr !!}>
                                    </div>
                                    <div class="col-md-6">
                                        <label style="font-size:0.73rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;" class="form-label">ZIP Code</label>
                                        <input type="text" name="shipping_zip" value="{{ old('shipping_zip') }}" placeholder="ZIP Code" style="{{ $inputStyle }}" {!! $focusAttr !!}>
                                    </div>
                                    <div class="col-md-12">
                                        <label style="font-size:0.73rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;" class="form-label">Country</label>
                                        <input type="text" name="shipping_country" value="{{ old('shipping_country') }}" placeholder="Country" style="{{ $inputStyle }}" {!! $focusAttr !!}>
                                    </div>
                                </div>
                            </div>
                            <div id="shipping-same-notice" style="padding:18px 28px; color:#6C757D; font-size:0.9rem;">
                                <i class="bi bi-check-circle-fill text-success me-2"></i> Same as billing address
                            </div>
                        </div>

                        {{-- Payment Method --}}
                        <div style="background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.06); overflow:hidden; margin-bottom:20px;">
                            <div style="padding:18px 28px; border-bottom:1px solid #f0f0f0; background:linear-gradient(135deg, #022C2B 0%, #017075 100%);">
                                <h3 style="color:#fff; font-size:1rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; margin:0;">
                                    <i class="bi bi-credit-card me-2" style="color:#00e5ff;"></i> Payment Method
                                </h3>
                            </div>
                            <div style="padding:24px 28px; display:flex; flex-direction:column; gap:12px;">
                                @if($codProvider)
                                    <label style="display:flex; align-items:center; gap:14px; padding:16px 20px; border-radius:8px; border:1.5px solid #DEE2E6; cursor:pointer; transition:all 0.2s;"
                                           onmouseover="this.style.borderColor='#017075';" onmouseout="if(!this.querySelector('input').checked)this.style.borderColor='#DEE2E6';">
                                        <input type="radio" name="payment_method" value="cod"
                                               {{ old('payment_method', $codProvider ? 'cod' : 'razorpay') === 'cod' ? 'checked' : '' }}
                                               style="accent-color:#017075; width:18px; height:18px;">
                                        <div>
                                            <div style="font-weight:700; color:#0D0D0D; font-size:0.95rem;">Cash on Delivery</div>
                                            <div style="color:#6C757D; font-size:0.8rem;">Pay when your order arrives.</div>
                                        </div>
                                        <i class="bi bi-cash-coin ms-auto" style="font-size:1.4rem; color:#017075;"></i>
                                    </label>
                                @endif

                                @if($razorpayProvider)
                                    <label style="display:flex; align-items:center; gap:14px; padding:16px 20px; border-radius:8px; border:1.5px solid #DEE2E6; cursor:pointer; transition:all 0.2s;"
                                           onmouseover="this.style.borderColor='#017075';" onmouseout="if(!this.querySelector('input').checked)this.style.borderColor='#DEE2E6';">
                                        <input type="radio" name="payment_method" value="razorpay"
                                               {{ old('payment_method', $codProvider ? 'cod' : 'razorpay') === 'razorpay' ? 'checked' : '' }}
                                               style="accent-color:#017075; width:18px; height:18px;">
                                        <div>
                                            <div style="font-weight:700; color:#0D0D0D; font-size:0.95rem;">Razorpay</div>
                                            <div style="color:#6C757D; font-size:0.8rem;">UPI / Card / Netbanking — secure & instant.</div>
                                        </div>
                                        <i class="bi bi-lightning-charge ms-auto" style="font-size:1.4rem; color:#017075;"></i>
                                    </label>
                                @endif
                            </div>
                        </div>

                        <button id="place-order-btn" type="submit" class="btn-gehna btn-teal-gehna" style="border-radius:8px; font-size:1rem; padding:14px 36px;">
                            <i class="bi bi-shield-check me-2"></i> Place Order
                        </button>
                    </form>
                @endif
            </div>

            {{-- Right: Order Summary --}}
            <div class="col-lg-4">
                <div style="background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.06); overflow:hidden; position:sticky; top:100px;">
                    <div style="padding:18px 28px; border-bottom:1px solid #f0f0f0; background:linear-gradient(135deg, #022C2B 0%, #017075 100%);">
                        <h3 style="color:#fff; font-size:1rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; margin:0;">
                            <i class="bi bi-receipt me-2" style="color:#00e5ff;"></i> Order Summary
                        </h3>
                    </div>
                    <div style="padding:24px 28px;">

                        {{-- Cart Items --}}
                        <div style="margin-bottom:16px; padding-bottom:16px; border-bottom:1px solid #f0f0f0;">
                            @php
                                $freeItemsByCartId = $freeItems->keyBy(fn ($free) => $free['cart_item']->id);
                            @endphp
                            @foreach($cartItems as $item)
                                @php
                                    $freeQty = !empty($freeItemsByCartId[$item->id]) ? (int) $freeItemsByCartId[$item->id]['free_quantity'] : 0;
                                    $chargedQty = max(0, (int) $item->quantity - $freeQty);
                                @endphp
                                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:10px;">
                                    <div style="color:#495057; font-size:0.88rem; flex:1;">
                                        {{ optional($item->product)->name ?? 'Product' }}
                                        <span style="color:#aaa; font-size:0.82rem;"> × {{ $item->quantity }}</span>
                                        @if($freeQty > 0)
                                            <span style="color:#198754; font-weight:700; font-size:0.75rem;"> ({{ $freeQty }} FREE)</span>
                                        @endif
                                    </div>
                                    <div style="font-weight:700; color:#0D0D0D; font-size:0.9rem; white-space:nowrap;">
                                        @if($freeQty > 0)
                                            <s style="color:#aaa; font-weight:400;">Rs {{ number_format($item->quantity * $item->price, 2) }}</s>
                                            <span style="color:#198754;">Rs {{ number_format($chargedQty * $item->price, 2) }}</span>
                                        @else
                                            Rs {{ number_format($item->quantity * $item->price, 2) }}
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Coupon --}}
                        {{-- <div style="margin-bottom:16px; padding-bottom:16px; border-bottom:1px solid #f0f0f0;">
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
                                    <form action="{{ route('checkout.coupon.remove') }}" method="POST">
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
                        </div> --}}

                        {{-- Totals --}}
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                            <span style="color:#6C757D; font-size:0.9rem;">Subtotal</span>
                            <span style="color:#495057; font-size:0.9rem; font-weight:600;">Rs {{ number_format($subtotal, 2) }}</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:16px; padding-bottom:16px; border-bottom:1px solid #f0f0f0;">
                            <span style="color:#6C757D; font-size:0.9rem;">Discount</span>
                            <span style="color:#198754; font-size:0.9rem; font-weight:600;">- Rs {{ number_format($discount, 2) }}</span>
                        </div>
                        @if($appliedCoupon && $appliedCoupon['type'] === 'buy_get' && $freeItems->isNotEmpty())
                            <div style="margin-bottom:16px; padding-bottom:16px; border-bottom:1px solid #f0f0f0;">
                                <div style="font-size:0.85rem; color:#198754; font-weight:700; margin-bottom:4px;">
                                    <i class="bi bi-gift me-1"></i>FREE gifts (Buy {{ $appliedCoupon['buy_quantity'] }} Get {{ $appliedCoupon['get_quantity'] }})
                                </div>
                                @foreach($freeItems as $free)
                                    <div style="display:flex; justify-content:space-between; font-size:0.82rem; color:#198754;">
                                        <span>{{ optional($free['cart_item']->product)->name }} × {{ $free['free_quantity'] }}</span>
                                        <span style="font-weight:700;">FREE</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <div style="display:flex; justify-content:space-between; margin-bottom:16px; padding-bottom:16px; border-bottom:1px solid #f0f0f0;">
                            <span style="color:#6C757D; font-size:0.9rem;">Shipping</span>
                            <span style="color:#495057; font-size:0.9rem; font-weight:600;">{{ $shippingCharge <= 0 ? 'FREE' : ('Rs ' . number_format($shippingCharge, 2)) }}</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="color:#0D0D0D; font-weight:800; font-size:1rem; text-transform:uppercase; letter-spacing:1px;">Total</span>
                            <span style="color:#017075; font-weight:900; font-size:1.3rem;">Rs {{ number_format($grandTotal, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

@endsection

@if($razorpayProvider)
    @push('scripts')
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    @endpush
@endif

@push('scripts')
<script>
(function () {
    const form = document.getElementById('checkout-form');
    if (!form) return;

    const shippingCheckbox = document.getElementById('shipping_same_as_billing');
    const shippingFields = document.getElementById('shipping-fields');
    const shippingNotice = document.getElementById('shipping-same-notice');
    const submitButton = document.getElementById('place-order-btn');

    const toggleShipping = () => {
        if (shippingCheckbox.checked) {
            shippingFields.style.display = 'none';
            shippingNotice.style.display = 'block';
        } else {
            shippingFields.style.display = 'block';
            shippingNotice.style.display = 'none';
        }
    };

    shippingCheckbox.addEventListener('change', toggleShipping);
    toggleShipping();

    form.addEventListener('submit', async function (event) {
        const paymentMethod = form.querySelector('input[name="payment_method"]:checked');
        if (!paymentMethod || paymentMethod.value !== 'razorpay') return;

        event.preventDefault();
        submitButton.disabled = true;
        submitButton.style.opacity = '0.6';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: new FormData(form)
            });

            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Unable to initiate Razorpay payment.');

            const options = {
                key: data.razorpay.key,
                amount: data.razorpay.amount,
                currency: data.razorpay.currency,
                name: '{{ config('app.name', 'Laravel') }}',
                description: 'Order #' + data.order.id,
                order_id: data.razorpay.order_id,
                handler: async function (paymentResponse) {
                    const verify = await fetch('{{ route('checkout.razorpay.verify') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            order_id: data.order.id,
                            razorpay_order_id: paymentResponse.razorpay_order_id,
                            razorpay_payment_id: paymentResponse.razorpay_payment_id,
                            razorpay_signature: paymentResponse.razorpay_signature
                        })
                    });
                    const verifyData = await verify.json();
                    if (!verify.ok) throw new Error(verifyData.message || 'Payment verification failed.');
                    window.location.href = verifyData.redirect_url;
                },
                prefill: {
                    name: document.querySelector('input[name="billing_name"]').value,
                    email: document.querySelector('input[name="billing_email"]').value,
                    contact: document.querySelector('input[name="billing_phone"]').value
                },
                theme: { color: '#017075' },
                modal: {
                    ondismiss: function () {
                        submitButton.disabled = false;
                        submitButton.style.opacity = '1';
                    }
                }
            };

            const rzp = new Razorpay(options);
            rzp.on('payment.failed', function () {
                alert('Payment failed. Please try again.');
                submitButton.disabled = false;
                submitButton.style.opacity = '1';
            });
            rzp.open();
        } catch (error) {
            alert(error.message || 'Something went wrong.');
            submitButton.disabled = false;
            submitButton.style.opacity = '1';
        }
    });

    const toggleCouponsBtn = document.getElementById('toggle-coupons-btn');
    const couponsWrap = document.getElementById('available-coupons-wrap');
    if (toggleCouponsBtn && couponsWrap) {
        toggleCouponsBtn.addEventListener('click', function () {
            const isHidden = couponsWrap.style.display === 'none';
            couponsWrap.style.display = isHidden ? 'block' : 'none';
            toggleCouponsBtn.textContent = isHidden ? 'Hide Coupons' : 'View Coupons';
        });
    }
})();
</script>
@endpush
