<p style="color:var(--df-text-secondary); font-size:0.88rem; margin-bottom:20px;">
    Ensure your account is using a long, random password to stay secure.
</p>

<form method="post" action="{{ route('password.update') }}">
    @csrf
    @method('put')

    <div class="row g-3">
        <div class="col-12">
            <label class="df-form-label" for="update_password_current_password">Current Password</label>
            <input id="update_password_current_password" name="current_password" type="password"
                   class="df-form-control {{ $errors->updatePassword->has('current_password') ? 'is-invalid' : '' }}" autocomplete="current-password">
            @if($errors->updatePassword->has('current_password'))
                <div class="text-danger mt-1" style="font-size:0.85rem;">{{ $errors->updatePassword->first('current_password') }}</div>
            @endif
        </div>

        <div class="col-12">
            <label class="df-form-label" for="update_password_password">New Password</label>
            <input id="update_password_password" name="password" type="password"
                   class="df-form-control {{ $errors->updatePassword->has('password') ? 'is-invalid' : '' }}" autocomplete="new-password">
            @if($errors->updatePassword->has('password'))
                <div class="text-danger mt-1" style="font-size:0.85rem;">{{ $errors->updatePassword->first('password') }}</div>
            @endif
        </div>

        <div class="col-12">
            <label class="df-form-label" for="update_password_password_confirmation">Confirm Password</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password"
                   class="df-form-control {{ $errors->updatePassword->has('password_confirmation') ? 'is-invalid' : '' }}" autocomplete="new-password">
            @if($errors->updatePassword->has('password_confirmation'))
                <div class="text-danger mt-1" style="font-size:0.85rem;">{{ $errors->updatePassword->first('password_confirmation') }}</div>
            @endif
        </div>

        <div class="col-12 d-flex align-items-center gap-3 mt-4 pt-3" style="border-top:1px solid var(--df-border);">
            <button type="submit" class="df-btn df-btn-primary">
                Save
            </button>
            @if (session('status') === 'password-updated')
                <span class="text-success" style="font-weight:600; font-size:0.85rem; display:flex; align-items:center; gap:4px;">
                    <i class="bi bi-check-circle-fill"></i> Saved.
                </span>
            @endif
        </div>
    </div>
</form>
