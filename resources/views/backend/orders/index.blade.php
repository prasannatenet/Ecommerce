@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Orders</h1>
    <div class="d-flex align-items-center gap-3">
        <nav class="df-breadcrumb d-none d-md-flex">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Orders</span>
        </nav>
        <a href="{{ route('admin.orders.export', request()->query()) }}" class="df-btn df-btn-light">
            <i class="bi bi-download"></i> Export CSV
        </a>
    </div>
</div>

@if(session('success'))
    <div class="df-alert df-alert-success">
        <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
    </div>
@endif

<div class="df-card mb-4">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-funnel"></i> Filters</h5>
    </div>
    <div class="df-card-body">
        <form method="GET" action="{{ route('admin.orders.index') }}" class="row g-3">
            <div class="col-md-2">
                <label class="df-form-label">Status</label>
                <select name="status" class="df-form-select">
                    <option value="">All</option>
                    @foreach(['pending','processing','shipped','delivered','cancelled'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="df-form-label">Payment Method</label>
                <select name="payment_method" class="df-form-select">
                    <option value="">All</option>
                    @foreach(['cod','razorpay'] as $m)
                        <option value="{{ $m }}" {{ request('payment_method') === $m ? 'selected' : '' }}>{{ strtoupper($m) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="df-form-label">Payment Status</label>
                <select name="payment_status" class="df-form-select">
                    <option value="">All</option>
                    @foreach(['pending','initiated','paid','failed'] as $p)
                        <option value="{{ $p }}" {{ request('payment_status') === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="df-form-label">Refund Status</label>
                <select name="refund_status" class="df-form-select">
                    <option value="">All</option>
                    @foreach(['none','partial','full'] as $r)
                        <option value="{{ $r }}" {{ request('refund_status') === $r ? 'selected' : '' }}>{{ ucfirst($r) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="df-form-label">Date From</label>
                <input type="date" name="date_from" class="df-form-control" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <label class="df-form-label">Date To</label>
                <input type="date" name="date_to" class="df-form-control" value="{{ request('date_to') }}">
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="df-btn df-btn-primary" type="submit"><i class="bi bi-search"></i> Apply</button>
                <a href="{{ route('admin.orders.index') }}" class="df-btn df-btn-light"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>
        </form>
    </div>
</div>

{{-- Stats Row --}}
<div class="row g-3 mb-4">
    @php
        $allOrders = $orders instanceof \Illuminate\Pagination\LengthAwarePaginator ? $orders : collect($orders);
        $totalOrders = $orders instanceof \Illuminate\Pagination\LengthAwarePaginator ? $orders->total() : $allOrders->count();
    @endphp
    <div class="col-6 col-lg-3">
        <div class="df-card">
            <div class="df-card-body" style="text-align:center; padding:20px 16px;">
                <div style="font-size:1.8rem; font-weight:800; color:var(--df-primary);">{{ $totalOrders }}</div>
                <div style="font-size:0.82rem; color:var(--df-text-secondary); font-weight:500;">Total Orders</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="df-card">
            <div class="df-card-body" style="text-align:center; padding:20px 16px;">
                <div style="font-size:1.8rem; font-weight:800; color:var(--df-warning, #f59e0b);">
                    {{ $orders instanceof \Illuminate\Pagination\LengthAwarePaginator ? '-' : $allOrders->where('status', 'pending')->count() }}
                </div>
                <div style="font-size:0.82rem; color:var(--df-text-secondary); font-weight:500;">Pending</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="df-card">
            <div class="df-card-body" style="text-align:center; padding:20px 16px;">
                <div style="font-size:1.8rem; font-weight:800; color:var(--df-info, #3b82f6);">
                    {{ $orders instanceof \Illuminate\Pagination\LengthAwarePaginator ? '-' : $allOrders->where('status', 'processing')->count() }}
                </div>
                <div style="font-size:0.82rem; color:var(--df-text-secondary); font-weight:500;">Processing</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="df-card">
            <div class="df-card-body" style="text-align:center; padding:20px 16px;">
                <div style="font-size:1.8rem; font-weight:800; color:var(--df-success, #10b981);">
                    {{ $orders instanceof \Illuminate\Pagination\LengthAwarePaginator ? '-' : $allOrders->where('status', 'delivered')->count() }}
                </div>
                <div style="font-size:0.82rem; color:var(--df-text-secondary); font-weight:500;">Delivered</div>
            </div>
        </div>
    </div>
</div>

<div class="df-card">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-receipt"></i> All Orders</h5>
        <span class="df-badge df-badge-muted">{{ $totalOrders }} orders</span>
    </div>
    <div class="df-card-body-flush">
        <div class="table-responsive">
            <table class="df-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Total</th>
                        <th>Shipments</th>
                        <th>Date</th>
                        <th style="width:80px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td>
                                <span style="font-weight:600; color:var(--df-primary);">#{{ $order->id }}</span>
                            </td>
                            <td>
                                @if($order->user)
                                    <span style="font-weight:500;">{{ $order->user->name }}</span>
                                    <div style="font-size:0.8rem; color:var(--df-text-secondary);">{{ $order->user->email }}</div>
                                @else
                                    <span style="color:var(--df-text-secondary);">Guest</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $statusMap = [
                                        'pending'    => 'df-badge-warning',
                                        'processing' => 'df-badge-info',
                                        'shipped'    => 'df-badge-purple',
                                        'delivered'  => 'df-badge-success',
                                        'cancelled'  => 'df-badge-danger',
                                    ];
                                    $statusClass = $statusMap[$order->status] ?? 'df-badge-muted';
                                @endphp
                                <span class="df-badge {{ $statusClass }}">{{ ucfirst($order->status) }}</span>
                            </td>
                            <td>
                                <div style="font-size:0.82rem; font-weight:600; color:var(--df-text-primary);">
                                    {{ strtoupper($order->payment_method ?? 'N/A') }}
                                </div>
                                @php
                                    $paymentStatusMap = [
                                        'pending' => 'df-badge-warning',
                                        'initiated' => 'df-badge-info',
                                        'paid' => 'df-badge-success',
                                        'failed' => 'df-badge-danger',
                                    ];
                                    $paymentStatusClass = $paymentStatusMap[$order->payment_status] ?? 'df-badge-muted';
                                @endphp
                                <span class="df-badge {{ $paymentStatusClass }}" style="margin-top:4px;">{{ ucfirst($order->payment_status ?? 'pending') }}</span>
                            </td>
                            <td>
                                <span class="df-price-regular">₹{{ number_format($order->total, 2) }}</span>
                            </td>
                            <td>
                                <span class="df-badge df-badge-muted">{{ $order->shipments->count() }}</span>
                            </td>
                            <td style="color:var(--df-text-secondary); font-size:0.85rem;">
                                {{ $order->created_at->format('M d, Y') }}
                                <div style="font-size:0.78rem;">{{ $order->created_at->format('h:i A') }}</div>
                            </td>
                            <td>
                                <div class="df-actions">
                                    <a href="{{ route('admin.orders.show', $order) }}" class="df-action-btn info" title="View Order">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="df-empty-state">
                                    <div class="empty-icon"><i class="bi bi-receipt"></i></div>
                                    <p>No orders found.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($orders->hasPages())
    <div class="mt-3">{{ $orders->links() }}</div>
@endif

@endsection
