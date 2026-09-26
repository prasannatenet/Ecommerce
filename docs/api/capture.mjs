// Captures real responses from the running API so API.md documents the exact
// JSON the server returns, not hand-written guesses.
import fs from 'node:fs';

// Where the browser-facing API lives locally. Point this at a staging host to
// capture against that instead.
const BASE = process.env.GEHNA_API_BASE || 'http://127.0.0.1:8123/api/v1';
// The base URL the doc advertises. The capture itself runs against a local
// server; render.php rewrites the captured origin to this one.
const PROD = 'https://astroemerging.com/gehna/api/v1';

const out = {};

/** Call the API and record the request plus the decoded response. */
async function capture(name, method, path, { query = null, body = null, auth = false, token = null } = {}) {
  const url = `${BASE}${path}${query ? '?' + new URLSearchParams(query) : ''}`;

  const headers = { Accept: 'application/json' };
  if (auth && token) headers.Authorization = `Bearer ${token}`;
  if (body) headers['Content-Type'] = 'application/json';

  const res = await fetch(url, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
  });

  let json = null;
  try { json = await res.json(); } catch { /* empty body */ }

  out[name] = {
    method,
    path,
    query,
    auth,
    status: res.status,
    request: body,
    response: json,
  };

  return json;
}

/* ── Discover real ids to use in the examples ─────────────────────── */

const products = await capture('products.list', 'GET', '/products', { query: { per_page: '3' } });
const firstSlug = products.data[0].slug;

const categories = await capture('categories.index', 'GET', '/categories');
const firstCategory = categories.data.find((c) => c.products_count > 0) ?? categories.data[0];

const brands = await capture('brands.index', 'GET', '/brands');
const combos = await capture('combos.index', 'GET', '/combos');
const pages = await capture('pages.index', 'GET', '/pages');

/* ── Public catalogue ─────────────────────────────────────────────── */

await capture('home', 'GET', '/home');
await capture('categories.tree', 'GET', '/categories', { query: { tree: '1' } });
await capture('categories.show', 'GET', `/categories/${firstCategory.slug}`);
await capture('categories.products', 'GET', `/categories/${firstCategory.slug}/products`, { query: { per_page: '2' } });
await capture('products.show', 'GET', `/products/${firstSlug}`);
await capture('products.related', 'GET', `/products/${firstSlug}/related`, { query: { per_page: '2' } });
await capture('products.reviews', 'GET', `/products/${firstSlug}/reviews`, { query: { per_page: '2' } });
await capture('search', 'GET', '/search', { query: { q: 'bracelet' } });
await capture('products.filtered', 'GET', '/products', { query: { on_sale: '1', sort: 'price_asc', per_page: '2' } });
await capture('sliders', 'GET', '/sliders');
await capture('faqs', 'GET', '/faqs');
await capture('testimonials', 'GET', '/testimonials');
await capture('settings', 'GET', '/settings');
await capture('newsletter', 'POST', '/newsletter', { body: { email: 'doc-preview@example.com' } });
await capture('metal-prices', 'GET', '/metal-prices');

if (brands.data.length) {
  await capture('brands.show', 'GET', `/brands/${brands.data[0].slug}`);
  await capture('brands.products', 'GET', `/brands/${brands.data[0].slug}/products`, { query: { per_page: '2' } });
} else {
  await capture('brands.show', 'GET', '/brands/api-docs-brand');
  await capture('brands.products', 'GET', '/brands/api-docs-brand/products', { query: { per_page: '2' } });
}
if (combos.data.length) {
  await capture('combos.show', 'GET', `/combos/${combos.data[0].slug}`);
}
if (pages.data.length) {
  await capture('pages.show', 'GET', `/pages/${pages.data[0].slug}`);
} else {
  await capture('pages.show', 'GET', '/pages/api-docs-page');
}

/* ── Errors a frontend must handle ────────────────────────────────── */

await capture('error.404', 'GET', '/products/this-product-does-not-exist');
await capture('error.422', 'POST', '/auth/login', { body: { email: 'nope', password: '' } });
await capture('error.401', 'GET', '/cart');

/* ── Authenticated flow ───────────────────────────────────────────── */

const email = `api.doc.${Date.now()}@example.com`;

const registered = await capture('auth.register', 'POST', '/auth/register', {
  body: {
    name: 'Doc Preview',
    email,
    password: 'Password123!',
    password_confirmation: 'Password123!',
    phone: `9${String(Date.now()).slice(-9)}`,
  },
});

const token = registered.data.token;

await capture('auth.login', 'POST', '/auth/login', {
  body: { email, password: 'Password123!' },
});
await capture('auth.me', 'GET', '/auth/me', { auth: true, token });
await capture('auth.profile', 'PUT', '/auth/profile', {
  auth: true, token, body: { name: 'Doc Preview', phone: null },
});

await capture('cart.empty', 'GET', '/cart', { auth: true, token });
await capture('cart.add', 'POST', '/cart', { auth: true, token, body: { product_id: products.data[0].id, quantity: 2 } });

const lineId = out['cart.add'].response.data[0].id;
await capture('cart.update', 'PATCH', `/cart/${lineId}`, { auth: true, token, body: { quantity: 3 } });
await capture('cart.get', 'GET', '/cart', { auth: true, token });
await capture('cart.remove', 'DELETE', `/cart/${lineId}`, { auth: true, token });
await capture('cart.clear', 'DELETE', '/cart', { auth: true, token });

await capture('wishlist.toggle', 'POST', '/wishlist/toggle', { auth: true, token, body: { product_id: products.data[0].id } });
await capture('wishlist.get', 'GET', '/wishlist', { auth: true, token });
await capture('wishlist.remove', 'DELETE', `/wishlist/${products.data[0].id}`, { auth: true, token });

await capture('orders.list', 'GET', '/orders', { auth: true, token });
await capture('auth.logout', 'POST', '/auth/logout', { auth: true, token });

// The token above was just revoked, so mint a fresh one for the last call.
const relogin = await fetch(`${BASE}/auth/login`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  body: JSON.stringify({ email, password: 'Password123!' }),
}).then((r) => r.json());

await capture('auth.logout-all', 'POST', '/auth/logout-all', { auth: true, token: relogin.data.token });

/* ── Redact the live token so it never ships in a doc ─────────────── */

out['auth.register'].response.data.token = '<token>';
out['auth.login'].response.data.token = '<token>';

fs.writeFileSync(new URL('./captured.json', import.meta.url), JSON.stringify(out, null, 2));

console.log('CAPTURED ' + Object.keys(out).length + ' endpoints');
console.log('prod base for docs: ' + PROD);
