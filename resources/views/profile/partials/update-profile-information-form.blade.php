<p style="color:var(--df-text-secondary); font-size:0.88rem; margin-bottom:20px;">
    Update your account's profile information and email address.
</p>

<form id="send-verification" method="post" action="{{ route('verification.send') }}">
    @csrf
</form>

<form method="post" action="{{ route('profile.update') }}">
    @csrf
    @method('patch')

    <div class="row g-3">
        <div class="col-12">
            <label class="df-form-label" for="name">Name</label>
            <input id="name" name="name" type="text" class="df-form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                   value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
            @if($errors->has('name'))
                <div class="text-danger mt-1" style="font-size:0.85rem;">{{ $errors->first('name') }}</div>
            @endif
        </div>

        <div class="col-12">
            <label class="df-form-label" for="email">Email</label>
            <input id="email" name="email" type="email" class="df-form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
                   value="{{ old('email', $user->email) }}" required autocomplete="username">
            @if($errors->has('email'))
                <div class="text-danger mt-1" style="font-size:0.85rem;">{{ $errors->first('email') }}</div>
            @endif

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-2" style="background:var(--df-warning-light, #fffbeb); padding:10px; border-radius:6px; border:1px solid var(--df-warning, #f59e0b);">
                    <p style="font-size:0.85rem; color:#b45309; margin-bottom:6px;">Your email address is unverified.</p>
                    <button form="send-verification" class="btn btn-sm btn-warning" style="font-size:0.75rem; padding:4px 8px;">
                        Click here to re-send the verification email.
                    </button>
                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-success" style="font-size:0.8rem; font-weight:600; margin-bottom:0;">
                            A new verification link has been sent to your email address.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="col-12 d-flex align-items-center gap-3 mt-4 pt-3" style="border-top:1px solid var(--df-border);">
            <button type="submit" class="df-btn df-btn-primary">
                Save
            </button>
            @if (session('status') === 'profile-updated')
                <span class="text-success" style="font-weight:600; font-size:0.85rem; display:flex; align-items:center; gap:4px;">
                    <i class="bi bi-check-circle-fill"></i> Saved.
                </span>
            @endif
        </div>
    </div>
</form>
