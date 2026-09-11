@extends('layouts.frontend')

@section('title', 'My Orders | GEHNA')

@section('content')

    {{-- Page Header --}}
    <div class="page-header-teal">
        <div class="container">
            <h1 class="page-header-title" style="color: white !important;">My Orders</h1>
        </div>
    </div>

    <section style="border: solid 1px #ccc; box-shadow: rgba(50, 50, 93, 0.25) 0px 2px 5px -1px, rgba(0, 0, 0, 0.3) 0px 1px 3px -1px; margin-bottom: 3px;">
    <div class="container px-4">
        <nav aria-label="breadcrumb" class="py-2">
            <ol class="breadcrumb mb-0">
                 <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('account.index') }}">Account</a></li>
                    <li class="breadcrumb-item active">Orders</li>
            </ol>
        </nav>
    </div>
</section>

    <section style="background:#f5f5f5; padding: 50px 0; min-height: 55vh;">
        <div class="container">

            @if (session('success'))
                <div class="alert alert-success py-2 mb-4" style="font-size:0.875rem; border-radius:8px;">
                    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger py-2 mb-4" style="font-size:0.875rem; border-radius:8px;">
                    <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
                </div>
            @endif

            <div style="background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.06); overflow:hidden;">
                <div
                    style="padding:20px 28px; border-bottom:1px solid #f0f0f0; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                    <h3
                        style="font-size:1rem; font-weight:800; color:#0D0D0D; text-transform:uppercase; letter-spacing:1px; margin:0;">
                        Order History</h3>
                    <a href="{{ route('products.index') }}" class="btn-gehna btn-teal-gehna"
                        style="padding:8px 20px; font-size:0.85rem;">
                        <i class="bi bi-bag me-2"></i> Shop More
                    </a>
                </div>

                <div class="table-responsive">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f8f9fa;">
                                <th
                                    style="padding:14px 24px; text-align:left; font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D; border-bottom:1px solid #f0f0f0;">
                                    Order</th>
                                <th
                                    style="padding:14px 24px; text-align:left; font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D; border-bottom:1px solid #f0f0f0;">
                                    Date</th>
                                <th
                                    style="padding:14px 24px; text-align:left; font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D; border-bottom:1px solid #f0f0f0;">
                                    Status</th>
                                <th
                                    style="padding:14px 24px; text-align:left; font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D; border-bottom:1px solid #f0f0f0;">
                                    Payment</th>
                                <th
                                    style="padding:14px 24px; text-align:left; font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D; border-bottom:1px solid #f0f0f0;">
                                    Total</th>
                                <th
                                    style="padding:14px 24px; text-align:left; font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D; border-bottom:1px solid #f0f0f0;">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $order)
                                @php
                                    $statusColors = [
                                        'pending' => '#ffc107',
                                        'processing' => '#0dcaf0',
                                        'shipped' => '#0d6efd',
                                        'delivered' => '#198754',
                                        'cancelled' => '#dc3545',
                                    ];
                                    $sc = $statusColors[$order->status] ?? '#6C757D';
                                    $payColors = [
                                        'paid' => '#198754',
                                        'pending' => '#ffc107',
                                        'failed' => '#dc3545',
                                        'refunded' => '#6f42c1',
                                    ];
                                    $pc = $payColors[$order->payment_status] ?? '#6C757D';
                                @endphp
                                <tr style="border-bottom:1px solid #f8f8f8;" onmouseover="this.style.background='#fafafa'"
                                    onmouseout="this.style.background='#fff'">
                                    <td style="padding:16px 24px; font-weight:700; color:#0D0D0D;">{{ $loop->iteration }}
                                    </td>
                                    <td style="padding:16px 24px; color:#6C757D; font-size:0.9rem;">
                                        {{ $order->created_at->format('M d, Y') }}</td>
                                    <td style="padding:16px 24px;">
                                        <span
                                            style="display:inline-block; padding:3px 10px; border-radius:20px; font-size:0.75rem; font-weight:600; text-transform:uppercase; background:{{ $sc }}22; color:{{ $sc }};">{{ $order->status }}</span>
                                    </td>
                                    <td style="padding:16px 24px;">
                                        <span
                                            style="display:inline-block; padding:3px 10px; border-radius:20px; font-size:0.75rem; font-weight:600; text-transform:uppercase; background:{{ $pc }}22; color:{{ $pc }};">{{ $order->payment_status }}</span>
                                    </td>
                                    <td style="padding:16px 24px; color:#017075; font-weight:700;">Rs
                                        {{ number_format($order->total, 2) }}</td>
                                    <td style="padding:16px 24px;">
                                        <a href="{{ route('account.orders.show', $order) }}"
                                            style="color:#017075; font-size:0.85rem; font-weight:600; text-decoration:none; padding:6px 14px; border:1.5px solid #017075; border-radius:6px; white-space:nowrap;">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="padding:60px 24px; text-align:center; color:#6C757D;">
                                        <i class="bi bi-bag-x"
                                            style="font-size:2rem; color:#dee2e6; display:block; margin-bottom:12px;"></i>
                                        No orders yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">{{ $orders->links() }}</div>

        </div>
    </section>

@endsection
