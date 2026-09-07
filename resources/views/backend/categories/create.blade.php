@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Add Category</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <a href="{{ route('admin.categories.index') }}">Category</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <span class="current">Add Category</span>
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
        <form action="{{ route('admin.categories.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="df-card">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-folder2-open"></i> Category Details</h5>
                </div>
                <div class="df-card-body">
                    <div class="mb-3">
                        <label class="df-form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="df-form-control" value="{{ old('name') }}"
                               placeholder="e.g. Electronics" required>
                    </div>
                    <div class="mb-3">
                        <label class="df-form-label">Slug <span class="text-danger">*</span></label>
                        <input type="text" name="slug" class="df-form-control" value="{{ old('slug') }}"
                               placeholder="Auto-generated from name">
                    </div>
                    <div class="mb-3">
                        <label class="df-form-label">Parent Category</label>
                        <select name="parent_id" class="df-form-select">
                            <option value="">— None (Top Level) —</option>
                            @foreach($categories as $cat)
                                @include('backend.categories.partials.category-options', ['category' => $cat, 'level' => 0, 'selected' => old('parent_id')])
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="df-form-label">Description</label>
                        <textarea name="description" class="df-form-control" rows="3"
                                  placeholder="Category description">{{ old('description') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <div class="df-form-check">
                            <input type="checkbox" name="is_final" value="1" id="isFinal" {{ old('is_final') ? 'checked' : '' }}>
                            <label for="isFinal">Final Category (products can be assigned only to this)</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="df-card">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-image"></i> Category Image</h5>
                </div>
                <div class="df-card-body">
                    <div class="df-upload-zone" onclick="document.getElementById('cat-image').click()">
                        <div class="upload-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                        <p>Drop your image here or <span class="browse-link">click to browse</span></p>
                    </div>
                    <input type="file" id="cat-image" name="image" accept="image/*" style="display:none">
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="df-btn df-btn-primary">
                    <i class="bi bi-check2-circle"></i> Create Category
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
                <h5 class="df-card-title"><i class="bi bi-lightbulb"></i> Category Hierarchy</h5>
            </div>
            <div class="df-card-body">
                <p style="color:var(--df-text-secondary); font-size:0.88rem;">
                    Categories support nesting to create a hierarchy, for example:
                </p>
                <div style="background:var(--df-body-bg); padding:14px 18px; border-radius:10px; font-size:0.85rem; color:var(--df-text-secondary);">
                    📁 Electronics<br>
                    &nbsp;&nbsp;📁 Mobiles<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;📄 Android<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;📄 iPhone<br>
                    &nbsp;&nbsp;📁 Laptops<br>
                    📁 Clothing
                </div>
            </div>
        </div>
    </div>
</div>

@endsection