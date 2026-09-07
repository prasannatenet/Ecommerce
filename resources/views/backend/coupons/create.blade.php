@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Create Coupon</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <a href="{{ route('admin.coupons.index') }}">Coupons</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <span class="current">Create</span>
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
                <form action="{{ route('admin.coupons.store') }}" method="POST">
                    @csrf
                    @include('backend.coupons.partials.form', ['coupon' => null])

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="df-btn df-btn-primary">
                            <i class="bi bi-check2-circle"></i> Create Coupon
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
                <h5 class="df-card-title"><i class="bi bi-lightbulb"></i> Tips</h5>
            </div>
            <div class="df-card-body">
                <p style="color:var(--df-text-secondary); font-size:0.88rem; margin-bottom:12px;">
                    <strong>Percent discount</strong> reduces by a percentage of the order total.
                    <strong>Fixed discount</strong> reduces by a flat amount. <strong>Buy X Get Y</strong> discounts the free quantity, and <strong>Gehna Coins</strong> records coins as an order reward.
                </p>
                <p style="color:var(--df-text-secondary); font-size:0.88rem; margin:0;">
                    Leave "Max Uses" empty for unlimited usage. Set a "Min Order Amount" to prevent coupon abuse on small orders.
                </p>
            </div>
        </div>
    </div>
</div>

@endsection
