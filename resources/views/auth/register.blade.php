@extends('layouts.frontend')

@section('title', 'Create Account | GEHNA Fitness')

@section('styles')
<style>
    .auth-page--register .auth-showcase {
        background-image: linear-gradient(145deg, rgba(1, 72, 73, .94), rgba(1, 112, 117, .74));
    }
</style>
@endsection

@section('content')
<section class="auth-page auth-page--register">
    <div class="auth-frame">
        <div class="auth-showcase">
            <a href="{{ route('home') }}" class="auth-brand">GEHNA<span>.</span></a>
            <div class="auth-showcase-copy">
                <p class="auth-eyebrow">Join the community</p>
                <h1>Make every workout count.</h1>
                <p>Create your account for faster checkout, order tracking, and member-only offers.</p>
            </div>
            <div class="auth-showcase-mark"><i class="bi bi-arrow-up-right"></i></div>
        </div>

        <div class="auth-form-panel">
            <div class="auth-heading">
                <p class="auth-eyebrow">Get started</p>
                <h2>Sign up</h2>
                <p>It is free to sign up and only takes a minute.</p>
            </div>

            <div class="auth-social-row" aria-label="Social sign up options">
                <button type="button" class="auth-social auth-social--google"><span>G</span> Google</button>
                <button type="button" class="auth-social auth-social--facebook"><i class="bi bi-facebook"></i> Facebook</button>
                <button type="button" class="auth-social auth-social--twitter"><i class="bi bi-twitter"></i> Twitter</button>
            </div>
            <div class="auth-divider"><span>or sign up with email</span></div>

            @if($errors->any())
                <div class="auth-alert auth-alert--error">
                    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}" class="auth-form">
                @csrf
                <div class="auth-two-fields">
                    <div class="auth-field">
                        <label for="name">Full name</label>
                        <input id="name" type="text" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus placeholder="Your full name">
                    </div>
                    <div class="auth-field">
                        <label for="email">Email address</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="you@example.com">
                    </div>
                </div>
                <div class="auth-two-fields">
                    <div class="auth-field">
                        <label for="password">Password</label>
                        <div class="auth-password-wrap">
                            <input id="password" type="password" name="password" required autocomplete="new-password" placeholder="Create password">
                            <button type="button" class="auth-password-toggle" onclick="togglePass('password', 'eye1')" aria-label="Show password"><i class="bi bi-eye" id="eye1"></i></button>
                        </div>
                    </div>
                    <div class="auth-field">
                        <label for="password_confirmation">Confirm password</label>
                        <div class="auth-password-wrap">
                            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Repeat password">
                            <button type="button" class="auth-password-toggle" onclick="togglePass('password_confirmation', 'eye2')" aria-label="Show password"><i class="bi bi-eye" id="eye2"></i></button>
                        </div>
                    </div>
                </div>
                <button type="submit" class="auth-submit">Register now <i class="bi bi-arrow-right"></i></button>
            </form>

            <p class="auth-switch">Already a member? <a href="{{ route('login') }}">Sign in</a></p>
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
