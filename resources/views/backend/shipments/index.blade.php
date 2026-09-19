@extends('layouts.app')
@section('content')

@php
    $statusColors = [
        'pending' => 'df-badge-muted',
        'booked' => 'df-badge-info',
        'processing' => 'df-badge-info',
        'shipped' => 'df-badge-primary',
        'in_transit' => 'df-badge-purple',
        'out_for_delivery' => 'df-badge-warning',
        'delivered' => 'df-badge-success',
        'undelivered' => 'df-badge-danger',
        'rto' => 'df-badge-danger',
        'cancelled' => 'df-badge-danger',
        'booking_failed' => 'df-badge-danger',
    ];
@endphp

<div class="df-page-header">
    <h1 class="df-page-title">Shipment Management</h1>
    <div class="d-flex align-items-center gap-3">
        <nav class="df-breadcrumb d-none d-md-flex">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Shipments</span>
        </nav>
        <a href="{{ route('admin.delivery-partners.index') }}" class="df-btn df-btn-light">
            <i class="bi bi-truck"></i> Delivery Settings
        </a>
    </div>
</div>

@if(session('success'))
    <div class="df-alert df-alert-success">
        <i class="bi bi-check-circle-fill"></i>
        <div style="white-space:pre-line;">{{ session('success') }}</div>
    </div>
@endif

@if(session('delivery_error'))
    <div class="df-alert df-alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div style="white-space:pre-line;">{{ session('delivery_error') }}</div>
    </div>
@endif

