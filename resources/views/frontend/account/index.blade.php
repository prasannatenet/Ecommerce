@extends('layouts.frontend')

@section('title', 'My Account | GEHNA')

@section('content')

    {{-- Page Header --}}
    <div class="page-header-teal">
        <div class="container">
            <h1 class="page-header-title text-white">My Account</h1>
        </div>
    </div>

    <section style="border: solid 1px #ccc; box-shadow: rgba(50, 50, 93, 0.25) 0px 2px 5px -1px, rgba(0, 0, 0, 0.3) 0px 1px 3px -1px; margin-bottom: 3px;">
    <div class="container px-4">
        <nav aria-label="breadcrumb" class="py-2">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            </ol>
        </nav>
    </div>
</section>



    <section style="background:#f5f5f5; padding: 50px 0; min-height: 55vh;">
        <div class="container">

            @if(session('error'))
                <div class="alert alert-danger py-2 mb-4" style="font-size:0.875rem; border-radius:8px;">
                    <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
                </div>
            @endif

            {{-- Welcome Bar --}}
            <div class="heading-section d-flex mb-4 align-items-center justify-content-between flex-wrap gap-3" style="background:linear-gradient(135deg,#013a3c 0%,#017075 60%,#02AAB1 100%); border-radius:12px; padding:20px 28px;">
                <div>
                    <p class="mb-0 text-white-50">Welcome back</p>
                    <h2 style="color:#fff; font-size:1.5rem; font-weight:900; margin:0;">{{ $user->name }}</h2>
                </div>
                <a href="{{ route('products.index') }}" class="btn-gehna btn-teal-gehna">
                    <i class="bi bi-bag me-2"></i> Continue Shopping
                </a>
            </div>

            {{-- Stats --}}
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="d-flex gap-3" style="background:#fff; border-radius:12px; padding:24px; box-shadow:0 4px 20px rgba(0,0,0,0.06); text-align:center;">
                        <div style="width:56px; height:56px; border-radius:50%; background:rgba(1,112,117,0.1); display:flex; align-items:center; justify-content:center; ">
                            <i class="bi bi-bag-check" style="font-size:1.6rem; color:#017075;"></i>
                        </div>
                        <div>
                            <span style="font-size:2rem; font-weight:900; color:#0D0D0D; margin:0;">{{ $stats['total_orders'] }}</span> &nbsp;
                            <span style="color:#6C757D; font-size:16px; text-transform:uppercase; letter-spacing:1px; margin:4px 0 0;">Total Orders</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-3" style="background:#fff; border-radius:12px; padding:24px; box-shadow:0 4px 20px rgba(0,0,0,0.06); text-align:center;">
                        <div style="width:56px; height:56px; border-radius:50%; background:rgba(1,112,117,0.1); display:flex; align-items:center; justify-content:center; ">
                            <i class="bi bi-bag-check" style="font-size:1.6rem; color:#017075;"></i>
                        </div>
                        <div>
                            <span style="font-size:2rem; font-weight:900; color:#0D0D0D; margin:0;">{{ $stats['pending_orders'] }}</span> &nbsp;
                            <span style="color:#6C757D; font-size:16px; text-transform:uppercase; letter-spacing:1px; margin:4px 0 0;">Active Orders</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-3" style="background:#fff; border-radius:12px; padding:24px; box-shadow:0 4px 20px rgba(0,0,0,0.06); text-align:center;">
                        <div style="width:56px; height:56px; border-radius:50%; background:rgba(25,135,84,0.1); display:flex; align-items:center; justify-content:center; ">
                            <i class="bi bi-check2-circle" style="font-size:1.6rem; color:#198754;"></i>
                        </div>
                        <div>
                            <span style="font-size:2rem; font-weight:900; color:#0D0D0D; margin:0;">{{ $stats['completed_orders'] }}</span> &nbsp;
                            <span style="color:#6C757D; font-size:16px; text-transform:uppercase; letter-spacing:1px; margin:4px 0 0;">Delivered</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                {{-- Recent Orders --}}
                <div class="col-lg-8">
                    <div style="background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.06); overflow:hidden;">
                        <div style="padding:20px 24px; border-bottom:1px solid #f0f0f0; display:flex; align-items:center; justify-content:space-between;">
                            <h3 style="font-size:1rem; font-weight:800; color:#0D0D0D; text-transform:uppercase; letter-spacing:1px; margin:0;">Recent Orders</h3>
                            <a href="{{ route('account.orders') }}" style="color:#017075; font-size:0.85rem; font-weight:600; text-decoration:none;">View All →</a>
                        </div>

                        @if($recentOrders->isEmpty())
                            <div style="padding:40px; text-align:center; color:#6C757D;">
                                <i class="bi bi-bag-x" style="font-size:2rem; color:#dee2e6; display:block; margin-bottom:12px;"></i>
                                You have not placed any orders yet.
                            </div>
                        @else
                            <div style="padding:8px 0;">
                                @foreach($recentOrders as $order)
                                                            <div style="padding:14px 24px; border-bottom:1px solid #f8f8f8; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;"
                                                                 onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background='#fff'">
                                                                <div>
                                                                    <p style="font-weight:700; color:#0D0D0D; margin:0; font-size:0.95rem;">{{ $loop->iteration }}</p>
                                                                    <p style="color:#6C757D; font-size:0.8rem; margin:2px 0 0;">{{ $order->created_at->format('M d, Y') }}</p>
                                                                </div>
                                                                <div style="text-align:center;">
                                                                    @php
                                    $statusColors = ['pending' => '#ffc107', 'processing' => '#0dcaf0', 'shipped' => '#0d6efd', 'delivered' => '#198754', 'cancelled' => '#dc3545'];
                                    $sc = $statusColors[$order->status] ?? '#6C757D';
                                                                    @endphp
                                                                    <span style="display:inline-block; padding:3px 10px; border-radius:20px; font-size:0.75rem; font-weight:600; text-transform:uppercase; background:{{ $sc }}22; color:{{ $sc }};">{{ $order->status }}</span>
                                                                </div>
                                                                <div style="text-align:right;">
                                                                    <p style="color:#017075; font-weight:700; margin:0;">Rs {{ number_format($order->total, 2) }}</p>
                                                                </div>
                                                                <a href="{{ route('account.orders.show', $order) }}"
                                                                   style="color:#017075; font-size:0.85rem; font-weight:600; text-decoration:none; padding:6px 14px; border:1.5px solid #017075; border-radius:6px;">
                                                                    Details
                                                                </a>
                                                            </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Quick Links --}}
                <div class="col-lg-4">
                    <div style="background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.06); overflow:hidden;">
                        <div style="padding:20px 24px; border-bottom:1px solid #f0f0f0;">
                            <h3 style="font-size:1rem; font-weight:800; color:#0D0D0D; text-transform:uppercase; letter-spacing:1px; margin:0;">Quick Links</h3>
                        </div>
                        <div style="padding:12px 16px; display:flex; flex-direction:column; gap:6px;">
                            @php
    $links = [
        ['icon' => 'bi-person-gear', 'label' => 'Profile Settings', 'route' => route('account.profile')],
        ['icon' => 'bi-bag-check', 'label' => 'Order History', 'route' => route('account.orders')],
        ['icon' => 'bi-heart', 'label' => 'Wishlist', 'route' => route('wishlist.index')],
        ['icon' => 'bi-cart3', 'label' => 'My Cart', 'route' => route('cart.index')],
    ];
                            @endphp
                            @foreach($links as $link)
                                <a href="{{ $link['route'] }}"
                                   style="display:flex; align-items:center; gap:12px; padding:12px 14px; border-radius:8px; color:#495057; font-size:0.9rem; font-weight:600; text-decoration:none; transition:all 0.2s; border:1.5px solid #f0f0f0;"
                                   onmouseover="this.style.borderColor='#017075'; this.style.color='#017075'; this.style.background='rgba(1,112,117,0.04)';"
                                   onmouseout="this.style.borderColor='#f0f0f0'; this.style.color='#495057'; this.style.background='#fff';">
                                    <i class="bi {{ $link['icon'] }}" style="font-size:1.1rem; color:#017075; width:20px;"></i>
                                    {{ $link['label'] }}
                                    <i class="bi bi-chevron-right ms-auto" style="font-size:0.75rem; color:#aaa;"></i>
                                </a>
                            @endforeach
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit"
                                        style="width:100%; display:flex; align-items:center; gap:12px; padding:12px 14px; border-radius:8px; color:#dc3545; font-size:0.9rem; font-weight:600; text-decoration:none; background:#fff; border:1.5px solid #f0f0f0; cursor:pointer; transition:all 0.2s;"
                                        onmouseover="this.style.borderColor='#dc3545'; this.style.background='rgba(220,53,69,0.04)';"
                                        onmouseout="this.style.borderColor='#f0f0f0'; this.style.background='#fff';">
                                    <i class="bi bi-box-arrow-right" style="font-size:1.1rem; color:#dc3545; width:20px;"></i>
                                    Logout
                                    <i class="bi bi-chevron-right ms-auto" style="font-size:0.75rem; color:#aaa;"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

@endsection
