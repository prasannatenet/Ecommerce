/**
 * ============================================================================
 *  GEHNA STOREFRONT API  --  React client
 * ============================================================================
 *
 *  A single, dependency-free API client for the Gehna storefront API.
 *  Copy this file into your React project, e.g.  src/api/gehnaApi.js
 *
 *      import gehnaApi, { authApi, productApi } from './api/gehnaApi';
 *
 *  No axios, no react-query, no build changes. It uses the browser's own
 *  fetch, so it drops into Vite, CRA, Next.js or plain HTML alike.
 *
 *  ---------------------------------------------------------------------------
 *  LIVE ENDPOINTS
 *  ---------------------------------------------------------------------------
 *      Base URL   https://astroemerging.com/gehna/api/v1
 *
 *  To point this same file at a different environment (staging, or a local
 *  `php artisan serve`) set the global BEFORE the module loads:
 *
 *      <script>window.GEHNA_API_BASE = 'http://localhost:8000/api/v1';</script>
 *      <script type="module" src="/js/gehna-api.js"></script>
 *
 *  A trailing slash is stripped, so the value never doubles up when a path is
 *  appended.
 *
 *  ---------------------------------------------------------------------------
 *  QUICK START
 *  ---------------------------------------------------------------------------
 *
 *      // 1. Sign in -- the token is stored automatically
 *      const { data } = await gehnaApi.login({
 *          email: 'you@example.com',
 *          password: 'your-password',
 *      });
 *      const user = data.user;
 *
 *      // 2. Every later call is authenticated automatically
 *      const { data: products, meta } = await gehnaApi.getProducts({ per_page: 12 });
 *
 *      // 3. Sign out
 *      await gehnaApi.logout();
 *
 *  ---------------------------------------------------------------------------
 *  RESPONSES
 *  ---------------------------------------------------------------------------
 *  Every endpoint returns the same envelope, so one shape covers the whole API:
 *
 *      { success: true, message: 'OK', data: {...} | [...], meta: {...} }
 *
 *  On failure it throws an ApiError carrying the status, the message and the
 *  per-field validation messages:
 *
 *      { success: false, message: '...', errors: { email: ['...'] } }
 *
 *  List endpoints add `meta.current_page / last_page / per_page / total`.
 *
 *  ---------------------------------------------------------------------------
 *  ERROR HANDLING
 *  ---------------------------------------------------------------------------
 *
 *      import { ApiError } from './api/gehnaApi';
 *
 *      try {
 *          await gehnaApi.addToCart({ product_id: 7 });
 *      } catch (error) {
 *          if (error instanceof ApiError) {
 *              if (error.status === 401)  redirectToLogin();
 *              if (error.status === 422)  showFieldErrors(error.errors);
 *              if (error.isNetworkError) showOfflineBanner();
 *          }
 *      }
 *
 *  ---------------------------------------------------------------------------
 *  AUTHENTICATION
 *  ---------------------------------------------------------------------------
 *  Tokens are Sanctum bearer tokens kept in localStorage. If a request comes
 *  back 401 the client clears the stored token for you, so the UI never has to
 *  deal with a stale session.
 *
 *  NOTE: localStorage survives an XSS read. For a high-value account, swap
 *  `tokenStorage` for an httpOnly cookie set by the server.
 *
 */

const GEHNA_API_BASE = String(
  globalThis.GEHNA_API_BASE ?? 'https://astroemerging.com/gehna/api/v1',
).replace(/\/+$/, '');

const TOKEN_KEY = 'gehna_api_token';

/* -------------------------------------------------------------------------- */
/*  Token storage                                                             */
/* -------------------------------------------------------------------------- */

/**
 * localStorage wrapper that no-ops in Node (SSR / tests) and survives Safari's
 * private mode, where touching localStorage throws a SecurityError.
 */
const tokenStorage = {
  get() {
    try {
      return globalThis.localStorage?.getItem(TOKEN_KEY) ?? null;
    } catch {
      return null;
    }
  },
  set(token) {
    try {
      if (token) globalThis.localStorage?.setItem(TOKEN_KEY, token);
      else globalThis.localStorage?.removeItem(TOKEN_KEY);
    } catch {
      /* storage unavailable: the token simply lives for this page load */
    }
  },
  clear() {
    this.set(null);
  },
};

