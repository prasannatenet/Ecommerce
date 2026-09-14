@extends('layouts.app')
@section('content')

@push('styles')
<style>
    .home-section-edit-intro {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 18px 20px;
        margin-bottom: 20px;
        background: linear-gradient(110deg, #fff8f1, #fff);
        border: 1px solid #f0dfcf;
        border-radius: 12px;
    }
    .home-section-edit-intro h2 {
        margin: 0 0 4px;
        color: #2a2020;
        font-size: 1.05rem;
        font-weight: 700;
    }
    .home-section-edit-intro p {
        margin: 0;
        color: #756a66;
        font-size: 0.86rem;
    }
    .media-preview-thumb {
        width: 100%;
        max-width: 200px;
        height: auto;
        max-height: 120px;
        object-fit: cover;
        border: 1px solid var(--df-border-color);
        border-radius: 8px;
        background: var(--df-bg-secondary);
    }
    .section-key-badge {
        font-size: 0.72rem;
        padding: 4px 12px;
        border-radius: 999px;
        background: var(--df-primary-light);
        color: var(--df-primary);
        border: 1px solid var(--df-primary);
        font-weight: 600;
    }
    .section-type-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }
    .section-type-icon.featured {
        background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        color: #2e7d32;
    }
    .section-type-icon.large-image {
        background: linear-gradient(135deg, #e3f2fd, #bbdefb);
        color: #1565c0;
    }
    .preview-card {
        background: #f8f9fa;
        border: 1px solid var(--df-border-color);
        border-radius: 8px;
        padding: 12px;
        margin-top: 8px;
    }
    .preview-card img,
    .preview-card video {
        width: 100%;
        max-height: 150px;
        object-fit: cover;
        border-radius: 6px;
    }
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 26px;
    }
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: 0.3s;
        border-radius: 26px;
    }
    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 20px;
        width: 20px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: 0.3s;
        border-radius: 50%;
    }
    input:checked + .toggle-slider {
        background-color: var(--df-primary);
    }
    input:checked + .toggle-slider:before {
        transform: translateX(24px);
    }
    .file-upload-info {
        font-size: 0.75rem;
        color: var(--df-text-secondary);
        margin-top: 4px;
    }
</style>
@endpush

<div class="df-page-header">
    <h1 class="df-page-title">Edit Home Section</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <a href="{{ route('admin.home-sections.index') }}">Home Sections</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <span class="current">Edit</span>
    </nav>
</div>

@if(session('success'))
    <div class="df-alert df-alert-success">
        <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="df-alert df-alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="home-section-edit-intro">
    <div class="d-flex align-items-center gap-3">
        <div class="section-type-icon {{ $homeSection->section_key }}">
            @if($homeSection->isFeaturedSection())
                <i class="bi bi-play-circle"></i>
            @else
                <i class="bi bi-image"></i>
            @endif
        </div>
        <div>
            <h2>{{ $homeSection->isFeaturedSection() ? 'Featured Promo Section' : 'Large Image Section' }}</h2>
            <p>Section key: <code>{{ $homeSection->section_key }}</code> &mdash; this identifier is fixed and cannot be changed.</p>
        </div>
    </div>
    <span class="section-key-badge">{{ $homeSection->section_key }}</span>
</div>

