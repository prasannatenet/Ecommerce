@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Add Delivery Partner</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <a href="{{ route('admin.delivery-partners.index') }}">Delivery Partners</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <span class="current">Add Partner</span>
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
                <h5 class="df-card-title"><i class="bi bi-truck"></i> Partner Details</h5>
            </div>
            <div class="df-card-body">
                <form action="{{ route('admin.delivery-partners.store') }}" method="POST">
                    @csrf
                    @include('backend.delivery_partners.partials.form', ['partner' => null])

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="df-btn df-btn-primary">
                            <i class="bi bi-check2-circle"></i> Create Partner
                        </button>
                        <a href="{{ route('admin.delivery-partners.index') }}" class="df-btn df-btn-light">
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
                    Delivery partners handle the shipping of orders to customers. Add their name, unique code, and contact information for easy tracking and communication.
                </p>
            </div>
        </div>
    </div>
</div>

@endsection