/* -------------------------------------------------------------------------- */
/*  Errors                                                                    */
/* -------------------------------------------------------------------------- */

/** Thrown for every non-2xx response, plus network failures. */
export class ApiError extends Error {
  constructor(message, { status = 0, errors = {}, data = null, meta = {} } = {}) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
    this.data = data;
    this.meta = meta;

    /** True when the request never reached the server (offline, DNS, CORS). */
    this.isNetworkError = status === 0;

    /** True when the token is missing, expired or revoked. */
    this.isUnauthenticated = status === 401;

    /** True when the server rejected the submitted values. */
    this.isValidationError = status === 422;
  }
}


/* -------------------------------------------------------------------------- */
/*  Core request                                                              */
/* -------------------------------------------------------------------------- */

/** Build a query string, dropping keys that are null, undefined or ''. */
function cleanParams(params = {}) {
  const search = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value === null || value === undefined || value === '') continue;

    // Arrays become `?category_id=1,2,3`, the format the API parses back into
    // an IN (...) clause.
    if (Array.isArray(value)) {
      if (value.length === 0) continue;
      search.append(key, value.join(','));
    } else {
      search.append(key, String(value));
    }
  }

  const query = search.toString();

  return query ? `?${query}` : '';
}

/**
 * Perform one API call.
 *
 * @param {string}  path      Path after the version prefix, e.g. '/products'
 * @param {object}  [options]
 * @param {string}  [options.method='GET']
 * @param {object}  [options.params]        Query-string values
 * @param {object}  [options.body]          JSON request body
 * @param {boolean} [options.auth=true]     Send the bearer token
 * @param {AbortSignal} [options.signal]    Cancel the request
 * @param {RequestCache} [options.cache]    Browser fetch cache policy
 */
async function request(path, options = {}) {
  const { method = 'GET', params, body, auth = true, signal, cache = 'default' } = options;

  const headers = { Accept: 'application/json' };
  const isFormData = typeof FormData !== 'undefined' && body instanceof FormData;
  let requestMethod = method;
  let requestBody = body;

  // The API is stateless: authenticate with a bearer token, never a cookie, so
  // there is no session to expire and no CSRF token to fetch first.
  const token = auth ? tokenStorage.get() : null;
  if (token) headers.Authorization = `Bearer ${token}`;

  if (isFormData) {
    if (method === 'PUT' || method === 'PATCH') {
      const multipartBody = new FormData();
      for (const [key, value] of body.entries()) multipartBody.append(key, value);
      multipartBody.append('_method', method);
      requestMethod = 'POST';
      requestBody = multipartBody;
    }
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    requestBody = body === undefined ? undefined : JSON.stringify(body);
  }

  let response;

  try {
    response = await fetch(`${GEHNA_API_BASE}${path}${cleanParams(params)}`, {
      method: requestMethod,
      headers,
      signal,
      cache,
      body: requestBody,
    });
  } catch {
    // fetch only rejects on a transport failure, never on a 4xx/5xx.
    throw new ApiError(
      'Could not reach the server. Check your connection and try again.',
      { status: 0 },
    );
  }

  // 204 and other empty bodies are valid success responses.
  const isJson = (response.headers.get('content-type') || '').includes('application/json');
  const payload = isJson ? await response.json().catch(() => null) : null;

  if (!response.ok) {
    // A dead token must not linger: drop it so the UI re-renders as signed out.
    if (response.status === 401) tokenStorage.clear();

    throw new ApiError(
      payload?.message || `Request failed with status ${response.status}`,
      {
        status: response.status,
        errors: payload?.errors ?? {},
        data: payload?.data ?? null,
        meta: payload?.meta ?? {},
      },
    );
  }

  // The server always sends the envelope, but tolerate a bare array/object so
  // an endpoint added later without one still works.
  if (payload && typeof payload === 'object' && !Array.isArray(payload) && 'data' in payload) {
    return payload;
  }

  return { success: true, message: 'OK', data: payload, meta: {} };
}


