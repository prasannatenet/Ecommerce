@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">{{ $brand->name }}</h1>
    <div class="d-flex align-items-center gap-3">
        <nav class="df-breadcrumb d-none d-md-flex">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="{{ route('admin.brands.index') }}">Brands</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">{{ $brand->name }}</span>
        </nav>
        <a href="{{ route('admin.brands.edit', $brand) }}" class="df-btn df-btn-primary">
            <i class="bi bi-pencil"></i> Edit Brand
        </a>
    </div>
</div>

<div class="row g-4">
    {{-- Brand Info Card --}}
    <div class="col-lg-4">
        <div class="df-card">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-award"></i> Brand Info</h5>
            </div>
            <div class="df-card-body" style="text-align:center;">
                @if($brand->logo)
                    <img src="{{ asset('storage/' . $brand->logo) }}" alt="{{ $brand->name }}"
                         style="width:120px; height:120px; object-fit:contain; border-radius:14px; background:var(--df-bg-secondary); padding:12px; margin-bottom:16px;">
                @else
                    <div style="width:120px; height:120px; border-radius:14px; background:var(--df-bg-secondary); display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">
                        <i class="bi bi-award" style="font-size:3rem; color:var(--df-text-secondary);"></i>
                    </div>
                @endif
                <h4 style="font-weight:700; margin-bottom:4px;">{{ $brand->name }}</h4>
                <p style="color:var(--df-text-secondary); font-size:0.85rem; margin-bottom:16px;">
                    <code style="font-size:0.82rem;">{{ $brand->slug }}</code>
                </p>

                @if($brand->description)
                    <p style="color:var(--df-text-secondary); font-size:0.88rem; text-align:left; margin-bottom:16px;">
                        {{ $brand->description }}
                    </p>
                @endif

                <div style="border-top:1px solid var(--df-border); padding-top:16px;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="df-form-label mb-0">Products</span>
                        <span class="df-badge df-badge-info">{{ $brand->products->count() }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="df-form-label mb-0">Created</span>
                        <span style="font-size:0.85rem; color:var(--df-text-secondary);">{{ $brand->created_at->format('M d, Y') }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="df-form-label mb-0">Updated</span>
                        <span style="font-size:0.85rem; color:var(--df-text-secondary);">{{ $brand->updated_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Products under this Brand --}}
    <div class="col-lg-8">
        <div class="df-card">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-box-seam"></i> Products</h5>
                <span class="df-badge df-badge-muted">{{ $brand->products->count() }} products</span>
            </div>
            <div class="df-card-body-flush">
                <div class="table-responsive">
                    <table class="df-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product Name</th>
                                <th>SKU</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th style="width:80px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($brand->products as $product)
                                <tr>
                                    <td>{{ $product->id }}</td>
                                    <td><span style="font-weight:600;">{{ $product->name }}</span></td>
                                    <td><code style="font-size:0.82rem; color:var(--df-text-secondary);">{{ $product->sku ?? '—' }}</code></td>
                                    <td>
                                        @if($product->sale_price)
                                            <span class="df-price-original">₹{{ number_format($product->base_price, 2) }}</span>
                                            <span class="df-price-sale">₹{{ number_format($product->sale_price, 2) }}</span>
                                        @else
                                            <span class="df-price-regular">₹{{ number_format($product->base_price, 2) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($product->is_active)
                                            <span class="df-badge df-badge-success">Active</span>
                                        @else
                                            <span class="df-badge df-badge-danger">Draft</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.products.show', $product) }}" class="df-action-btn info" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="df-empty-state">
                                            <div class="empty-icon"><i class="bi bi-box-seam"></i></div>
                                            <p>No products under this brand yet.</p>
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
</div>

@endsection