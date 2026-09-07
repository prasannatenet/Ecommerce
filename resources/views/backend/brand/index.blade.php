@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Brands</h1>
    <div class="d-flex align-items-center gap-3">
        <nav class="df-breadcrumb d-none d-md-flex">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Brands</span>
        </nav>
        <a href="{{ route('admin.brands.create') }}" class="df-btn df-btn-primary">
            <i class="bi bi-plus-lg"></i> Add Brand
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
        <h5 class="df-card-title"><i class="bi bi-award"></i> All Brands</h5>
        <span class="df-badge df-badge-muted">{{ $brands->count() }} brands</span>
    </div>
    <div class="df-card-body-flush">
        <div class="table-responsive">
            <table class="df-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th style="width:70px;">Logo</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Products</th>
                        <th>Description</th>
                        <th>Created</th>
                        <th style="width:160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($brands as $b)
                        <tr>
                            <td>{{ $b->id }}</td>
                            <td>
                                @if($b->logo)
                                    <img src="{{ asset('storage/' . $b->logo) }}" alt="{{ $b->name }}"
                                         style="width:44px; height:44px; object-fit:contain; border-radius:8px; background:var(--df-bg-secondary); padding:4px;">
                                @else
                                    <div style="width:44px; height:44px; border-radius:8px; background:var(--df-bg-secondary); display:flex; align-items:center; justify-content:center;">
                                        <i class="bi bi-award" style="font-size:1.2rem; color:var(--df-text-secondary);"></i>
                                    </div>
                                @endif
                            </td>
                            <td><span style="font-weight:600;">{{ $b->name }}</span></td>
                            <td><code style="font-size:0.82rem; color:var(--df-text-secondary);">{{ $b->slug }}</code></td>
                            <td><span class="df-badge df-badge-info">{{ $b->products_count ?? $b->products->count() }}</span></td>
                            <td style="color:var(--df-text-secondary);">{{ Str::limit($b->description, 40) ?: '—' }}</td>
                            <td style="color:var(--df-text-secondary); font-size:0.85rem;">{{ $b->created_at->format('M d, Y') }}</td>
                            <td>
                                <div class="df-actions">
                                    <a href="{{ route('admin.brands.show', $b) }}" class="df-action-btn info" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('admin.brands.edit', $b) }}" class="df-action-btn warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.brands.destroy', $b) }}"
                                          style="display:inline" onsubmit="return confirm('Delete this brand?')">
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
                                    <div class="empty-icon"><i class="bi bi-award"></i></div>
                                    <p>No brands created yet. <a href="{{ route('admin.brands.create') }}">Create your first brand</a></p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
