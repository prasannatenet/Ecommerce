@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">{{ $category->name }}</h1>
    <div class="d-flex align-items-center gap-3">
        <nav class="df-breadcrumb d-none d-md-flex">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <a href="{{ route('admin.categories.index') }}">Category</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">{{ $category->name }}</span>
        </nav>
        <a href="{{ route('admin.categories.edit', $category) }}" class="df-btn df-btn-primary df-btn-sm">
            <i class="bi bi-pencil"></i> Edit
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">

        {{-- Category Info --}}
        <div class="df-card">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-folder2-open"></i> Category Details</h5>
            </div>
            <div class="df-card-body">
                <div class="d-flex align-items-start gap-4">
                    @if($category->image)
                        <img src="{{ asset('storage/' . $category->image) }}" alt="{{ $category->name }}"
                             style="width:100px;height:100px;object-fit:cover;border-radius:14px;border:1px solid var(--df-border-color);">
                    @endif
                    <div>
                        <h5 style="font-weight:700;">{{ $category->name }}</h5>
                        <p class="df-form-hint mb-1"><code>{{ $category->slug }}</code></p>
                        @if($category->description)
                            <p style="color:var(--df-text-secondary);" class="mb-0">{{ $category->description }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Subcategories --}}
        @if($category->children->count())
            <div class="df-card">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-diagram-3"></i> Subcategories</h5>
                    <span class="df-badge df-badge-muted">{{ $category->children->count() }}</span>
                </div>
                <div class="df-card-body-flush">
                    <div class="table-responsive">
                        <table class="df-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Products</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($category->children as $child)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.categories.show', $child) }}" style="font-weight:600; text-decoration:none;">
                                                {{ $child->name }}
                                            </a>
                                        </td>
                                        <td><span class="df-badge df-badge-primary">{{ $child->products->count() }}</span></td>
                                        <td>
                                            <div class="df-actions">
                                                <a href="{{ route('admin.categories.show', $child) }}" class="df-action-btn info"><i class="bi bi-eye"></i></a>
                                                <a href="{{ route('admin.categories.edit', $child) }}" class="df-action-btn warning"><i class="bi bi-pencil"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- Products in this category --}}
        <div class="df-card">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-box-seam"></i> Products</h5>
                <span class="df-badge df-badge-muted">{{ $category->products->count() }}</span>
            </div>
            <div class="df-card-body-flush">
                <div class="table-responsive">
                    <table class="df-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($category->products as $product)
                                <tr>
                                    <td><a href="{{ route('admin.products.show', $product) }}" style="font-weight:600; text-decoration:none;">{{ $product->name }}</a></td>
                                    <td><span class="df-price-regular">₹{{ number_format($product->base_price, 2) }}</span></td>
                                    <td>
                                        <span class="df-badge {{ $product->is_active ? 'df-badge-success' : 'df-badge-danger' }}">
                                            {{ $product->is_active ? 'Active' : 'Draft' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="df-actions">
                                            <a href="{{ route('admin.products.edit', $product) }}" class="df-action-btn warning"><i class="bi bi-pencil"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <div class="df-empty-state py-3">
                                            <p>No products in this category</p>
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

    <div class="col-lg-4">
        {{-- Quick Stats --}}
        <div class="df-card">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-bar-chart-line"></i> Quick Stats</h5>
            </div>
            <div class="df-card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="df-form-label mb-0">Products</span>
                    <span class="df-badge df-badge-primary">{{ $category->products->count() }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="df-form-label mb-0">Subcategories</span>
                    <span class="df-badge df-badge-muted">{{ $category->children->count() }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="df-form-label mb-0">Parent</span>
                    @if($category->parent)
                        <a href="{{ route('admin.categories.show', $category->parent) }}" class="df-badge df-badge-info" style="text-decoration:none;">
                            {{ $category->parent->name }}
                        </a>
                    @else
                        <span style="color:var(--df-text-muted);">Root</span>
                    @endif
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="df-form-label mb-0">Final</span>
                    <span class="df-badge {{ $category->is_final ? 'df-badge-success' : 'df-badge-muted' }}">
                        {{ $category->is_final ? 'Yes' : 'No' }}
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="df-form-label mb-0">Created</span>
                    <span style="font-size:0.85rem; color:var(--df-text-secondary);">{{ $category->created_at->format('M d, Y') }}</span>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="df-card">
            <div class="df-card-body">
                <a href="{{ route('admin.categories.edit', $category) }}" class="df-btn df-btn-primary w-100 mb-2" style="justify-content:center;">
                    <i class="bi bi-pencil"></i> Edit Category
                </a>
                <a href="{{ route('admin.categories.index') }}" class="df-btn df-btn-light w-100" style="justify-content:center;">
                    <i class="bi bi-arrow-left"></i> Back to Categories
                </a>
            </div>
        </div>
    </div>
</div>

@endsection