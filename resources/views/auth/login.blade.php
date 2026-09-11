@extends('layouts.frontend')

@section('title', 'Login | GEHNA Fitness')

@section('styles')

@endsection

@section('content')
<section class="auth-page auth-page--login">
    <div class="auth-frame">
        <div class="auth-showcase">
            <a href="{{ route('home') }}" class="auth-brand">GEHNA<span>.</span></a>
            <div class="auth-showcase-copy">
                <p class="auth-eyebrow">Welcome back</p>
                <h1>Your next great session starts here.</h1>
                <p>Sign in to keep your orders, wishlist, and training essentials in one place.</p>
            </div>
            <div class="auth-showcase-mark"><i class="bi bi-arrow-up-right"></i></div>
        </div>

        <div class="auth-form-panel">
            <div class="auth-heading">
                <p class="auth-eyebrow">Member access</p>
                <h2>Sign in</h2>
                <p>Welcome back. Enter your details to continue.</p>
            </div>

            <div class="auth-social-row" aria-label="Social sign in options">
                <button type="button" class="auth-social auth-social--google"><span>G</span> Google</button>
                <button type="button" class="auth-social auth-social--facebook"><i class="bi bi-facebook"></i> Facebook</button>
                <button type="button" class="auth-social auth-social--twitter"><i class="bi bi-twitter"></i> Twitter</button>
            </div>
            <div class="auth-divider"><span>or sign in with email</span></div>

            @if(session('error'))
                <div class="auth-alert auth-alert--error">{{ session('error') }}</div>
            @endif
            @if(session('status'))
                <div class="auth-alert auth-alert--success">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="auth-alert auth-alert--error">
                    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="auth-form">
                @csrf
                <div class="auth-field">
                    <label for="email">Email address</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus placeholder="you@example.com">
                </div>
                <div class="auth-field">
                    <label for="password">Password</label>
                    <div class="auth-password-wrap">
                        <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
                        <button type="button" class="auth-password-toggle" onclick="togglePass('password', 'eyeIcon1')" aria-label="Show password"><i class="bi bi-eye" id="eyeIcon1"></i></button>
                    </div>
                </div>
                <div class="auth-form-options">
                    <label class="auth-check"><input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}> <span>Remember me</span></label>
                    @if(Route::has('password.request'))
                        <a href="{{ route('password.request') }}">Forgot password?</a>
                    @endif
                </div>
                <button type="submit" class="auth-submit">Sign in <i class="bi bi-arrow-right"></i></button>
            </form>

            <p class="auth-switch">New to GEHNA? <a href="{{ route('register') }}">Create an account</a></p>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
function togglePass(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    input.type = input.type === 'password' ? 'text' : 'password';
    icon.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>
@endpush
