@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Profile Settings</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <span class="current">Profile</span>
    </nav>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="df-card h-100">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-person-badge"></i> Profile Information</h5>
            </div>
            <div class="df-card-body">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="df-card h-100">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-shield-lock"></i> Update Password</h5>
            </div>
            <div class="df-card-body">
                @include('profile.partials.update-password-form')
            </div>
        </div>
    </div>

    <div class="col-12 mt-4">
        <div class="df-card" style="border-color:var(--df-danger); border-left:4px solid var(--df-danger);">
            <div class="df-card-header">
                <h5 class="df-card-title text-danger"><i class="bi bi-exclamation-triangle"></i> Danger Zone</h5>
            </div>
            <div class="df-card-body">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</div>

@endsection
