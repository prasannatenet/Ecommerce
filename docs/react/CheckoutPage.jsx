/**
 * Complete checkout page for the Gehna storefront API.
 *
 * Flow (API.md, section 6 "Checkout and payments"):
 *
 *   1. GET  /checkout/summary              -> pricing, providers, checkout_token
 *   2. POST /checkout/apply-coupon         -> optional
 *      POST /checkout/apply-coins          -> optional
 *   3. POST /checkout/place-order          -> COD: done. Razorpay: popup payload
 *   4. Razorpay popup handler              -> POST /checkout/verify-razorpay
 *   5. Uncertain outcome?                  -> GET  /checkout/payment-status/{id}
 *
 * The checkout_token from the summary is kept for the lifetime of the page and
 * reused on every retry, so a double tap or a network retry can never create a
 * second order - the server returns the order that already exists.
 *
 * Drop next to the API client:
 *   public/js/gehna-api.js  ->  src/api/gehnaApi.js   (see README.md)
 */
import { useEffect, useRef, useState } from 'react';
import api, { ApiError } from './api/gehnaApi';

const EMPTY_ADDRESS = {
    billing_name: '',
    billing_email: '',
    billing_phone: '',
    billing_line1: '',
    billing_city: '',
    billing_state: '',
    billing_zip: '',
    billing_country: 'India',
};

