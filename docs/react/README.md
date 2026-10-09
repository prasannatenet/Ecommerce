# React integration for the Gehna storefront API

The REST API and client cover the customer-facing React storefront: catalogue,
content, accounts, cart, wishlist, checkout, payment, order history, reviews,
order cancellation, return requests, and coupon/combo/product-sale offers.

| File | What it is |
| --- | --- |
| `CheckoutPage.jsx` | Full checkout: summary, coupon, Gehna Coins, address, COD + Razorpay online payment, signature verification, payment-status polling, retry safety. |
| `../../public/js/gehna-api.js` | Dependency-free API client. Copy it to `src/api/gehnaApi.js`. |
| `../../API.md` | Full route reference, validation, response envelopes, and examples. |

Endpoint reference with real request/response JSON: **`API.md`, section 6
"Checkout and payments"**.

## Setup

1. Copy the client into your app:

   ```bash
   mkdir -p src/api
   cp public/js/gehna-api.js src/api/gehnaApi.js
   ```

2. Put `CheckoutPage.jsx` next to it (or anywhere; fix the import path in the
   first lines of the file):

   ```jsx
   import api, { ApiError } from './api/gehnaApi';
   ```

3. Point the client at your backend **before** the module loads (local dev):

   ```html
   <script>window.GEHNA_API_BASE = 'http://localhost:8000/api/v1';</script>
   ```

   Production default is `https://astroemerging.com/gehna/api/v1`.

4. Sign the customer in first - every checkout call needs the bearer token:

   ```js
   await api.login({ email, password });   // token stored automatically
   ```

5. Use the API groups in React:

   ```jsx
   import api, { productApi, cartApi } from './api/gehnaApi';

   const { data: products } = await productApi.list({ per_page: 12, sort: 'latest' });
   await cartApi.add({ product_id: products[0].id, quantity: 1 });
   const { data: checkout } = await api.getCheckoutSummary();
   ```

6. Render checkout when needed:

   ```jsx
   <CheckoutPage onSignedOut={() => navigate('/login')} />
   ```

For a product review use `productApi.submitReview(slug, { rating, comment,
images })`; it accepts `File` objects and submits multipart form data. Customers
must have purchased the product. Use `orderApi.cancel(id, reason)` to cancel an
eligible order and `orderApi.requestReturn(id, payload)` to request a return.
Use `api.getCoupons({ per_page: 12, page: 1 })` for coupons only, or
`api.getOffers({ per_page: 12, page: 1 })` to load coupons, combo offers, and
sale products together. Pagination details are returned in `meta`.

## Detect a delivery pincode

GPS permission gives the most accurate result. Ask the visitor to allow
location access, send their coordinates, then display the returned pincode for
confirmation before using it for delivery estimates:

```js
const { data: place } = await api.detectLocation({
  latitude: position.coords.latitude,
  longitude: position.coords.longitude,
});
// place.pincode, place.city, place.state
```

If GPS is unavailable or declined, `api.detectLocationByIp()` may provide an
approximate pincode. It needs `BIGDATACLOUD_API_KEY` configured in Laravel and
can be inaccurate with VPNs, mobile networks, or proxies. The provider key
stays on the Laravel server; do not add it to React `.env` files.

## Store settings in React

The public site reads only display-safe settings. Load them through
`api.getSettings()` when the React app starts; it returns the current logo,
favicon, contact details, and social URLs without SMTP credentials:

```jsx
const { data: settings } = await api.getSettings();
// settings.logo, settings.favicon, settings.email, settings.phone
// settings.social.facebook, settings.social.instagram, etc.
```

To pick up changes made later from another admin session, refresh public
settings on an interval. `GET /settings` is uncached, so each refresh reads the
latest saved values:

```jsx
useEffect(() => {
  let active = true;

  const refreshSettings = async () => {
    try {
      const { data } = await api.getSettings();
      if (active) setSettings(data);
    } catch (error) {
      if (active) setSettingsError(error.message);
    }
  };

  refreshSettings();
  const timer = setInterval(refreshSettings, 30_000);

  return () => {
    active = false;
    clearInterval(timer);
  };
}, []);
```

Render the logo/contact/social links from this React state. Set the document
favicon from `settings.favicon` when it changes if the tab icon should update.

To build the settings form in the **Laravel admin** using React, sign in as an
admin first, then call:

```js
const { data: editableSettings } = await api.getAdminSettings();
const { data: savedSettings } = await api.updateAdminSettings({
  site_name: 'My Store',
  email: 'hello@example.com',
  facebook_url: 'https://facebook.com/my-store',
  logo: selectedLogoFile,       // optional File
  favicon: selectedFaviconFile, // optional File
});
```

`updateAdminSettings` sends multipart data for images and returns the saved
values. Use that response to update the admin UI immediately. For the separate
customer-facing website, call `api.getSettings()` again after a save, or refresh
it periodically (for example, every 30 seconds) if changes should appear in
already-open customer tabs. The API returns fresh values and disables browser
caching, but it does not push live updates to other tabs by itself; instant
cross-session updates would require a WebSocket/broadcast service.

The React origin must be listed in `API_CORS_ORIGINS` on the server
(`http://localhost:5173` and `http://localhost:3000` are included by default).

## How the payment flow works

```
GET  /checkout/summary              -> totals, providers, checkout_token
POST /checkout/place-order
      payment_method "cod"          -> done (order created, is_paid: false)
      payment_method "razorpay"     -> data.razorpay -> open Razorpay popup
popup handler                       -> POST /checkout/verify-razorpay -> paid
popup dismissed / unsure?           -> GET /checkout/payment-status/{id}
                                       (state: paid | failed | processing | cod_confirmed)
```

Things the example already handles for you:

- **Idempotency** - the `checkout_token` from the summary is reused on every
  retry, so a double tap or a network retry can never create a second order.
- **Double submit** - one busy guard blocks every button while a request runs.
- **422 field errors** - `error.errors` is mapped onto the matching inputs.
- **401** - `onSignedOut` is called instead of leaving a stale signed-in UI.
- **Popup abandoned** - the page polls `payment-status` because the Razorpay
  webhook on the server may still confirm the payment after the popup closes.
- **Totals** - rendered from `summary.pricing`, never recomputed client-side.
- **Customer actions** - orders may be cancelled while pending/processing, and
  return requests may be submitted for delivered orders; the API enforces both.

Razorpay's `checkout.js` is loaded on demand; there is no build configuration,
no axios, no react-query - just the client file and this component.
