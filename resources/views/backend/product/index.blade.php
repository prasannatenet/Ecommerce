@extends('layouts.app')
@section('content')

{{-- Page Header --}}
<div class="df-page-header">
    <div>
        <h1 class="df-page-title">All Products</h1>
    </div>
    <div class="d-flex align-items-center gap-3">
        <nav class="df-breadcrumb d-none d-md-flex">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Products</span>
        </nav>
        <a href="{{ route('admin.products.create') }}" class="df-btn df-btn-primary">
            <i class="bi bi-plus-lg"></i> Add Product
        </a>
    </div>
</div>

@if(session('success'))
    <div class="df-alert df-alert-success">
        <i class="bi bi-check-circle-fill"></i>
        {{ session('success') }}
    </div>
@endif

{{-- Filters --}}
<div class="df-card">
    <div class="df-card-body">
        <form method="GET" action="{{ route('admin.products.index') }}" class="df-filter-bar">
            <div class="filter-group" style="flex:2; min-width: 200px;">
                <label class="filter-label">Search</label>
                <input type="text" name="search" class="df-form-control" placeholder="Product name or SKU..."
                       value="{{ request('search') }}">
            </div>
            <div class="filter-group">
                <label class="filter-label">Type</label>
                <select name="type" class="df-form-select">
                    <option value="">All Types</option>
                    <option value="simple" {{ request('type') === 'simple' ? 'selected' : '' }}>Simple</option>
                    <option value="variable" {{ request('type') === 'variable' ? 'selected' : '' }}>Variable</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Category</label>
                <select name="category_id" class="df-form-select">
                    <option value="">All</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Brand</label>
                <select name="brand_id" class="df-form-select">
                    <option value="">All</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}" {{ request('brand_id') == $brand->id ? 'selected' : '' }}>
                            {{ $brand->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Tag</label>
                <select name="tag_id" class="df-form-select">
                    <option value="">All</option>
                    @foreach($tags as $tag)
                        <option value="{{ $tag->id }}" {{ request('tag_id') == $tag->id ? 'selected' : '' }}>
                            {{ $tag->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group" style="flex:0;">
                <label class="filter-label">&nbsp;</label>
                <button type="submit" class="df-btn df-btn-primary df-btn-sm" style="height:42px; width: 42px; padding:0; justify-content:center;">
                    <i class="bi bi-funnel-fill"></i>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Product Table --}}
<div class="df-card">
    <div class="df-card-header">
        <h5 class="df-card-title">
            <i class="bi bi-box-seam"></i> Product List
        </h5>
        <span class="df-badge df-badge-muted">{{ $products->total() }} items</span>
    </div>
    <div class="df-card-body-flush">
        <div class="table-responsive">
            <table class="df-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">Image</th>
                        <th>Product Name</th>
                        <th>Type</th>
                        <th>SKU</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Category</th>
                        <th>Tags</th>
                        <th>Status</th>
                        <th style="width: 130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td>
                                @if($product->images->first())
                                    <img src="{{ asset('storage/' . $product->images->first()->path) }}"
                                         class="df-product-img" alt="{{ $product->name }}">
                                @else
                                    <div class="df-product-img-placeholder">
                                        <i class="bi bi-image"></i>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.products.show', $product) }}" class="df-product-name" style="text-decoration:none;">
                                    {{ $product->name }}
                                </a>
                                @if($product->short_description)
                                    <div class="df-product-desc">{{ Str::limit($product->short_description, 40) }}</div>
                                @endif
                            </td>
                            <td>
                                @if($product->product_type === 'simple')
                                    <span class="df-badge df-badge-info">Simple</span>
                                @else
                                    <span class="df-badge df-badge-purple">Variable</span>
                                @endif
                            </td>
                            <td>
                                <code style="font-size:0.82rem; color: var(--df-text-secondary);">{{ $product->sku ?? '—' }}</code>
                            </td>
                            <td>
                                @if($product->sale_price)
                                    <span class="df-price-original">₹{{ number_format($product->base_price, 2) }}</span>
                                    <br>
                                    <span class="df-price-sale">₹{{ number_format($product->sale_price, 2) }}</span>
                                @else
                                    <span class="df-price-regular">₹{{ number_format($product->base_price, 2) }}</span>
                                @endif
                            </td>
                            <td>
                                @php $stock = $product->total_stock @endphp
                                <span class="df-badge {{ $stock > 10 ? 'df-badge-success' : ($stock > 0 ? 'df-badge-warning' : 'df-badge-danger') }}">
                                    {{ $stock }}
                                </span>
                            </td>
                            <td>
                                <span style="font-size:0.85rem;">{{ $product->category->name ?? '—' }}</span>
                            </td>
                            <td>
                                @foreach($product->tags->take(2) as $tag)
                                    <span class="df-badge df-badge-primary" style="font-size:0.72rem;">{{ $tag->name }}</span>
                                @endforeach
                                @if($product->tags->count() > 2)
                                    <span class="df-badge df-badge-muted" style="font-size:0.72rem;">+{{ $product->tags->count() - 2 }}</span>
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
                                <div class="df-actions">
                                    <a href="{{ route('admin.products.show', $product) }}" class="df-action-btn info" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('admin.products.edit', $product) }}" class="df-action-btn warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.products.destroy', $product) }}"
                                          style="display:inline"
                                          onsubmit="return confirm('Delete this product?')">
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
                            <td colspan="10">
                                <div class="df-empty-state">
                                    <div class="empty-icon"><i class="bi bi-box-seam"></i></div>
                                    <p>No products found. <a href="{{ route('admin.products.create') }}">Create your first product</a></p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    {{ $products->links() }}
</div>
@endsection