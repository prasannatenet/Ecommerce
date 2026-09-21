/* GEHNA card stepper: Add to Cart <-> - qty + counter (simple products). */
(function () {
if (window.__gehnaCartStepperBound) return;
window.__gehnaCartStepperBound = true;
var quantities = {};
function R() { return window.GEHNA_ROUTES || {}; }
function qUrl() { return R().cartQuantities || '/cart/quantities'; }
function sUrl() { return R().cartSetQuantity || '/cart/set-quantity'; }
function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.getAttribute('content') : ''; }
function toQty(v) { var n = parseInt(v, 10); return (isNaN(n) || n < 0) ? 0 : n; }
function pidOf(form) { var i = form ? form.querySelector('input[name="product_id"]') : null; return i ? String(i.value) : ''; }
function isCardForm(form) {
if (!form || form.tagName !== 'FORM') return false;
var a = form.getAttribute('action') || '';
if (a.indexOf('/cart/add') === -1) return false;
if (form.id === 'product-cart-form') return false;
if (form.classList.contains('js-cart-qty-form')) return false;
var pid = form.querySelector('input[name="product_id"]');
var variation = form.querySelector('input[name="product_variation_id"]');
if (!pid || !pid.value) return false;
if (variation && String(variation.value || '').trim() !== '') return false;
var submit = form.querySelector('button[type="submit"], button:not([type])');
if (!submit) return false;
var label = (submit.textContent || '').toLowerCase();
if (label.indexOf('option') !== -1) return false;
if (label.indexOf('cart') === -1 && label.indexOf('add') === -1) return false;
return true;
}
function paintOne(form, pid) {
var submit = form.querySelector('button[type="submit"], button:not([type])');
if (!submit) return;
var stepper = form.querySelector('[data-cart-stepper="' + pid + '"]');
if (!stepper) {
stepper = document.createElement('div');
stepper.className = 'gehna-qty-stepper';
stepper.setAttribute('data-cart-stepper', pid);
stepper.innerHTML = '<button type="button" data-minus aria-label="Decrease quantity">-</button><span data-qty>1</span><button type="button" data-plus aria-label="Increase quantity">+</button>';
submit.insertAdjacentElement('afterend', stepper);
stepper.querySelector('[data-minus]').addEventListener('click', function () { changeQty(pid, toQty(quantities[pid] || 0) - 1); });
stepper.querySelector('[data-plus]').addEventListener('click', function () { changeQty(pid, toQty(quantities[pid] || 0) + 1); });
}
var qty = toQty(quantities[pid] || 0);
// Copy the Add to Cart button's exact height/border-radius while it is
// still visible, so the counter renders at the same size as the button.
if (submit.style.display !== 'none') {
try {
var cs = window.getComputedStyle(submit);
if (cs && cs.height && cs.height !== 'auto') {
form.dataset.stepperH = cs.height;
form.dataset.stepperR = cs.borderRadius;
}
} catch (err) { /* keep the CSS fallback size */ }
}
if (form.dataset.stepperH) {
stepper.style.height = form.dataset.stepperH;
stepper.style.borderRadius = form.dataset.stepperR || '';
}
var qtyEl = stepper.querySelector('[data-qty]');
if (qtyEl) qtyEl.textContent = qty;
var show = qty > 0;
stepper.style.display = show ? '' : 'none';
submit.style.display = show ? 'none' : '';
stepper.classList.toggle('is-busy', !!form.dataset.stepperBusy);
}
function paintAll() {
document.querySelectorAll('form[action*="/cart/add"]').forEach(function (f) { if (isCardForm(f)) paintOne(f, pidOf(f)); });
}
function setBusy(pid, busy) {
document.querySelectorAll('form[action*="/cart/add"]').forEach(function (f) {
if (!isCardForm(f) || pidOf(f) !== String(pid)) return;
if (busy) f.dataset.stepperBusy = '1'; else delete f.dataset.stepperBusy;
paintOne(f, String(pid));
});
}
function badges(count) {
if (count === undefined || count === null) return;
document.querySelectorAll('.site-cart-link .site-cart-count, .site-mobile-cart-count, .nav-cart-count, .cart-count').forEach(function (el) { el.textContent = count; el.classList.remove('d-none'); });
}
function applyState(data, fallbackPid, fallbackQty) {
if (data && data.quantities && typeof data.quantities === 'object') {
quantities = {};
Object.keys(data.quantities).forEach(function (k) { quantities[String(k)] = toQty(data.quantities[k]); });
} else if (fallbackPid) { quantities[String(fallbackPid)] = toQty(fallbackQty); }
if (data && data.cart_count !== undefined) badges(data.cart_count);
paintAll();
}
function changeQty(pid, next) {
next = Math.max(0, Math.min(99, toQty(next)));
setBusy(pid, true);
var fd = new FormData();
fd.append('product_id', pid);
fd.append('quantity', String(next));
fetch(sUrl(), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'fetch', 'Accept': 'application/json' }, body: fd })
.then(function (res) { return res.json().catch(function () { return {}; }).then(function (data) { if (!res.ok || data.success === false) throw new Error(data.message || 'Something went wrong.'); return data; }); })
.then(function (data) { applyState(data, pid, next); if (typeof showToast === 'function' && data.message) showToast(data.message); })
.catch(function (err) { if (typeof showToast === 'function') showToast(err && err.message ? err.message : 'Something went wrong.'); })
.finally(function () { setBusy(pid, false); });
}
function hydrate() {
fetch(qUrl(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' } })
.then(function (res) { return res.json().catch(function () { return {}; }); })
.then(function (data) { if (data && data.quantities) applyState(data); else paintAll(); })
.catch(function () { paintAll(); });
}
document.addEventListener('gehna:cart-updated', function (e) { var d = (e && e.detail) || {}; applyState(d, d.productId, d.quantity); });
if (window.MutationObserver) {
var scheduled = false;
var obs = new MutationObserver(function () { if (scheduled) return; scheduled = true; setTimeout(function () { scheduled = false; paintAll(); }, 80); });
obs.observe(document.documentElement, { childList: true, subtree: true });
}
function boot() {
    var state = window.GEHNA_CART_STATE;
    if (state && state.quantities && typeof state.quantities === 'object') {
        applyState(state); // server-rendered quantities: paint instantly, no AJAX wait
    } else {
        paintAll();
        hydrate(); // fallback for pages without the inline state
    }
}
if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); }
else { boot(); }
window.addEventListener('pageshow', function (e) { if (e && e.persisted) hydrate(); }); // refresh after back/forward cache
})();


