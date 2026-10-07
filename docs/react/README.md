# React checkout for the Gehna storefront API

Everything you need to place orders from a React frontend: the API client and a
complete checkout page.

| File | What it is |
| --- | --- |
| `CheckoutPage.jsx` | Full checkout: summary, coupon, Gehna Coins, address, COD + Razorpay online payment, signature verification, payment-status polling, retry safety. |
| `../../public/js/gehna-api.js` | The dependency-free API client. Copy it to `src/api/gehnaApi.js`. |

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

5. Render the page:

   ```jsx
   <CheckoutPage onSignedOut={() => navigate('/login')} />
   ```

Your React origin must be listed in `API_CORS_ORIGINS` on the server
(`http://localhost:5173` and `http://localhost:3000` already are).

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

Razorpay's `checkout.js` is loaded on demand; there is no build configuration,
no axios, no react-query - just the client file and this component.