/* -------------------------------------------------------------------------- */
/*  AUTH                                                                      */
/* -------------------------------------------------------------------------- */

/**
 *  POST /auth/register     POST /auth/login
 *  GET  /auth/me           PUT  /auth/profile
 *  PUT  /auth/password     POST /auth/logout
 *  POST /auth/logout-all
 */
export const authApi = {
  /**
   * Create an account and sign in.
   * @param {{name:string, email:string, password:string,
   *          password_confirmation:string, phone?:string}} payload
   */
  async register(payload) {
    const response = await request('/auth/register', {
      method: 'POST',
      auth: false,
      body: payload,
    });

    tokenStorage.set(response.data.token);

    return response;
  },

  /**
   * Sign in with email + password.
   * @param {{email:string, password:string, remember?:boolean}} payload
   */
  async login(payload) {
    const response = await request('/auth/login', {
      method: 'POST',
      auth: false,
      body: payload,
    });

    // Store the token so every later call is authenticated automatically.
    tokenStorage.set(response.data.token);

    return response;
  },

  /** The signed-in customer. Returns null when the token is no longer valid. */
  async me() {
    if (!tokenStorage.get()) return { success: true, data: null, meta: {} };

    try {
      return await request('/auth/me');
    } catch (error) {
      // A stale token on app boot is normal (it expires, or was revoked on
      // another device). It must not break the render, just sign the user out.
      if (error instanceof ApiError && error.isUnauthenticated) {
        return { success: true, data: null, meta: {} };
      }
      throw error;
    }
  },

  /** Update the signed-in customer's name / phone. */
  updateProfile(payload) {
    return request('/auth/profile', { method: 'PUT', body: payload });
  },

  /** Change password. Revokes the token on every other device. */
  changePassword({ current_password, password, password_confirmation }) {
    return request('/auth/password', {
      method: 'PUT',
      body: { current_password, password, password_confirmation },
    });
  },

  /**
   * Sign out of this device and drop the stored token.
   *
   * Idempotent: a 401 is swallowed, because "this token was already invalid"
   * and "signed out" are the same end state for the user. Throwing here would
   * make a sign-out button surface an error while the user is in fact signed
   * out. Every other failure still propagates.
   */
  async logout() {
    try {
      return await request('/auth/logout', { method: 'POST' });
    } catch (error) {
      if (!(error instanceof ApiError) || !error.isUnauthenticated) throw error;

      return { success: true, message: 'Logged out successfully', data: null, meta: {} };
    } finally {
      // Clear locally even if the server call failed, otherwise the UI would
      // stay "signed in" with a token it has already forgotten about.
      tokenStorage.clear();
    }
  },

  /** Sign out of every device. Idempotent in the same way as logout(). */
  async logoutAll() {
    try {
      return await request('/auth/logout-all', { method: 'POST' });
    } catch (error) {
      if (!(error instanceof ApiError) || !error.isUnauthenticated) throw error;

      return { success: true, message: 'Signed out of all devices', data: null, meta: {} };
    } finally {
      tokenStorage.clear();
    }
  },

  /** True when a token is held. Not proof it is valid: call me() for that. */
  isAuthenticated() {
    return Boolean(tokenStorage.get());
  },

  /** Forget the token without calling the server. */
  clearToken() {
    tokenStorage.clear();
  },
};


/* -------------------------------------------------------------------------- */
/*  CATALOGUE                                                                 */
/* -------------------------------------------------------------------------- */

/**
 * Product listing and detail.
 *
 *  GET /products   GET /products/{slug}
 *  GET /products/{slug}/related   GET /products/{slug}/reviews
 *
 * List filters, all optional, and all also accepted by the search endpoint:
 *
 *   q           free text over name, SKU and description
 *   category_id id, or [ids]
 *   brand_id    id, or [ids]
 *   audience    'women' | 'men' | 'unisex'
 *   material    'gold' | 'silver' | 'diamond' | 'platinum' | 'other'
 *   min_price   number
 *   max_price   number
 *   in_stock    1: only items that can actually be bought
 *   on_sale     1: only discounted items
 *   sort        'latest' (default) | 'oldest' | 'price_asc' | 'price_desc' | 'name'
 *   per_page    1..60, default 12
 *   page        page number
 */
