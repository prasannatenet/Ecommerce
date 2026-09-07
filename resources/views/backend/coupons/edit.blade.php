@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Edit Coupon</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <a href="{{ route('admin.coupons.index') }}">Coupons</a>
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
                <h5 class="df-card-title"><i class="bi bi-ticket-perforated"></i> Coupon Details</h5>
            </div>
            <div class="df-card-body">
                <form action="{{ route('admin.coupons.update', $coupon) }}" method="POST">
                    @csrf
                    @method('PUT')
                    @include('backend.coupons.partials.form', ['coupon' => $coupon])

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="df-btn df-btn-primary">
                            <i class="bi bi-check2-circle"></i> Update Coupon
                        </button>
                        <a href="{{ route('admin.coupons.index') }}" class="df-btn df-btn-light">
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
                <h5 class="df-card-title"><i class="bi bi-info-circle"></i> Coupon Info</h5>
            </div>
            <div class="df-card-body">
                <p style="color:var(--df-text-secondary); font-size:0.88rem;">
                    Buy X Get Y coupons apply free units at checkout. Gehna Coins coupons record the configured coin reward with the order.
                </p>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">Code</span>
                    <code style="font-size:0.88rem; font-weight:700; color:var(--df-primary); background:var(--df-bg-secondary); padding:4px 10px; border-radius:6px;">{{ $coupon->code }}</code>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">Status</span>
                    @if($coupon->is_active)
                        <span class="df-badge df-badge-success">Active</span>
                    @else
                        <span class="df-badge df-badge-danger">Inactive</span>
                    @endif
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">Used</span>
                    <span>
                        <span class="df-badge df-badge-info">{{ $coupon->used_count }}</span>
                        @if($coupon->max_uses)
                            <span style="color:var(--df-text-secondary); font-size:0.82rem;"> / {{ $coupon->max_uses }}</span>
                        @endif
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">Created</span>
                    <span style="font-size:0.85rem; color:var(--df-text-secondary);">{{ $coupon->created_at->format('M d, Y') }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="df-form-label mb-0">Updated</span>
                    <span style="font-size:0.85rem; color:var(--df-text-secondary);">{{ $coupon->updated_at->format('M d, Y') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
