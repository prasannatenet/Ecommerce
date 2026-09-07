@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Add Payment Provider</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <a href="{{ route('admin.payment-providers.index') }}">Payment Providers</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <span class="current">Add Provider</span>
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
                <form action="{{ route('admin.payment-providers.store') }}" method="POST">
                    @csrf
                    @include('backend.payment_providers.partials.form', ['provider' => null])

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="df-btn df-btn-primary">
                            <i class="bi bi-check2-circle"></i> Create Provider
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
                <h5 class="df-card-title"><i class="bi bi-lightbulb"></i> Tips</h5>
            </div>
            <div class="df-card-body">
                <p style="color:var(--df-text-secondary); font-size:0.88rem; margin-bottom:12px;">
                    The <strong>slug</strong> is used internally to identify the payment gateway (e.g. <code>stripe</code>, <code>razorpay</code>).
                </p>
                <p style="color:var(--df-text-secondary); font-size:0.88rem; margin:0;">
                    Store your API keys securely. The secret key will be masked in the listing view for security.
                </p>
            </div>
        </div>
    </div>
</div>

@endsection
