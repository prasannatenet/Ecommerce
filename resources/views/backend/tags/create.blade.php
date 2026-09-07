@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Add Tag</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <a href="{{ route('admin.tags.index') }}">Tags</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <span class="current">Add Tag</span>
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
                <h5 class="df-card-title"><i class="bi bi-bookmark-star"></i> Tag Details</h5>
            </div>
            <div class="df-card-body">
                <form action="{{ route('admin.tags.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="df-form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="df-form-control" value="{{ old('name') }}"
                               placeholder="e.g. Summer Collection" required>
                    </div>
                    <div class="mb-3">
                        <label class="df-form-label">Slug <span class="text-danger">*</span></label>
                        <input type="text" name="slug" class="df-form-control" value="{{ old('slug') }}"
                               placeholder="Auto-generated from name">
                        <p class="df-form-hint">Leave blank to auto-generate from name.</p>
                    </div>
                    <div class="mb-4">
                        <label class="df-form-label">Description</label>
                        <textarea name="description" class="df-form-control" rows="3"
                                  placeholder="Optional tag description">{{ old('description') }}</textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="df-btn df-btn-primary">
                            <i class="bi bi-check2-circle"></i> Create Tag
                        </button>
                        <a href="{{ route('admin.tags.index') }}" class="df-btn df-btn-light">
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
                <h5 class="df-card-title"><i class="bi bi-lightbulb"></i> Tips</h5>
            </div>
            <div class="df-card-body">
                <p style="color:var(--df-text-secondary); font-size:0.88rem; margin:0;">
                    Tags work like WordPress taxonomies — they let you group and filter products across categories.
                    Use descriptive names like "Summer Sale", "Best Seller", "New Arrival".
                </p>
            </div>
        </div>
    </div>
</div>

@endsection
