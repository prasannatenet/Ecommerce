@extends('layouts.frontend')

@section('title', 'Order Confirmed | GEHNA')

@section('content')

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7 col-xl-6">
                <div style="background:#fff; border-radius:16px; box-shadow:0 24px 60px rgba(0,0,0,0.5); overflow:hidden; text-align:center;">

                    {{-- Success Banner --}}
                    <div style="background:linear-gradient(135deg, #022C2B 0%, #017075 100%); padding:40px 40px 30px;">
                        <div style="width:80px; height:80px; border-radius:50%; background:rgba(255,255,255,0.15); display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                            <i class="bi bi-check2-circle" style="font-size:2.8rem; color:#00e5ff;"></i>
                        </div>
                        <h1 style="color:#fff; font-size:1.8rem; font-weight:900; margin-bottom:8px;">Order Confirmed!</h1>
                        <p style="color:rgba(255,255,255,0.7); font-size:0.95rem; margin:0;">Thank you! Your order has been placed successfully.</p>
                    </div>

                    {{-- Order Details --}}
                    <div style="padding:32px 40px;">
                        <div style="background:#f8f9fa; border-radius:10px; padding:20px 24px; margin-bottom:28px; text-align:left;">
                            <div class="row g-3">
                                <div class="col-6">
                                    <p style="font-size:0.7rem; text-transform:uppercase; letter-spacing:1.5px; color:#6C757D; margin:0 0 4px;">Order ID</p>
                                    <p style="font-weight:800; color:#0D0D0D; margin:0; font-size:1rem;">#{{ $order->id }}</p>
                                </div>
                                <div class="col-6">
                                    <p style="font-size:0.7rem; text-transform:uppercase; letter-spacing:1.5px; color:#6C757D; margin:0 0 4px;">Payment Method</p>
                                    <p style="font-weight:700; color:#6C757D; margin:0; text-transform:uppercase;">{{ $order->payment_method ?? 'N/A' }}</p>
                                </div>
                                <div class="col-6">
                                    <p style="font-size:0.7rem; text-transform:uppercase; letter-spacing:1.5px; color:#6C757D; margin:0 0 4px;">Payment Status</p>
                                    @php
                                        $payColors = ['paid'=>'#198754','pending'=>'#ffc107','failed'=>'#dc3545'];
                                        $pc = $payColors[$order->payment_status] ?? '#6C757D';
                                    @endphp
                                    <span style="display:inline-block; padding:3px 10px; border-radius:20px; font-size:0.75rem; font-weight:600; text-transform:uppercase; background:{{ $pc }}22; color:{{ $pc }};">{{ $order->payment_status ?? 'N/A' }}</span>
                                </div>
                                <div class="col-6">
                                    <p style="font-size:0.7rem; text-transform:uppercase; letter-spacing:1.5px; color:#6C757D; margin:0 0 4px;">Total Amount</p>
                                    <p style="font-weight:900; color:#017075; margin:0; font-size:1.1rem;">Rs {{ number_format($order->total, 2) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-column gap-3">
                            <a href="{{ route('orders.show', $order) }}" class="btn-gehna btn-teal-gehna w-100 justify-content-center" style="border-radius:8px;">
                                <i class="bi bi-receipt me-2"></i> View Order Details
                            </a>
                            <a href="{{ route('products.index') }}"
                               style="display:flex; align-items:center; justify-content:center; gap:8px; padding:12px 28px; border-radius:8px; border:1.5px solid #017075; color:#017075; font-weight:700; font-size:0.95rem; text-decoration:none; transition:all 0.2s;"
                               onmouseover="this.style.background='#017075'; this.style.color='#fff';"
                               onmouseout="this.style.background='#fff'; this.style.color='#017075';">
                                <i class="bi bi-arrow-right me-2"></i> Continue Shopping
                            </a>
                            <a href="{{ route('home') }}"
                               style="display:flex; align-items:center; justify-content:center; gap:8px; padding:12px 28px; border-radius:8px; border:1.5px solid #DEE2E6; color:#6C757D; font-weight:600; font-size:0.9rem; text-decoration:none; transition:all 0.2s;"
                               onmouseover="this.style.borderColor='#aaa'; this.style.color='#495057';"
                               onmouseout="this.style.borderColor='#DEE2E6'; this.style.color='#6C757D';">
                                <i class="bi bi-house me-2"></i> Back to Home
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

@endsection