<div class="df-card">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-gear"></i> Section Details</h5>
    </div>
    <div class="df-card-body">
        <form action="{{ route('admin.home-sections.update', $homeSection) }}" method="POST" enctype="multipart/form-data" class="row g-4">
            @csrf
            @method('PUT')

            <!-- Section key (read-only display) -->
            <div class="col-12">
                <label class="df-form-label">Section Key</label>
                <div class="df-form-control" style="background: var(--df-bg-secondary); cursor: not-allowed;">
                    <span class="d-inline-flex align-items-center gap-2">
                        <i class="bi bi-key"></i>
                        {{ $homeSection->section_key }}
                    </span>
                </div>
                <p class="df-form-hint">This is the fixed identifier for this section and cannot be changed.</p>
            </div>

            <!-- Category selection for all sections -->
            <div class="col-md-6">
                <label class="df-form-label">Category Filter</label>
                <select name="category_name" class="df-form-select" id="categorySelect">
                    <option value="">-- Select Category --</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->name }}" {{ $homeSection->category_name == $category->name ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
                <p class="df-form-hint">Select a category to filter products for this section. Leave empty to hide products.</p>
            </div>

            <!-- Sort order -->
            <div class="col-md-6">
                <label class="df-form-label">Sort Order</label>
                <input type="number" min="0" name="sort_order" class="df-form-control" value="{{ $homeSection->sort_order }}">
                <p class="df-form-hint">Lower numbers appear first.</p>
            </div>

            <!-- Featured-specific: text fields -->
            @if($homeSection->isFeaturedSection())
                <div class="col-md-6">
                    <label class="df-form-label">Title</label>
                    <input type="text" name="title" class="df-form-control" value="{{ $homeSection->title }}" placeholder="Earring Collection">
                    <p class="df-form-hint">Heading text for the featured section.</p>
                </div>
                <div class="col-md-6">
                    <label class="df-form-label">Subtext</label>
                    <textarea name="subtext" rows="2" class="df-form-control" placeholder="Discover our latest collection of handcrafted jewellery.">{{ $homeSection->subtext }}</textarea>
                    <p class="df-form-hint">Short description under the heading.</p>
                </div>
                <div class="col-md-6">
                    <label class="df-form-label">CTA Button Text</label>
                    <input type="text" name="cta_text" class="df-form-control" value="{{ $homeSection->cta_text }}" placeholder="Explore Collection">
                    <p class="df-form-hint">e.g. "Explore Collection"</p>
                </div>
                <div class="col-md-6">
                    <label class="df-form-label">CTA Button Link</label>
                    <input type="url" name="cta_link" class="df-form-control" value="{{ $homeSection->cta_link }}" placeholder="https://example.com/products or /categories">
                    <p class="df-form-hint">e.g. "/categories" or an external URL.</p>
                </div>
            @endif

            <!-- Active toggle -->
            <div class="col-12">
                <label class="df-form-label d-block">Status</label>
                <div class="d-flex align-items-center gap-3">
                    <label class="toggle-switch">
                        <input type="checkbox" name="is_active" value="1" {{ $homeSection->is_active ? 'checked' : '' }}>
                        <span class="toggle-slider"></span>
                    </label>
                    <span class="badge {{ $homeSection->is_active ? 'bg-success' : 'bg-secondary' }}">
                        {{ $homeSection->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <p class="df-form-hint mt-2">When inactive, the section will be hidden from the homepage.</p>
            </div>

            <!-- Featured-specific: media uploads -->
            @if($homeSection->isFeaturedSection())
                <div class="col-md-6">
                    <label class="df-form-label">Video (MP4)</label>
                    <input type="file" name="video" class="df-form-control" accept="video/mp4">
                    <p class="file-upload-info"><i class="bi bi-info-circle"></i> Max 50MB. Leave empty to keep current video.</p>
                    @if($homeSection->video_path)
                        <div class="preview-card">
                            <video src="{{ asset('storage/' . $homeSection->video_path) }}" controls></video>
                            <p class="df-form-hint mt-2 mb-0"><i class="bi bi-play-circle"></i> Current video</p>
                        </div>
                    @endif
                </div>
                <div class="col-md-6">
                    <label class="df-form-label">Poster / Ad Image</label>
                    <input type="file" name="poster_image" class="df-form-control" accept="image/*">
                    <p class="file-upload-info"><i class="bi bi-info-circle"></i> Max 5MB. JPEG, PNG, WebP. Leave empty to keep current image.</p>
                    @if($homeSection->poster_image_path)
                        <div class="preview-card">
                            <img src="{{ asset('storage/' . $homeSection->poster_image_path) }}" alt="Poster">
                            <p class="df-form-hint mt-2 mb-0"><i class="bi bi-image"></i> Current poster</p>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Large image section: media upload -->
            @if($homeSection->isLargeImageSection())
                <div class="col-md-6">
                    <label class="df-form-label">Large Image</label>
                    <input type="file" name="large_image" class="df-form-control" accept="image/*">
                    <p class="file-upload-info"><i class="bi bi-info-circle"></i> Max 5MB. JPEG, PNG, WebP. Leave empty to keep current image.</p>
                    @if($homeSection->large_image_path)
                        <div class="preview-card">
                            <img src="{{ asset('storage/' . $homeSection->large_image_path) }}" alt="Large image">
                            <p class="df-form-hint mt-2 mb-0"><i class="bi bi-image"></i> Current large image</p>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Submit -->
            <div class="col-12">
                <hr>
                <div class="d-flex justify-content-between align-items-center">
                    <a href="{{ route('admin.home-sections.index') }}" class="df-btn df-btn-outline">
                        <i class="bi bi-arrow-left"></i> Back to List
                    </a>
                    <button type="submit" class="df-btn df-btn-primary">
                        <i class="bi bi-check-lg"></i> Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection