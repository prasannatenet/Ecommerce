@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Order Items</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <span class="current">Order Items</span>
    </nav>
</div>

<div class="df-card">
    <div class="df-card-body-flush">
        <div class="table-responsive">
            <table class="df-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Order</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Unit</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td>{{ $item->id }}</td>
                            <td>#{{ $item->order_id }}</td>
                            <td>{{ $item->product_name ?? optional($item->product)->name }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>₹{{ number_format($item->unit_price ?? 0, 2) }}</td>
                            <td>₹{{ number_format($item->line_total ?? 0, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="df-empty-state">
                                    <div class="empty-icon"><i class="bi bi-box-seam"></i></div>
                                    <p>No order items found.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($items->hasPages())
    <div class="mt-3">{{ $items->links() }}</div>
@endif

@endsection
