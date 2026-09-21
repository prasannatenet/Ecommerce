@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Combo Offers</h1>
    <div class="d-flex align-items-center gap-3">
        <nav class="df-breadcrumb d-none d-md-flex">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Combo Offers</span>
        </nav>
        <a href="{{ route('admin.combos.create') }}" class="df-btn df-btn-primary">
            <i class="bi bi-plus-lg"></i> Add Combo
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
        <h5 class="df-card-title"><i class="bi bi-gift"></i> All Combos</h5>
        <span class="df-badge df-badge-muted">{{ $combos->total() }} combos</span>
    </div>
    <div class="df-card-body-flush">
        <div class="table-responsive">
            <table class="df-table">
                <thead>
                    <tr>
                        <th>Combo</th>
                        <th style="min-width:260px;">Products In Combo</th>
                        <th>Total Price</th>
                        <th>Special Discount</th>
                        <th>Combo Price</th>
                        <th>Status</th>
                        <th>Schedule</th>
                        <th style="width:130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($combos as $combo)
                        @php
                            $isScheduled = $combo->is_active && $combo->starts_at && $combo->starts_at->isFuture();
                            $isExpired = $combo->expires_at && $combo->expires_at->isPast();
                        @endphp
                        <tr>
                            <td>
                                <div style="font-weight:700;">{{ $combo->name }}</div>
                                <code style="font-size:0.75rem; color:var(--df-text-secondary);">{{ $combo->slug }}</code>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    @foreach($combo->products->take(3) as $product)
                                        <div title="{{ $product->name }}" style="width:38px; height:38px; border-radius:8px; overflow:hidden; background:var(--df-bg-secondary); border:2px solid #fff; margin-right:-10px; box-shadow:0 1px 3px rgba(0,0,0,0.12); display:flex; align-items:center; justify-content:center;">
                                            @php $thumb = $product->primary_image ?? optional($product->images->first())->path; @endphp
                                            @if($thumb)
                                                <img src="{{ asset('storage/' . $thumb) }}" alt="{{ $product->name }}" style="width:100%; height:100%; object-fit:cover;">
                                            @else
                                                <i class="bi bi-box-seam" style="color:var(--df-text-secondary); font-size:0.9rem;"></i>
                                            @endif
                                        </div>
                                    @endforeach
                                    @if($combo->products->count() > 3)
                                        <span class="df-badge df-badge-info" style="margin-left:18px !important;">+{{ $combo->products->count() - 3 }} more</span>
                                    @endif
                                    <span class="df-badge df-badge-muted" style="margin-left:18px !important;">{{ $combo->products->count() }} items</span>
                                </div>
                            </td>
                            <td>
                                <span style="text-decoration:line-through; color:var(--df-text-secondary);">₹{{ number_format($combo->productsTotal(), 2) }}</span>
                            </td>
                            <td>
                                <span class="df-badge df-badge-purple" style="font-size:0.8rem; padding:5px 10px;">
                                    @if($combo->discount_type === 'percent')
                                        {{ rtrim(rtrim(number_format($combo->discount_value, 2), '0'), '.') }}% OFF
                                    @else
                                        ₹{{ number_format($combo->discount_value, 2) }} OFF
                                    @endif
                                </span>
                                <div style="font-size:0.78rem; font-weight:600;">Save ₹{{ number_format($combo->discountAmount(), 2) }}</div>
                            </td>
                            <td>
                                <span style="font-weight:800; font-size:1rem; color:var(--df-primary);">₹{{ number_format($combo->comboPrice(), 2) }}</span>
                            </td>
                            <td>
                                @if(!$combo->is_active)
                                    <span class="df-badge df-badge-danger">Inactive</span>
                                @elseif($isExpired)
                                    <span class="df-badge df-badge-muted">Expired</span>
                                @elseif($isScheduled)
                                    <span class="df-badge df-badge-warning">Scheduled</span>
                                @else
                                    <span class="df-badge df-badge-success">Live</span>
                                @endif
                            </td>
                            <td style="font-size:0.82rem; color:var(--df-text-secondary);">
                                <div>{{ $combo->starts_at ? $combo->starts_at->format('M d, Y H:i') : '—' }}</div>
                                <div style="font-size:0.75rem;">to {{ $combo->expires_at ? $combo->expires_at->format('M d, Y H:i') : 'No expiry' }}</div>
                            </td>
                            <td>
                                <div class="df-actions">
                                    <form action="{{ route('admin.combos.toggle', $combo) }}" method="POST" style="display:inline"
                                          onsubmit="return confirm('{{ $combo->is_active ? 'Deactivate' : 'Activate' }} this combo?');">
                                        @csrf
                                        <button class="df-action-btn {{ $combo->is_active ? 'muted' : 'success' }}"
                                                title="{{ $combo->is_active ? 'Deactivate' : 'Activate' }}">
                                            <i class="bi {{ $combo->is_active ? 'bi-pause-fill' : 'bi-play-fill' }}"></i>
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.combos.edit', $combo) }}" class="df-action-btn warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route('admin.combos.destroy', $combo) }}" method="POST"
                                          style="display:inline" onsubmit="return confirm('Delete this combo?');">
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
                                    <div class="empty-icon"><i class="bi bi-gift"></i></div>
                                    <p>No combos created yet. <a href="{{ route('admin.combos.create') }}">Create your first combo offer</a> — pick 2 or more products and give a special discount.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($combos->hasPages())
    <div class="mt-3">{{ $combos->links() }}</div>
@endif

@endsection