/** Load checkout.razorpay.com/v1/checkout.js once, on demand. */
function loadRazorpayScript() {
    return new Promise((resolve, reject) => {
        if (window.Razorpay) return resolve(window.Razorpay);
        const script = document.createElement('script');
        script.src = 'https://checkout.razorpay.com/v1/checkout.js';
        script.onload = () => resolve(window.Razorpay);
        script.onerror = () => reject(new Error('Could not load the Razorpay checkout.'));
        document.body.appendChild(script);
    });
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

export default function CheckoutPage({ onSignedOut }) {
    // --- state -----------------------------------------------------------------
    const [summary, setSummary] = useState(null);
    const [loadError, setLoadError] = useState('');
    const [busy, setBusy] = useState(false);            // blocks every button
    const [fieldErrors, setFieldErrors] = useState({}); // 422 errors by field
    const [notice, setNotice] = useState('');           // message safe for a toast
    const [placedOrder, setPlacedOrder] = useState(null); // set => success screen
    const [statusLine, setStatusLine] = useState('');    // "Confirming payment..."

    const [couponInput, setCouponInput] = useState('');
    const [coinsInput, setCoinsInput] = useState('');
    const [paymentMethod, setPaymentMethod] = useState('cod');
    const [sameAsBilling, setSameAsBilling] = useState(true);
    const [address, setAddress] = useState(EMPTY_ADDRESS);

    // Kept in refs: they must survive re-renders between retries.
    const checkoutTokenRef = useRef(null); // idempotency key, one per checkout
    const busyRef = useRef(false);

    // --- load ------------------------------------------------------------------
    const loadSummary = async () => {
        setLoadError('');
        try {
            const { data } = await api.getCheckoutSummary();
            setSummary(data);
            checkoutTokenRef.current ??= data.checkout_token;
            setPaymentMethod(data.payment_providers.razorpay?.enabled ? 'razorpay' : 'cod');
        } catch (error) {
            if (error instanceof ApiError && error.isUnauthenticated) onSignedOut?.();
            else if (error instanceof ApiError && error.status === 422)
                setLoadError('Your cart is empty - add a product before checking out.');
            else setLoadError(error.message ?? 'Could not load the checkout.');
        }
    };

    useEffect(() => {
        loadSummary().then(async () => {
            // Prefill the address from the signed-in account, best effort.
            try {
                const { data: user } = await api.me();
                setAddress((a) => ({
                    ...a,
                    billing_name: user.name ?? '',
                    billing_email: user.email ?? '',
                    billing_phone: user.phone ?? '',
                }));
            } catch {
                /* the summary already loaded; the form simply starts blank */
            }
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // --- shared runner ----------------------------------------------------------
    // One guard for every action: re-entrant calls (double taps) are ignored,
    // and one catch maps ApiError onto the form.
    const run = async (fn) => {
        if (busyRef.current) return;
        busyRef.current = true;
        setBusy(true);
        setFieldErrors({});
        setNotice('');
        try {
            await fn();
        } catch (error) {
            if (error instanceof ApiError) {
                if (error.isUnauthenticated) return onSignedOut?.();
                setFieldErrors(error.errors ?? {});
                setNotice(
                    error.isNetworkError
                        ? 'Network problem - check your connection and retry.'
                        : error.message,
                );
            } else {
                setNotice(error.message ?? 'Something went wrong.');
            }
        } finally {
            busyRef.current = false;
            setBusy(false);
        }
    };

    const refreshTotals = async () => {
        const { data } = await api.getCheckoutSummary();
        setSummary(data);
        checkoutTokenRef.current ??= data.checkout_token;
    };

    // --- coupon and coins -------------------------------------------------------
    const applyCoupon = () =>
        run(async () => {
            await api.applyCoupon(couponInput.trim());
            setCouponInput('');
            await refreshTotals();
            setNotice('Coupon applied.');
        });

    const removeCoupon = () =>
        run(async () => {
            await api.removeCoupon();
            await refreshTotals();
            setNotice('Coupon removed.');
        });

    const applyCoins = () =>
        run(async () => {
            await api.applyCoins(Number(coinsInput) || 0);
            setCoinsInput('');
            await refreshTotals();
            setNotice('Gehna Coins applied.');
        });

    const removeCoins = () =>
        run(async () => {
            await api.removeCoins();
            await refreshTotals();
            setNotice('Gehna Coins removed.');
        });

    // --- payment ---------------------------------------------------------------
    /** Poll until the server records a final payment state. */
    const pollUntilResolved = async (orderId) => {
        for (let attempt = 0; attempt < 8; attempt += 1) {
            setStatusLine('Confirming your payment...');
            const { data } = await api.getPaymentStatus(orderId);
            if (data.state === 'paid') return { paid: true, data };
            if (data.state === 'failed') return { paid: false, data };
            await sleep(1500);
        }
        return { paid: false, data: null }; // still unknown after the last poll
    };

    /** Open the Razorpay popup; resolve true once the payment is verified. */
    const payWithRazorpay = (placed) =>
        run(async () => {
            const Razorpay = await loadRazorpayScript();
            const verified = await new Promise((resolve) => {
                const rzp = new Razorpay({
                    ...placed.razorpay, // key, order_id, amount, currency, prefill
                    handler: async (response) => {
                        try {
                            await api.verifyRazorpay({
                                order_id: placed.order.id,
                                ...response, // razorpay_order_id/payment_id/signature
                            });
                            resolve(true);
                        } catch {
                            resolve(false);
                        }
                    },
                    modal: {
                        ondismiss: async () => {
                            // Popup closed without paying: ask the server what
                            // happened (the webhook may still have confirmed it).
                            const { paid } = await pollUntilResolved(placed.order.id);
                            resolve(paid);
                        },
                    },
                });
                rzp.open();
            });

            if (verified) {
                setPlacedOrder({ ...placed.order, payment_status: 'paid' });
                setStatusLine('');
                return;
            }

            const { data } = await api.getPaymentStatus(placed.order.id);
            setStatusLine('');
            setNotice(
                data?.state === 'failed'
                    ? 'Payment was not completed. You can safely retry - no second order will be created.'
                    : 'Payment not confirmed yet. Press "Place order" again to re-check.',
            );
        });

    const placeOrder = () =>
        run(async () => {
            const { data: placed } = await api.placeOrder({
                payment_method: paymentMethod,
                checkout_token: checkoutTokenRef.current, // idempotency: reuse always
                ...(couponInput.trim() ? { coupon_code: couponInput.trim() } : {}),
                ...address,
                shipping_same_as_billing: sameAsBilling,
            });

            if (placed.payment_method === 'cod' || placed.is_paid) {
                setPlacedOrder(placed.order); // nothing more to do
                return;
            }

            // Razorpay: hand the payload to the popup. Release the guard first,
            // because payWithRazorpay acquires it again through run().
            busyRef.current = false;
            setBusy(false);
            await payWithRazorpay(placed);
        });

    // --- render ----------------------------------------------------------------
    if (loadError) {
        return (
            <section className="checkout">
                <p className="checkout__error">{loadError}</p>
            </section>
        );
    }

    if (placedOrder) {
        return (
            <section className="checkout">
                <h1>Thank you! Order #{placedOrder.id} is confirmed.</h1>
                <p>
                    {placedOrder.payment_status === 'paid'
                        ? 'Payment received.'
                        : 'Payment is due on delivery.'}{' '}
                    Total: ₹{Number(placedOrder.total).toLocaleString('en-IN')}
                </p>
            </section>
        );
    }

    if (!summary) {
        return (
            <section className="checkout">
                <p>Loading checkout...</p>
            </section>
        );
    }

    const { pricing, applied_coupon, applied_coins, user_coins, payment_providers } = summary;

    return (
        <section className="checkout">
            <h1>Checkout</h1>

            {/* ── Totals straight from the server: never recompute them ── */}
            <div className="checkout__summary">
                <p>Subtotal: ₹{pricing.subtotal.toLocaleString('en-IN')}</p>
                {pricing.combo_discount > 0 && <p>Bundle discount: -₹{pricing.combo_discount}</p>}
                {pricing.coupon_discount > 0 && (
                    <p>Coupon ({applied_coupon?.code}): -₹{pricing.coupon_discount}</p>
                )}
                {pricing.coins_discount > 0 && <p>Gehna Coins: -₹{pricing.coins_discount}</p>}
                <p>Shipping: {pricing.shipping_charge === 0 ? 'Free' : `₹${pricing.shipping_charge}`}</p>
                <p>
                    <strong>Total: ₹{pricing.grand_total.toLocaleString('en-IN')}</strong>
                </p>
            </div>

            {/* ── Coupon ── */}
            <div className="checkout__coupon">
                {applied_coupon ? (
                    <p>
                        Coupon <strong>{applied_coupon.code}</strong> applied (-₹{applied_coupon.discount}){' '}
                        <button type="button" onClick={removeCoupon} disabled={busy}>
                            Remove
                        </button>
                    </p>
                ) : (
                    <>
                        <input
                            placeholder="Coupon code"
                            value={couponInput}
                            onChange={(e) => setCouponInput(e.target.value)}
                        />
                        <button type="button" onClick={applyCoupon} disabled={busy || !couponInput.trim()}>
                            Apply
                        </button>
                    </>
                )}
            </div>

            {/* ── Gehna Coins ── */}
            <div className="checkout__coins">
                <span>Gehna Coins balance: {user_coins.balance}</span>
                {applied_coins.coins > 0 ? (
                    <p>
                        {applied_coins.coins} coins applied (-₹{applied_coins.discount}){' '}
                        <button type="button" onClick={removeCoins} disabled={busy}>
                            Remove
                        </button>
                    </p>
                ) : user_coins.balance > 0 ? (
                    <>
                        <input
                            type="number"
                            min="1"
                            max={user_coins.balance}
                            placeholder="Coins to redeem"
                            value={coinsInput}
                            onChange={(e) => setCoinsInput(e.target.value)}
                        />
                        <button type="button" onClick={applyCoins} disabled={busy || !coinsInput}>
                            Redeem
                        </button>
                    </>
                ) : null}
            </div>

            {/* ── Address ── */}
            <form className="checkout__address" onSubmit={(e) => e.preventDefault()}>
                {Object.entries({
                    billing_name: 'Full name',
                    billing_email: 'Email',
                    billing_phone: 'Phone',
                    billing_line1: 'Address line 1',
                    billing_city: 'City',
                    billing_state: 'State',
                    billing_zip: 'PIN code',
                    billing_country: 'Country',
                }).map(([name, label]) => (
                    <label key={name}>
                        {label}
                        <input
                            value={address[name]}
                            onChange={(e) => setAddress({ ...address, [name]: e.target.value })}
                        />
                        {fieldErrors[name]?.[0] && (
                            <span className="checkout__field-error">{fieldErrors[name][0]}</span>
                        )}
                    </label>
                ))}
                <label>
                    <input
                        type="checkbox"
                        checked={sameAsBilling}
                        onChange={(e) => setSameAsBilling(e.target.checked)}
                    />
                    Ship to the same address
                </label>
            </form>

            {/* ── Payment method: only the providers the server says are on ── */}
            <div className="checkout__payment">
                {payment_providers.cod?.enabled && (
                    <label>
                        <input
                            type="radio"
                            name="payment_method"
                            value="cod"
                            checked={paymentMethod === 'cod'}
                            onChange={(e) => setPaymentMethod(e.target.value)}
                        />
                        Cash on Delivery
                    </label>
                )}
                {payment_providers.razorpay?.enabled && (
                    <label>
                        <input
                            type="radio"
                            name="payment_method"
                            value="razorpay"
                            checked={paymentMethod === 'razorpay'}
                            onChange={(e) => setPaymentMethod(e.target.value)}
                        />
                        Pay online (UPI / Card / Netbanking)
                    </label>
                )}
            </div>

            {notice && (
                <p className="checkout__notice" role="status">
                    {notice}
                </p>
            )}
            {statusLine && busy && <p className="checkout__status">{statusLine}</p>}

            <button type="button" className="checkout__place" onClick={placeOrder} disabled={busy}>
                {busy ? 'Placing order...' : `Place order - ₹${pricing.grand_total.toLocaleString('en-IN')}`}
            </button>
        </section>
    );
}