{{-- Shipment stats --}}
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <a href="{{ route('admin.shipments.index') }}" class="text-decoration-none">
            <div class="df-card mb-0 h-100">
                <div class="df-card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="df-stat-title mb-2">Total shipments</p>
                            <h2 class="df-stat-value mb-0">{{ number_format($stats['total']) }}</h2>
                            <small class="text-muted">All courier bookings</small>
                        </div>
                        <div class="df-stat-icon"><i class="bi bi-box-seam"></i></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-md-6">
        <a href="{{ route('admin.shipments.index', ['status' => 'in_transit']) }}" class="text-decoration-none">
            <div class="df-card mb-0 h-100">
                <div class="df-card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="df-stat-title mb-2">In transit</p>
                            <h2 class="df-stat-value mb-0">{{ number_format($stats['in_transit']) }}</h2>
                            <small class="text-muted">Shipped · in transit · out for delivery</small>
                        </div>
                        <div class="df-stat-icon"><i class="bi bi-truck"></i></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-md-6">
        <a href="{{ route('admin.shipments.index', ['status' => 'delivered']) }}" class="text-decoration-none">
            <div class="df-card mb-0 h-100">
                <div class="df-card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="df-stat-title mb-2">Delivered</p>
                            <h2 class="df-stat-value mb-0">{{ number_format($stats['delivered']) }}</h2>
                            <small class="text-muted">Confirmed by courier</small>
                        </div>
                        <div class="df-stat-icon"><i class="bi bi-check2-circle"></i></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-md-6">
        <a href="{{ route('admin.shipments.index', ['status' => 'exceptions']) }}" class="text-decoration-none">
            <div class="df-card mb-0 h-100">
                <div class="df-card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="df-stat-title mb-2">Exceptions</p>
                            <h2 class="df-stat-value mb-0">{{ number_format($stats['exceptions']) }}</h2>
                            <small class="text-muted">Failed / RTO / cancelled / sync errors</small>
                        </div>
                        <div class="df-stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>
{{-- Filters --}}
<div class="df-card mb-4">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-funnel"></i> Filters</h5>
    </div>
    <div class="df-card-body">
        <form method="GET" action="{{ route('admin.shipments.index') }}" class="row g-3">
            <div class="col-md-3">
                <label class="df-form-label">Shipment status</label>
                <select name="status" class="df-form-select">
                    <option value="">All statuses</option>
                    <option value="in_transit" {{ $filters['status'] === 'in_transit' ? 'selected' : '' }}>In transit (grouped)</option>
                    <option value="exceptions" {{ $filters['status'] === 'exceptions' ? 'selected' : '' }}>Exceptions (grouped)</option>
                    @foreach($statusOptions as $statusKey => $statusLabel)
                        <option value="{{ $statusKey }}" {{ $filters['status'] === $statusKey ? 'selected' : '' }}>{{ $statusLabel }}</option>
                    @endforeach
                    <option value="booking_failed" {{ $filters['status'] === 'booking_failed' ? 'selected' : '' }}>Booking Failed</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="df-form-label">Delivery partner</label>
                <select name="partner" class="df-form-select">
                    <option value="">All partners</option>
                    @foreach($partners as $partner)
                        <option value="{{ $partner->id }}" {{ (string) $filters['partner'] === (string) $partner->id ? 'selected' : '' }}>
                            {{ $partner->name }}{{ $partner->is_active ? '' : ' (inactive)' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="df-form-label">Order ID / AWB</label>
                <input type="text" name="q" class="df-form-control" value="{{ $filters['q'] }}"
                       placeholder="e.g. 10025 or AWB number">
            </div>
            <div class="col-md-2">
                <label class="df-form-label">Per page</label>
                <select name="per_page" class="df-form-select">
                    @foreach([10, 15, 25, 50, 100] as $size)
                        <option value="{{ $size }}" {{ (int) $filters['per_page'] === $size ? 'selected' : '' }}>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button class="df-btn df-btn-primary w-100" type="submit"><i class="bi bi-search"></i></button>
            </div>
            <div class="col-12 d-flex gap-2">
                <a href="{{ route('admin.shipments.index') }}" class="df-btn df-btn-light">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </a>
                <span class="df-form-hint mb-0 align-self-center">
                    Tracking is synced automatically every 10 minutes and instantly by webhook callbacks.
                </span>
            </div>
        </form>
    </div>
</div>
{{-- Shipment records --}}
<div class="df-card">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-truck"></i> Shipment Records</h5>
        <span class="df-badge df-badge-muted">{{ $shipments->total() }} shipments</span>
    </div>
    <div class="df-card-body-flush">
        <div class="table-responsive">
            <table class="df-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Delivery partner</th>
                        <th>AWB</th>
                        <th>Status</th>
                        <th>Booked</th>
                        <th>Last update</th>
                        <th style="width:150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shipments as $shipment)
                        <tr>
                            <td>
                                <a href="{{ route('admin.orders.show', $shipment->order_id) }}" style="font-weight:600;">
                                    #{{ $shipment->order_id }}
                                </a>
                                @if($shipment->order)
                                    <div style="font-size:0.78rem; color:var(--df-text-secondary);">
                                        {{ strtoupper($shipment->order->payment_method ?? '') }}
                                        · ₹{{ number_format((float) $shipment->order->total, 2) }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span style="font-weight:500;">{{ optional($shipment->deliveryPartner)->name ?? '—' }}</span>
                                @if($shipment->deliveryPartner)
                                    <div style="font-size:0.78rem; color:var(--df-text-secondary);">
                                        {{ $shipment->deliveryPartner->is_sandbox ? 'Sandbox' : 'Production' }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($shipment->tracking_number)
                                    <code style="font-size:0.82rem; color:var(--df-text-secondary);">{{ $shipment->tracking_number }}</code>
                                    @if($shipment->tracking_url)
                                        <a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener noreferrer"
                                           style="font-size:0.78rem; color:var(--df-primary);">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>
                                    @endif
                                @else
                                    <span style="color:var(--df-text-secondary);">AWB pending</span>
                                @endif
                            </td>
<td>
                                <span class="df-badge {{ $statusColors[$shipment->status] ?? 'df-badge-muted' }}">
                                    {{ $shipment->statusLabel() }}
                                </span>
                                @if($shipment->isSyncedFromProvider())
                                    <div style="margin-top:4px;">
                                        <span class="df-badge df-badge-muted" style="font-size:0.7rem;">
                                            <i class="bi bi-cloud-check"></i> Synced from provider
                                        </span>
                                    </div>
                                @endif
                                @if($shipment->last_sync_error)
                                    <div style="margin-top:4px;">
                                        <span class="df-badge df-badge-danger" style="font-size:0.7rem;"
                                              title="{{ $shipment->last_sync_error }}">
                                            <i class="bi bi-exclamation-triangle"></i> Sync error
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td style="font-size:0.82rem;">
                                {{ $shipment->booked_at ? $shipment->booked_at->format('M d, Y') : '—' }}
                            </td>
                            <td style="font-size:0.82rem;">
                                {{ $shipment->last_synced_at ? $shipment->last_synced_at->diffForHumans() : 'Never' }}
                            </td>
                            <td>
                                <div class="df-actions">
                                    @if(empty($shipment->tracking_number) && $shipment->deliveryPartner && $shipment->deliveryPartner->isIntegrated())
                                        <form action="{{ route('admin.shipments.book', $shipment) }}" method="POST" style="display:inline">
                                            @csrf
                                            <button class="df-action-btn success" type="submit" title="Book with courier">
                                                <i class="bi bi-send"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if($shipment->tracking_number)
                                        <form action="{{ route('admin.shipments.sync', $shipment) }}" method="POST" style="display:inline">
                                            @csrf
                                            <button class="df-action-btn warning" type="submit" title="Sync tracking">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        </form>
                                        <a href="{{ route('admin.shipments.label', $shipment) }}" class="df-action-btn info" title="Download label">
                                            <i class="bi bi-file-earmark-arrow-down"></i>
                                        </a>
                                    @endif
                                    <a href="{{ route('admin.orders.show', $shipment->order_id) }}" class="df-action-btn" title="View order">
                                        <i class="bi bi-receipt"></i>
                                    </a>
                                    @if($shipment->trackingEvents->isNotEmpty())
                                        <button class="df-action-btn" type="button" data-bs-toggle="collapse"
                                                data-bs-target="#shipmentEvents{{ $shipment->id }}" title="Tracking history">
                                            <i class="bi bi-clock-history"></i>
                                        </button>
                                    @endif
                                </div>
                                @if($shipment->trackingEvents->isNotEmpty())
                                    <div class="collapse mt-2" id="shipmentEvents{{ $shipment->id }}">
                                        @foreach($shipment->trackingEvents->take(5) as $event)
                                            <div style="font-size:0.78rem; color:var(--df-text-secondary); margin-bottom:3px;">
                                                <strong>{{ $event->status_label ?: $event->status_code }}</strong>
                                                @if($event->location) · {{ $event->location }} @endif
                                                @if($event->scanned_at) · {{ \Carbon\Carbon::parse($event->scanned_at)->format('M d, H:i') }} @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="df-empty-state">
                                    <div class="empty-icon"><i class="bi bi-truck"></i></div>
                                    <p>No shipments found for these filters.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($shipments->hasPages())
    <div class="mt-3">{{ $shipments->links() }}</div>
@endif

@endsection