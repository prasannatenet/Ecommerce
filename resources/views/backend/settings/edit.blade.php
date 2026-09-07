@extends('layouts.app')
@section('content')

    <div class="df-page-header">
        <h1 class="df-page-title">Site Settings</h1>
        <nav class="df-breadcrumb">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Site Settings</span>
        </nav>
    </div>

    @if(session('success'))
        <div class="df-alert df-alert-success">
            <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="df-alert df-alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <ul class="mb-0">
                @foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </div>
    @endif

    @if($errors->has('smtp_test'))
        <div class="df-alert df-alert-danger">
            <i class="bi bi-exclamation-octagon-fill"></i> {{ $errors->first('smtp_test') }}
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="df-card">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-gear"></i> General Settings</h5>
                </div>
                <div class="df-card-body">
                    <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="df-form-label">Site Name</label>
                                <input type="text" name="site_name" class="df-form-control" value="{{ old('site_name', optional($setting)->site_name) }}"
                                       placeholder="e.g. My E-commerce Store">
                            </div>
                            <div class="col-md-6">
                                <label class="df-form-label">Contact Email</label>
                                <input type="email" name="email" class="df-form-control" value="{{ old('email', optional($setting)->email) }}"
                                       placeholder="info@example.com">
                            </div>
                            <div class="col-md-6">
                                <label class="df-form-label">Contact Phone</label>
                                <input type="text" name="phone" class="df-form-control" value="{{ old('phone', optional($setting)->phone) }}"
                                       placeholder="+91 98765 43210">
                            </div>
                            <div class="col-12 mt-4 mb-2">
                                <h6 style="font-weight:700; color:var(--df-text-secondary); text-transform:uppercase; font-size:0.8rem; letter-spacing:0.5px; border-bottom:1px solid var(--df-border); padding-bottom:8px;">Address Information</h6>
                            </div>
                            <div class="col-12">
                                <label class="df-form-label">Address Line</label>
                                <input type="text" name="address" class="df-form-control" value="{{ old('address', optional($setting)->address) }}"
                                       placeholder="123 Main St, Suite 100">
                            </div>
                            <div class="col-md-3">
                                <label class="df-form-label">City</label>
                                <input type="text" name="city" class="df-form-control" value="{{ old('city', optional($setting)->city) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="df-form-label">State</label>
                                <input type="text" name="state" class="df-form-control" value="{{ old('state', optional($setting)->state) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="df-form-label">Country</label>
                                <input type="text" name="country" class="df-form-control" value="{{ old('country', optional($setting)->country) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="df-form-label">ZIP / Postal Code</label>
                                <input type="text" name="zip" class="df-form-control" value="{{ old('zip', optional($setting)->zip) }}">
                            </div>

                            <div class="col-12 mt-4 mb-2">
                                <h6 style="font-weight:700; color:var(--df-text-secondary); text-transform:uppercase; font-size:0.8rem; letter-spacing:0.5px; border-bottom:1px solid var(--df-border); padding-bottom:8px;">Social Media Links</h6>
                            </div>
                            <div class="col-md-6">
                                <label class="df-form-label">Facebook URL</label>
                                <input type="url" name="facebook_url" class="df-form-control" value="{{ old('facebook_url', optional($setting)->facebook_url) }}" placeholder="https://facebook.com/yourpage">
                            </div>
                            <div class="col-md-6">
                                <label class="df-form-label">Instagram URL</label>
                                <input type="url" name="instagram_url" class="df-form-control" value="{{ old('instagram_url', optional($setting)->instagram_url) }}" placeholder="https://instagram.com/yourhandle">
                            </div>
                            <div class="col-md-6">
                                <label class="df-form-label">Twitter/X URL</label>
                                <input type="url" name="twitter_url" class="df-form-control" value="{{ old('twitter_url', optional($setting)->twitter_url) }}" placeholder="https://x.com/yourhandle">
                            </div>
                            <div class="col-md-6">
                                <label class="df-form-label">YouTube URL</label>
                                <input type="url" name="youtube_url" class="df-form-control" value="{{ old('youtube_url', optional($setting)->youtube_url) }}" placeholder="https://youtube.com/@yourchannel">
                            </div>
                            <div class="col-md-6">
                                <label class="df-form-label">LinkedIn URL</label>
                                <input type="url" name="linkedin_url" class="df-form-control" value="{{ old('linkedin_url', optional($setting)->linkedin_url) }}" placeholder="https://linkedin.com/company/yourcompany">
                            </div>

                            <div class="col-12 mt-4 mb-2">
                                <h6 style="font-weight:700; color:var(--df-text-secondary); text-transform:uppercase; font-size:0.8rem; letter-spacing:0.5px; border-bottom:1px solid var(--df-border); padding-bottom:8px;">SMTP Configuration</h6>
                            </div>
                            <div class="col-md-6">
                                <label class="df-form-label">SMTP Host</label>
                                <input type="text" name="smtp_host" class="df-form-control" value="{{ old('smtp_host', optional($setting)->smtp_host) }}" placeholder="smtp.mailprovider.com">
                            </div>
                            <div class="col-md-3">
                                <label class="df-form-label">SMTP Port</label>
                                <input type="number" name="smtp_port" class="df-form-control" value="{{ old('smtp_port', optional($setting)->smtp_port) }}" placeholder="587" min="1" max="65535">
                            </div>
                            <div class="col-md-3">
                                <label class="df-form-label">Encryption</label>
                                <select name="smtp_encryption" class="df-form-control">
                                    <option value="">None</option>
                                    <option value="tls" @selected(old('smtp_encryption', optional($setting)->smtp_encryption) === 'tls')>TLS</option>
                                    <option value="ssl" @selected(old('smtp_encryption', optional($setting)->smtp_encryption) === 'ssl')>SSL</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="df-form-label">SMTP Username</label>
                                <input type="text" name="smtp_username" class="df-form-control" value="{{ old('smtp_username', optional($setting)->smtp_username) }}" placeholder="smtp-user@example.com">
                            </div>
                            <div class="col-md-6">
                                <label class="df-form-label">SMTP Password</label>
                                <input type="password" name="smtp_password" class="df-form-control" value="" placeholder="Leave blank to keep current password">
                            </div>
                            <div class="col-md-6">
                                <label class="df-form-label">From Email</label>
                                <input type="email" name="smtp_from_email" class="df-form-control" value="{{ old('smtp_from_email', optional($setting)->smtp_from_email) }}" placeholder="noreply@example.com">
                            </div>
                            <div class="col-md-6">
                                <label class="df-form-label">From Name</label>
                                <input type="text" name="smtp_from_name" class="df-form-control" value="{{ old('smtp_from_name', optional($setting)->smtp_from_name) }}" placeholder="Store Name">
                            </div>
                            <div class="col-md-6">
                                <label class="df-form-label">Test Recipient Email</label>
                                <input type="email" name="test_email" class="df-form-control" value="{{ old('test_email') }}" placeholder="you@example.com">
                                <small style="color:var(--df-text-secondary); font-size:0.8rem;">Used when clicking "Save & Send Test SMTP".</small>
                            </div>

                            <div class="col-12 mt-4 mb-2">
                                <h6 style="font-weight:700; color:var(--df-text-secondary); text-transform:uppercase; font-size:0.8rem; letter-spacing:0.5px; border-bottom:1px solid var(--df-border); padding-bottom:8px;">Branding & SEO</h6>
                            </div>
                            <div class="col-12">
                                <label class="df-form-label">Site Description</label>
                                <textarea name="description" class="df-form-control" rows="3"
                                          placeholder="Short description of your store used for SEO...">{{ old('description', optional($setting)->description) }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="df-form-label">Site Logo</label>
                                <input type="file" name="logo" id="logoInput" class="df-form-control" accept="image/*">
                                <div id="logoPreview" class="mt-3 d-none">
                                    <div style="padding:10px; background:var(--df-body-bg); border:1px dashed var(--df-border-color); border-radius:8px; display:inline-block;">
                                        <p class="mb-2" style="font-size:0.75rem; color:var(--df-text-muted); font-weight:600; text-transform:uppercase;">New Preview:</p>
                                        <img src="" alt="Logo Preview" style="height:50px; object-fit:contain;">
                                    </div>
                                </div>
                                @if(optional($setting)->logo_path)
                                    <div class="mt-3" id="currentLogo">
                                        <p class="mb-2" style="font-size:0.75rem; color:var(--df-text-muted); font-weight:600; text-transform:uppercase;">Current Logo:</p>
                                        <div style="padding:10px; background:var(--df-body-bg); border-radius:8px; display:inline-block;">
                                            <img src="{{ asset('storage/' . $setting->logo_path) }}" alt="Logo" style="height:50px; object-fit:contain;">
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label class="df-form-label">Favicon</label>
                                <input type="file" name="favicon" id="faviconInput" class="df-form-control" accept="image/*">
                                <div id="faviconPreview" class="mt-3 d-none">
                                    <div style="padding:10px; background:var(--df-body-bg); border:1px dashed var(--df-border-color); border-radius:8px; display:inline-block;">
                                        <p class="mb-2" style="font-size:0.75rem; color:var(--df-text-muted); font-weight:600; text-transform:uppercase;">New Preview:</p>
                                        <img src="" alt="Favicon Preview" style="height:32px; object-fit:contain;">
                                    </div>
                                </div>
                                @if(optional($setting)->favicon_path)
                                    <div class="mt-3" id="currentFavicon">
                                        <p class="mb-2" style="font-size:0.75rem; color:var(--df-text-muted); font-weight:600; text-transform:uppercase;">Current Favicon:</p>
                                        <div style="padding:10px; background:var(--df-bg-secondary); border-radius:8px; display:inline-block;">
                                            <img src="{{ asset('storage/' . $setting->favicon_path) }}" alt="Favicon" style="height:32px; object-fit:contain;">
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4 pt-3" style="border-top:1px solid var(--df-border);">
                            <button type="submit" class="df-btn df-btn-primary">
                                <i class="bi bi-check2-circle"></i> Save Settings
                            </button>
                            <button type="submit" name="action" value="test_smtp" class="df-btn" style="background:#0f766e; color:#fff; border:1px solid #0f766e;">
                                <i class="bi bi-envelope-check"></i> Save &amp; Send Test SMTP
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="df-card mb-4">
                <div class="df-card-header">
                    <h5 class="df-card-title"><i class="bi bi-info-circle"></i> About Settings</h5>
                </div>
                <div class="df-card-body">
                    <p style="color:var(--df-text-secondary); font-size:0.88rem; margin-bottom:12px;">
                        These settings control the global appearance and contact information of your store.
                    </p>
                    <div style="font-size:0.88rem;">
                        <ul style="padding-left:16px; margin-bottom:0; color:var(--df-text-secondary);">
                            <li style="margin-bottom:6px;"><strong>Site Name</strong> appears in browser tabs and emails.</li>
                            <li style="margin-bottom:6px;"><strong>Contact Email</strong> is used as the sender for systemic emails.</li>
                            <li style="margin-bottom:6px;"><strong>SMTP details</strong> override default mail configuration for outgoing emails.</li>
                            <li><strong>Favicon</strong> should be a square image, typically 32x32 pixels.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function setupImagePreview(inputId, previewId, currentId) {
            const input = document.getElementById(inputId);
            const previewContainer = document.getElementById(previewId);
            const previewImg = previewContainer.querySelector('img');
            const currentContainer = document.getElementById(currentId);

            input.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImg.src = e.target.result;
                        previewContainer.classList.remove('d-none');
                        if (currentContainer) {
                            currentContainer.style.opacity = '0.5';
                        }
                    }
                    reader.readAsDataURL(file);
                } else {
                    previewContainer.classList.add('d-none');
                    if (currentContainer) {
                        currentContainer.style.opacity = '1';
                    }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            setupImagePreview('logoInput', 'logoPreview', 'currentLogo');
            setupImagePreview('faviconInput', 'faviconPreview', 'currentFavicon');
        });
    </script>
    @endpush
@endsection