export const productApi = {
  /**
   * @example
   *   const { data, meta } = await productApi.list({ on_sale: 1, sort: 'price_asc' });
   *   console.log(meta.total, meta.last_page);
   */
  list(params = {}) {
    return request('/products', { params });
  },

  /**
   * Product detail. `data` is `{ product, reviews }`.
   *
   * @example
   *   const { data: { product, reviews } } =
   *       await productApi.get('rose-gold-my-love-bracelet');
   *   console.log(product.price, product.variations, reviews.summary.average);
   */
  get(slug) {
    return request(`/products/${encodeURIComponent(slug)}`);
  },

  /** "You may also like" rail for a product. */
  related(slug, params = {}) {
    return request(`/products/${encodeURIComponent(slug)}/related`, { params });
  },

  /** Reviews for a product. Pass `rating: 5` to filter by star count. */
  reviews(slug, params = {}) {
    return request(`/products/${encodeURIComponent(slug)}/reviews`, { params });
  },

  /** Submit a review with optional image files. Only verified buyers may review. */
  submitReview(slug, payload) {
    return request(`/products/${encodeURIComponent(slug)}/reviews`, {
      method: 'POST',
      body: reviewFormData(payload),
    });
  },

  /** Edit your review; include image files and/or remove_image_ids if needed. */
  updateReview(slug, reviewId, payload) {
    return request(`/products/${encodeURIComponent(slug)}/reviews/${reviewId}`, {
      method: 'PUT',
      body: reviewFormData(payload),
    });
  },

  deleteReview(slug, reviewId) {
    return request(`/products/${encodeURIComponent(slug)}/reviews/${reviewId}`, {
      method: 'DELETE',
    });
  },
};

function reviewFormData(payload) {
  if (typeof FormData !== 'undefined' && payload instanceof FormData) return payload;
  if (typeof FormData === 'undefined') return payload;

  const form = new FormData();

  for (const [key, value] of Object.entries(payload)) {
    if (value === null || value === undefined) continue;

    if (Array.isArray(value)) {
      value.forEach((item) => form.append(`${key}[]`, item));
    } else {
      form.append(key, value);
    }
  }

  return form;
}

/**
 *  GET /categories   GET /categories/tree
 *  GET /categories/{slug}   GET /categories/{slug}/products
 */
export const categoryApi = {
  /**
   * All categories.
   * @param {object}  [params]
   * @param {string}  [params.parent_id] 'root' for top level only, or a category id
   * @param {boolean} [params.tree]     return the nested tree for a mega-menu
   */
  list(params = {}) {
    return request('/categories', { params });
  },

  /** Nested tree: every root category with its children nested inside. */
  tree() {
    return request('/categories', { params: { tree: 1 } });
  },

  /** One category by slug, including its parent and children. */
  get(slug) {
    return request(`/categories/${encodeURIComponent(slug)}`);
  },

  /**
   * Products in a category, accepting the same filters as productApi.list().
   * The response's `category` field carries the category, and `meta.included`
   * lists every category id the result covered (parent plus children).
   */
  products(slug, params = {}) {
    return request(`/categories/${encodeURIComponent(slug)}/products`, { params });
  },
};

/**
 *  GET /brands   GET /brands/{slug}   GET /brands/{slug}/products
 */
export const brandApi = {
  list() {
    return request('/brands');
  },
  get(slug) {
    return request(`/brands/${encodeURIComponent(slug)}`);
  },
  /** Products for a brand, accepting the same filters as productApi.list(). */
  products(slug, params = {}) {
    return request(`/brands/${encodeURIComponent(slug)}/products`, { params });
  },
};

/**
 *  GET /combos   GET /combos/{slug}
 *
 * Only live combos (enabled and inside their date window) are listed.
 */
export const comboApi = {
  list() {
    return request('/combos');
  },
  get(slug) {
    return request(`/combos/${encodeURIComponent(slug)}`);
  },
};

/**
 *  GET /search?q=...
 *
 * Returns products plus the categories that matched and the customer's recent
 * searches, so one call can populate a whole search results page.
 */
