@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Edit Brand</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <a href="{{ route('admin.brands.index') }}">Brands</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <span class="current">Edit</span>
    </nav>
</div>

@if($errors->any())
    <div class="df-alert df-alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <ul class="mb-0">
            @foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <div class="df-card">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-award"></i> Brand Details</h5>
            </div>
            <div class="df-card-body">
                <form action="{{ route('admin.brands.update', $brand->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="df-form-label">Brand Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="df-form-control"
                               value="{{ old('name', $brand->name) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="df-form-label">Brand Logo</label>
                        @if($brand->logo)
                            <div class="mb-2">
                                <img src="{{ asset('storage/' . $brand->logo) }}" alt="{{ $brand->name }}"
                                     style="width:80px; height:80px; object-fit:contain; border-radius:10px; background:var(--df-bg-secondary); padding:8px;">
                            </div>
                        @endif
                        <input type="file" name="logo" class="df-form-control" accept="image/*">
                        <p class="df-form-hint">Leave empty to keep the current logo.</p>
                    </div>
                    <div class="mb-4">
                        <label class="df-form-label">Description</label>
                        <textarea name="description" class="df-form-control" rows="3">{{ old('description', $brand->description) }}</textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="df-btn df-btn-primary">
                            <i class="bi bi-check2-circle"></i> Update Brand
                        </button>
                        <a href="{{ route('admin.brands.index') }}" class="df-btn df-btn-light">
                            <i class="bi bi-arrow-left"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="df-card">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-info-circle"></i> Brand Info</h5>
            </div>
            <div class="df-card-body">
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

@endsection