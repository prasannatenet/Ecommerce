@php
    // Present when a 6-digit code is already on its way: render step 2.
    $passwordChangePending = $passwordChangePending ?? null;

    // a***@example.com — enough to recognise the inbox, not enough to harvest.
    $maskedEmail = $user->email;
    if (($atPos = strrpos($maskedEmail, '@')) !== false) {
        $maskedEmail = substr($maskedEmail, 0, 2) . '***' . substr($maskedEmail, $atPos);
    }
@endphp

@if ($passwordChangePending)
    {{-- ============================================================= --}}
    {{-- Step 2 of 2: the emailed 6-digit code. Only this step can      --}}
    {{-- actually change the password.                                  --}}
    {{-- ============================================================= --}}
    <p style="color:var(--df-text-secondary); font-size:0.88rem; margin-bottom:20px;">
        We emailed a 6-digit code to <strong>{{ $maskedEmail }}</strong>.
        Enter it below to confirm your new password — the password stays
        unchanged until the code is verified. The code expires in
        {{ \App\Models\PasswordVerificationCode::EXPIRES_IN_MINUTES }} minutes.
    </p>

    <form method="post" action="{{ route('profile.password.verify') }}">
        @csrf

        <div class="row g-3">
            <div class="col-12">
                <label class="df-form-label" for="password_verification_code">Verification Code</label>
                <input id="password_verification_code" name="code" type="text" inputmode="numeric"
                       pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"
                       placeholder="Enter 6-digit code"
                       class="df-form-control {{ $errors->passwordVerify->has('code') ? 'is-invalid' : '' }}"
                       style="letter-spacing:8px; font-size:1.3rem; text-align:center; font-weight:700;"
                       autofocus>
                @if ($errors->passwordVerify->has('code'))
                    <div class="text-danger mt-1" style="font-size:0.85rem;">{{ $errors->passwordVerify->first('code') }}</div>
                @endif
            </div>

            <div class="col-12 d-flex align-items-center gap-3 mt-4 pt-3" style="border-top:1px solid var(--df-border);">
                <button type="submit" class="df-btn df-btn-primary">
                    Verify &amp; Update Password
                </button>

                @if (session('status') === 'password-code-sent')
                    <span class="text-success" style="font-weight:600; font-size:0.85rem;">
                        <i class="bi bi-check-circle-fill"></i> New code sent to {{ $maskedEmail }}.
                    </span>
                @endif
                @if (session('status') === 'password-updated')
                    <span class="text-success" style="font-weight:600; font-size:0.85rem; display:flex; align-items:center; gap:4px;">
                        <i class="bi bi-check-circle-fill"></i> Saved.
                    </span>
                @endif
            </div>
        </div>
    </form>

    {{-- Sibling forms, never nested: nested forms are invalid HTML and
         silently stop submitting in every browser. --}}
    <div class="d-flex align-items-center gap-2 mt-3">
        <form method="post" action="{{ route('profile.password.resend') }}" class="d-inline">
            @csrf
            <button type="submit" class="df-btn" style="border:1px solid var(--df-border); background:transparent;">
                Resend code
            </button>
        </form>

        <form method="post" action="{{ route('profile.password.cancel') }}" class="d-inline">
            @csrf
            <button type="submit" class="df-btn" style="border:1px solid var(--df-border); background:transparent; color:var(--df-danger);">
                Cancel
            </button>
        </form>
    </div>

    @if (session('error'))
        <div class="mt-3" style="color:var(--df-danger); font-size:0.85rem; font-weight:600;">
            <i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}
        </div>
    @endif
@else
    {{-- ============================================================= --}}
    {{-- Step 1 of 2: validate the passwords here, email the code.      --}}
    {{-- Nothing is written to the user yet — verify() does that.        --}}
    {{-- ============================================================= --}}
    <p style="color:var(--df-text-secondary); font-size:0.88rem; margin-bottom:20px;">
        Enter your current and desired new password. We will then email you a
        6-digit code — the password changes only after that code is verified.
    </p>

    <form method="post" action="{{ route('profile.password.code') }}">
        @csrf

        <div class="row g-3">
            <div class="col-12">
                <label class="df-form-label" for="update_password_current_password">Current Password</label>
                <input id="update_password_current_password" name="current_password" type="password"
                       class="df-form-control {{ $errors->passwordChange->has('current_password') ? 'is-invalid' : '' }}" autocomplete="current-password">
                @if ($errors->passwordChange->has('current_password'))
                    <div class="text-danger mt-1" style="font-size:0.85rem;">{{ $errors->passwordChange->first('current_password') }}</div>
                @endif
            </div>

            <div class="col-12">
                <label class="df-form-label" for="update_password_password">New Password</label>
                <input id="update_password_password" name="password" type="password"
                       class="df-form-control {{ $errors->passwordChange->has('password') ? 'is-invalid' : '' }}" autocomplete="new-password">
                @if ($errors->passwordChange->has('password'))
                    <div class="text-danger mt-1" style="font-size:0.85rem;">{{ $errors->passwordChange->first('password') }}</div>
                @endif
            </div>

            <div class="col-12">
                <label class="df-form-label" for="update_password_password_confirmation">Confirm Password</label>
                <input id="update_password_password_confirmation" name="password_confirmation" type="password"
                       class="df-form-control {{ $errors->passwordChange->has('password_confirmation') ? 'is-invalid' : '' }}" autocomplete="new-password">
                @if ($errors->passwordChange->has('password_confirmation'))
                    <div class="text-danger mt-1" style="font-size:0.85rem;">{{ $errors->passwordChange->first('password_confirmation') }}</div>
                @endif
            </div>

            <div class="col-12 d-flex align-items-center gap-3 mt-4 pt-3" style="border-top:1px solid var(--df-border);">
                <button type="submit" class="df-btn df-btn-primary">
                    Send verification code
                </button>
            </div>
        </div>
    </form>

    @if (session('status') === 'password-code-sent')
        <div class="mt-3" style="color:#0F766E; font-size:0.85rem; font-weight:600;">
            <i class="bi bi-check-circle-fill"></i> A 6-digit code was sent to {{ $maskedEmail }} — enter it below.
        </div>
    @endif
    @if (session('error'))
        <div class="mt-3" style="color:var(--df-danger); font-size:0.85rem; font-weight:600;">
            <i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}
        </div>
    @endif
@endif
