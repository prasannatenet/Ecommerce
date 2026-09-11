@extends('layouts.frontend')

@section('title', ($page->meta_title ?: $page->title) . ' | ' . ($appSetting->site_name ?? 'GEHNA'))

@section('content')
    <div class="page-header-teal" style="color: white; padding: 28px 50px 44px;">
        <div class="container-fluid">
            <h1 class="page-header-title">{{ $page->title }}</h1>

        </div>
    </div>

    <section style="border: solid 1px #ccc; box-shadow: rgba(50, 50, 93, 0.25) 0px 2px 5px -1px, rgba(0, 0, 0, 0.3) 0px 1px 3px -1px; margin-bottom: 3px;">
    <div class="container-fluid px-4">
        <nav aria-label="breadcrumb" class="py-2">
            <ol class="breadcrumb mb-0">
                 <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $page->title }}</li>
            </ol>
        </nav>
    </div>
</section>

    <section style="background:#f5f5f5;  min-height:55vh; padding: 28px 50px 44px;">
        <div class="container-fluid">
            <div style="background:#fff; border-radius:12px; padding:28px; box-shadow:0 4px 20px rgba(0,0,0,0.06);">
                {!! $page->content !!}
            </div>
        </div>
    </section>
@endsection
