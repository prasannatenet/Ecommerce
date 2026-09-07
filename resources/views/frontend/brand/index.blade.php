@extends('layouts.frontend')

@section('title', 'Brands | GEHNA')

@section('content')

    {{-- Page Header --}}


<div class="hero-dark-wrapper" style="padding-bottom: 0; ">
    <section class="page-header page-header-teal" style="padding-bottom:30px;">
        <div class="container position-relative" style="z-index:2">
            <h1 class="page-header-title" style="color: white">Our Brands</h1>
            <nav aria-label="breadcrumb">
                <ul class="breadcrumb breadcrumb-gehna">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item active">Brands</li>
                </ul>
            </nav>
        </div>
    </section>
</div>

    <section style="background:#f5f5f5; padding: 60px 0; min-height: 55vh;">
        <div class="container">

            <div style="text-align:center; margin-bottom:40px;">
                <p
                    style="color:#017075; font-size:0.75rem; letter-spacing:3px; text-transform:uppercase; font-weight:700; margin-bottom:8px;">
                    Trusted Partners</p>
                <h2 style="font-size:1.8rem; font-weight:900; color:#0D0D0D; margin-bottom:12px;">Shop by Brand</h2>
                <p style="color:#6C757D; font-size:0.95rem; max-width:540px; margin:0 auto;">We partner with world-leading
                    manufacturers to bring you professional-grade fitness equipment.</p>
            </div>

            @if ($brands->isEmpty())
                <div
                    style="background:#fff; border-radius:12px; padding:70px 40px; text-align:center; box-shadow:0 4px 20px rgba(0,0,0,0.06);">
                    <p style="color:#6C757D;">No brands available at the moment.</p>
                </div>
            @else
                <div class="row g-4">
                    @foreach ($brands as $brand)
                        <div class="col-6 col-md-4 col-lg-3">
                            <a href="{{ route('brand.show', $brand->slug) }}"
                                style="display:flex; flex-direction:column; align-items:center; justify-content:center; background:#fff; border-radius:12px; padding:28px 20px; box-shadow:0 4px 20px rgba(0,0,0,0.06); border:1.5px solid #f0f0f0; min-height:180px; text-decoration:none; position:relative; overflow:hidden; transition:all 0.3s;"
                                onmouseover="this.style.borderColor='#017075'; this.style.boxShadow='0 8px 30px rgba(1,112,117,0.15)'; this.querySelector('.brand-accent').style.transform='scaleX(1)';"
                                onmouseout="this.style.borderColor='#f0f0f0'; this.style.boxShadow='0 4px 20px rgba(0,0,0,0.06)'; this.querySelector('.brand-accent').style.transform='scaleX(0)';">

                                @if ($brand->logo)
                                    <img src="{{ asset('storage/' . $brand->logo) }}" alt="{{ $brand->name }}"
                                        style="max-width:100px; max-height:60px; object-fit:contain; filter:grayscale(1); opacity:0.7; transition:all 0.3s; margin-bottom:14px;"
                                        onmouseover="this.style.filter='grayscale(0)'; this.style.opacity='1';"
                                        onmouseout="this.style.filter='grayscale(1)'; this.style.opacity='0.7';">
                                @endif

                                <h3
                                    style="font-size:0.95rem; font-weight:800; color:#0D0D0D; text-transform:uppercase; letter-spacing:1px; text-align:center; margin:0 0 6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%;">
                                    {{ $brand->name }}</h3>
                                <span
                                    style="font-size:0.78rem; color:#6C757D; font-weight:600; text-transform:uppercase; letter-spacing:1px;">{{ $brand->products_count ?? 0 }}
                                    Items</span>

                                <div class="brand-accent"
                                    style="position:absolute; bottom:0; left:0; right:0; height:3px; background:linear-gradient(90deg, #017075, #00e5ff); transform:scaleX(0); transform-origin:left; transition:transform 0.3s;">
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
    </section>

@endsection
