@extends('layouts.frontend')

@section('title', 'Reset Password | GEHNA Fitness')

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

                            <p style="color:#00e5ff; font-size:0.75rem; letter-spacing:3px; text-transform:uppercase; font-weight:600;" class="mb-2">Set New Password</p>
                            <h1 style="color:#fff; font-size:1.9rem; font-weight:900; line-height:1.2;" class="mb-3">Reset Your<br>Password</h1>
                            <p style="color:rgba(255,255,255,0.6); font-size:0.9rem;" class="mb-4">
                                Create a new secure password to continue using your account safely.
                            </p>

                            <ul style="list-style:none; padding:0; margin:0;" class="d-flex flex-column gap-3">
                                <li class="d-flex align-items-start gap-2" style="color:rgba(255,255,255,0.8); font-size:0.9rem;">
                                    <i class="bi bi-shield-check" style="color:#00e5ff; margin-top:2px;"></i>
                                    Token-based secure password reset.
                                </li>
                                <li class="d-flex align-items-start gap-2" style="color:rgba(255,255,255,0.8); font-size:0.9rem;">
                                    <i class="bi bi-lock-fill" style="color:#00e5ff; margin-top:2px;"></i>
                                    Use a strong password for better protection.
                                </li>
                                <li class="d-flex align-items-start gap-2" style="color:rgba(255,255,255,0.8); font-size:0.9rem;">
                                    <i class="bi bi-person-check-fill" style="color:#00e5ff; margin-top:2px;"></i>
                                    Get back to your orders and wishlist quickly.
                                </li>
                            </ul>
                        </div>

                        <div class="text-center mt-4 d-none d-lg-block">
                            <div style="width:80px; height:80px; border-radius:50%; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; margin:0 auto;">
                                <i class="bi bi-key" style="font-size:2.5rem; color:#00e5ff;"></i>
                            </div>
                        </div>
                    </div>

                    {{-- RIGHT PANEL (FORM) --}}
                    <div class="col-lg-7" style="background:#fff; padding: 50px 45px;">

                        <h2 style="font-size:1.5rem; font-weight:900; color:#0D0D0D; letter-spacing:1px;" class="mb-1">Create New Password</h2>
                        <p style="color:#6C757D; font-size:0.9rem;" class="mb-4">
                            Remembered it?
                            <a href="{{ route('login') }}" style="color:#017075; font-weight:600;">Back to login</a>
                        </p>

                        @if($errors->any())
                            <div class="alert alert-danger py-2 mb-4" style="font-size:0.875rem; border-radius:8px;">
                                @foreach($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif

                        <form method="POST" action="{{ route('password.store') }}">
                            @csrf
                            <input type="hidden" name="token" value="{{ $request->route('token') }}">

                            <div class="mb-3">
                                <label for="email" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;" class="form-label">Email Address</label>
                                <input id="email" type="email" name="email"
                                       value="{{ old('email', $request->email) }}"
                                       required autofocus autocomplete="username"
                                       class="form-control"
                                       style="border:1.5px solid #DEE2E6; border-radius:8px; padding:12px 16px; font-size:0.95rem; background:#f9f9f9; transition:border-color 0.3s;"
                                       onfocus="this.style.borderColor='#017075'; this.style.background='#fff';"
                                       onblur="this.style.borderColor='#DEE2E6'; this.style.background='#f9f9f9';"
                                       placeholder="your@email.com">
                            </div>

                            <div class="mb-3">
                                <label for="password" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;" class="form-label">New Password</label>
                                <div style="position:relative;">
                                    <input id="password" type="password" name="password"
                                           required autocomplete="new-password"
                                           class="form-control"
                                           style="border:1.5px solid #DEE2E6; border-radius:8px; padding:12px 45px 12px 16px; font-size:0.95rem; background:#f9f9f9; transition:border-color 0.3s;"
                                           onfocus="this.style.borderColor='#017075'; this.style.background='#fff';"
                                           onblur="this.style.borderColor='#DEE2E6'; this.style.background='#f9f9f9';"
                                           placeholder="Enter new password">
                                    <button type="button" onclick="togglePass('password', 'eye1')"
                                            style="position:absolute; right:14px; top:50%; transform:translateY(-50%); background:none; border:none; color:#6C757D; cursor:pointer;">
                                        <i class="bi bi-eye" id="eye1"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="password_confirmation" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;" class="form-label">Confirm New Password</label>
                                <div style="position:relative;">
                                    <input id="password_confirmation" type="password" name="password_confirmation"
                                           required autocomplete="new-password"
                                           class="form-control"
                                           style="border:1.5px solid #DEE2E6; border-radius:8px; padding:12px 45px 12px 16px; font-size:0.95rem; background:#f9f9f9; transition:border-color 0.3s;"
                                           onfocus="this.style.borderColor='#017075'; this.style.background='#fff';"
                                           onblur="this.style.borderColor='#DEE2E6'; this.style.background='#f9f9f9';"
                                           placeholder="Confirm new password">
                                    <button type="button" onclick="togglePass('password_confirmation', 'eye2')"
                                            style="position:absolute; right:14px; top:50%; transform:translateY(-50%); background:none; border:none; color:#6C757D; cursor:pointer;">
                                        <i class="bi bi-eye" id="eye2"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn-gehna btn-teal-gehna w-100 justify-content-center" style="border-radius:8px;">
                                <i class="bi bi-check-circle me-2"></i> Reset Password
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
function togglePass(inputId, iconId) {
    var input = document.getElementById(inputId);
    var icon = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>
@endpush
