<style>
    .frontend-site .footer {
        background: #f5f4f0 !important;
        color: #24211d !important;
        padding: 52px 24px 18px;
        border-top: 1px solid #e5ded0;
    }

    .frontend-site .footer::before {
        display: none;
    }

    .frontend-site .footer-wrapper {
        width: min(calc(100% - 48px), 1344px);
        display: grid;
        grid-template-columns: 1fr;
        grid-template-areas:
            "brand"
            "nav"
            "info"
            "signup";
        gap: 0;
        padding: 0;
    }

    .frontend-site .footer-left {
        display: contents;
    }

    .frontend-site .footer-brand-row {
        display: none;
    }

    .frontend-site .footer-brand-row .logo {
        color: #24211d !important;
    }

    .frontend-site .footer-brand-row .logo span {
        color: #24211d !important;
        font-size: clamp(2rem, 4vw, 3.2rem) !important;
        letter-spacing: 0.16em !important;
    }

    .frontend-site .footer-tagline {
        display: none;
    }

    .frontend-site .footer-nav {
        grid-area: nav;
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 0;
        border-top: 1px solid #e5ded0;
        border-bottom: 1px solid #e5ded0;
        padding: 13px 0;
    }

    .frontend-site .footer-nav a {
        color: #6f685d !important;
        font-size: 1rem;
        font-weight: 700;
        letter-spacing: 0.16em;
        line-height: 1;
        padding: 0 26px;
        text-transform: uppercase;
        border-right: 1px solid #e5ded0;
    }

    .frontend-site .footer-nav a:last-child {
        border-right: 0;
    }

    .frontend-site .footer-nav a:hover {
        color: #8e702d !important;
    }

    .frontend-site .footer-right {
        grid-area: info;
        grid-template-columns: minmax(150px, .7fr) repeat(3, minmax(0, 1fr));
        gap: 0;
        padding: 34px 0 30px;
        border-bottom: 1px solid #e5ded0;
        text-align: center;
    }

    .frontend-site .footer-logo-col {
        min-height: 112px;
        padding: 0 28px;
        border-right: 1px solid #e5ded0;
        display: flex;
        align-items: flex-start;
        justify-content: center;
    }

    .frontend-site .footer-logo-col .logo {
        color: #24211d !important;
        font-family: Georgia, 'Times New Roman', serif;
        font-size: 2.6rem;
        line-height: 1;
        text-decoration: none;
    }

    .frontend-site .footer-col {
        min-height: 112px;
        padding: 0 28px;
        border-right: 1px solid #e5ded0;
    }

    .frontend-site .footer-col:last-child {
        border-right: 0;
    }

    .frontend-site .footer-col h6 {
        color: #24211d !important;
        font-size: 0.78rem;
        letter-spacing: 0.16em;
        line-height: 1.4;
        margin: 0 0 14px;
        text-transform: uppercase;
    }

    .frontend-site .footer-col a,
    .frontend-site .footer-col span {
        color: #6f685d !important;
        font-size: 0.84rem;
        line-height: 1.8;
        margin-bottom: 0;
    }

    .frontend-site .footer-col a:hover {
        color: #8e702d !important;
    }

    .frontend-site .newsletter-box {
        grid-area: signup;
        display: grid;
        grid-template-columns: 1fr minmax(250px, 360px) 1fr;
        align-items: center;
        gap: 24px;
        max-width: none;
        min-height: 0;
        padding: 28px 0 12px;
        background: none;
    }

    .frontend-site .newsletter-vector,
    .frontend-site .newsletter-text,
    .frontend-site .newsletter-box > div[style*="margin-top"] {
        display: none;
    }

    .frontend-site .newsletter-title {
        color: #0f172a !important;
        font-family: "Sprintura Demo", cursive;
        font-size: 1.7rem;
        font-weight: 400;
        margin: 0;
        opacity: 1 !important;
        text-align: right;
        text-shadow: none;
    }

    .frontend-site .newsletter-box form {
        margin: 0;
    }

    .frontend-site .newsletter-input {
        width: 100%;
        min-height: 44px;
        background: #ffffff;
        border: 1px solid #e5ded0;
        border-radius: 0;
    }

    .frontend-site .newsletter-input input {
        color: #24211d;
        font-size: 0.8rem;
    }

    .frontend-site .newsletter-input input::placeholder {
        color: #b8b0a3;
    }

    .frontend-site .newsletter-input button {
        width: 42px;
        height: 42px;
        background: #b08d3c;
        color: #ffffff;
    }

    .frontend-site .newsletter-input button svg {
        display: none;
    }

    .frontend-site .newsletter-input button::after {
        content: '\2192';
        font-size: 1.25rem;
        line-height: 1;
    }

    .frontend-site .footer-bottom {
        width: min(calc(100% - 48px), 1344px);
        margin: 0 auto;
        padding: 12px 0 0;
    }

    .frontend-site .footer-bottom-right {
        grid-column: 1;
        border-top: 0;
        padding: 0;
        justify-content: center;
        flex-wrap: wrap;
        gap: 14px 26px;
    }

    .frontend-site .footer-links {
        justify-content: center;
        flex-wrap: wrap;
        gap: 14px 22px;
    }

    .frontend-site .footer-links span,
    .frontend-site .footer-links a {
        color: #b8b0a3 !important;
        font-size: 0.78rem;
    }

    .frontend-site .footer-social {
        margin-left: 0;
    }

    .frontend-site .footer-social a {
        color: #6f685d;
    }

    .frontend-site .footer-social a:hover {
        color: #8e702d !important;
    }

    @media (max-width: 700px) {
        .frontend-site .footer-wrapper,
        .frontend-site .footer-bottom {
            width: 100%;
        }

        .frontend-site .footer-nav a {
            margin: 6px 0;
            padding: 0 12px;
        }

        .frontend-site .footer-right {
            grid-template-columns: 1fr;
            gap: 22px;
        }

        .frontend-site .footer-logo-col {
            min-height: 0;
            padding: 0 0 22px;
            border-right: 0;
            border-bottom: 1px solid #e5ded0;
        }

        .frontend-site .footer-col {
            min-height: 0;
            padding: 0;
            border-right: 0;
        }

        .frontend-site .newsletter-box {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .frontend-site .newsletter-title {
            text-align: center;
        }
    }

    /* Reference-style footer treatment */
    .frontend-site .footer {
        background: #222222 !important;
        border-top: 1px solid #303030;
        color: #8a8a8a !important;
        padding: 38px 24px 26px;
    }

    .frontend-site .footer-wrapper {
        display: block;
        width: min(100%, 960px);
    }

    .frontend-site .footer-brand-row {
        display: flex;
        justify-content: center;
        margin: 0;
        padding: 0 0 24px;
    }

    .frontend-site .footer-brand-row .logo,
    .frontend-site .footer-brand-row .logo span {
        color: #efbc5e !important;
        font-family: Georgia, 'Times New Roman', serif;
        font-size: 3rem !important;
        font-weight: 400 !important;
        letter-spacing: 0.02em !important;
        text-decoration: none;
    }

    .frontend-site .footer-nav {
        border-top: 1px solid #fdfbfb;
        border-bottom: 0;
        gap: 12px 115px;
        padding: 18px 0 4px;
    }

    .frontend-site .footer-nav a {
        border: 0;
        color: #c0c0bc !important;
        font-size: 1rem;
        font-weight: 600;
        letter-spacing: 0.24em;
        padding: 0;
    }

    .frontend-site .footer-nav a:hover,
    .frontend-site .footer-links a:hover {
        color: #e5b86b !important;
    }

    .frontend-site .footer-secondary-nav {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 12px 110px;
        padding: 12px 0 22px;
        border-bottom: 1px solid #ffffff;
    }

    .frontend-site .footer-secondary-nav a {
        color: #8a8a8a;
        font-size: 0.58rem;
        font-weight: 600;
        letter-spacing: 0.22em;
        text-decoration: none;
        text-transform: uppercase;
    }

    .frontend-site .footer-secondary-nav a:hover {
        color: #e5b86b;
    }

    .frontend-site .newsletter-box,
    .frontend-site .footer-right {
        display: none;
    }

    .frontend-site .footer-bottom {
        display: block;
        width: min(100%, 960px);
        margin-top: 0;
        padding: 0;
    }

    .frontend-site .footer-bottom-right {
        display: flex;
        flex-direction: column;
        gap: 22px;
        border-top: 0;
        padding-top: 22px;
    }

    .frontend-site .footer-social {
        order: 1;
        margin: 0;
        gap: 9px;
    }

    .frontend-site .footer-social a {
        width: 30px;
        height: 30px;
        border: 1px solid #444444;
        color: #b5b5b1;
        font-size: 0.72rem;
    }

    .frontend-site .footer-social a:hover {
        border-color: #e5b86b;
        color: #e5b86b;
    }

    .frontend-site .footer-links {
        order: 2;
        justify-content: center;
        flex-wrap: wrap;
        gap: 10px 18px;
    }

    .frontend-site .footer-links span,
    .frontend-site .footer-links a {
        color: white !important;
        font-size: 0.68rem;
        letter-spacing: 0.01em;
    }
</style>

<footer class="footer">

    <div class="container-fluid footer-wrapper">

        <!-- LEFT SIDE -->
        <div class="footer-left">
            <div class="footer-brand-row">
                <a class="logo" href="{{ route('home') }}">
                    @if ($appSetting && $appSetting->logo_path)
                        <img src="{{ Storage::url($appSetting->logo_path) }}"
                            alt="{{ $appSetting->site_name ?? 'Boxima' }}" height="38">
                    @else
                        <span style="font-size:1.7rem; font-weight:900; letter-spacing:2px;">GEHNA</span>
                    @endif
                </a>
                <span class="footer-tagline">KEEP GOING</span>
            </div>

            <nav class="footer-nav" aria-label="Footer navigation">
                <a href="{{ route('home') }}">Home</a>
                <a href="{{ route('products.index') }}">Shop</a>
                <a href="{{ route('categories.index') }}">Categories</a>
                <a href="{{ route('brands.index') }}">Brands</a>
                <a href="{{ route('cart.index') }}">Cart</a>
            </nav>

            <nav class="footer-secondary-nav" aria-label="Additional footer navigation">
                <a href="{{ route('products.index') }}">Collections</a>
                <a href="{{ route('categories.index') }}">New Arrivals</a>
                <a href="{{ route('brands.index') }}">Our Story</a>
                <a href="{{ route('products.index') }}">Contact</a>
            </nav>

            <div class="newsletter-box">
                <img src="{{ asset('frontend/images/fSectionvector.png') }}" class="newsletter-vector" alt="">
                <p class="newsletter-title">Newsletter Signup</p>

                <form action="{{ route('newsletter.subscribe') }}" method="POST">
                    @csrf
                    <div class="newsletter-input">
                        <input type="email" name="newsletter_email" value="{{ old('newsletter_email') }}" placeholder="Your email" required>
                        <button type="submit" aria-label="Subscribe newsletter"
                            style="background:none; border:none; padding:0; cursor:pointer;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="85" height="85" viewBox="0 0 85 85"
                                fill="none">
                                <g filter="url(#filter0_d_6335_2864)">
                                    <circle cx="42.5" cy="38.5" r="27.5" fill="url(#paint0_linear_6335_2864)" />
                                    <circle cx="42.5" cy="38.5" r="27" stroke="#004B4E" />
                                </g>
                                <path
                                    d="M30.9757 39.4468C30.0799 39.9168 30.1171 40.6051 31.0563 40.9768L33.839 42.0778C34.779 42.451 36.2013 42.2473 36.998 41.6256L49.0821 32.0959C49.8765 31.471 49.9617 31.5632 49.2718 32.3018L39.7188 42.5269C39.0266 43.2632 39.231 44.1675 40.1741 44.5322L40.5001 44.6592C41.4431 45.0239 42.9801 45.6363 43.9169 46.0173L47.0032 47.2739C47.9408 47.6549 48.9427 47.2058 49.2091 46.2302L53.9128 28.9407C54.1784 27.9651 53.6627 27.5509 52.7669 28.0201L30.9757 39.4468Z"
                                    fill="white" />
                                <path
                                    d="M38.6129 50.7665C38.5579 50.9314 40.5161 47.9311 40.5161 47.9311C41.0697 47.0856 40.7553 46.0767 39.82 45.6942L37.6845 44.8201C36.7492 44.4376 36.3009 44.8867 36.6881 45.8212C36.6881 45.8212 38.6694 50.5969 38.6129 50.7665Z"
                                    fill="white" />
                                <defs>
                                    <filter id="filter0_d_6335_2864" x="0" y="0" width="85" height="85"
                                        filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
                                        <feFlood flood-opacity="0" result="BackgroundImageFix" />
                                        <feColorMatrix in="SourceAlpha" type="matrix"
                                            values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" result="hardAlpha" />
                                        <feOffset dy="4" />
                                        <feGaussianBlur stdDeviation="7.5" />
                                        <feComposite in2="hardAlpha" operator="out" />
                                        <feColorMatrix type="matrix"
                                            values="0 0 0 0 0 0 0 0 0 0.417925 0 0 0 0 0.438101 0 0 0 0.5 0" />
                                        <feBlend mode="normal" in2="BackgroundImageFix"
                                            result="effect1_dropShadow_6335_2864" />
                                        <feBlend mode="normal" in="SourceGraphic" in2="effect1_dropShadow_6335_2864"
                                            result="shape" />
                                    </filter>
                                    <linearGradient id="paint0_linear_6335_2864" x1="15" y1="11" x2="75.2743"
                                        y2="16.6241" gradientUnits="userSpaceOnUse">
                                        <stop stop-color="#017075" />
                                        <stop offset="0.55" stop-color="#02AAB1" />
                                        <stop offset="1" stop-color="#00595D" />
                                    </linearGradient>
                                </defs>
                            </svg>
                        </button>
                    </div>
                </form>

                @if(session('newsletter_success'))
                    <p style="margin-top:8px; color:#90ee90; font-size:0.8rem;">{{ session('newsletter_success') }}</p>
                @endif
                @if(session('newsletter_info'))
                    <p style="margin-top:8px; color:#b7d6ff; font-size:0.8rem;">{{ session('newsletter_info') }}</p>
                @endif
                @error('newsletter_email')
                    <p style="margin-top:8px; color:#ff9ca8; font-size:0.8rem;">{{ $message }}</p>
                @enderror

                <p class="newsletter-text">
                    {{ $appSetting->description ?? 'Premium home gym equipment trusted by fitness enthusiasts across India. Quality gear for every workout goal.' }}
                </p>

                {{-- Contact info from settings --}}
                @if ($appSetting && ($appSetting->email || $appSetting->phone))
                    <div style="margin-top:14px; display:flex; flex-direction:column; gap:6px;">
                        @if ($appSetting->email)
                            <a href="mailto:{{ $appSetting->email }}"
                                style="color:rgba(255,255,255,0.65); font-size:0.85rem; text-decoration:none; display:flex; align-items:center; gap:8px; transition:color 0.2s;"
                                onmouseover="this.style.color='#00e5ff';"
                                onmouseout="this.style.color='rgba(255,255,255,0.65)';">
                                <i class="bi bi-envelope" style="color:#00e5ff;"></i> {{ $appSetting->email }}
                            </a>
                        @endif
                        @if ($appSetting->phone)
                            <a href="tel:{{ $appSetting->phone }}"
                                style="color:rgba(255,255,255,0.65); font-size:0.85rem; text-decoration:none; display:flex; align-items:center; gap:8px; transition:color 0.2s;"
                                onmouseover="this.style.color='#00e5ff';"
                                onmouseout="this.style.color='rgba(255,255,255,0.65)';">
                                <i class="bi bi-telephone" style="color:#00e5ff;"></i> {{ $appSetting->phone }}
                            </a>
                        @endif
                        @if ($appSetting->address || $appSetting->city)
                            <p
                                style="color:rgba(255,255,255,0.65); font-size:0.85rem; margin:0; display:flex; align-items:flex-start; gap:8px;">
                                <i class="bi bi-geo-alt" style="color:#00e5ff; margin-top:2px;"></i>
                                {{ collect([$appSetting->address, $appSetting->city, $appSetting->state, $appSetting->country])->filter()->implode(', ') }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <!-- RIGHT SIDE -->
        <div class="footer-right">

            <div class="footer-logo-col">
                <a class="logo" href="{{ route('home') }}">
                    @if ($appSetting && $appSetting->logo_path)
                        <img src="{{ Storage::url($appSetting->logo_path) }}"
                            alt="{{ $appSetting->site_name ?? 'Gehna' }}" height="48">
                    @else
                        GEHNA
                    @endif
                </a>
            </div>

            <div class="footer-col">
                <h6>Quick Links</h6>
                <a href="{{ route('home') }}">Home</a>
                @if (!empty($aboutUsPage))
                    <a href="{{ route('pages.show', $aboutUsPage->slug) }}">{{ $aboutUsPage->title }}</a>
                @endif
                <a href="{{ route('products.index') }}">Shop</a>
                <a href="{{ route('categories.index') }}">Categories</a>
                <a href="{{ route('brands.index') }}">Brands</a>
                @auth
                    <a href="{{ route('account.index') }}">My Account</a>
                @else
                    <a href="{{ route('login') }}">Login</a>
                    <a href="{{ route('register') }}">Register</a>
                @endauth
            </div>

            <div class="footer-col">
                <h6>Account</h6>
                @auth
                    {{-- <a href="{{ route('account.index') }}">Dashboard</a> --}}
                    <a href="{{ route('account.orders') }}">My Orders</a>
                    <a href="{{ route('wishlist.index') }}">Wishlist</a>
                    <a href="{{ route('cart.index') }}">Cart</a>
                    <a href="{{ route('account.profile') }}">Profile Settings</a>
                    @if (!empty($aboutUsPage))
                        <a href="{{ route('pages.show', $aboutUsPage->slug) }}">{{ $aboutUsPage->title }}</a>
                    @endif
                @else
                    <a href="{{ route('login') }}">Login</a>
                    <a href="{{ route('register') }}">Create Account</a>
                    <a href="{{ route('password.request') }}">Forgot Password</a>
                @endauth
            </div>

            <div class="footer-col">
                <h6>Say Hello</h6>
                @if ($appSetting && $appSetting->email)
                    <a href="mailto:{{ $appSetting->email }}">{{ $appSetting->email }}</a>
                @else
                    <a href="mailto:info@boxima.com">info@boxima.com</a>
                @endif
                @if ($appSetting && $appSetting->phone)
                    <a href="tel:{{ $appSetting->phone }}">{{ $appSetting->phone }}</a>
                @endif
                @if ($appSetting && $appSetting->address)
                    <span style="color:rgba(255,255,255,0.55); font-size:0.85rem; line-height:1.5;">
                        {{ collect([$appSetting->address, $appSetting->city, $appSetting->state])->filter()->implode(', ') }}
                    </span>
                @endif
            </div>

        </div>

    </div>

    <!-- BOTTOM -->
    <div class="container-fluid footer-bottom">
        <div class="footer-bottom-right">
            <div class="footer-links">
                <span style="color:rgba(255,255,255,0.4); font-size:0.82rem;">
                    &copy; {{ date('Y') }} {{ $appSetting->site_name ?? 'Boxima Fitness' }}. All rights reserved.
                </span>
                <a href="#">Terms</a>
                <a href="#">Privacy</a>
                <a href="#">Cookies</a>
            </div>

            <div class="footer-social">
                <a href="{{ ($appSetting && $appSetting->linkedin_url) ? $appSetting->linkedin_url : '#' }}"
                    @if($appSetting && $appSetting->linkedin_url) target="_blank" rel="noopener" @endif
                    class="social-link social-linkedin" aria-label="LinkedIn">
                    <i class="bi bi-linkedin"></i>
                </a>

                <a href="{{ ($appSetting && $appSetting->facebook_url) ? $appSetting->facebook_url : '#' }}"
                    @if($appSetting && $appSetting->facebook_url) target="_blank" rel="noopener" @endif
                    class="social-link social-facebook" aria-label="Facebook">
                    <i class="bi bi-facebook"></i>
                </a>

                <a href="{{ ($appSetting && $appSetting->twitter_url) ? $appSetting->twitter_url : '#' }}"
                    @if($appSetting && $appSetting->twitter_url) target="_blank" rel="noopener" @endif
                    class="social-link social-twitter" aria-label="Twitter/X">
                    <i class="bi bi-twitter-x"></i>
                </a>

                <a href="{{ ($appSetting && $appSetting->instagram_url) ? $appSetting->instagram_url : '#' }}"
                    @if($appSetting && $appSetting->instagram_url) target="_blank" rel="noopener" @endif
                    class="social-link social-instagram" aria-label="Instagram">
                    <i class="bi bi-instagram"></i>
                </a>

                <a href="{{ ($appSetting && $appSetting->youtube_url) ? $appSetting->youtube_url : '#' }}"
                    @if($appSetting && $appSetting->youtube_url) target="_blank" rel="noopener" @endif
                    class="social-link social-youtube" aria-label="YouTube">
                    <i class="bi bi-youtube"></i>
                </a>
            </div>
        </div>
    </div>

</footer>
