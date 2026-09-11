@extends('layouts.frontend')

@section('title', 'Profile Settings | GEHNA')

@section('content')

    {{-- Page Header --}}
    <div class="page-header-teal">
        <div class="container">
            <h1 class="page-header-title text-white">Profile Settings</h1>
        </div>
    </div>

    <section style="border: solid 1px #ccc; box-shadow: rgba(50, 50, 93, 0.25) 0px 2px 5px -1px, rgba(0, 0, 0, 0.3) 0px 1px 3px -1px; margin-bottom: 3px;">
    <div class="container px-4">
        <nav aria-label="breadcrumb" class="py-2">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('account.index') }}">Account</a></li>
                    <li class="breadcrumb-item active">Profile</li>

            </ol>
        </nav>
    </div>
</section>

    <section style="background:#f5f5f5; padding: 50px 0; min-height: 55vh;">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">

                    @if(session('success'))
                        <div class="alert alert-success py-2 mb-4" style="font-size:0.875rem; border-radius:8px;">
                            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                        </div>
                    @endif
                    @if(session('success_password'))
                        <div class="alert alert-success py-2 mb-4" style="font-size:0.875rem; border-radius:8px;">
                            <i class="bi bi-check-circle me-2"></i>{{ session('success_password') }}
                        </div>
                    @endif

                    <div class="row g-4">

                        {{-- Account Info --}}
                        <div class="col-lg-6">
                            <div
                                style="background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.06); overflow:hidden;">
                                <div
                                    class="heading-section">
                                    <h3
                                        style="color:#fff; font-size:1rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; margin:0;">
                                        <i class="bi bi-person-circle me-2" style="color:#00e5ff;"></i> Account Information
                                    </h3>
                                </div>
                                <div style="padding:28px;">
                                    <form action="{{ route('account.profile.update') }}" method="POST">
                                        @csrf
                                        @method('PUT')

                                        <div class="mb-3">
                                            <label
                                                style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;"
                                                class="form-label">Full Name</label>
                                            <input type="text" name="name" value="{{ old('name', $user->name) }}"
                                                class="form-control"
                                                style="border:1.5px solid #DEE2E6; border-radius:8px; padding:12px 16px; font-size:0.95rem; background:#f9f9f9;"
                                                onfocus="this.style.borderColor='#017075'; this.style.background='#fff';"
                                                onblur="this.style.borderColor='#DEE2E6'; this.style.background='#f9f9f9';"
                                                placeholder="Your full name" required>
                                            @error('name')<p style="color:#dc3545; font-size:0.8rem; margin-top:4px;">
                                            {{ $message }}</p>@enderror
                                        </div>

                                        <div class="mb-4">
                                            <label
                                                style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;"
                                                class="form-label">Email Address</label>
                                            <input type="email" name="email" value="{{ old('email', $user->email) }}"
                                                class="form-control"
                                                style="border:1.5px solid #DEE2E6; border-radius:8px; padding:12px 16px; font-size:0.95rem; background:#f9f9f9;"
                                                onfocus="this.style.borderColor='#017075'; this.style.background='#fff';"
                                                onblur="this.style.borderColor='#DEE2E6'; this.style.background='#f9f9f9';"
                                                placeholder="your@email.com" required>
                                            @error('email')<p style="color:#dc3545; font-size:0.8rem; margin-top:4px;">
                                            {{ $message }}</p>@enderror
                                        </div>

                                        <button type="submit" class="btn-gehna btn-teal-gehna w-100 justify-content-center"
                                            style="border-radius:8px;">
                                            <i class="bi bi-check2 me-2"></i> Save Profile
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        {{-- Change Password --}}
                        <div class="col-lg-6">
                            <div
                                style="background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.06); overflow:hidden;">
                                <div
                                    class="heading-section">
                                    <h3
                                        style="color:#fff; font-size:1rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; margin:0;">
                                        <i class="bi bi-shield-lock me-2" style="color:#00e5ff;"></i> Change Password
                                    </h3>
                                </div>
                                <div style="padding:28px;">
                                    <form action="{{ route('account.password.update') }}" method="POST">
                                        @csrf
                                        @method('PUT')

                                        <div class="mb-3">
                                            <label
                                                style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;"
                                                class="form-label">Current Password</label>
                                            <div style="position:relative;">
                                                <input type="password" name="current_password" id="cur_pass"
                                                    class="form-control"
                                                    style="border:1.5px solid #DEE2E6; border-radius:8px; padding:12px 45px 12px 16px; font-size:0.95rem; background:#f9f9f9;"
                                                    onfocus="this.style.borderColor='#017075'; this.style.background='#fff';"
                                                    onblur="this.style.borderColor='#DEE2E6'; this.style.background='#f9f9f9';"
                                                    placeholder="Enter current password">
                                                <button type="button" onclick="togglePass('cur_pass','eye_cur')"
                                                    style="position:absolute; right:14px; top:50%; transform:translateY(-50%); background:none; border:none; color:#6C757D; cursor:pointer;">
                                                    <i class="bi bi-eye" id="eye_cur"></i>
                                                </button>
                                            </div>
                                            @error('current_password')<p
                                                style="color:#dc3545; font-size:0.8rem; margin-top:4px;">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <label
                                                style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;"
                                                class="form-label">New Password</label>
                                            <div style="position:relative;">
                                                <input type="password" name="password" id="new_pass" class="form-control"
                                                    style="border:1.5px solid #DEE2E6; border-radius:8px; padding:12px 45px 12px 16px; font-size:0.95rem; background:#f9f9f9;"
                                                    onfocus="this.style.borderColor='#017075'; this.style.background='#fff';"
                                                    onblur="this.style.borderColor='#DEE2E6'; this.style.background='#f9f9f9';"
                                                    placeholder="Enter new password">
                                                <button type="button" onclick="togglePass('new_pass','eye_new')"
                                                    style="position:absolute; right:14px; top:50%; transform:translateY(-50%); background:none; border:none; color:#6C757D; cursor:pointer;">
                                                    <i class="bi bi-eye" id="eye_new"></i>
                                                </button>
                                            </div>
                                            @error('password')<p style="color:#dc3545; font-size:0.8rem; margin-top:4px;">
                                            {{ $message }}</p>@enderror
                                        </div>

                                        <div class="mb-4">
                                            <label
                                                style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; color:#6C757D;"
                                                class="form-label">Confirm New Password</label>
                                            <div style="position:relative;">
                                                <input type="password" name="password_confirmation" id="conf_pass"
                                                    class="form-control"
                                                    style="border:1.5px solid #DEE2E6; border-radius:8px; padding:12px 45px 12px 16px; font-size:0.95rem; background:#f9f9f9;"
                                                    onfocus="this.style.borderColor='#017075'; this.style.background='#fff';"
                                                    onblur="this.style.borderColor='#DEE2E6'; this.style.background='#f9f9f9';"
                                                    placeholder="Confirm new password">
                                                <button type="button" onclick="togglePass('conf_pass','eye_conf')"
                                                    style="position:absolute; right:14px; top:50%; transform:translateY(-50%); background:none; border:none; color:#6C757D; cursor:pointer;">
                                                    <i class="bi bi-eye" id="eye_conf"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <button type="submit" class="btn-gehna btn-teal-gehna w-100 justify-content-center"
                                            style="border-radius:8px;">
                                            <i class="bi bi-lock me-2"></i> Update Password
                                        </button>
                                    </form>
                                </div>
                            </div>
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