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
 */
async function request(path, options = {}) {
  const { method = 'GET', params, body, auth = true, signal } = options;

  const headers = { Accept: 'application/json' };

  // The API is stateless: authenticate with a bearer token, never a cookie, so
  // there is no session to expire and no CSRF token to fetch first.
  const token = auth ? tokenStorage.get() : null;
  if (token) headers.Authorization = `Bearer ${token}`;

  if (body !== undefined) headers['Content-Type'] = 'application/json';

  let response;

  try {
    response = await fetch(`${GEHNA_API_BASE}${path}${cleanParams(params)}`, {
      method,
      headers,
      signal,
      body: body === undefined ? undefined : JSON.stringify(body),
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
};

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
 * Read-only. Placing an order stays on the server-rendered checkout, because
 * it moves stock, calls the payment gateway and books a delivery partner.
 */
export const orderApi = {
  /** `params` accepts `status` and `payment_status`. */
  list(params = {}) {
    return request('/orders', { params });
  },
  get(id) {
    return request(`/orders/${id}`);
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
    return request('/settings');
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

  // content
  getHome: contentApi.home,
  getSliders: contentApi.sliders,
  getFaqs: contentApi.faqs,
  getTestimonials: contentApi.testimonials,
  getSettings: contentApi.settings,
  getPages: contentApi.pages,
  getPage: contentApi.page,
  subscribeNewsletter: contentApi.subscribe,
  getMetalPrices: contentApi.metalPrices,
  getMetalPrice: contentApi.metalPrice,

  // escape hatch for an endpoint this file does not wrap yet
  request,
};

export default gehnaApi;
