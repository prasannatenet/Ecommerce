@extends('layouts.frontend')

@section('title', 'Shop by Category | GEHNA')

@section('content')

    {{-- PAGE HEADER --}}
    <section style="background:linear-gradient(135deg,#013a3c 0%,#017075 60%,#02AAB1 100%); padding:48px 50px 40px;">
        <div class="container-fluid px-4">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb mb-0" style="background:none; padding:0;">
                    <li class="breadcrumb-item">
                        <a href="{{ route('home') }}" style="color:rgba(255,255,255,0.7); text-decoration:none;">Home</a>
                    </li>
                    <li class="breadcrumb-item active" style="color:#fff;">Categories</li>
                </ol>
            </nav>
            <h1 style="color:#fff !IMPORTANT; font-size:2.2rem; font-weight:800; margin:0 0 6px;">
                Shop by <span style="color:#00e5ff;">Category</span>
            </h1>
            <p style="color:rgba(255,255,255,0.75); margin:0; font-size:0.95rem;">
                {{ $categories->count() }} {{ Str::plural('category', $categories->count()) }} available
            </p>
        </div>
    </section>

    {{-- CATEGORIES GRID --}}
    <section style="background:#f8f9fa; padding:50px 50px 70px;">
        <div class="container-fluid px-4">

            @if($categories->isEmpty())
                <div style="background:#fff; border-radius:16px; padding:80px 40px; text-align:center; border:1px solid #e9ecef;">
                    <i class="bi bi-grid" style="font-size:4rem; color:#ccc;"></i>
                    <h4 class="mt-3" style="color:#6C757D;">No categories yet</h4>
                    <p style="color:#aaa; margin:0;">Check back soon.</p>
                </div>
            @else
                <div class="row g-4">
                    @foreach($categories as $category)
                        <div class="col-sm-6 col-lg-3">
                            <a href="{{ route('category.show', $category->slug) }}" class="cat-shop-card">

                                {{-- Image --}}
                                <div class="cat-shop-img-wrap">
                                    @php
                                        $categoryImagePath = is_string($category->image)
                                            ? trim($category->image)
                                            : (is_string($category->image_path ?? null) ? trim($category->image_path) : null);

                                        if (filled($categoryImagePath)) {
                                            if (\Illuminate\Support\Str::startsWith($categoryImagePath, ['http://', 'https://'])) {
                                                $categoryImageUrl = $categoryImagePath;
                                            } elseif (\Illuminate\Support\Str::startsWith($categoryImagePath, ['storage/', 'frontend/'])) {
                                                $categoryImageUrl = asset($categoryImagePath);
                                            } else {
                                                $categoryImageUrl = asset('storage/' . ltrim($categoryImagePath, '/'));
                                            }
                                        } else {
                                            $categoryImageUrl = null;
                                        }
                                    @endphp

                                    @if($categoryImageUrl)
                                        <img src="{{ $categoryImageUrl }}"
                                             alt="{{ $category->name }}" class="cat-shop-img">
                                    @else
                                        <div class="cat-shop-no-img">
                                            <i class="bi bi-image"></i>
                                            <span>No Image</span>
                                        </div>
                                    @endif
                                </div>

                                {{-- Body --}}
                                <div class="cat-shop-body">
                                    <p class="cat-shop-brand">Category</p>
                                    <h3 class="cat-shop-title">{{ $category->name }}</h3>
                                    @if($category->description)
                                        <p class="cat-shop-desc">{{ Str::limit(strip_tags($category->description), 68) }}</p>
                                    @endif

                                    <div class="cat-shop-footer">
                                        <span class="cat-shop-count">
                                            <i class="bi bi-box-seam me-1"></i>
                                            {{ $category->products_count ?? 0 }} Products
                                        </span>
                                        <span class="cat-shop-icon">
                                            <i class="bi bi-arrow-right"></i>
                                        </span>
                                    </div>
                                </div>

                            </a>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
    </section>

@endsection

@push('styles')
<style>
    .cat-shop-card {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e9ecef;
        overflow: hidden;
        height: 100%;
        display: block;
        text-decoration: none;
        transition: box-shadow 0.25s, transform 0.25s;
    }
    .cat-shop-card:hover {
        box-shadow: 0 10px 28px rgba(1,112,117,0.16);
        transform: translateY(-4px);
    }

    .cat-shop-img-wrap {
        display: block;
        background: #f8f9fa;
        position: relative;
        aspect-ratio: 1 / 1;
        overflow: hidden;
        text-decoration: none;
    }
    .cat-shop-img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 16px;
        transition: transform 0.3s;
    }
    .cat-shop-card:hover .cat-shop-img {
        transform: scale(1.05);
    }
    .cat-shop-no-img {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #ccc;
        font-size: 0.78rem;
        gap: 6px;
    }
    .cat-shop-no-img i {
        font-size: 2.5rem;
    }

    .cat-shop-body {
        padding: 14px 16px;
    }
    .cat-shop-brand {
        color: #017075;
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        font-weight: 700;
        margin: 0 0 4px;
    }
    .cat-shop-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: #0D0D0D;
        line-height: 1.4;
        margin: 0 0 10px;
        min-height: 2.7em;
    }
    .cat-shop-desc {
        color: #6c757d;
        font-size: 0.82rem;
        line-height: 1.5;
        margin: 0 0 14px;
        min-height: 2.4em;
    }
    .cat-shop-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .cat-shop-count {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 20px;
        border: 1px solid #dbe6ea;
        background: #f8fbfc;
        color: #495057;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.4px;
        text-transform: uppercase;
    }
    .cat-shop-card:hover .cat-shop-count {
        background: #017075;
        border-color: #017075;
        color: #fff;
    }
    .cat-shop-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        border: 1.5px solid #dee2e6;
        background: #fff;
        color: #6C757D;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    .cat-shop-card:hover .cat-shop-icon {
        border-color: #017075;
        color: #017075;
        background: rgba(1,112,117,0.06);
        transform: translateX(2px);
    }
</style>
@endpush
