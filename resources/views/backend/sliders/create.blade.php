@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Add Slide</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <a href="{{ route('admin.sliders.index') }}">Sliders</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <span class="current">Add Slide</span>
    </nav>
</div>

@if($errors->any())
    <div class="df-alert df-alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="df-card">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-images"></i> Slide Details</h5>
    </div>
    <div class="df-card-body">
        <form action="{{ route('admin.sliders.store') }}" method="POST" enctype="multipart/form-data" class="row g-4">
            @csrf
            <div class="col-md-8">
                <label class="df-form-label">Title <span class="text-danger">*</span></label>
                <input type="text" name="title" class="df-form-control" value="{{ old('title') }}" required>
            </div>
            <div class="col-md-4">
                <label class="df-form-label">Sort Order</label>
                <input type="number" min="0" name="sort_order" class="df-form-control" value="{{ old('sort_order', 0) }}">
            </div>
            <div class="col-12">
                <label class="df-form-label">Subheading</label>
                <textarea name="subheading" rows="3" class="df-form-control" placeholder="Short description for hero section">{{ old('subheading') }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="df-form-label">Button CTA Text</label>
                <input type="text" name="button_text" class="df-form-control" value="{{ old('button_text') }}" placeholder="e.g. Shop Now">
            </div>
            <div class="col-md-6">
                <label class="df-form-label">Button Link</label>
                <input type="url" name="button_link" class="df-form-control" value="{{ old('button_link') }}" placeholder="https://example.com/products">
            </div>
            <div class="col-12">
                <label class="df-form-label">Slider Image</label>
                <input type="file" name="image" class="df-form-control" accept="image/*">
                <p class="df-form-hint">Upload the complete hero artwork. Recommended size: 1920x445px. Wider images work best.</p>
            </div>
            <div class="col-12">
                <label class="d-inline-flex align-items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                    <span class="df-form-label mb-0">Active</span>
                </label>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="df-btn df-btn-primary"><i class="bi bi-check2-circle"></i> Save Slide</button>
                <a href="{{ route('admin.sliders.index') }}" class="df-btn df-btn-light"><i class="bi bi-arrow-left"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>

@endsection
