@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Edit Payment Provider</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <a href="{{ route('admin.payment-providers.index') }}">Payment Providers</a>
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
                <h5 class="df-card-title"><i class="bi bi-credit-card-2-front"></i> Provider Details</h5>
            </div>
            <div class="df-card-body">
                <form action="{{ route('admin.payment-providers.update', $provider) }}" method="POST">
                    @csrf
                    @method('PUT')
                    @include('backend.payment_providers.partials.form', ['provider' => $provider])

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="df-btn df-btn-primary">
                            <i class="bi bi-check2-circle"></i> Update Provider
                        </button>
                        <a href="{{ route('admin.payment-providers.index') }}" class="df-btn df-btn-light">
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
                <h5 class="df-card-title"><i class="bi bi-info-circle"></i> Provider Info</h5>
            </div>
            <div class="df-card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">Status</span>
                    @if($provider->is_active)
                        <span class="df-badge df-badge-success">Active</span>
                    @else
                        <span class="df-badge df-badge-danger">Inactive</span>
                    @endif
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">Slug</span>
                    <code style="font-size:0.82rem; color:var(--df-text-secondary);">{{ $provider->slug }}</code>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">Created</span>
                    <span style="font-size:0.85rem; color:var(--df-text-secondary);">{{ $provider->created_at->format('M d, Y') }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="df-form-label mb-0">Updated</span>
                    <span style="font-size:0.85rem; color:var(--df-text-secondary);">{{ $provider->updated_at->format('M d, Y') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
