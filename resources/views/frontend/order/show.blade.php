@extends('layouts.frontend')

@section('title', 'Order #{{ $order->id }} | GEHNA')

@section('content')

        {{-- Page Header --}}
        <div class="page-header-teal">
            <div class="container">
                <h1 class="page-header-title" style="color: white !important;">Order #{{ $order->id }}</h1>
            </div>
        </div>

<section style="border: solid 1px #ccc; box-shadow: rgba(50, 50, 93, 0.25) 0px 2px 5px -1px, rgba(0, 0, 0, 0.3) 0px 1px 3px -1px; margin-bottom: 3px;">
    <div class="container px-4">
        <nav aria-label="breadcrumb" class="py-2">
            <ol class="breadcrumb mb-0">
               <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('account.index') }}">Account</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('account.orders') }}">Orders</a></li>
                        <li class="breadcrumb-item active" aria-current="page">#{{ $order->id }}</li>
            </ol>
        </nav>
    </div>
</section>

        <section style="background:#f5f5f5; padding: 50px 0; min-height: 55vh;">
            <div class="container">

                @if(session('success'))
                    <div class="alert alert-success py-2 mb-4" style="font-size:0.875rem; border-radius:8px;">
                        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger py-2 mb-4" style="font-size:0.875rem; border-radius:8px;">
                        <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
                    </div>
                @endif

                <div class="row g-4">
                    {{-- Order Items --}}
                    <div class="col-lg-8">
                        <div style="background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.06); overflow:hidden;">
                            <div class="heading-section">
                                <h3 style="color:#fff; font-size:1rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; margin:0;">
                                    <i class="bi bi-bag-check me-2" style="color:#fff;"></i> Order Items
                                </h3>
                            </div>
                            <div style="padding:0;">
                                @foreach($order->items as $item)
                                    <div style="padding:18px 28px; border-bottom:1px solid #f8f8f8; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                                        <div style="flex:1;">
                                            <p style="font-weight:700; color:#0D0D0D; margin:0; font-size:0.95rem;">{{ $item->product_name }}</p>
                                            <p style="color:#6C757D; font-size:0.85rem; margin:4px 0 0;">Qty: {{ $item->quantity }}</p>
                                        </div>
                                        <div style="color:#017075; font-weight:700; font-size:1rem;">
                                            Rs {{ number_format($item->line_total, 2) }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @if($order->status === 'delivered')
                                <div style="padding:20px 28px; background:#fffaf2; border-top:1px solid #f0f0f0;">
                                    <h4 style="font-size:1rem; font-weight:800; margin-bottom:12px;">Request a return</h4>
                                    <form method="POST" action="{{ route('orders.return-request.store', $order) }}">
                                        @csrf
                                        @foreach($order->items as $item)
                                            <label style="display:flex; align-items:center; gap:10px; margin-bottom:9px; font-size:.9rem;">
                                                <input type="checkbox" name="items[]" value="{{ $item->id }}">
                                                <span style="flex:1">{{ $item->product_name }}</span>
                                                <input type="number" name="quantities[{{ $item->id }}]" min="1" max="{{ $item->quantity }}" value="1" style="width:65px" class="form-control form-control-sm">
                                            </label>
                                        @endforeach
                                        <textarea name="reason" required rows="2" class="form-control mb-2" placeholder="Reason for return"></textarea>
                                        <select name="payout_method" required class="form-select mb-2" onchange="this.form.querySelector('.bank-payout').style.display = this.value === 'bank' ? 'block' : 'none'; this.form.querySelector('.upi-payout').style.display = this.value === 'upi' ? 'block' : 'none';">
                                            <option value="">Choose refund destination</option>
                                            <option value="gehna_coins">Gehna Coins</option>
                                            <option value="bank">Bank account</option>
                                            <option value="upi">UPI</option>
                                        </select>
                                        <div class="bank-payout" style="display:none;">
                                            <input name="account_holder" class="form-control mb-2" placeholder="Account holder name">
                                            <input name="bank_name" class="form-control mb-2" placeholder="Bank name">
                                            <input name="account_number" class="form-control mb-2" placeholder="Account number">
                                            <input name="ifsc" class="form-control mb-2" placeholder="IFSC code">
                                        </div>
                                        <div class="upi-payout" style="display:none;">
                                            <input name="upi_id" class="form-control mb-2" placeholder="UPI ID, e.g. name@upi">
                                        </div>
                                        <button class="btn btn-dark w-100" type="submit">Submit Return Request</button>
                                    </form>
                                </div>
                            @endif
                            @if($order->returnRequests->isNotEmpty())
                                <div style="padding:18px 28px; border-top:1px solid #f0f0f0;">
                                    <h4 style="font-size:.95rem; font-weight:800; margin-bottom:10px;">Return requests</h4>
                                    @foreach($order->returnRequests as $returnRequest)
                                        <div class="d-flex justify-content-between mb-2" style="font-size:.85rem;">
                                            <span>#{{ $returnRequest->id }} · ₹{{ number_format($returnRequest->amount, 2) }}</span>
                                            <span style="font-weight:700; text-transform:uppercase;">{{ $returnRequest->status }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Order Summary --}}
                    <div class="col-lg-4">
                        <div style="background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.06); overflow:hidden; position:sticky; top:100px;">
                            <div class="heading-section">
                                <h3 style="color:#fff; font-size:1rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; margin:0;">
                                    <i class="bi bi-receipt me-2" style="color:#fff;"></i> Summary
                                </h3>
                            </div>
                            <div style="padding:24px 28px;">
                                @php
    $statusColors = ['pending' => '#ffc107', 'processing' => '#0dcaf0', 'shipped' => '#0d6efd', 'delivered' => '#198754', 'cancelled' => '#dc3545'];
    $sc = $statusColors[$order->status] ?? '#6C757D';
    $payColors = ['paid' => '#198754', 'pending' => '#ffc107', 'failed' => '#dc3545', 'refunded' => '#6f42c1'];
    $pc = $payColors[$order->payment_status] ?? '#6C757D';
                                @endphp

                                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f8f8f8;">
                                    <span style="color:#6C757D; font-size:0.9rem;">Status</span>
                                    <span style="display:inline-block; padding:3px 10px; border-radius:20px; font-size:0.75rem; font-weight:600; text-transform:uppercase; background:{{ $sc }}22; color:{{ $sc }};">{{ $order->status }}</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f8f8f8;">
                                    <span style="color:#6C757D; font-size:0.9rem;">Payment</span>
                                    <span style="display:inline-block; padding:3px 10px; border-radius:20px; font-size:0.75rem; font-weight:600; text-transform:uppercase; background:{{ $pc }}22; color:{{ $pc }};">{{ $order->payment_status }}</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f8f8f8;">
                                    <span style="color:#6C757D; font-size:0.9rem;">Method</span>
                                    <span style="color:#495057; font-size:0.9rem; font-weight:600; text-transform:uppercase;">{{ $order->payment_method }}</span>
                                </div>
                                @if($order->refund_status)
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f8f8f8;">
                                    <span style="color:#6C757D; font-size:0.9rem;">Refund</span>
                                    <span style="color:#495057; font-size:0.9rem; font-weight:600; text-transform:uppercase;">{{ $order->refund_status }}</span>
                                </div>
                                @endif
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 0 0;">
                                    <span style="color:#0D0D0D; font-weight:800; font-size:1rem;">Total</span>
                                    <span style="color:#017075; font-weight:900; font-size:1.2rem;">Rs {{ number_format($order->total, 2) }}</span>
                                </div>

                                @if(in_array($order->status, ['pending', 'processing']))
                                    <div style="margin-top:20px; padding-top:20px; border-top:1px solid #f0f0f0;">
                                        <form action="{{ route('orders.cancel', $order) }}" method="POST">
                                            @csrf
                                            <textarea name="reason" rows="2"
                                                      class="form-control mb-3"
                                                      style="border:1.5px solid #DEE2E6; border-radius:8px; padding:10px 14px; font-size:0.9rem; background:#f9f9f9; resize:none;"
                                                      onfocus="this.style.borderColor='#dc3545';"
                                                      onblur="this.style.borderColor='#DEE2E6';"
                                                      placeholder="Reason for cancellation (optional)"></textarea>
                                            <button type="submit"
                                                    onclick="return confirm('Are you sure you want to cancel this order?')"
                                                    style="width:100%; padding:11px; border-radius:8px; border:1.5px solid #dc3545; background:#fff; color:#dc3545; font-size:0.9rem; font-weight:700; cursor:pointer; transition:all 0.2s; text-transform:uppercase; letter-spacing:1px;"
                                                    onmouseover="this.style.background='#dc3545'; this.style.color='#fff';"
                                                    onmouseout="this.style.background='#fff'; this.style.color='#dc3545';">
                                                <i class="bi bi-x-circle me-2"></i> Cancel Order
                                            </button>
                                        </form>
                                    </div>
                                @endif

                                <div style="margin-top:16px;">
                                    <a href="{{ route('account.orders') }}"
                                       style="display:flex; align-items:center; justify-content:center; gap:8px; color:#017075; font-size:0.9rem; font-weight:600; text-decoration:none; padding:10px; border:1.5px solid #017075; border-radius:8px; transition:all 0.2s;"
                                       onmouseover="this.style.background='#017075'; this.style.color='#fff';"
                                       onmouseout="this.style.background='#fff'; this.style.color='#017075';">
                                        <i class="bi bi-arrow-left"></i> Back to Orders
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

@endsection
