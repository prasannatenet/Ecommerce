@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Coupons</h1>
    <div class="d-flex align-items-center gap-3">
        <nav class="df-breadcrumb d-none d-md-flex">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Coupons</span>
        </nav>
        <a href="{{ route('admin.coupons.create') }}" class="df-btn df-btn-primary">
            <i class="bi bi-plus-lg"></i> Add Coupon
        </a>
    </div>
</div>

@if(session('success'))
    <div class="df-alert df-alert-success">
        <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
    </div>
@endif

<div class="df-card">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-ticket-perforated"></i> All Coupons</h5>
        <span class="df-badge df-badge-muted">{{ $coupons->total() }} coupons</span>
    </div>
    <div class="df-card-body-flush">
        <div class="table-responsive">
            <table class="df-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Type</th>
                        <th>Discount</th>
                        <th>Usage</th>
                        <th>Min Order</th>
                        <th>Status</th>
                        <th>Valid Period</th>
                        <th style="width:130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($coupons as $coupon)
                        <tr>
                            <td>
                                <code style="font-size:0.88rem; font-weight:700; color:var(--df-primary); background:var(--df-bg-secondary); padding:4px 10px; border-radius:6px;">{{ $coupon->code }}</code>
                            </td>
                            <td>
                                @if($coupon->type === 'percent')
                                    <span class="df-badge df-badge-purple">Percent</span>
                                @elseif($coupon->type === 'fixed')
                                    <span class="df-badge df-badge-info">Fixed</span>
                                @elseif($coupon->type === 'buy_get')
                                    <span class="df-badge df-badge-info">Buy X Get Y</span>
                                @else
                                    <span class="df-badge df-badge-info">Gehna Coins</span>
                                @endif
                            </td>
                            <td>
                                <span style="font-weight:700; font-size:1rem;">
                                    @if($coupon->type === 'percent')
                                        {{ rtrim(rtrim(number_format($coupon->amount, 2), '0'), '.') }}%
                                    @elseif($coupon->type === 'fixed')
                                        ₹{{ number_format($coupon->amount, 2) }}
                                    @elseif($coupon->type === 'buy_get')
                                        Buy {{ $coupon->buy_quantity }}, Get {{ $coupon->get_quantity }}
                                    @else
                                        {{ number_format($coupon->reward_coins) }} coins
                                    @endif
                                </span>
                            </td>
                            <td>
                                <span class="df-badge df-badge-info">{{ $coupon->used_count }}</span>
                                @if($coupon->max_uses)
                                    <span style="color:var(--df-text-secondary); font-size:0.82rem;"> / {{ $coupon->max_uses }}</span>
                                @else
                                    <span style="color:var(--df-text-secondary); font-size:0.78rem;"> / ∞</span>
                                @endif
                            </td>
                            <td>
                                @if($coupon->min_order_amount)
                                    <span style="font-size:0.85rem;">₹{{ number_format($coupon->min_order_amount, 2) }}</span>
                                @else
                                    <span style="color:var(--df-text-secondary);">—</span>
                                @endif
                            </td>
                            <td>
                                @if($coupon->is_active)
                                    <span class="df-badge df-badge-success">Active</span>
                                @else
                                    <span class="df-badge df-badge-danger">Inactive</span>
                                @endif
                            </td>
                            <td style="font-size:0.82rem; color:var(--df-text-secondary);">
                                <div>{{ $coupon->starts_at ? $coupon->starts_at->format('M d, Y') : '—' }}</div>
                                <div style="font-size:0.75rem;">to {{ $coupon->expires_at ? $coupon->expires_at->format('M d, Y') : 'No expiry' }}</div>
                            </td>
                            <td>
                                <div class="df-actions">
                                    <a href="{{ route('admin.coupons.edit', $coupon) }}" class="df-action-btn warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route('admin.coupons.destroy', $coupon) }}" method="POST"
                                          style="display:inline" onsubmit="return confirm('Delete this coupon?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="df-action-btn danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="df-empty-state">
                                    <div class="empty-icon"><i class="bi bi-ticket-perforated"></i></div>
                                    <p>No coupons created yet. <a href="{{ route('admin.coupons.create') }}">Create your first coupon</a></p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($coupons->hasPages())
    <div class="mt-3">{{ $coupons->links() }}</div>
@endif

@endsection
