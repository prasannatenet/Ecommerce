@extends('layouts.frontend')

@section('title', 'Verify Email | GEHNA Fitness')

@section('content')
<section style="background: linear-gradient(100deg, #131313 1.15%, #022C2B 100%); min-height: 80vh; display: flex; align-items: center; padding: 60px 0;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9 col-xl-8">
                <div class="row g-0 overflow-hidden" style="border-radius:16px; box-shadow: 0 24px 60px rgba(0,0,0,0.5);">

                    {{-- LEFT PANEL --}}
                    <div class="col-lg-5 d-flex flex-column justify-content-between"
                         style="background: linear-gradient(135deg, #022C2B 0%, #017075 100%); padding: 50px 40px; position:relative; overflow:hidden;">
                        <div style="position:absolute; width:280px; height:280px; border-radius:50%; background:rgba(0,229,255,0.05); top:-70px; right:-70px; pointer-events:none;"></div>
                        <div style="position:absolute; width:180px; height:180px; border-radius:50%; background:rgba(0,229,255,0.06); bottom:-50px; left:-50px; pointer-events:none;"></div>

                        <div>
                            <a href="{{ route('home') }}" class="d-inline-block mb-4">
                                <span style="font-size:1.6rem; font-weight:900; letter-spacing:2px; color:#fff;">GEHNA</span>
                            </a>

                            <p style="color:#00e5ff; font-size:0.75rem; letter-spacing:3px; text-transform:uppercase; font-weight:600;" class="mb-2">Email Verification</p>
                            <h1 style="color:#fff; font-size:1.9rem; font-weight:900; line-height:1.2;" class="mb-3">Verify Your<br>Email Address</h1>
                            <p style="color:rgba(255,255,255,0.6); font-size:0.9rem;" class="mb-4">
                                Click the link in your inbox to activate your account and start shopping.
                            </p>

                            <ul style="list-style:none; padding:0; margin:0;" class="d-flex flex-column gap-3">
                                <li class="d-flex align-items-start gap-2" style="color:rgba(255,255,255,0.8); font-size:0.9rem;">
                                    <i class="bi bi-envelope-check" style="color:#00e5ff; margin-top:2px;"></i>
                                    One-click verification from your email.
                                </li>
                                <li class="d-flex align-items-start gap-2" style="color:rgba(255,255,255,0.8); font-size:0.9rem;">
                                    <i class="bi bi-shield-check" style="color:#00e5ff; margin-top:2px;"></i>
                                    Helps protect your account from misuse.
                                </li>
                                <li class="d-flex align-items-start gap-2" style="color:rgba(255,255,255,0.8); font-size:0.9rem;">
                                    <i class="bi bi-arrow-repeat" style="color:#00e5ff; margin-top:2px;"></i>
                                    You can resend the link anytime below.
                                </li>
                            </ul>
                        </div>

                        <div class="text-center mt-4 d-none d-lg-block">
                            <div style="width:80px; height:80px; border-radius:50%; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; margin:0 auto;">
                                <i class="bi bi-envelope-check" style="font-size:2.5rem; color:#00e5ff;"></i>
                            </div>
                        </div>
                    </div>

                    {{-- RIGHT PANEL --}}
                    <div class="col-lg-7" style="background:#fff; padding: 50px 45px; display:flex; flex-direction:column; justify-content:center;">

                        <h2 style="font-size:1.5rem; font-weight:900; color:#0D0D0D; letter-spacing:1px;" class="mb-1">Check Your Inbox</h2>
                        <p style="color:#6C757D; font-size:0.9rem;" class="mb-4">
                            A verification link has been sent to your email address.
                        </p>

                        @if(session('status') == 'verification-link-sent')
                            <div class="alert alert-success py-2 mb-4" style="font-size:0.875rem; border-radius:8px;">
                                <i class="bi bi-check-circle me-2"></i>
                                {{ __('A new verification link has been sent to your email address.') }}
                            </div>
                        @endif

                        <div style="background:#f9f9f9; border-radius:10px; padding:20px 24px; margin-bottom:24px; border-left:4px solid #017075;">
                            <p style="color:#495057; font-size:0.9rem; margin:0; line-height:1.7;">
                                {{ __("Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another.") }}
                            </p>
                        </div>

                        <form method="POST" action="{{ route('verification.send') }}" class="mb-3">
                            @csrf
                            <button type="submit" class="btn-gehna btn-teal-gehna w-100 justify-content-center" style="border-radius:8px;">
                                <i class="bi bi-send me-2"></i> Resend Verification Email
                            </button>
                        </form>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-100 justify-content-center"
                                    style="border-radius:8px; padding:12px 28px; border:1.5px solid #DEE2E6; background:#fff; color:#6C757D; font-size:0.95rem; font-weight:600; cursor:pointer; transition:all 0.2s;"
                                    onmouseover="this.style.borderColor='#017075'; this.style.color='#017075';"
                                    onmouseout="this.style.borderColor='#DEE2E6'; this.style.color='#6C757D';">
                                <i class="bi bi-box-arrow-right me-2"></i> Log Out
                            </button>
                        </form>

                        <div style="margin-top:24px; padding-top:24px; border-top:1px solid #f0f0f0; text-align:center;">
                            <p style="color:#6C757D; font-size:0.875rem; margin:0;">
                                Already verified?
                                <a href="{{ route('login') }}" style="color:#017075; font-weight:600;">Go to login →</a>
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>
@endsection
