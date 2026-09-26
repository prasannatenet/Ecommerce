{{--
    Live API demo.

    Renders products, categories and login entirely from the JSON API: the page
    itself queries nothing. Everything below is fed by public/js/gehna-api.js,
    the same client file intended for the React app, so if an endpoint or a
    field name is wrong this page is visibly empty rather than quietly falling
    back to server-rendered data.

    This file is deliberately pure ASCII (entities instead of em-dashes, a
    &#8377; escape inside the script) so it renders identically whatever
    charset a host serves it with.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gehna API &mdash; Live Demo</title>
    <style>
        :root {
            --ink: #1c1917; --muted: #78716c; --line: #e7e5e4;
            --bg: #faf9f7; --card: #fff; --gold: #b08d57;
            --ok: #15803d; --bad: #b91c1c;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--bg); color: var(--ink);
            font: 15px/1.6 ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
        }
        header { background: var(--ink); color: #fff; padding: 28px 24px; }
        header h1 { margin: 0 0 6px; font-size: 22px; letter-spacing: .02em; }
        header p { margin: 0; color: #c9c2b8; font-size: 14px; }
        header code { background: rgba(255,255,255,.12); padding: 2px 7px; border-radius: 5px; font-size: 13px; }
        main { max-width: 1180px; margin: 0 auto; padding: 28px 24px 72px; }
        section { margin-bottom: 40px; }
        h2 { font-size: 17px; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); margin: 0 0 14px; }
        .grid { display: grid; gap: 16px; }
        .grid.products { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
        .grid.categories { grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; }
        .card .thumb { aspect-ratio: 1; background: #f1efeb; display: grid; place-items: center; overflow: hidden; }
        .card .thumb img { width: 100%; height: 100%; object-fit: cover; }
        .card .thumb .none { color: var(--muted); font-size: 12px; }
        .card .body { padding: 12px 14px 14px; }
        .card .name { font-weight: 600; font-size: 14px; margin-bottom: 4px; }
        .card .meta { color: var(--muted); font-size: 12px; }
        .card .price { margin-top: 8px; font-weight: 700; }
        .card .was { color: var(--muted); text-decoration: line-through; font-weight: 400; font-size: 13px; margin-left: 6px; }
        .badge { display: inline-block; background: #fdecec; color: var(--bad); font-size: 11px; padding: 1px 7px; border-radius: 20px; font-weight: 600; }
        .badge.gold { background: #f6efe3; color: #8a6a33; }
        form { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
        label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 4px; }
        input, select { font: inherit; padding: 9px 11px; border: 1px solid var(--line); border-radius: 8px; background: #fff; min-width: 170px; }
        button { font: inherit; font-weight: 600; padding: 10px 18px; border: 0; border-radius: 8px; background: var(--gold); color: #fff; cursor: pointer; }
        button.ghost { background: transparent; color: var(--ink); border: 1px solid var(--line); }
        button:disabled { opacity: .55; cursor: not-allowed; }
        .panel { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 18px; }
        .msg { padding: 10px 13px; border-radius: 8px; font-size: 14px; margin-top: 12px; }
        .msg.ok { background: #ecfdf3; color: var(--ok); }
        .msg.bad { background: #fef2f2; color: var(--bad); }
        .pill { display: inline-block; background: #f5f5f4; border: 1px solid var(--line); border-radius: 20px; padding: 5px 12px; font-size: 13px; margin: 0 6px 6px 0; cursor: pointer; }
        .pill.active { background: var(--ink); color: #fff; border-color: var(--ink); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); }
        th { color: var(--muted); font-weight: 600; }
        .spinner { color: var(--muted); font-size: 14px; }
    </style>
</head>

<body>
<header>
    <h1>Gehna Storefront API &mdash; Live Demo</h1>
    <p>
        Every box below is filled by the JSON API, not by this page.
        Base URL: <code id="base-url">{{ $apiBase }}</code>
        &nbsp;&middot;&nbsp; <a href="{{ $clientUrl }}" style="color:#e7c893">view the client file</a>
    </p>
</header>

<main>
    <section>
        <h2>1 &middot; Login (POST /auth/login)</h2>
        <div class="panel">
            <form id="login-form">
                <div>
                    <label for="email">Email</label>
                    <input id="email" type="email" placeholder="you@example.com" required>
                </div>
                <div>
                    <label for="password">Password</label>
                    <input id="password" type="password" placeholder="********" required>
                </div>
                <button type="submit" id="login-btn">Sign in</button>
                <button type="button" class="ghost" id="logout-btn" hidden>Sign out</button>
            </form>
            <div id="login-msg"></div>
            <div id="session" hidden style="margin-top:14px">
                <table>
                    <tr><th>Signed in as</th><td id="s-name"></td></tr>
                    <tr><th>Email</th><td id="s-email"></td></tr>
                    <tr><th>Phone</th><td id="s-phone"></td></tr>
                    <tr><th>Gehna Coins</th><td id="s-coins"></td></tr>
                    <tr><th>Roles</th><td id="s-roles"></td></tr>
                    <tr><th>Token (truncated)</th><td id="s-token"></td></tr>
                </table>
            </div>
        </div>
    </section>

    <section>
        <h2>2 &middot; Authenticated endpoints</h2>
        <div class="panel">
            <p style="margin-top:0;color:var(--muted)">
                Sign in above to call these. They return 401 without a token.
            </p>
            <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">
                <div><strong id="c-cart">-</strong><div class="meta">cart items</div></div>
                <div><strong id="c-wish">-</strong><div class="meta">wishlist</div></div>
                <div><strong id="c-orders">-</strong><div class="meta">orders</div></div>
                <div><strong id="c-sub">-</strong><div class="meta">cart subtotal</div></div>
            </div>
            <div id="auth-msg"></div>
        </div>
    </section>

    <section>
        <h2>3 &middot; Categories (GET /categories)</h2>
        <p class="spinner" id="cat-loading">Loading...</p>
        <div id="cat-pills"></div>
        <div class="grid categories" id="categories" style="margin-top:14px"></div>
    </section>

    <section>
        <h2>4 &middot; Products (GET /products &middot; GET /categories/{slug}/products)</h2>
        <div class="panel" style="margin-bottom:16px">
            <form id="filter-form">
                <div>
                    <label for="sort">Sort</label>
                    <select id="sort">
                        <option value="latest">Newest first</option>
                        <option value="price_asc">Price: low to high</option>
                        <option value="price_desc">Price: high to low</option>
                        <option value="name">Name A-Z</option>
                        <option value="oldest">Oldest first</option>
                    </select>
                </div>
                <div>
                    <label for="audience">Audience</label>
                    <select id="audience">
                        <option value="">Any</option>
                        <option value="women">Women</option>
                        <option value="men">Men</option>
                        <option value="unisex">Unisex</option>
                    </select>
                </div>
                <div>
                    <label for="offers">Offers</label>
                    <select id="offers">
                        <option value="">All products</option>
                        <option value="sale">On sale only</option>
                        <option value="sale_stock">On sale &amp; in stock</option>
                    </select>
                </div>
                <div>
                    <label for="per_page">Per page</label>
                    <select id="per_page">
                        <option>4</option><option selected>8</option><option>12</option><option>24</option>
                    </select>
                </div>
                <button type="submit">Apply</button>
            </form>
        </div>
        <p class="spinner" id="prod-loading">Loading...</p>
        <p id="prod-meta" class="meta" style="margin:0 0 12px"></p>
        <div class="grid products" id="products"></div>
        <div style="margin-top:16px;display:flex;gap:10px;align-items:center">
            <button type="button" class="ghost" id="prev-page">&larr; Previous</button>
            <button type="button" class="ghost" id="next-page">Next &rarr;</button>
            <span class="meta" id="page-label"></span>
        </div>
    </section>

    <section>
        <h2>5 &middot; Search (GET /search?q=)</h2>
        <div class="panel">
            <form id="search-form">
                <div style="flex:1">
                    <label for="q">Search products</label>
                    <input id="q" type="search" placeholder="bracelet, ring, gold..." style="width:100%">
                </div>
                <button type="submit">Search</button>
            </form>
            <div id="search-meta" class="meta" style="margin-top:12px"></div>
        </div>
    </section>
</main>

{{-- The client reads its base URL from this global, so this page and the React
     app can point at different environments without editing the client file. --}}
<script>window.GEHNA_API_BASE = @json($apiBase);</script>
<script type="module" src="{{ $clientUrl }}"></script>


<script type="module">
    import api, { ApiError } from '{{ $clientUrl }}';

    const RUPEE = '\u20B9';

    /* -- helpers ------------------------------------------------ */

    const $ = (id) => document.getElementById(id);

    /** Escape text before it goes anywhere near innerHTML. */
    const esc = (value) =>
        String(value ?? '').replace(/[&<>"']/g, (c) => (
            { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
        ));

    const money = (amount) =>
        RUPEE + Number(amount ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });

    function showMessage(el, text, ok = true) {
        el.innerHTML = text
            ? `<div class="msg ${ok ? 'ok' : 'bad'}">${esc(text)}</div>`
            : '';
    }

    /** Turn an ApiError into one readable line, including field errors. */
    function describe(error) {
        if (!(error instanceof ApiError)) return 'Something went wrong.';

        const fields = Object.entries(error.errors ?? {})
            .map(([field, messages]) => `${field}: ${[].concat(messages).join(' ')}`)
            .join(' ');

        return fields ? `${error.message} - ${fields}` : error.message;
    }

    /* -- 3. categories ------------------------------------------ */

    let activeCategory = null;

    async function loadCategories() {
        try {
            const { data } = await api.getCategories();

            $('cat-loading').hidden = true;
            $('cat-pills').innerHTML =
                `<span class="pill ${activeCategory === null ? 'active' : ''}" data-slug="">All products</span>` +
                data
                    .map((c) => `<span class="pill" data-slug="${esc(c.slug)}">${esc(c.name)} (${c.products_count ?? 0})</span>`)
                    .join('');

            $('categories').innerHTML = data
                .map((c) => `
                    <div class="card">
                        <div class="thumb">
                            ${c.image
                                ? `<img src="${esc(c.image)}" alt="${esc(c.name)}" loading="lazy">`
                                : '<span class="none">no image</span>'}
                        </div>
                        <div class="body">
                            <div class="name">${esc(c.name)}</div>
                            <div class="meta">${esc(c.slug)}</div>
                            <div class="meta">${c.products_count ?? 0} product(s)</div>
                        </div>
                    </div>`)
                .join('');

            $('cat-pills').querySelectorAll('.pill').forEach((pill) => {
                pill.addEventListener('click', () => {
                    activeCategory = pill.dataset.slug || null;
                    currentPage = 1;
                    $('cat-pills').querySelectorAll('.pill').forEach((p) => p.classList.remove('active'));
                    pill.classList.add('active');
                    loadProducts();
                });
            });
        } catch (error) {
            $('cat-loading').textContent = describe(error);
        }
    }


    /* -- 4. products -------------------------------------------- */

    let currentPage = 1;
    let lastPage = 1;

    async function loadProducts() {
        $('prod-loading').hidden = false;
        $('products').innerHTML = '';

        const offers = $('offers').value;

        const params = {
            sort: $('sort').value,
            audience: $('audience').value,
            per_page: $('per_page').value,
            page: currentPage,
            on_sale: offers === 'sale' || offers === 'sale_stock' ? 1 : '',
            in_stock: offers === 'sale_stock' ? 1 : '',
        };

        try {
            // The same ProductResource backs both endpoints, so switching
            // between "all" and "in a category" only changes the URL.
            const response = activeCategory
                ? await api.getCategoryProducts(activeCategory, params)
                : await api.getProducts(params);

            const { data, meta } = response;
            lastPage = meta.last_page ?? 1;

            $('prod-loading').hidden = true;
            $('prod-meta').textContent =
                `${meta.total ?? data.length} product(s) / page ${meta.current_page} of ${meta.last_page}` +
                (activeCategory ? ` / in "${activeCategory}"` : '');

            $('page-label').textContent = `Page ${currentPage} / ${lastPage}`;
            $('prev-page').disabled = currentPage <= 1;
            $('next-page').disabled = currentPage >= lastPage;

            $('products').innerHTML = data
                .map((p) => {
                    const image = p.primary_image || (p.images && p.images[0]);

                    return `
                        <div class="card">
                            <div class="thumb">
                                ${image
                                    ? `<img src="${esc(image)}" alt="${esc(p.name)}" loading="lazy">`
                                    : '<span class="none">no image</span>'}
                            </div>
                            <div class="body">
                                <div class="name">${esc(p.name)}</div>
                                <div class="meta">${esc(p.category?.name ?? 'Uncategorised')}</div>
                                <div class="meta">${esc(p.material_type ?? '')} / ${esc(p.audience ?? '')}</div>
                                <div class="price">
                                    ${money(p.price)}
                                    ${p.is_on_sale ? `<span class="was">${money(p.regular_price)}</span>` : ''}
                                </div>
                                <div style="margin-top:8px">
                                    ${p.is_on_sale ? `<span class="badge">-${p.discount_percentage ?? 0}%</span>` : ''}
                                    ${p.in_stock
                                        ? '<span class="badge gold">in stock</span>'
                                        : '<span class="badge">out of stock</span>'}
                                </div>
                            </div>
                        </div>`;
                })
                .join('');
        } catch (error) {
            $('prod-loading').textContent = describe(error);
        }
    }

    /* -- 5. search ---------------------------------------------- */

    async function runSearch(term) {
        if (!term.trim()) return;

        try {
            const { data, meta } = await api.search(term, { per_page: 24 });

            $('search-meta').textContent =
                `"${term}" matched ${meta.total} product(s) across ${meta.categories.length} categor(ies). ` +
                `First: ${data[0]?.name ?? 'none'}`;
        } catch (error) {
            $('search-meta').textContent = describe(error);
        }
    }


    /* -- 1 and 2. auth ------------------------------------------ */

    async function refreshCustomerPanel() {
        const msg = $('auth-msg');

        try {
            const [cart, wishlist, orders] = await Promise.all([
                api.getCart(),
                api.getWishlist(),
                api.getOrders(),
            ]);

            $('c-cart').textContent = cart.meta.count ?? 0;
            $('c-sub').textContent = money(cart.meta.subtotal);
            $('c-wish').textContent = wishlist.meta.count ?? 0;
            $('c-orders').textContent = orders.meta.total ?? 0;
            showMessage(msg, '');
        } catch (error) {
            $('c-cart').textContent = $('c-wish').textContent = '-';
            $('c-orders').textContent = $('c-sub').textContent = '-';
            showMessage(msg, describe(error), false);
        }
    }

    async function showSession() {
        const { data: user } = await api.me();

        if (!user) {
            $('session').hidden = true;
            $('logout-btn').hidden = true;
            $('login-btn').hidden = false;
            await refreshCustomerPanel();
            return;
        }

        $('s-name').textContent = user.name;
        $('s-email').textContent = user.email;
        $('s-phone').textContent = user.phone ?? '-';
        $('s-coins').textContent = user.gehna_coins;
        $('s-roles').textContent = user.roles?.join(', ') || 'customer';

        const token = localStorage.getItem('gehna_api_token') ?? '';
        $('s-token').textContent = token ? token.slice(0, 18) + '...' : '-';

        $('session').hidden = false;
        $('logout-btn').hidden = false;
        $('login-btn').hidden = true;

        await refreshCustomerPanel();
    }

    $('login-form').addEventListener('submit', async (event) => {
        event.preventDefault();

        const button = $('login-btn');
        button.disabled = true;

        try {
            const { data } = await api.login({
                email: $('email').value.trim(),
                password: $('password').value,
            });

            showMessage($('login-msg'), `Signed in as ${data.user.name}.`, true);
            await showSession();
        } catch (error) {
            showMessage($('login-msg'), describe(error), false);
        } finally {
            button.disabled = false;
        }
    });

    $('logout-btn').addEventListener('click', async () => {
        await api.logout();
        showMessage($('login-msg'), 'Signed out.', true);
        $('password').value = '';
        await showSession();
    });

    /* -- wire up ------------------------------------------------ */

    $('filter-form').addEventListener('submit', (event) => {
        event.preventDefault();
        currentPage = 1;
        loadProducts();
    });

    $('prev-page').addEventListener('click', () => {
        if (currentPage > 1) { currentPage -= 1; loadProducts(); }
    });

    $('next-page').addEventListener('click', () => {
        if (currentPage < lastPage) { currentPage += 1; loadProducts(); }
    });

    $('search-form').addEventListener('submit', (event) => {
        event.preventDefault();
        runSearch($('q').value);
    });

    // Restore a session from a token left in localStorage, then paint the page.
    await showSession();
    await loadCategories();
    await loadProducts();
</script>
</body>
</html>