export const searchApi = {
  query(term, params = {}) {
    return request('/search', { params: { ...params, q: term } });
  },
};


/* -------------------------------------------------------------------------- */
/*  CUSTOMER -- all require a bearer token                                    */
/* -------------------------------------------------------------------------- */

/**
 *  GET    /cart          POST   /cart
 *  PATCH  /cart/{id}     DELETE /cart/{id}
 *  DELETE /cart
 */
export const cartApi = {
  /** `meta` carries `subtotal`, `total` and the item `count` for the badge. */
  get() {
    return request('/cart');
  },

  /**
   * Add a product. Sending the same product/variation twice tops up the
   * existing line rather than creating a duplicate.
   *
   * @param {{product_id:number, product_variation_id?:number, quantity?:number}} payload
   */
  add(payload) {
    return request('/cart', { method: 'POST', body: payload });
  },

  /** Set a line's quantity. Pass 0 to remove the line. */
  update(lineId, quantity) {
    return request(`/cart/${lineId}`, { method: 'PATCH', body: { quantity } });
  },

  remove(lineId) {
    return request(`/cart/${lineId}`, { method: 'DELETE' });
  },

  /** Empty the cart. */
  clear() {
    return request('/cart', { method: 'DELETE' });
  },
};

/**
 *  GET    /wishlist            POST   /wishlist/toggle
 *  DELETE /wishlist/{productId}
 *  DELETE /wishlist
 */
export const wishlistApi = {
  /** `data` is an array of full product objects, ready to render as cards. */
  get(params = {}) {
    return request('/wishlist', { params });
  },

  /**
   * Add the product if it is not saved, remove it if it is.
   * The response's `data.in_wishlist` is the new state: flip the heart from
   * that rather than guessing, or the icon drifts out of sync with the server.
   *
   * @param {{product_id:number, product_variation_id?:number}} payload
   */
  toggle(payload) {
    return request('/wishlist/toggle', { method: 'POST', body: payload });
  },

  remove(productId) {
    return request(`/wishlist/${productId}`, { method: 'DELETE' });
  },

  clear() {
    return request('/wishlist', { method: 'DELETE' });
  },
};

/**
 *  GET /orders   GET /orders/{id}
 *
 * Order history, customer cancellation and return requests.
 */
export const orderApi = {
  /** `params` accepts `status` and `payment_status`. */
  list(params = {}) {
    return request('/orders', { params });
  },
  get(id) {
    return request(`/orders/${id}`);
  },
  cancel(id, reason = '') {
    return request(`/orders/${id}/cancel`, { method: 'POST', body: { reason } });
  },
  requestReturn(id, payload) {
    return request(`/orders/${id}/return-requests`, { method: 'POST', body: payload });
  },
};

/**
 *  GET  /checkout/summary            POST /checkout/apply-coupon
 *  POST /checkout/remove-coupon       POST /checkout/apply-coins
 *  POST /checkout/remove-coins        POST /checkout/place-order
 *  POST /checkout/verify-razorpay    GET  /checkout/payment-status/{order}
 */
