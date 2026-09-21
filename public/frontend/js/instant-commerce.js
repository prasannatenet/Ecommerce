/* GEHNA instant add-to-cart / wishlist (no page reload) - part 1 */
(function () {
    if (window.__gehnaInstantBound) return;
    window.__gehnaInstantBound = true;

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }
    function wishlistToggleUrl() {
        if (window.GEHNA_ROUTES && window.GEHNA_ROUTES.wishlistToggle) return window.GEHNA_ROUTES.wishlistToggle;
        return '/wishlist/toggle';
    }
    function updateCartBadges(count) {
        if (count === undefined || count === null) return;
        document.querySelectorAll('.site-cart-link .site-cart-count, .site-mobile-cart-count, .nav-cart-count, .cart-count').forEach(function (el) {
            el.textContent = count;
            el.classList.remove('d-none');
            el.classList.remove('bump');
            void el.offsetWidth;
            el.classList.add('bump');
        });
    }
    function updateWishlistBadges(count) {
        if (count === undefined || count === null) return;
        document.querySelectorAll('.nav-wishlist-count, #siteWishlistCount').forEach(function (el) {
            el.textContent = count;
            if (el.classList.contains('nav-wishlist-count')) el.classList.toggle('d-none', Number(count) <= 0);
            el.classList.remove('bump');
            void el.offsetWidth;
            el.classList.add('bump');
        });
    }
    function syncWishlistButtons(productId, wishlisted) {
        var id = String(productId);
        var wished = !!wishlisted;
        document.querySelectorAll('.wishlist-btn-ajax[data-product-id="' + id + '"]').forEach(function (btn) {
            btn.classList.toggle('is-wishlisted', wished);
            btn.setAttribute('title', wished ? 'Wishlisted' : 'Wishlist');
            btn.setAttribute('aria-label', wished ? 'Wishlisted' : 'Add to wishlist');
            var icon = btn.querySelector('i');
            if (!icon) return;
            if (wished) { icon.className = 'bi bi-heart-fill'; icon.style.color = '#dc3545'; }
            else { icon.className = 'bi bi-heart'; icon.style.color = ''; }
        });
        document.querySelectorAll('form').forEach(function (form) {
            var action = form.getAttribute('action') || '';
            if (action.indexOf('wishlist') === -1) return;
            var input = form.querySelector('input[name="product_id"]');
            if (!input || String(input.value) !== id) return;
            var btn = form.querySelector('button');
            var icon = form.querySelector('i');
            if (btn) btn.classList.toggle('is-wishlisted', wished);
            if (btn) btn.classList.toggle('active', wished);
            if (btn) btn.setAttribute('title', wished ? 'Wishlisted' : 'Wishlist');
            if (btn) btn.setAttribute('aria-label', wished ? 'Wishlisted' : 'Add to wishlist');
            if (icon && icon.className.indexOf('bi-heart') !== -1) {
                var extra = icon.className.indexOf('me-') !== -1 ? ' me-2' : (icon.className.indexOf('me-1') !== -1 ? ' me-1' : '');
                icon.className = wished ? 'bi bi-heart-fill' + extra : 'bi bi-heart' + extra;
                icon.style.color = wished ? '#dc3545' : '';
            }
        });
        var pdpBtn = document.getElementById('wishlistToggleBtn');
        if (pdpBtn) {
            var pidInput = document.querySelector('#wishlistToggleForm input[name="product_id"]');
            if (!pidInput || String(pidInput.value) === id) {
                var pic = pdpBtn.querySelector('i');
                if (pic) { pic.className = wished ? 'bi bi-heart-fill me-2' : 'bi bi-heart me-2'; pic.style.color = wished ? '#dc3545' : ''; }
                var plabel = pdpBtn.querySelector('span');
                if (plabel) { plabel.textContent = wished ? 'Wishlisted' : 'Add to Wishlist'; }
                else {
                    Array.prototype.forEach.call(pdpBtn.childNodes, function (n) {
                        if (n.nodeType === 3) n.textContent = wished ? 'Wishlisted' : 'Add to Wishlist';
                    });
                }
                pdpBtn.classList.toggle('active', wished);
            }
        }
    }
    function setBusy(btn, busy, preserveContent) {
        if (!btn) return;
        if (busy) {
            if (preserveContent) {
                btn.disabled = true;
                btn.classList.add('is-adding');
                return;
            }
            if (!btn.dataset.originalHtml) btn.dataset.originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.classList.add('is-adding');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        } else {
            btn.disabled = false;
            btn.classList.remove('is-adding');
            if (btn.dataset.originalHtml) { btn.innerHTML = btn.dataset.originalHtml; delete btn.dataset.originalHtml; }
        }
    }
    function parseJsonSafe(res) {
        return res.text().then(function (text) {
            try { return text ? JSON.parse(text) : {}; }
            catch (e) { return {}; }
        });
    }
    function ajaxSubmit(form, btn) {
        var action = form.getAttribute('action') || '';
        var isCart = action.indexOf('/cart/add') !== -1;
        var isWish = action.indexOf('/wishlist') !== -1 && action.indexOf('toggle') !== -1;
        if (!isCart && !isWish) return false;
        setBusy(btn, true);
        fetch(action, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'fetch', 'Accept': 'application/json' },
            body: new FormData(form)
        }).then(function (res) {
            return parseJsonSafe(res).then(function (data) {
                if (!res.ok || data.success === false) throw new Error(data.message || 'Something went wrong.');
                return data;
            });
        }).then(function (data) {
            if (typeof showToast === 'function') showToast(data.message || (isCart ? 'Added to cart.' : 'Wishlist updated.'));
            if (isCart && data.cart_count !== undefined) updateCartBadges(data.cart_count);
            if (isCart) {
                var cartPid = form.querySelector('input[name="product_id"]');
                var cartQty = 1;
                if (data.quantities && cartPid && data.quantities[String(cartPid.value)] !== undefined) {
                    cartQty = parseInt(data.quantities[String(cartPid.value)], 10) || 0;
                } else if (data.product_id) {
                    if (data.quantities && data.quantities[String(data.product_id)] !== undefined) {
                        cartQty = parseInt(data.quantities[String(data.product_id)], 10) || 0;
                    } else {
                        var qInput = form.querySelector('input[name="quantity"]');
                        cartQty = parseInt((qInput && qInput.value) || '1', 10) || 1;
                    }
                    cartPid = { value: data.product_id };
                }
                try {
                    document.dispatchEvent(new CustomEvent('gehna:cart-updated', {
                        detail: {
                            quantities: data.quantities || null,
                            cart_count: data.cart_count,
                            productId: cartPid ? String(cartPid.value) : null,
                            quantity: cartQty,
                        }
                    }));
                } catch (evtErr) { /* older browsers: stepper hydrates on next poll */ }
            }
            if (isWish) {
                var count = data.wishlist_count !== undefined ? data.wishlist_count : data.total_count;
                updateWishlistBadges(count);
                var w = data.wishlisted !== undefined ? data.wishlisted : (data.action === 'added');
                var pi = form.querySelector('input[name="product_id"]');
                setBusy(btn, false);
                btn = null;
                if (pi) syncWishlistButtons(pi.value, w);
                if (typeof window.refreshWishlistSidebar === 'function') window.refreshWishlistSidebar();
                try {
                    document.dispatchEvent(new CustomEvent('gehna:wishlist-updated', {
                        detail: { count: count, productId: pi ? pi.value : null, wishlisted: w }
                    }));
                } catch (evtErr) { /* older browsers */ }
            }
        }).catch(function (err) {
            if (typeof showToast === 'function') showToast(err && err.message ? err.message : 'Something went wrong.');
        }).finally(function () { setBusy(btn, false); });
        return true;
    }
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM') return;
        var action = form.getAttribute('action') || '';
        if (action.indexOf('/cart/add') === -1 && action.indexOf('/wishlist/toggle') === -1) return;
        if (form.classList.contains('js-cart-qty-form')) return;
        var btn = (e.submitter && e.submitter.tagName === 'BUTTON') ? e.submitter : form.querySelector('button[type="submit"]');
        if (ajaxSubmit(form, btn)) e.preventDefault();
    });
    window.toggleAjaxWishlist = function (productId, el) {
        if (el && el.closest && el.closest('form')) {
            var pf = el.closest('form');
            if ((pf.getAttribute('action') || '').indexOf('wishlist') !== -1) return;
        }
        var btn = el || null;
        setBusy(btn, true);
        var fd = new FormData();
        fd.append('product_id', productId);
        var vi = document.getElementById('product_variation_id') || document.getElementById('wishlist_variation_id');
        if (vi && vi.value) fd.append('product_variation_id', vi.value);
        fetch(wishlistToggleUrl(), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'fetch', 'Accept': 'application/json' },
            body: fd
        }).then(function (res) {
            return parseJsonSafe(res).then(function (data) {
                if (!res.ok || data.success === false) throw new Error(data.message || 'Something went wrong.');
                return data;
            });
        }).then(function (data) {
            if (typeof showToast === 'function') showToast(data.message || 'Wishlist updated.');
            var count = data.wishlist_count !== undefined ? data.wishlist_count : data.total_count;
            updateWishlistBadges(count);
            setBusy(btn, false);
            btn = null;
            syncWishlistButtons(productId, data.wishlisted !== undefined ? data.wishlisted : (data.action === 'added'));
            if (typeof window.refreshWishlistSidebar === 'function') window.refreshWishlistSidebar();
            try {
                document.dispatchEvent(new CustomEvent('gehna:wishlist-updated', {
                    detail: {
                        count: count,
                        productId: productId,
                        wishlisted: data.wishlisted !== undefined ? data.wishlisted : (data.action === 'added')
                    }
                }));
            } catch (evtErr) { /* older browsers */ }
        }).catch(function (err) {
            if (typeof showToast === 'function') showToast(err && err.message ? err.message : 'Something went wrong.');
        }).finally(function () { setBusy(btn, false); });
    };
})();
