<style>
    .frontend-site .footer {
        background: var(--primary, #faf8f3);
        border-top: 1px solid var(--border, #ded8cc);
        color: var(--secondary);
        padding: 56px 24px 28px;
    }

    .frontend-site .footer::before {
        display: none;
    }

    .frontend-site .footer-wrapper,
    .frontend-site .footer-bottom {
        width: min(100%, 1280px);
        margin: 0 auto;
        padding: 0;
    }

    .frontend-site .footer-wrapper {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr)) minmax(250px, 1.35fr);
        gap: 36px;
        align-items: start;
    }

    .frontend-site .footer-brand-row,
    .frontend-site .footer-nav,
    .frontend-site .footer-secondary-nav,
    .frontend-site .newsletter-box,
    .frontend-site .footer-logo-col {
        display: none;
    }

    .frontend-site .footer-left,
    .frontend-site .footer-right {
        display: contents;
    }

    .frontend-site .footer-col {
        min-width: 0;
    }

    .frontend-site .footer-col h6 {
        color: var(--secondary, #0f3d32) !important;
        font-size: 18px;
        font-weight: 400;
        line-height: 1.4;
        margin: 0 0 11px;
    }

    .frontend-site .footer-col a,
    .frontend-site .footer-col span,
    .frontend-site .footer-contact-line {
        color: #fff !important;
        display: block;
        font-size: 15px;
        line-height: 36px;
        font-weight: 400;
        margin: 0;
        text-decoration: none;
        opacity: 0.7;
        font-family: 'Poppins', sans-serif;
    }

    .frontend-site .footer-col a:hover {
        color: #fff !important;
        opacity: 1;
    }

    .frontend-site .footer-contact-line+.footer-contact-line {
        margin-top: 4px;
    }

    .frontend-site .footer-social {
        display: flex;
        gap: 13px;
        margin-top: 17px;
    }

    .frontend-site .footer-social a {
        align-items: center;
        background: var(--primary, #0f3d32);
        border-radius: 50%;
        color: #fff !important;
        display: inline-flex;
        height: 30px;
        justify-content: center;
        text-decoration: none;
        transition: background 0.2s ease, transform 0.2s ease;
        width: 30px;
    }

    .frontend-site .footer-social a:hover {
        background: var(--gold, #c9a227);
        transform: translateY(-2px);
    }

    .frontend-site .footer-bottom {
        align-items: center;
        border-top: 1px solid #197769;
        display: flex;
        justify-content: space-between;
        margin-top: 44px;
        padding-top: 22px;
    }

    .frontend-site .footer-links {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 10px 20px;
    }

    .frontend-site .footer-links span,
    .frontend-site .footer-links a {
        color: #fff;
        font-size: 0.78rem;
        text-decoration: none;
        opacity: 0.7;
    }

    .frontend-site .footer-links a:hover {
        color: var(--gold, #c9a227) !important;
    }

    .frontend-site .footer-payments {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 18px;
        background: white;
        padding: 4px;
        border-radius: 4px;
    }

    .frontend-site .footer-payments img {
        max-width: 50px;
    }

    .footer-contact-col p {
        font-family: "Poppins";
        line-height: 32px;
        font-weight: 400;
        opacity: 0.6;
    }

    @media (max-width: 900px) {
        .frontend-site .footer-wrapper {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .frontend-site .footer-contact-col {
            grid-column: span 2;
        }
    }

    @media (max-width: 600px) {
        .frontend-site .footer {
            padding: 42px 20px 24px;
        }

        .frontend-site .footer-wrapper {
            grid-template-columns: 1fr 1fr;
            gap: 30px 20px;
        }

        .frontend-site .footer-contact-col {
            grid-column: 1 / -1;
        }

        .frontend-site .footer-bottom {
            align-items: flex-start;
            flex-direction: column;
            gap: 18px;
            margin-top: 34px;
        }
    }

    .frontend-site .footer-col a {
        display: flex;
        gap: 7px;
    }

    .social-icons {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .social-icons a {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;

        border: 1px solid var(--border, #ded8cc);
        border-radius: 50%;

        color: var(--primary, #0f3d32);
        background: #fff;

        text-decoration: none;
        transition: all 0.3s ease;
        opacity: 1 !important;
    }

    .social-icons a i {
        font-size: 17px;
        color: var(--primary);
    }

    .social-icons a:hover {
        background: black;
        color: #fff !important;
        border-color: var(--primary, #0f3d32);
        transform: translateY(-3px);
        box-shadow: 0 6px 15px rgba(15, 61, 50, 0.18);
    }

    .social-icons a:hover i {
        color: white;
    }
</style>

<footer class="footer">
    <div class="container-fluid footer-wrapper">
        <div class="footer-col">
            <h6>Know Your Jewellery</h6>
            <a href="{{ route('products.index') }}"><i class="bi bi-chevron-right"></i> Jewellery guide</a>
            <a href="{{ route('categories.index') }}"><i class="bi bi-chevron-right"></i> Shop by category</a>
            <a href="{{ route('brands.index') }}"><i class="bi bi-chevron-right"></i> Shop by brand</a>
            <a href="{{ route('products.index') }}"><i class="bi bi-chevron-right"></i> New arrivals</a>
            <a href="{{ route('products.index') }}"><i class="bi bi-chevron-right"></i> All creations</a>
        </div>

        <div class="footer-col">
            <h6>{{ $appSetting->site_name ?? 'Gehna' }} Advantage</h6>
            <a href="{{ route('products.index') }}">Quality jewellery</a>
            <a href="{{ route('cart.index') }}">Easy checkout</a>
            <a href="{{ route('account.orders') }}">Order tracking</a>
            <a href="{{ route('wishlist.index') }}">Wishlist</a>
            <a href="{{ route('cart.index') }}">Secure shopping</a>
        </div>

        <div class="footer-col">
            <h6>Customer Service</h6>
            <a href="{{ route('account.orders') }}">Return an order</a>
            <a href="{{ route('account.orders') }}">Order status</a>
            <a href="{{ route('account.index') }}">My account</a>
            <a href="{{ route('products.index') }}">Shipping information</a>
            <a href="{{ route('cart.index') }}">Shopping bag</a>
        </div>

        <div class="footer-col">
            <h6>About Us</h6>
            @if (!empty($aboutUsPage))
                <a href="{{ route('pages.show', $aboutUsPage->slug) }}">{{ $aboutUsPage->title }}</a>
            @endif
            <a href="{{ route('brands.index') }}">Our brands</a>
            <a href="{{ route('categories.index') }}">Our collections</a>
            <a href="{{ route('home') }}">Our story</a>
        </div>

        <div class="footer-col footer-contact-col">
            <h6>Contact Us</h6>

            <div class="mb-2">Gehna Trading Pvt Ltd </div>

            <p> 6th Floor, Disney Cyberspace,<br>
                Malyiya Nagar, JE Industrial Estate,<br>
                Jaipur, Rajasthan,<br>
                India 123456<br>
                CIN: UE123456789978
            </p>

            <div class="social-icons">
                <a href="#" aria-label="Facebook">
                    <i class="bi bi-facebook"></i>
                </a>

                <a href="#" aria-label="Instagram">
                    <i class="bi bi-instagram"></i>
                </a>

                <a href="#" aria-label="YouTube">
                    <i class="bi bi-youtube"></i>
                </a>

                <a href="#" aria-label="WhatsApp">
                    <i class="bi bi-whatsapp"></i>
                </a>
            </div>

            @if ($appSetting && $appSetting->email)
                <a class="footer-contact-line" href="mailto:{{ $appSetting->email }}">{{ $appSetting->email }}</a>
            @endif
            @if ($appSetting && $appSetting->phone)
                <a class="footer-contact-line" href="tel:{{ $appSetting->phone }}">{{ $appSetting->phone }}</a>
            @endif
            @if ($appSetting && ($appSetting->address || $appSetting->city))
                <span
                    class="footer-contact-line">{{ collect([$appSetting->address, $appSetting->city, $appSetting->state, $appSetting->country, $appSetting->zip])->filter()->implode(', ') }}</span>
            @endif

            <div class="footer-social" aria-label="Social media links">
                @if ($appSetting && $appSetting->instagram_url)
                    <a href="{{ $appSetting->instagram_url }}" target="_blank" rel="noopener" aria-label="Instagram"><i
                            class="bi bi-instagram"></i></a>
                @endif
                @if ($appSetting && $appSetting->facebook_url)
                    <a href="{{ $appSetting->facebook_url }}" target="_blank" rel="noopener" aria-label="Facebook"><i
                            class="bi bi-facebook"></i></a>
                @endif
                @if ($appSetting && $appSetting->linkedin_url)
                    <a href="{{ $appSetting->linkedin_url }}" target="_blank" rel="noopener" aria-label="LinkedIn"><i
                            class="bi bi-linkedin"></i></a>
                @endif
                @if ($appSetting && $appSetting->twitter_url)
                    <a href="{{ $appSetting->twitter_url }}" target="_blank" rel="noopener" aria-label="Twitter/X"><i
                            class="bi bi-twitter-x"></i></a>
                @endif
                @if ($appSetting && $appSetting->youtube_url)
                    <a href="{{ $appSetting->youtube_url }}" target="_blank" rel="noopener" aria-label="YouTube"><i
                            class="bi bi-youtube"></i></a>
                @endif
            </div>
        </div>
    </div>


    <div class="container-fluid footer-bottom">
        <div class="footer-links">
            <span>&copy; {{ date('Y') }} {{ $appSetting->site_name ?? 'Gehna' }}. All rights reserved.</span>
            <a href="{{ route('home') }}">Terms</a>
            <a href="{{ route('home') }}">Privacy</a>
        </div>

        <div class="footer-payments" aria-label="Accepted payment methods">
            <span><img src="{{ asset('frontend/assets/visa.svg') }}"></span>
            <span><img src="{{ asset('frontend/assets/mc.png') }}"></span>
            <span><img src="{{ asset('frontend/assets/paypal.png') }}"></span>
            <span><img src="{{ asset('frontend/assets/ae.png') }}"></span>
            <span><img src="{{ asset('frontend/assets/upi.png') }}"></span>
        </div>
    </div>
</footer>