export const checkoutApi = {
  /** Fetch cart totals, available coupons, user coins & active payment providers. */
  summary(params = {}) {
    return request('/checkout/summary', { params });
  },

  /** Apply a coupon code. */
  applyCoupon(couponCode) {
    return request('/checkout/apply-coupon', { method: 'POST', body: { coupon_code: couponCode } });
  },

  /** Remove current coupon. */
  removeCoupon() {
    return request('/checkout/remove-coupon', { method: 'POST' });
  },

  /** Apply Gehna Coins. */
  applyCoins(coins) {
    return request('/checkout/apply-coins', { method: 'POST', body: { coins } });
  },

  /** Remove Gehna Coins. */
  removeCoins() {
    return request('/checkout/remove-coins', { method: 'POST' });
  },

  /**
   * Place an order (COD or Razorpay).
   *
   * @param {{
   *   payment_method: 'cod'|'razorpay',
   *   checkout_token?: string,
   *   coupon_code?: string,
   *   coins?: number,
   *   billing_name: string,
   *   billing_email: string,
   *   billing_phone: string,
   *   billing_line1: string,
   *   billing_city: string,
   *   billing_state: string,
   *   billing_zip: string,
   *   billing_country: string,
   *   shipping_same_as_billing?: boolean,
   *   shipping_name?: string,
   *   shipping_phone?: string,
   *   shipping_line1?: string,
   *   shipping_city?: string,
   *   shipping_state?: string,
   *   shipping_zip?: string,
   *   shipping_country?: string
   * }} payload
   */
  placeOrder(payload) {
    return request('/checkout/place-order', { method: 'POST', body: payload });
  },

  /**
   * Verify Razorpay payment signature after customer pays in the Razorpay popup.
   *
   * @param {{
   *   order_id: number,
   *   razorpay_order_id: string,
   *   razorpay_payment_id: string,
   *   razorpay_signature: string
   * }} payload
   */
  verifyRazorpay(payload) {
    return request('/checkout/verify-razorpay', { method: 'POST', body: payload });
  },

  /** Get current payment status of an order. */
  getPaymentStatus(orderId) {
    return request(`/checkout/payment-status/${orderId}`);
  },
};



/* -------------------------------------------------------------------------- */
/*  SITE CONTENT                                                              */
/* -------------------------------------------------------------------------- */

/**
 *  GET  /home
 *  GET  /sliders            GET  /faqs
 *  GET  /testimonials       GET  /settings
 *  POST /newsletter         GET  /pages
 *  GET  /pages/{slug}       GET  /metal-prices
 *  GET  /metal-prices/{metal}
 */
export const contentApi = {
  /**
   * The whole landing page in one call: sliders, categories, new arrivals,
   * featured, on-sale, brands, FAQs and testimonials.
   */
  home(params = {}) {
    return request('/home', { params });
  },

  sliders() {
    return request('/sliders');
  },

  faqs() {
    return request('/faqs');
  },

  testimonials() {
    return request('/testimonials');
  },

  /** Public store settings: name, contact details, address, social links. */
  settings() {
    return request('/settings', { cache: 'no-store' });
  },

  /** CMS pages (About Us, Contact, policies). The index returns titles only. */
  pages() {
    return request('/pages');
  },

  /** One CMS page with its full HTML content. */
  page(slug) {
    return request(`/pages/${encodeURIComponent(slug)}`);
  },

  /** Newsletter sign-up. `email` is required. */
  subscribe(email) {
    return request('/newsletter', { method: 'POST', body: { email } });
  },

  /** Live gold / silver spot rates. `data.metals` is the list. */
  metalPrices() {
    return request('/metal-prices');
  },

  /** One metal: metalPrice('gold'). */
  metalPrice(metal) {
    return request(`/metal-prices/${encodeURIComponent(metal)}`);
  },
};

/**
 * Public store settings and admin-only settings management.
 *
 * Admin writes require the logged-in user's Sanctum token and admin permission.
 * Image fields are sent as multipart FormData by the shared request helper.
 */
export const settingsApi = {
  /** Current public-facing website settings. No token required. */
  getPublic() {
    return request('/settings', { auth: false, cache: 'no-store' });
  },

  /** Full editable settings. Requires an authenticated administrator. */
  getAdmin() {
    return request('/admin/settings', { cache: 'no-store' });
  },

  /** Update store fields and optionally upload logo / favicon files. */
  updateAdmin(payload) {
    const body = typeof FormData !== 'undefined' && payload instanceof FormData
      ? payload
      : settingsFormData(payload);

    return request('/admin/settings', {
      method: 'PUT',
      body,
      cache: 'no-store',
    });
  },
};

function settingsFormData(payload) {
  const form = new FormData();

  for (const [key, value] of Object.entries(payload)) {
    if (value === null || value === undefined) continue;
    form.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : value);
  }

  return form;
}

/**
 *  GET /offers
 *
 * Public customer-facing coupon, combo and discounted-product offers.
 */
export const offersApi = {
  /** `meta` includes separate coupon and sale-product pagination. */
  list(params = {}) {
    return request('/offers', { params });
  },
};

