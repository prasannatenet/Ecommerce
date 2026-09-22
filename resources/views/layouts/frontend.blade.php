<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', ($appSetting->site_name ?? 'GEHNA') . ' | Fine Jewellery')</title>
    <meta name="description"
        content="{{ $appSetting->description ?? 'Discover thoughtfully crafted jewellery for every occasion.' }}">
    <link rel="icon" type="image/x-icon" href="{{ optional($appSetting)->favicon_path ? asset('storage/' . $appSetting->favicon_path) : asset('frontend/images/fav.png') }}">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Bai+Jamjuree:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;1,200;1,300;1,400;1,500;1,600;1,700&family=Inter:opsz@14..32&display=swap"
        rel="stylesheet">
    <!-- GEHNA Custom CSS -->
    <link rel="stylesheet" href="{{ asset('frontend/css/gehna.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/custom.css') }}">
    <script src="{{ asset('frontend/js/load.js') }}"></script>
    <script src="{{ asset('frontend/js/app.js') }}?v={{ filemtime(public_path('frontend/js/app.js')) }}"></script>

        @yield('styles')
    @stack('styles')

    <style>
        .timeline-container { border-left: 2px solid #e9ecef; padding-left: 16px; }
        .tracking-event-item { position: relative; padding-left: 16px; margin-bottom: 14px; }
        .tracking-event-item:last-child { margin-bottom: 0; }
        .tracking-event-item:before { content: ''; position: absolute; left: -8px; top: 0; width: 14px; height: 14px; border-radius: 50%; border: 2px solid #017075; background: #fff; }
        .tracking-event-item:last-child:before { background: #017075; }
        .tracking-event-status { font-weight: 600; color: #0D0D0D; margin: 0; font-size: 0.85rem; }
        .tracking-event-meta { color: #6C757D; font-size: 0.8rem; margin: 2px 0 0; }
    </style>
</head>

<body class="frontend-site {{ request()->routeIs('home') ? 'home-page' : '' }}">

    {{-- Pages that should render without the global chrome opt out with @section('hide_topbar_navbar', true) --}}
    @sectionMissing('hide_topbar_navbar')
        @include('layouts.topbar')
        @include('layouts.navbar')
    @endif

    @hasSection('hero')
        @yield('hero')
    @else
        @if (request()->routeIs('home'))
            @include('layouts.heroSection')
        @endif
    @endif

    <!-- ===== MAIN CONTENT ===== -->
    <main>
        @yield('content')
    </main>



    {{-- Pages that should render without the site footer opt out with @section('hide_footer', true) --}}
    @sectionMissing('hide_footer')
        @include('layouts.footer')
    @endif


    <!-- ===== QUICK VIEW MODAL ===== -->
    <div class="modal fade modal-gehna" id="quickViewModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="quickViewTitle">Quick View</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="quickViewBody"></div>
            </div>
        </div>
    </div>

    <!-- ===== OFFER POPUP ===== -->
    <div class="offer-popup-overlay" id="offerPopup" aria-hidden="true" role="dialog"
        aria-label="Get 10% off your first order">
        <div class="offer-popup">
            <button type="button" class="offer-popup-close" aria-label="Close offer">&times;</button>
            <div class="offer-popup-header">
                <div class="offer-popup-icon">
                    <i class="bi bi-gift"></i>
                </div>
                <span class="offer-popup-kicker">Exclusive Offer</span>
                <h3 style="color: #ffffff !important; font-family: sans-serif;">Get 10% OFF</h3>
                <p class="offer-popup-subtitle">Your first order!</p>
            </div>
            <div class="offer-popup-body">
                <p class="offer-popup-lead">Enter your email to receive your exclusive discount code</p>
                <form class="offer-popup-form" id="offerPopupForm" novalidate>
                    <div class="offer-popup-field">
                        <i class="bi bi-envelope" ></i>
                        <input type="email" name="email" class="offer-popup-input" placeholder="Your email address"
                            aria-label="Email address" required>
                    </div>
                    <p class="offer-popup-error" id="offerPopupError" hidden>Please enter a valid email address to claim your code.</p>
                    <button type="submit" class="btn-gehna btn-primary-gehna w-100">
                        <i class="bi bi-ticket-perforated" style="color: #ffffff !important;"></i><span style="color: #ffffff !important;">Claim My Discount</span>
                    </button>
                </form>
                <p class="offer-popup-note">No spam. Unsubscribe anytime.</p>
            </div>
        </div>
    </div>

    <!-- ===== WISHLIST OFFCANVAS ===== -->
    @include('frontend.wishlist.index')

    <script>
        /* Wishlist drawer: silent background refresh — no page reload, no scroll impact. */
        window.refreshWishlistSidebar = (function () {
            var pending = null;
            function swapInto(container, incoming) {
                if (!container) return;
                container.innerHTML = '';
                while (incoming.firstChild) container.appendChild(incoming.firstChild);
            }
            function refresh() {
                if (pending) return pending;
                pending = fetch('{{ route("wishlist.list") }}', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                })
                .then(function (res) { if (!res.ok) throw new Error('HTTP ' + res.status); return res.text(); })
                .then(function (html) {
                    pending = null;
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var fresh = doc.getElementById('wishlistSidebar');
                    var body = fresh ? fresh.querySelector('.offcanvas-body') : null;
                    var current = document.querySelector('#wishlistSidebar .offcanvas-body');
                    if (body && current) swapInto(current, body);
                })
                .catch(function () { pending = null; });
                return pending;
            }
            return refresh;
        })();

        document.addEventListener('DOMContentLoaded', function () {
            var sidebar = document.getElementById('wishlistSidebar');
            if (!sidebar || typeof bootstrap === 'undefined') return;
            sidebar.addEventListener('show.bs.offcanvas', function () {
                window.refreshWishlistSidebar();
            });
        });
    </script>

    <!-- ===== CONFIRM POPUP ===== -->
    <div class="confirm-popup-overlay" id="confirmPopup">
        <div class="confirm-popup-box">
            <div class="confirm-popup-icon">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <h5 class="confirm-popup-title" id="confirmPopupTitle">Are you sure?</h5>
            <p class="confirm-popup-msg" id="confirmPopupMsg"></p>
            <div class="confirm-popup-actions">
                <button class="confirm-popup-btn confirm-popup-cancel" id="confirmPopupCancel">Cancel</button>
                <button class="confirm-popup-btn confirm-popup-yes" id="confirmPopupYes">Yes, Remove</button>
            </div>
        </div>
    </div>

    <!-- ===== BACK TO TOP ===== -->
    <button class="back-to-top" id="backToTop"><i class="bi bi-chevron-up"></i></button>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script> -->
    <script>
        // Video modal — only on home page
        (function() {
            const modal    = document.getElementById("videoModal");
            const iframe   = document.getElementById("videoFrame");
            const closeBtn = document.querySelector(".close-btn");
            const watchButtons = document.querySelectorAll(".watch-video");
            if (!modal || !iframe) return;

            document.querySelectorAll(".video-circle").forEach(item => {
                item.addEventListener("click", () => {
                    iframe.src = item.dataset.video;
                    modal.classList.add("active");
                });
            });

            watchButtons.forEach((watchBtn) => {
                watchBtn.addEventListener("click", function() {
                    iframe.src = this.dataset.video;
                    modal.classList.add("active");
                });
            });

            if (closeBtn) {
                closeBtn.addEventListener("click", () => {
                    modal.classList.remove("active");
                    iframe.src = "";
                });
            }

            modal.addEventListener("click", (e) => {
                if (e.target === modal) {
                    modal.classList.remove("active");
                    iframe.src = "";
                }
            });
        })();
    </script>
    <script>
        const mindsetSection = document.querySelector(".mindset-section");
        const mindsetWrapper = document.querySelector(".mindset-wrapper");
        const spotlight = document.querySelector(".spotlight");
        const highlightText = document.querySelector(".mindset-section .text.highlight");
        const cursorCircle = document.querySelector(".cursor-circle");
        const isMobile = window.matchMedia("(max-width: 768px)");

        const resetMindsetEffects = () => {
            if (cursorCircle) cursorCircle.style.opacity = "0";
            if (highlightText) {
                const resetMask = "radial-gradient(circle 0px at 0 0, black 0%, transparent 100%)";
                highlightText.style.webkitMaskImage = resetMask;
                highlightText.style.maskImage = resetMask;
            }
        };

        if (mindsetSection && mindsetWrapper && spotlight && highlightText && cursorCircle) {
            mindsetSection.addEventListener("mouseenter", () => {
                if (!isMobile.matches) cursorCircle.style.opacity = "1";
            });

            mindsetSection.addEventListener("mouseleave", () => {
                resetMindsetEffects();
            });

            mindsetSection.addEventListener("mousemove", (e) => {
                if (isMobile.matches) {
                    resetMindsetEffects();
                    return;
                }

                const sectionRect = mindsetSection.getBoundingClientRect();
                const wrapperRect = mindsetWrapper.getBoundingClientRect();
                const sectionX = e.clientX - sectionRect.left;
                const sectionY = e.clientY - sectionRect.top;
                const wrapperX = e.clientX - wrapperRect.left;
                const wrapperY = e.clientY - wrapperRect.top;

                cursorCircle.style.left = `${wrapperX}px`;
                cursorCircle.style.top = `${wrapperY}px`;
                cursorCircle.style.opacity = "1";

                spotlight.style.background = `
      radial-gradient(
        circle 170px at ${sectionX}px ${sectionY}px,
        rgba(255,255,255,0.38) 0%,
        rgba(238,240,241,0) 72%
      )
    `;

                const textRect = highlightText.getBoundingClientRect();
                const localX = e.clientX - textRect.left;
                const localY = e.clientY - textRect.top;
                const revealYOffset = 42;
                const mask =
                    `radial-gradient(circle 96px at ${localX}px ${localY + revealYOffset}px, black 86%, transparent 100%)`;
                highlightText.style.webkitMaskImage = mask;
                highlightText.style.maskImage = mask;
            });

            isMobile.addEventListener("change", () => {
                resetMindsetEffects();
            });
        }
    </script>
    <script>
        (() => {
            const slider = document.querySelector(".pm-testimonial-slider");
            const track = document.querySelector(".pm-testimonial-track");
            if (!slider || !track) return;

            const originalItems = Array.from(track.children);
            originalItems.forEach((item) => {
                track.appendChild(item.cloneNode(true));
            });

            let rafId = null;
            const speed = 0.45;

            const tick = () => {
                slider.scrollLeft += speed;
                if (slider.scrollLeft >= track.scrollWidth / 2) {
                    slider.scrollLeft = 0;
                }
                rafId = requestAnimationFrame(tick);
            };

            const start = () => {
                if (!rafId) rafId = requestAnimationFrame(tick);
            };

            const stop = () => {
                if (rafId) {
                    cancelAnimationFrame(rafId);
                    rafId = null;
                }
            };

            start();
            slider.addEventListener("mouseenter", stop);
            slider.addEventListener("mouseleave", start);
            window.addEventListener("blur", stop);
            window.addEventListener("focus", start);
        })();
    </script>
    <script>
        (function() {
            const items = document.querySelectorAll(".faq-item");
            items.forEach(item => {
                item.addEventListener("click", () => {
                    items.forEach(i => { if (i !== item) i.classList.remove("active"); });
                    item.classList.toggle("active");
                });
            });
        })();
    </script>

    <script>
        function toggleAjaxWishlist(productId, btnElement) {
            let icon = btnElement.querySelector('i');
            let isAdded = icon.classList.contains('bi-heart');
            
            // Optimistic UI update
            if(isAdded) {
                // If it was empty heart, now it's filled red
                icon.classList.remove('bi-heart');
                icon.classList.add('bi-heart-fill');
                icon.style.color = 'red';
            } else {
                icon.classList.remove('bi-heart-fill');
                icon.classList.add('bi-heart');
                icon.style.color = '';
            }

            // AJAX request to backend
            fetch('{{ route('wishlist.toggle') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json, text/javascript, */*; q=0.01',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ product_id: productId })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    // Update all header counts
                    document.querySelectorAll('.nav-wishlist-count').forEach(countElem => {
                        countElem.innerText = data.total_count;
                        if (data.total_count > 0) {
                            countElem.classList.remove('d-none');
                        } else {
                            countElem.classList.add('d-none');
                        }
                    });

                    // Silently refresh the wishlist drawer in the background
                    if (typeof window.refreshWishlistSidebar === 'function') window.refreshWishlistSidebar();

                    // Show toast notification instead of opening sidebar
                    showToast(data.message);
                } else if (data.redirect) {
                    // Not logged in case (if we handle that in JSON)
                    window.location.href = data.redirect;
                }
            })
            .catch(err => {
                console.error("Wishlist Error:", err);
                // Revert UI on failure
                if(isAdded) {
                    icon.classList.remove('bi-heart-fill');
                    icon.classList.add('bi-heart');
                    icon.style.color = '';
                } else {
                    icon.classList.remove('bi-heart');
                    icon.classList.add('bi-heart-fill');
                    icon.style.color = 'red';
                }
                alert('Please log in first to use the wishlist.');
            });
        }

    function moveCheckedToCart() {
        const checkboxes = document.querySelectorAll('#wishlistSidebar .wishlist-item-checkbox:checked');
        
        if (checkboxes.length === 0) {
            showToast('Please select at least one item to move to cart.');
            return;
        }

        const productIds = Array.from(checkboxes).map(cb => parseInt(cb.value));

        fetch('{{ route("wishlist.move-to-cart") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
            body: JSON.stringify({ product_ids: productIds }),
        })
        .then(res => res.json())
        .then(data => {
            if (data.redirect) {
                window.location.href = data.redirect;
                return;
            }
            if (data.success) {
                showToast(data.message);

                // Update wishlist count badges
                document.querySelectorAll('.nav-wishlist-count').forEach(el => {
                    el.textContent = data.wishlist_count;
                });

                // Update cart count badges
                document.querySelectorAll('.nav-cart-count, .cart-count').forEach(el => {
                    el.textContent = data.cart_count;
                });

                // Silently refresh the wishlist drawer in the background
                if (typeof window.refreshWishlistSidebar === 'function') window.refreshWishlistSidebar();
            }
        })
        .catch(() => showToast('Something went wrong. Please try again.'));
    }

    function clearWishlistItems() {
        const checkboxes = document.querySelectorAll('#wishlistSidebar .wishlist-item-checkbox:checked');
        const checkedIds = Array.from(checkboxes).map(cb => parseInt(cb.value));

        const popup = document.getElementById('confirmPopup');
        const msgEl = document.getElementById('confirmPopupMsg');
        const yesBtn = document.getElementById('confirmPopupYes');
        const cancelBtn = document.getElementById('confirmPopupCancel');

        if (checkedIds.length > 0) {
            msgEl.textContent = 'Remove ' + checkedIds.length + ' selected item(s) from your wishlist?';
        } else {
            msgEl.textContent = 'This will remove ALL items from your wishlist.';
        }

        popup.classList.add('show');

        // Clone buttons to remove old event listeners
        const newYes = yesBtn.cloneNode(true);
        const newCancel = cancelBtn.cloneNode(true);
        yesBtn.parentNode.replaceChild(newYes, yesBtn);
        cancelBtn.parentNode.replaceChild(newCancel, cancelBtn);

        newCancel.addEventListener('click', () => {
            popup.classList.remove('show');
        });

        newYes.addEventListener('click', () => {
            popup.classList.remove('show');

            const body = checkedIds.length > 0 ? { product_ids: checkedIds } : {};

            fetch('{{ route("wishlist.clear") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify(body),
            })
            .then(res => res.json())
            .then(data => {
                if (data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                if (data.success) {
                    showToast(data.message);

                    document.querySelectorAll('.nav-wishlist-count').forEach(el => {
                        el.textContent = data.wishlist_count;
                    });

                    // Silently refresh the wishlist drawer in the background
                    if (typeof window.refreshWishlistSidebar === 'function') window.refreshWishlistSidebar();
                }
            })
            .catch(() => showToast('Something went wrong. Please try again.'));
        });
    }
    </script>
    {{-- Instant add-to-cart / wishlist (no page reload). --}}
    <script>
        window.GEHNA_ROUTES = Object.assign({}, window.GEHNA_ROUTES || {}, {
            wishlistToggle: '{{ route("wishlist.toggle") }}',
            wishlistList: '{{ route("wishlist.list") }}',
            cartQuantities: '{{ route("cart.quantities") }}',
            cartSetQuantity: '{{ route("cart.set-quantity") }}'
        });
        {{-- Server-rendered cart state: card counters paint instantly on load,
             no AJAX round-trip (cart-stepper.js uses this before falling back
             to the /cart/quantities fetch). --}}
        window.GEHNA_CART_STATE = {
            quantities: @json((array) ($simpleCartQuantities ?? [])),
            cart_count: @json((int) ($headerCartCount ?? 0))
        };
    </script>
    <script src="{{ asset('frontend/js/instant-commerce.js') }}"></script>
    <script src="{{ asset('frontend/js/cart-stepper.js') }}"></script>
    <script src="{{ asset('frontend/js/product-share.js') }}"></script>
    @stack('scripts')
</body>

</html>

