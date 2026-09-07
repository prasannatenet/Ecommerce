@extends('layouts.frontend')

@section('title', ($page->meta_title ?: $page->title) . ' | ' . ($appSetting->site_name ?? 'GEHNA'))

@section('content')
    <div class="page-header-teal" style="color: white; padding: 28px 50px 44px;">
        <div class="container-fluid">
            <h1 class="page-header-title">{{ $page->title }}</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-gehna">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $page->title }}</li>
                </ol>
            </nav>
        </div>
    </div>

    <section style="background:#f5f5f5;  min-height:55vh; padding: 28px 50px 44px;">
        <div class="container-fluid">
            <div style="background:#fff; border-radius:12px; padding:28px; box-shadow:0 4px 20px rgba(0,0,0,0.06);">
                {!! $page->content !!}
            </div>
        </div>
    </section>
@endsection
