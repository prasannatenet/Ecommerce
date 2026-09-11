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
        width: 140px;
        height: 78px;
        object-fit: cover;
        border: 1px solid var(--df-border-color);
        border-radius: 6px;
        background: var(--df-bg-secondary);
    }
    .media-preview-thumb.video-thumb {
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--df-text-secondary);
        font-size: 1.6rem;
    }
    .section-key-badge {
        font-size: 0.72rem;
        padding: 2px 10px;
        border-radius: 999px;
        background: var(--df-bg-secondary);
        color: var(--df-text-secondary);
        border: 1px solid var(--df-border-color);
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

@if($errors->any())
    <div class="df-alert df-alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="home-section-edit-intro">
    <div>
        <h2>Edit {{ $homeSection->isFeaturedSection() ? 'Featured Promo' : 'Large Image Section' }}</h2>
        <p>Section key: <code>{{ $homeSection->section_key }}</code> &mdash; this identifier is fixed and cannot be changed.</p>
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
                <div class="df-form-control" style="background: var(--df-bg-secondary); cursor: not-allowed;">{{ $homeSection->section_key }}</div>
                <p class="df-form-hint">This is the fixed identifier for this section and cannot be changed.</p>
            </div>

            <!-- Featured-specific: text fields -->
            @if($homeSection->isFeaturedSection())
                <div class="col-md-6">
                    <label class="df-form-label">Title</label>
                    <input type="text" name="title" class="df-form-control" value="{{ old('title', $homeSection->title) }}" placeholder="Earring Collection">
                    <p class="df-form-hint">Heading text for the featured section.</p>
                </div>
                <div class="col-md-6">
                    <label class="df-form-label">Subtext</label>
                    <textarea name="subtext" rows="3" class="df-form-control" placeholder="Discover our latest collection of handcrafted jewellery.">{{ old('subtext', $homeSection->subtext) }}</textarea>
                    <p class="df-form-hint">Short description under the heading.</p>
                </div>
                <div class="col-md-6">
                    <label class="df-form-label">CTA Button Text</label>
                    <input type="text" name="cta_text" class="df-form-control" value="{{ old('cta_text', $homeSection->cta_text) }}" placeholder="Explore Collection">
                    <p class="df-form-hint">e.g. "Explore Collection"</p>
                </div>
                <div class="col-md-6">
                    <label class="df-form-label">CTA Button Link</label>
                    <input type="url" name="cta_link" class="df-form-control" value="{{ old('cta_link', $homeSection->cta_link) }}" placeholder="https://example.com/products or /categories">
                    <p class="df-form-hint">e.g. "/categories" or an external URL.</p>
                </div>
            @endif

            <!-- Sort order -->
            <div class="col-md-4">
                <label class="df-form-label">Sort Order</label>
                <input type="number" min="0" name="sort_order" class="df-form-control" value="{{ old('sort_order', $homeSection->sort_order) }}">
            </div>

            <!-- Active toggle -->
            <div class="col-md-4">
                <label class="d-inline-flex align-items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $homeSection->is_active) ? 'checked' : '' }}>
                    <span class="df-form-label mb-0">Active</span>
                </label>
                <p class="df-form-hint mt-1">When inactive, the section falls back to default assets.</p>

            <!-- Featured-specific: media uploads -->
            @if($homeSection->isFeaturedSection())
                <div class="col-md-6">
                    <label class="df-form-label">Video (MP4)</label>
                    <input type="file" name="video" class="df-form-control" accept="video/mp4">
                    <p class="df-form-hint">Max 50MB. Leave empty to keep current video.</p>
                    @if($homeSection->video_path)
                        <div class="mt-2">
                            <video src="{{ asset('storage/' . $homeSection->video_path) }}" class="media-preview-thumb" style="width:100%;height:auto;max-height:120px;" controls></video>
                            <p class="df-form-hint mt-1">Current video</p>
                        </div>
                    @endif
                </div>
                <div class="col-md-6">
                    <label class="df-form-label">Poster / Ad Image</label>
                    <input type="file" name="poster_image" class="df-form-control" accept="image/*">
                    <p class="df-form-hint">Max 5MB. JPEG, PNG, WebP. Leave empty to keep current image.</p>
                    @if($homeSection->poster_image_path)
                        <div class="mt-2">
                            <img src="{{ asset('storage/' . $homeSection->poster_image_path) }}" class="media-preview-thumb" alt="Poster">
                            <p class="df-form-hint mt-1">Current poster</p>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Large image section: media upload -->
            @if($homeSection->isLargeImageSection())
                <div class="col-md-6">
                    <label class="df-form-label">Large Image</label>
                    <input type="file" name="large_image" class="df-form-control" accept="image/*">
                    <p class="df-form-hint">Max 5MB. JPEG, PNG, WebP. Leave empty to keep current image.</p>
                    @if($homeSection->large_image_path)
                        <div class="mt-2">
                            <img src="{{ asset('storage/' . $homeSection->large_image_path) }}" class="media-preview-thumb" alt="Large image">
                            <p class="df-form-hint mt-1">Current large image</p>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Submit -->
            <div class="col-12">
                <button type="submit" class="df-btn df-btn-primary">
                    <i class="bi bi-check-lg"></i> Update Section
                </button>
                <a href="{{ route('admin.home-sections.index') }}" class="df-btn df-btn-outline">
                    <i class="bi bi-x-lg"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

@endsection
