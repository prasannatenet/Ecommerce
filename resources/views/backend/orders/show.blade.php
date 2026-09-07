@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Order #{{ $order->id }}</h1>
    <div class="d-flex align-items-center gap-3">
        <nav class="df-breadcrumb d-none d-md-flex">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="{{ route('admin.orders.index') }}">Orders</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">#{{ $order->id }}</span>
        </nav>
        <a href="{{ route('admin.orders.index') }}" class="df-btn df-btn-light">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

@if(session('success'))
    <div class="df-alert df-alert-success">
        <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="df-alert df-alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <ul class="mb-0">
            @foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    {{-- Left Column --}}
    <div class="col-lg-8">
        {{-- Order Details Card --}}
        <div class="df-card mb-4">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-receipt"></i> Order Details</h5>
                <form class="d-flex align-items-center gap-2" method="POST" action="{{ route('admin.orders.update', $order) }}">
                    @csrf
                    @method('PUT')
                    <select name="status" class="df-form-select" style="width:auto; min-width:140px;">
                        @foreach(['pending','processing','shipped','delivered','cancelled'] as $status)
                            <option value="{{ $status }}" {{ $order->status === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <select name="payment_status" class="df-form-select" style="width:auto; min-width:140px;">
                        @foreach(['pending','initiated','paid','failed'] as $pStatus)
                            <option value="{{ $pStatus }}" {{ ($order->payment_status ?? 'pending') === $pStatus ? 'selected' : '' }}>{{ ucfirst($pStatus) }}</option>
                        @endforeach
                    </select>
                    <button class="df-btn df-btn-primary df-btn-sm" type="submit">
                        <i class="bi bi-check2"></i> Update
                    </button>
                </form>
            </div>
            <div class="df-card-body">
                {{-- Order Meta --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; color:var(--df-text-secondary); font-weight:600; margin-bottom:4px;">Status</div>
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
                    </div>
                    <div class="col-md-3">
                        <div style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; color:var(--df-text-secondary); font-weight:600; margin-bottom:4px;">Total</div>
                        <span style="font-size:1.1rem; font-weight:700; color:var(--df-primary);">₹{{ number_format($order->total, 2) }}</span>
                    </div>
                    <div class="col-md-3">
                        <div style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; color:var(--df-text-secondary); font-weight:600; margin-bottom:4px;">Placed On</div>
                        <span style="font-size:0.88rem; color:var(--df-text-primary);">{{ $order->created_at->format('M d, Y') }}</span>
                        <div style="font-size:0.78rem; color:var(--df-text-secondary);">{{ $order->created_at->format('h:i A') }}</div>
                    </div>
                    <div class="col-md-3">
                        <div style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; color:var(--df-text-secondary); font-weight:600; margin-bottom:4px;">Shipments</div>
                        <span class="df-badge df-badge-info">{{ $order->shipments->count() }}</span>
                    </div>
                    <div class="col-md-3">
                        <div style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; color:var(--df-text-secondary); font-weight:600; margin-bottom:4px;">Payment Method</div>
                        <span class="df-badge df-badge-muted">{{ strtoupper($order->payment_method ?? 'N/A') }}</span>
                    </div>
                    <div class="col-md-3">
                        <div style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; color:var(--df-text-secondary); font-weight:600; margin-bottom:4px;">Payment Status</div>
                        @php
                            $paymentStatusMap = [
                                'pending' => 'df-badge-warning',
                                'initiated' => 'df-badge-info',
                                'paid' => 'df-badge-success',
                                'failed' => 'df-badge-danger',
                            ];
                            $paymentStatusClass = $paymentStatusMap[$order->payment_status] ?? 'df-badge-muted';
                        @endphp
                        <span class="df-badge {{ $paymentStatusClass }}">{{ ucfirst($order->payment_status ?? 'pending') }}</span>
                    </div>
                </div>

                {{-- Addresses --}}
                <div class="row g-4">
                    <div class="col-md-6">
                        <div style="background:var(--df-bg-secondary); border-radius:10px; padding:16px;">
                            <h6 style="font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:6px;">
                                <i class="bi bi-credit-card" style="color:var(--df-primary);"></i> Billing Address
                            </h6>
                            @php $billing = $order->billing_address ?? []; @endphp
                            <div style="font-size:0.88rem; color:var(--df-text-secondary); line-height:1.7;">
                                <div style="font-weight:600; color:var(--df-text-primary);">{{ $billing['name'] ?? '—' }}</div>
                                <div>{{ $billing['line1'] ?? '' }}</div>
                                <div>{{ $billing['city'] ?? '' }} {{ $billing['state'] ?? '' }} {{ $billing['zip'] ?? '' }}</div>
                                <div>{{ $billing['country'] ?? '' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div style="background:var(--df-bg-secondary); border-radius:10px; padding:16px;">
                            <h6 style="font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:6px;">
                                <i class="bi bi-truck" style="color:var(--df-primary);"></i> Shipping Address
                            </h6>
                            @php $shipping = $order->shipping_address ?? []; @endphp
                            <div style="font-size:0.88rem; color:var(--df-text-secondary); line-height:1.7;">
                                <div style="font-weight:600; color:var(--df-text-primary);">{{ $shipping['name'] ?? '—' }}</div>
                                <div>{{ $shipping['line1'] ?? '' }}</div>
                                <div>{{ $shipping['city'] ?? '' }} {{ $shipping['state'] ?? '' }} {{ $shipping['zip'] ?? '' }}</div>
                                <div>{{ $shipping['country'] ?? '' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="df-card mb-4">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-box-seam"></i> Order Items</h5>
                <span class="df-badge df-badge-muted">{{ $order->items->count() }} items</span>
            </div>
            <div class="df-card-body-flush">
                <div class="table-responsive">
                    <table class="df-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Qty</th>
                                <th>Unit</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($order->items as $item)
                                <tr>
                                    <td>
                                        <span style="font-weight:600;">{{ $item->product_name }}</span>
                                    </td>
                                    <td>{{ $item->sku ?? '—' }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>₹{{ number_format($item->unit_price, 2) }}</td>
                                    <td style="font-weight:700;">₹{{ number_format($item->line_total, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <div class="df-empty-state">
                                            <div class="empty-icon"><i class="bi bi-box-seam"></i></div>
                                            <p>No order items found for this order.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Shipments Card --}}
        <div class="df-card">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-truck"></i> Shipments</h5>
                <button class="df-btn df-btn-primary df-btn-sm" data-bs-toggle="collapse" data-bs-target="#newShipment">
                    <i class="bi bi-plus-lg"></i> Add Shipment
                </button>
            </div>
            <div class="df-card-body">
                {{-- Add Shipment Form (Collapsed) --}}
                <div id="newShipment" class="collapse mb-4">
                    <div style="background:var(--df-bg-secondary); border-radius:10px; padding:20px;">
                        <h6 style="font-weight:700; margin-bottom:16px;">New Shipment</h6>
                        <form method="POST" action="{{ route('admin.orders.shipments.store', $order) }}" class="row g-3">
                            @csrf
                            <div class="col-md-4">
                                <label class="df-form-label">Delivery Partner</label>
                                <select name="delivery_partner_id" class="df-form-select">
                                    <option value="">Select partner</option>
                                    @foreach($deliveryPartners as $partner)
                                        <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="df-form-label">Tracking Number</label>
                                <input type="text" name="tracking_number" class="df-form-control" placeholder="e.g. TRK123456">
                            </div>
                            <div class="col-md-4">
                                <label class="df-form-label">Status <span class="text-danger">*</span></label>
                                <select name="status" class="df-form-select" required>
                                    @foreach(['pending','shipped','in_transit','delivered','cancelled'] as $status)
                                        <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="df-form-label">Shipped At</label>
                                <input type="datetime-local" name="shipped_at" class="df-form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="df-form-label">Delivered At</label>
                                <input type="datetime-local" name="delivered_at" class="df-form-control">
                            </div>
                            <div class="col-12 d-flex justify-content-end gap-2">
                                <button type="button" class="df-btn df-btn-light" data-bs-toggle="collapse" data-bs-target="#newShipment">Cancel</button>
                                <button class="df-btn df-btn-primary" type="submit">
                                    <i class="bi bi-check2-circle"></i> Create Shipment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Shipments Table --}}
                <div class="table-responsive">
                    <table class="df-table">
                        <thead>
                            <tr>
                                <th>Partner</th>
                                <th>Tracking</th>
                                <th>Status</th>
                                <th>Shipped</th>
                                <th>Delivered</th>
                                <th style="width:180px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($order->shipments as $shipment)
                                <tr>
                                    <td><span style="font-weight:500;">{{ optional($shipment->deliveryPartner)->name ?? '—' }}</span></td>
                                    <td>
                                        @if($shipment->tracking_number)
                                            <code style="font-size:0.82rem; color:var(--df-text-secondary);">{{ $shipment->tracking_number }}</code>
                                        @else
                                            <span style="color:var(--df-text-secondary);">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $shipStatusMap = [
                                                'pending'    => 'df-badge-warning',
                                                'shipped'    => 'df-badge-info',
                                                'in_transit' => 'df-badge-purple',
                                                'delivered'  => 'df-badge-success',
                                                'cancelled'  => 'df-badge-danger',
                                            ];
                                            $shipStatusClass = $shipStatusMap[$shipment->status] ?? 'df-badge-muted';
                                        @endphp
                                        <span class="df-badge {{ $shipStatusClass }}">{{ ucfirst(str_replace('_', ' ', $shipment->status)) }}</span>
                                    </td>
                                    <td style="font-size:0.85rem; color:var(--df-text-secondary);">
                                        {{ $shipment->shipped_at ? $shipment->shipped_at->format('M d, Y h:i A') : '—' }}
                                    </td>
                                    <td style="font-size:0.85rem; color:var(--df-text-secondary);">
                                        {{ $shipment->delivered_at ? $shipment->delivered_at->format('M d, Y h:i A') : '—' }}
                                    </td>
                                    <td>
                                        <div class="df-actions" style="flex-wrap:nowrap;">
                                            <form action="{{ route('admin.shipments.update', $shipment) }}" method="POST" class="d-inline-flex align-items-center gap-1">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="delivery_partner_id" value="{{ $shipment->delivery_partner_id }}">
                                                <input type="hidden" name="tracking_number" value="{{ $shipment->tracking_number }}">
                                                <input type="hidden" name="shipped_at" value="{{ $shipment->shipped_at }}">
                                                <input type="hidden" name="delivered_at" value="{{ $shipment->delivered_at }}">
                                                <select name="status" class="df-form-select" style="width:auto; min-width:110px; padding:4px 8px; font-size:0.8rem;">
                                                    @foreach(['pending','shipped','in_transit','delivered','cancelled'] as $status)
                                                        <option value="{{ $status }}" {{ $shipment->status === $status ? 'selected' : '' }}>
                                                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button class="df-action-btn info" type="submit" title="Update Status">
                                                    <i class="bi bi-check2"></i>
                                                </button>
                                            </form>
                                            <form action="{{ route('admin.shipments.destroy', $shipment) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this shipment?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="df-action-btn danger" type="submit" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="df-empty-state">
                                            <div class="empty-icon"><i class="bi bi-truck"></i></div>
                                            <p>No shipments yet. Click "Add Shipment" to create one.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Right Column - Summary --}}
    <div class="col-lg-4">
        <div class="df-card">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-info-circle"></i> Summary</h5>
            </div>
            <div class="df-card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="df-form-label mb-0">Order ID</span>
                    <span style="font-weight:700; color:var(--df-primary);">#{{ $order->id }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="df-form-label mb-0">Status</span>
                    <span class="df-badge {{ $statusClass }}">{{ ucfirst($order->status) }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="df-form-label mb-0">Total</span>
                    <span style="font-weight:700; font-size:1.1rem;">₹{{ number_format($order->total, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="df-form-label mb-0">Payment Method</span>
                    <span style="font-weight:700; font-size:0.9rem;">{{ strtoupper($order->payment_method ?? 'N/A') }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="df-form-label mb-0">Payment Status</span>
                    <span class="df-badge {{ $paymentStatusClass }}">{{ ucfirst($order->payment_status ?? 'pending') }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="df-form-label mb-0">Gateway Order</span>
                    <span style="font-size:0.8rem; color:var(--df-text-secondary);">{{ $order->gateway_order_id ?? '—' }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="df-form-label mb-0">Transaction ID</span>
                    <span style="font-size:0.8rem; color:var(--df-text-secondary);">{{ $order->transaction_id ?? '—' }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="df-form-label mb-0">Shipments</span>
                    <span class="df-badge df-badge-info">{{ $order->shipments->count() }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="df-form-label mb-0">Refund Status</span>
                    <span class="df-badge df-badge-muted">{{ strtoupper($order->refund_status ?? 'none') }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="df-form-label mb-0">Refunded Total</span>
                    <span style="font-weight:700; font-size:0.9rem;">₹{{ number_format($order->refunded_total ?? 0, 2) }}</span>
                </div>
                @if($order->user)
                <div style="border-top:1px solid var(--df-border); padding-top:14px; margin-top:8px;">
                    <div style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; color:var(--df-text-secondary); font-weight:600; margin-bottom:8px;">Customer</div>
                    <div style="font-weight:600;">{{ $order->user->name }}</div>
                    <div style="font-size:0.85rem; color:var(--df-text-secondary);">{{ $order->user->email }}</div>
                </div>
                @endif
                <div style="border-top:1px solid var(--df-border); padding-top:14px; margin-top:14px;">
                    <a href="{{ route('admin.orders.invoice', $order) }}" class="df-btn df-btn-light w-100 mb-2">
                        <i class="bi bi-file-earmark-pdf"></i> Download Invoice PDF
                    </a>

                    @if(in_array($order->status, ['pending','processing']))
                        <form method="POST" action="{{ route('admin.orders.cancel', $order) }}" style="margin-bottom:12px;">
                            @csrf
                            <label class="df-form-label">Cancel Reason</label>
                            <textarea name="reason" class="df-form-control mb-2" rows="2" placeholder="Reason (optional)"></textarea>
                            <button type="submit" class="df-btn df-btn-danger w-100" onclick="return confirm('Cancel this order?')">
                                <i class="bi bi-x-circle"></i> Cancel Order
                            </button>
                        </form>
                    @endif

                    @if($order->payment_status === 'paid' && ($order->payment_method ?? null) === 'razorpay')
                        <form method="POST" action="{{ route('admin.orders.refund', $order) }}" style="margin-bottom:12px;">
                            @csrf
                            <label class="df-form-label">Refund Amount</label>
                            <input type="number" step="0.01" min="0.01" max="{{ $order->total }}" name="amount" class="df-form-control mb-2" value="{{ old('amount', number_format($order->total - ($order->refunded_total ?? 0), 2, '.', '')) }}">
                            <label class="df-form-label">Refund Method</label>
                            <select name="refund_method" class="df-form-select mb-2">
                                <option value="money">Money to original Razorpay payment</option>
                                <option value="gehna_coins">Gehna Coins</option>
                            </select>
                            <label class="df-form-label">Refund Reason</label>
                            <textarea name="reason" class="df-form-control mb-2" rows="2" placeholder="Reason (optional)"></textarea>
                            <button type="submit" class="df-btn df-btn-primary w-100" onclick="return confirm('Process refund for this order?')">
                                <i class="bi bi-arrow-counterclockwise"></i> Process Refund
                            </button>
                        </form>
                    @endif

                    @if($order->refunds->count())
                        <div style="border-top:1px solid var(--df-border); padding-top:12px; margin-top:10px;">
                            <div style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; color:var(--df-text-secondary); font-weight:600; margin-bottom:8px;">Refund History</div>
                            @foreach($order->refunds as $refund)
                                <div style="padding:8px 0; border-bottom:1px dashed var(--df-border);">
                                    <div class="d-flex justify-content-between" style="font-size:0.85rem;">
                                        <span>₹{{ number_format($refund->amount, 2) }}</span>
                                        <span class="df-badge df-badge-muted">{{ strtoupper($refund->status) }}</span>
                                    </div>
                                    <div style="font-size:0.78rem; color:var(--df-text-secondary);">{{ $refund->gateway_refund_id ?? 'Manual' }}</div>
                                    <a href="{{ route('admin.orders.credit-note', [$order, $refund]) }}" style="font-size:0.78rem; color:var(--df-primary);">Download Credit Note PDF</a>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="df-form-label mb-0">Created</span>
                        <span style="font-size:0.85rem; color:var(--df-text-secondary);">{{ $order->created_at->format('M d, Y') }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="df-form-label mb-0">Updated</span>
                        <span style="font-size:0.85rem; color:var(--df-text-secondary);">{{ $order->updated_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
