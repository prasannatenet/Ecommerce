@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Edit Category</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <a href="{{ route('admin.categories.index') }}">Category</a>
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
        <form action="{{ route('admin.categories.update', $category) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="df-card">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-folder2-open"></i> Category Details</h5>
                </div>
                <div class="df-card-body">
                    <div class="mb-3">
                        <label class="df-form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="df-form-control"
                               value="{{ old('name', $category->name) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="df-form-label">Slug <span class="text-danger">*</span></label>
                        <input type="text" name="slug" class="df-form-control"
                               value="{{ old('slug', $category->slug) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="df-form-label">Parent Category</label>
                        <select name="parent_id" class="df-form-select">
                            <option value="">— None (Top Level) —</option>
                            @foreach($categories as $cat)
                                @include('backend.categories.partials.category-options', ['category' => $cat, 'level' => 0, 'selected' => old('parent_id', $category->parent_id)])
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="df-form-label">Description</label>
                        <textarea name="description" class="df-form-control" rows="3">{{ old('description', $category->description) }}</textarea>
                    </div>
                    <div class="mb-3">
                        <div class="df-form-check">
                            <input type="checkbox" name="is_final" value="1" id="isFinal"
                                   {{ old('is_final', $category->is_final) ? 'checked' : '' }}>
                            <label for="isFinal">Final Category</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="df-card">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-image"></i> Category Image</h5>
                </div>
                <div class="df-card-body">
                    @if($category->image)
                        <div class="mb-3">
                            <img src="{{ asset('storage/' . $category->image) }}" alt="{{ $category->name }}"
                                 style="width:120px;height:120px;object-fit:cover;border-radius:14px;border:1px solid var(--df-border-color);">
                        </div>
                    @endif
                    <div class="df-upload-zone" onclick="document.getElementById('cat-image').click()">
                        <div class="upload-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                        <p>Drop new image here or <span class="browse-link">click to browse</span></p>
                    </div>
                    <input type="file" id="cat-image" name="image" accept="image/*" style="display:none">
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="df-btn df-btn-primary">
                    <i class="bi bi-check2-circle"></i> Update Category
                </button>
                <a href="{{ route('admin.categories.index') }}" class="df-btn df-btn-light">
                    <i class="bi bi-arrow-left"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <div class="col-lg-4">
        <div class="df-card">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-info-circle"></i> Category Info</h5>
            </div>
            <div class="df-card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">Products</span>
                    <span class="df-badge df-badge-primary">{{ $category->products->count() }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">Subcategories</span>
                    <span class="df-badge df-badge-muted">{{ $category->children->count() }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">Created</span>
                    <span style="font-size:0.85rem; color:var(--df-text-secondary);">{{ $category->created_at->format('M d, Y') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection