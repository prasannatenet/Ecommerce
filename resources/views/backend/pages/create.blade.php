@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Create Page</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <a href="{{ route('admin.pages.index') }}">Pages</a>
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

<div class="df-card">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-file-earmark-text"></i> Page Details</h5>
    </div>
    <div class="df-card-body">
        <form action="{{ route('admin.pages.store') }}" method="POST">
            @csrf
            @include('backend.pages.partials.form', ['page' => null])

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="df-btn df-btn-primary">
                    <i class="bi bi-check2-circle"></i> Create Page
                </button>
                <a href="{{ route('admin.pages.index') }}" class="df-btn df-btn-light">
                    <i class="bi bi-arrow-left"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

@endsection