/**
 *  GET /coupons
 *
 * Public, currently running coupon offers for the storefront.
 */
export const couponApi = {
  list(params = {}) {
    return request('/coupons', { params });
  },
};

/**
 * Public delivery-location lookup. GPS requires browser permission; IP results
 * are approximate and can be inaccurate for VPNs, mobile networks, and proxies.
 */
export const locationApi = {
  detectGps({ latitude, longitude }) {
    return request('/location/detect', {
      method: 'POST',
      auth: false,
      body: { latitude, longitude },
    });
  },

  detectByIp() {
    return request('/location/detect-by-ip', {
      method: 'POST',
      auth: false,
    });
  },
};

/* -------------------------------------------------------------------------- */
/*  Default export                                                            */
/* -------------------------------------------------------------------------- */

/**
 * Every group in one object, for apps that prefer a single namespace:
 *
 *      import api from './api/gehnaApi';
 *      const { data } = await api.login({ email, password });
 *      const { data: products } = await api.getProducts({ per_page: 12 });
 *
 * The named exports (authApi, productApi, ...) are the better choice for a
 * larger app: they tree-shake, and the call site says which part of the API it
 * is using.
 */
const gehnaApi = {
  baseUrl: GEHNA_API_BASE,

  // auth
  login: authApi.login,
  register: authApi.register,
  me: authApi.me,
  logout: authApi.logout,
  logoutAll: authApi.logoutAll,
  updateProfile: authApi.updateProfile,
  changePassword: authApi.changePassword,
  isAuthenticated: authApi.isAuthenticated,
  clearToken: authApi.clearToken,

  // catalogue
  getProducts: productApi.list,
  getProduct: productApi.get,
  getRelatedProducts: productApi.related,
  getProductReviews: productApi.reviews,
  submitProductReview: productApi.submitReview,
  updateProductReview: productApi.updateReview,
  deleteProductReview: productApi.deleteReview,
  getCategories: categoryApi.list,
  getCategoryTree: categoryApi.tree,
  getCategory: categoryApi.get,
  getCategoryProducts: categoryApi.products,
  getBrands: brandApi.list,
  getBrand: brandApi.get,
  getBrandProducts: brandApi.products,
  getCombos: comboApi.list,
  getCombo: comboApi.get,
  search: searchApi.query,

  // customer
  getCart: cartApi.get,
  addToCart: cartApi.add,
  updateCartItem: cartApi.update,
  removeCartItem: cartApi.remove,
  clearCart: cartApi.clear,
  getWishlist: wishlistApi.get,
  toggleWishlist: wishlistApi.toggle,
  removeFromWishlist: wishlistApi.remove,
  clearWishlist: wishlistApi.clear,
  getOrders: orderApi.list,
  getOrder: orderApi.get,
  cancelOrder: orderApi.cancel,
  requestOrderReturn: orderApi.requestReturn,

  // checkout & payment
  getCheckoutSummary: checkoutApi.summary,
  applyCoupon: checkoutApi.applyCoupon,
  removeCoupon: checkoutApi.removeCoupon,
  applyCoins: checkoutApi.applyCoins,
  removeCoins: checkoutApi.removeCoins,
  placeOrder: checkoutApi.placeOrder,
  verifyRazorpay: checkoutApi.verifyRazorpay,
  getPaymentStatus: checkoutApi.getPaymentStatus,

  // content
  getHome: contentApi.home,
  getSliders: contentApi.sliders,
  getFaqs: contentApi.faqs,
  getTestimonials: contentApi.testimonials,
  getSettings: contentApi.settings,
  getAdminSettings: settingsApi.getAdmin,
  updateAdminSettings: settingsApi.updateAdmin,
  getPages: contentApi.pages,
  getPage: contentApi.page,
  subscribeNewsletter: contentApi.subscribe,
  getMetalPrices: contentApi.metalPrices,
  getMetalPrice: contentApi.metalPrice,
  getOffers: offersApi.list,
  getCoupons: couponApi.list,
  detectLocation: locationApi.detectGps,
  detectLocationByIp: locationApi.detectByIp,

  // escape hatch for an endpoint this file does not wrap yet
  request,
};

export default gehnaApi;
