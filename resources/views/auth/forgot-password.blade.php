@extends('layouts.frontend')

@section('title', 'Forgot Password | GEHNA Fitness')

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

                            <p style="color:#00e5ff; font-size:0.75rem; letter-spacing:3px; text-transform:uppercase; font-weight:600;" class="mb-2">Password Recovery</p>
                            <h1 style="color:#fff; font-size:1.9rem; font-weight:900; line-height:1.2;" class="mb-3">Forgot Your<br>Password?</h1>
                            <p style="color:rgba(255,255,255,0.6); font-size:0.9rem;" class="mb-4">
                                Enter your email and we'll send you a secure reset link instantly.
                            </p>

                            <ul style="list-style:none; padding:0; margin:0;" class="d-flex flex-column gap-3">
                                <li class="d-flex align-items-start gap-2" style="color:rgba(255,255,255,0.8); font-size:0.9rem;">
                                    <i class="bi bi-shield-lock-fill" style="color:#00e5ff; margin-top:2px;"></i>
                                    Secure one-time reset token via email.
                                </li>
                                <li class="d-flex align-items-start gap-2" style="color:rgba(255,255,255,0.8); font-size:0.9rem;">
                                    <i class="bi bi-clock-history" style="color:#00e5ff; margin-top:2px;"></i>
                                    Link expires in 60 minutes for your safety.
                                </li>
                                <li class="d-flex align-items-start gap-2" style="color:rgba(255,255,255,0.8); font-size:0.9rem;">
                                    <i class="bi bi-person-check-fill" style="color:#00e5ff; margin-top:2px;"></i>
                                    Your orders and data stay completely safe.
                                </li>
                            </ul>
                        </div>

                        <div class="text-center mt-4 d-none d-lg-block">
                            <div style="width:80px; height:80px; border-radius:50%; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; margin:0 auto;">
                                <i class="bi bi-envelope-open" style="font-size:2.5rem; color:#00e5ff;"></i>
                            </div>
                        </div>
                    </div>

                    {{-- RIGHT PANEL (FORM) --}}
                    <div class="col-lg-7" style="background:#fff; padding: 50px 45px; display:flex; flex-direction:column; justify-content:center;">

                        <h2 style="font-size:1.5rem; font-weight:900; color:#0D0D0D; letter-spacing:1px;" class="mb-1">Reset Password</h2>
                        <p style="color:#6C757D; font-size:0.9rem;" class="mb-4">
                            Remember your password?
                            <a href="{{ route('login') }}" style="color:#017075; font-weight:600;">Back to login</a>
                        </p>

                        @if(session('status'))
                            <div class="alert alert-success py-2 mb-4" style="font-size:0.875rem; border-radius:8px;">
                                <i class="bi bi-check-circle me-2"></i>{{ session('status') }}
                            </div>
                        @endif
                        @if($errors->any())
                            <div class="alert alert-danger py-2 mb-4" style="font-size:0.875rem; border-radius:8px;">
                                @foreach($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif

                        <form method="POST" action="{{ route('password.email') }}">
                            @csrf

                            <div class="mb-4">
                                <label for="email" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;" class="form-label">Email Address</label>
                                <input id="email" type="email" name="email" value="{{ old('email') }}"
                                       required autofocus
                                       class="form-control"
                                       style="border:1.5px solid #DEE2E6; border-radius:8px; padding:12px 16px; font-size:0.95rem; background:#f9f9f9; transition:border-color 0.3s;"
                                       onfocus="this.style.borderColor='#017075'; this.style.background='#fff';"
                                       onblur="this.style.borderColor='#DEE2E6'; this.style.background='#f9f9f9';"
                                       placeholder="Enter your registered email">
                            </div>

                            <button type="submit" class="btn-gehna btn-teal-gehna w-100 justify-content-center" style="border-radius:8px;">
                                <i class="bi bi-send me-2"></i> Send Reset Link
                            </button>
                        </form>

                        <div style="margin-top:30px; padding-top:24px; border-top:1px solid #f0f0f0; text-align:center;">
                            <p style="color:#6C757D; font-size:0.875rem; margin:0;">
                                Don't have an account?
                                <a href="{{ route('register') }}" style="color:#017075; font-weight:600;">Create one free →</a>
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>
@endsection
