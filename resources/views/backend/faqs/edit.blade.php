@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Edit FAQ</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <a href="{{ route('admin.faqs.index') }}">FAQs</a>
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

<div class="df-card">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-question-circle"></i> FAQ Details</h5>
    </div>
    <div class="df-card-body">
        <form action="{{ route('admin.faqs.update', $faq) }}" method="POST" class="row g-4">
            @csrf
            @method('PUT')
            <div class="col-md-8">
                <label class="df-form-label">Question <span class="text-danger">*</span></label>
                <input type="text" name="question" class="df-form-control" value="{{ old('question', $faq->question) }}" required>
            </div>
            <div class="col-md-4">
                <label class="df-form-label">Sort Order</label>
                <input type="number" min="0" name="sort_order" class="df-form-control" value="{{ old('sort_order', $faq->sort_order) }}">
            </div>
            <div class="col-12">
                <label class="df-form-label">Answer <span class="text-danger">*</span></label>
                <textarea name="answer" rows="5" class="df-form-control" required>{{ old('answer', $faq->answer) }}</textarea>
            </div>
            <div class="col-12">
                <label class="d-inline-flex align-items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $faq->is_active) ? 'checked' : '' }}>
                    <span class="df-form-label mb-0">Active</span>
                </label>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="df-btn df-btn-primary"><i class="bi bi-check2-circle"></i> Update FAQ</button>
                <a href="{{ route('admin.faqs.index') }}" class="df-btn df-btn-light"><i class="bi bi-arrow-left"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>

@endsection
