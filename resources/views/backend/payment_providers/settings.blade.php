@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Payment Settings</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <span class="current">Payment Settings</span>
    </nav>
</div>

@if(session('success'))
    <div class="df-alert df-alert-success"><i class="bi bi-check-circle-fill"></i> {{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="df-alert df-alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" action="{{ route('admin.payment-providers.settings.update') }}">
    @csrf
    @method('PUT')

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="df-card h-100">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-cash-stack"></i> Cash on Delivery</h5>
                </div>
                <div class="df-card-body">
                    <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                        <input type="checkbox" name="cod_active" value="1" {{ old('cod_active', optional($cod)->is_active) ? 'checked' : '' }}>
                        <span>Enable COD at checkout</span>
                    </label>
                    <p class="mt-3 mb-0" style="color:var(--df-text-secondary); font-size:0.88rem;">Customers can pay at delivery time. Order payment stays pending until you collect it.</p>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="df-card h-100">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-credit-card-2-front"></i> Razorpay</h5>
                </div>
                <div class="df-card-body">
                    <label class="d-flex align-items-center gap-2 mb-3" style="cursor:pointer;">
                        <input type="checkbox" name="razorpay_active" value="1" {{ old('razorpay_active', optional($razorpay)->is_active) ? 'checked' : '' }}>
                        <span>Enable Razorpay at checkout</span>
                    </label>

                    <div class="mb-3">
                        <label class="df-form-label">Public Key</label>
                        <input class="df-form-control" type="text" name="razorpay_public_key" value="{{ old('razorpay_public_key', optional($razorpay)->public_key) }}" placeholder="rzp_live_xxx">
                    </div>
                    <div class="mb-3">
                        <label class="df-form-label">Secret Key</label>
                        <input class="df-form-control" type="text" name="razorpay_secret_key" value="{{ old('razorpay_secret_key', optional($razorpay)->secret_key) }}" placeholder="secret key">
                    </div>
                    <div>
                        <label class="df-form-label">Webhook Secret</label>
                        <input class="df-form-control" type="text" name="razorpay_webhook_secret" value="{{ old('razorpay_webhook_secret', optional($razorpay)->webhook_secret) }}" placeholder="used to verify webhook signature">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <button class="df-btn df-btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Save Payment Settings</button>
    </div>
</form>

@endsection