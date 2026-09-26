
// ===== CART MANAGEMENT =====
class Cart {
  constructor() {
    this.items = JSON.parse(localStorage.getItem('gehna_cart')) || [];
    this.updateCartBadge();
  }
  save() {
    localStorage.setItem('gehna_cart', JSON.stringify(this.items));
    this.updateCartBadge();
  }
  addItem(productId, qty = 1) {
    const product = products.find(p => p.id === productId);
    if (!product) return;
    const existing = this.items.find(i => i.id === productId);
    if (existing) {
      existing.qty += qty;
    } else {
      this.items.push({ id: product.id, name: product.name, price: product.price, originalPrice: product.originalPrice, image: product.image, qty: qty, weight: product.weight });
    }
    this.save();
    showToast(`${product.name} added to cart!`);
  }
  removeItem(productId) {
    this.items = this.items.filter(i => i.id !== productId);
    this.save();
  }
  updateQty(productId, qty) {
    const item = this.items.find(i => i.id === productId);
    if (item) {
      item.qty = Math.max(1, qty);
      this.save();
    }
  }
  getTotal() {
    return this.items.reduce((sum, item) => sum + item.price * item.qty, 0);
  }
  getOriginalTotal() {
    return this.items.reduce((sum, item) => sum + item.originalPrice * item.qty, 0);
  }
  getCount() {
    return this.items.reduce((sum, item) => sum + item.qty, 0);
  }
  updateCartBadge() {
    // Legacy localStorage logic disabled to prevent conflict with Laravel backend counts
    // const badges = document.querySelectorAll('.cart-count');
    // const count = this.getCount();
    // badges.forEach(b => { b.textContent = count; b.style.display = count > 0 ? 'flex' : 'none'; });
  }
  clear() { this.items = []; this.save(); }
}

const cart = new Cart();

// ===== WISHLIST =====
class Wishlist {
  constructor() {
    this.items = JSON.parse(localStorage.getItem('gehna_wishlist')) || [];
    this.updateIcons();
    this.renderSidebar();
  }
  save() { localStorage.setItem('gehna_wishlist', JSON.stringify(this.items)); }
  toggle(productId) {
    const idx = this.items.indexOf(productId);
    if (idx > -1) { this.items.splice(idx, 1); showToast('Removed from wishlist'); }
    else { this.items.push(productId); showToast('Added to wishlist! ❤️'); }
    this.save();
    this.updateIcons();
    this.renderSidebar();
  }
  has(productId) { return this.items.includes(productId); }

  clear() {
    this.items = [];
    this.save();
    this.updateIcons();
    this.renderSidebar();
    showToast('Wishlist cleared!');
  }

  moveToCart() {
    const checkboxes = document.querySelectorAll('.wishlist-item-checkbox:checked');
    let selectedIds;

    if (checkboxes.length === 0) {
      // If no checkboxes are selected, move all products to cart
      selectedIds = [...this.items];
    } else {
      // If some checkboxes are selected, move only those
      selectedIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
    }

    if (selectedIds.length === 0) return;

    selectedIds.forEach(id => {
      cart.addItem(id);
      this.items = this.items.filter(itemId => itemId !== id);
    });

    this.save();
    this.updateIcons();
    this.renderSidebar();
    showToast(`${selectedIds.length} items moved to cart!`);
  }

  updateIcons() {
    document.querySelectorAll('.wishlist-btn').forEach(btn => {
      const id = parseInt(btn.dataset.productId);
      const icon = btn.querySelector('i');
      if (this.has(id)) { icon.classList.remove('bi-heart'); icon.classList.add('bi-heart-fill'); btn.style.color = '#017075'; }
      else { icon.classList.remove('bi-heart-fill'); icon.classList.add('bi-heart'); btn.style.color = ''; }
    });
    // Legacy localStorage logic disabled to prevent conflict with Laravel backend counts
    const badges = document.querySelectorAll('.wishlist-count');
    // const count = this.items.length;
    // badges.forEach(b => {
    //   b.textContent = count;
    //   b.style.display = count > 0 ? 'flex' : 'none';
    // });
  }
  renderSidebar() {
    const sidebars = document.querySelectorAll('#wishlistSidebar .offcanvas-body');
    if (!sidebars.length) return;

    if (this.items.length === 0) {
      sidebars.forEach(sidebar => {
        sidebar.innerHTML = `
          <div class="text-center py-5">
            <i class="bi bi-heart text-muted" style="font-size: 3rem;"></i>
            <p class="mt-3 text-muted">Your wishlist is currently empty.</p>
            <a href="products.html" class="btn-gehna btn-primary-gehna mt-2">Explore Products</a>
          </div>
        `;
      });
      return;
    }

    let html = '<div class="wishlist-items-list">';
    this.items.forEach(id => {
      const product = products.find(p => p.id === id);
      if (product) {
        html += `
          <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-3">

  <!-- Left: Image + Info -->
  <div class="d-flex align-items-center">
    <div class="form-check me-2 mb-0">
      <input class="form-check-input wishlist-item-checkbox" type="checkbox" value="${product.id}" id="wishlist-check-${product.id}" style="cursor: pointer;">
    </div>
    <img src="${product.image}" alt="${product.name}" 
      style="width: 70px; height: 70px; object-fit: contain; background: #FFF; border-radius: 8px; margin-right: 15px; border: 1px solid #eee;">

    <div>
      <h6 class="mb-1" style="font-size: 0.9rem;">
        <a href="/product/${product.slug}" class="text-decoration-none text-dark">
          ${product.name}
        </a>
      </h6>

      <div class="fw-bold" style="color: #017075;">
        ₹${product.price.toLocaleString()}
      </div>
    </div>
  </div>

  <!-- Right: Buttons -->
  <div class="d-flex flex-column gap-2">
    <button class="btn btn-sm btn-primary-gehna"
      onclick="cart.addItem(${product.id}); wishlist.toggle(${product.id});"
      style="font-size: 0.75rem; white-space: nowrap;">
      <i class="bi bi-cart-plus"></i> Add to cart
    </button>

    <button class="btn btn-sm btn-outline-danger"
      onclick="wishlist.toggle(${product.id})"
      style="font-size: 0.75rem;">
      <i class="bi bi-trash"></i>
    </button>
  </div>

</div>
        `;
      }
    });
    html += '</div>';

    html += `
      <div class="wishlist-action-buttons mt-4 pt-3 border-top pb-4">
        <button class="btn btn-primary-gehna w-100 mb-2" onclick="wishlist.moveToCart()">
          <i class="bi bi-cart-check me-2"></i>Move to Cart
        </button>
        <button class="btn btn-outline-danger w-100" onclick="wishlist.clear()">
          <i class="bi bi-trash me-2"></i>Clear Wishlist
        </button>
      </div>
    `;

    sidebars.forEach(sidebar => {
      sidebar.innerHTML = html;
    });
  }
}
const wishlist = new Wishlist();

// ===== TOAST NOTIFICATION =====
function showToast(message) {
  let toast = document.getElementById('gehna-toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'gehna-toast';
    toast.className = 'toast-gehna';
    toast.innerHTML = `<i class="bi bi-check-circle-fill"></i><span class="toast-msg"></span><button class="toast-close" onclick="this.parentElement.classList.remove('show')">&times;</button>`;
    document.body.appendChild(toast);
  }
  toast.querySelector('.toast-msg').textContent = message;
  toast.classList.add('show');
  clearTimeout(toast._timeout);
  toast._timeout = setTimeout(() => toast.classList.remove('show'), 3000);
}

// ===== NAVBAR SCROLL =====
function initNavbar() {
  const navbar = document.querySelector('.navbar-gehna');
  if (!navbar) return;
  window.addEventListener('scroll', () => {
    navbar.classList.toggle('scrolled', window.scrollY > 50);
  });
}

// ===== BACK TO TOP =====
function initBackToTop() {
  const btn = document.getElementById('backToTop');
  if (!btn) return;
  window.addEventListener('scroll', () => {
    btn.classList.toggle('show', window.scrollY > 400);
  });
  btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
}

// ===== FLOATING OFFER TAB =====
// Left-edge tab that travels with the shopper and opens every running coupon.
function initFloatingOffers() {
  const wrap = document.getElementById('offerTab');
  if (!wrap) return;

  const btn = document.getElementById('offerTabBtn');
  const panel = document.getElementById('offerTabPanel');
  const closeBtn = document.getElementById('offerTabClose');
  const backdrop = document.getElementById('offerTabBackdrop');
  if (!btn || !panel) return;

  const isOpen = () => wrap.classList.contains('is-open');

  const open = () => {
    wrap.classList.add('is-open');
    btn.setAttribute('aria-expanded', 'true');
    panel.setAttribute('aria-hidden', 'false');
    if (backdrop) backdrop.hidden = false;
  };

  const close = () => {
    wrap.classList.remove('is-open');
    btn.setAttribute('aria-expanded', 'false');
    panel.setAttribute('aria-hidden', 'true');
    if (backdrop) backdrop.hidden = true;
  };

  btn.addEventListener('click', () => (isOpen() ? close() : open()));

  if (closeBtn) {
    closeBtn.addEventListener('click', () => {
      close();
      btn.focus();
    });
  }

  // The shield sits behind the panel, so tapping it dismisses the popup.
  if (backdrop) backdrop.addEventListener('click', close);

  // Any click outside the widget closes it (the tab itself is inside, so the
  // toggle above still wins).
  document.addEventListener('click', (e) => {
    if (isOpen() && !wrap.contains(e.target)) close();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && isOpen()) {
      close();
      btn.focus();
    }
  });

  // Copy a coupon code straight out of the panel.
  panel.querySelectorAll('[data-offer-code]').forEach((copyBtn) => {
    copyBtn.addEventListener('click', () => {
      const code = (copyBtn.dataset.offerCode || '').trim();
      if (!code) return;

      const done = () => showToast('Coupon code ' + code.toUpperCase() + ' copied!');

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code).then(done).catch(() => showToast('Coupon code: ' + code.toUpperCase()));
      } else {
        showToast('Coupon code: ' + code.toUpperCase());
      }
    });
  });
}

// ===== LIVE METAL RATES =====
// Top bar "LIVE" button: opens the gold & silver panel and keeps it current.
function initLiveRates() {
  const wrap = document.getElementById('liveRates');
  if (!wrap) return;

  const btn = document.getElementById('liveRatesBtn');
  const panel = document.getElementById('liveRatesPanel');
  const closeBtn = document.getElementById('liveRatesClose');
  const refreshBtn = document.getElementById('liveRatesRefresh');
  const url = wrap.getAttribute('data-live-rates-url');
  if (!btn || !panel || !url) return;

  const REFRESH_MS = 5 * 60 * 1000;
  const CARET_ICONS = {
    up: 'bi-caret-up-fill',
    down: 'bi-caret-down-fill',
    flat: 'bi-dash'
  };

  const isOpen = () => wrap.classList.contains('is-open');

  const open = () => {
    wrap.classList.add('is-open');
    btn.setAttribute('aria-expanded', 'true');
    panel.setAttribute('aria-hidden', 'false');
  };

  const close = () => {
    wrap.classList.remove('is-open');
    btn.setAttribute('aria-expanded', 'false');
    panel.setAttribute('aria-hidden', 'true');
  };

  btn.addEventListener('click', () => (isOpen() ? close() : open()));

  if (closeBtn) {
    closeBtn.addEventListener('click', () => {
      close();
      btn.focus();
    });
  }

  document.addEventListener('click', (e) => {
    if (isOpen() && !wrap.contains(e.target)) close();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && isOpen()) {
      close();
      btn.focus();
    }
  });

  /* The server sends the same pre-formatted strings the blade rendered, so a
     refresh can only ever replace text — never reformat it differently. */
  const apply = (data) => {
    if (!data) return;

    const flat = data.flat || {};
    const metals = data.metals || {};

    panel.querySelectorAll('[data-live]').forEach((el) => {
      const value = flat[el.getAttribute('data-live')];
      if (typeof value === 'string') el.textContent = value;
    });

    panel.querySelectorAll('[data-live-dir]').forEach((el) => {
      const dir = flat[el.getAttribute('data-live-dir')];
      if (!dir) return;

      el.classList.remove('is-up', 'is-down', 'is-flat');
      el.classList.add('is-' + dir);

      const icon = el.querySelector('i');
      if (icon) icon.className = 'bi ' + (CARET_ICONS[dir] || CARET_ICONS.flat);
    });

    panel.querySelectorAll('[data-live-metal]').forEach((el) => {
      const metal = metals[el.getAttribute('data-live-metal')];
      if (metal && metal.direction) el.setAttribute('data-live-direction', metal.direction);
    });

    btn.classList.toggle('is-offline', !data.available);
  };

  const refresh = () => {
    if (refreshBtn) refreshBtn.classList.add('is-loading');

    fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then((res) => {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(apply)
      .catch(() => {
        // Keep showing the last known rates rather than blanking the panel.
      })
      .then(() => {
        if (refreshBtn) refreshBtn.classList.remove('is-loading');
      });
  };

  if (refreshBtn) refreshBtn.addEventListener('click', refresh);

  window.setInterval(() => {
    if (document.visibilityState === 'visible') refresh();
  }, REFRESH_MS);
}

// ===== SCROLL ANIMATIONS =====
function initScrollAnimations() {
  const elements = document.querySelectorAll('.animate-on-scroll');
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
  elements.forEach(el => observer.observe(el));
}

// ===== RENDER PRODUCT CARDS =====
function renderProductCard(product) {
  const isWished = wishlist.has(product.id);
  return `
    <div class="col-lg-4 col-md-6 mb-4 product-col" data-category="${product.category}" data-weight="${product.weight}" data-price="${product.price}">
      <div class="product-card">
        <div class="product-img-wrap">
          <div class="product-badges">
            ${product.badge === 'sale' ? '<span class="badge-sale">SALE</span>' : ''}
            ${product.badge === 'new' ? '<span class="badge-new">NEW</span>' : ''}
          </div>
          <div class="product-actions-overlay">
            <button class="product-action-btn wishlist-btn" data-product-id="${product.id}" onclick="wishlist.toggle(${product.id})" title="Add to Wishlist">
              <i class="bi ${isWished ? 'bi-heart-fill' : 'bi-heart'}"></i>
            </button>
            <button class="product-action-btn" onclick="openQuickView(${product.id})" title="Quick View">
              <i class="bi bi-eye"></i>
            </button>
          </div>
          <a href="/product/${product.slug}">
            <img src="${product.image}" alt="${product.name}" loading="lazy">
          </a>
        </div>
        <div class="product-info">
          <span class="product-category">${product.category}</span>
          <a href="/product/${product.slug}"><h5 class="product-title">${product.name}</h5></a>
          <div class="product-rating">
            ${renderStars(product.rating)}
            <span>(${product.reviews})</span>
          </div>
          <div class="product-price-row">
            <span class="product-price">₹${product.price.toLocaleString()}</span>
            <span class="product-price-old">₹${product.originalPrice.toLocaleString()}</span>
            <span class="product-price-discount">${product.discount}% off</span>
          </div>
          <button class="btn-cart-gehna" onclick="cart.addItem(${product.id})">
            <i class="bi bi-cart-plus"></i> Add to Cart
          </button>
        </div>
      </div>
    </div>`;
}

function renderStars(rating) {
  let html = '';
  for (let i = 1; i <= 5; i++) {
    if (i <= Math.floor(rating)) html += '<i class="bi bi-star-fill"></i>';
    else if (i - 0.5 <= rating) html += '<i class="bi bi-star-half"></i>';
    else html += '<i class="bi bi-star"></i>';
  }
  return html;
}

// ===== QUICK VIEW MODAL =====
function openQuickView(productId) {
  const product = products.find(p => p.id === productId);
  if (!product) return;
  const modal = document.getElementById('quickViewModal');
  if (!modal) return;
  modal.querySelector('.modal-body').innerHTML = `
    <div class="row g-4">
      <div class="col-md-5">
        <div class="gallery-main"><img src="${product.image}" alt="${product.name}"></div>
      </div>
      <div class="col-md-7">
        <span class="product-category">${product.category}</span>
        <h4 class="product-detail-title mt-1">${product.name}</h4>
        <div class="product-rating mb-2">${renderStars(product.rating)} <span>(${product.reviews} reviews)</span></div>
        <div class="d-flex align-items-center gap-3 mb-3">
          <span class="product-detail-price">₹${product.price.toLocaleString()}</span>
          <span class="product-detail-price-old">₹${product.originalPrice.toLocaleString()}</span>
          <span class="badge-sale">${product.discount}% OFF</span>
        </div>
        <p class="text-muted mb-3">${product.description}</p>
        <div class="d-flex gap-3">
          <button class="btn-gehna btn-primary-gehna btn-sm-gehna" onclick="cart.addItem(${product.id}); bootstrap.Modal.getInstance(document.getElementById('quickViewModal')).hide();">
            <i class="bi bi-cart-plus"></i> Add to Cart
          </button>
          <a href="/product/${product.slug}" class="btn-gehna btn-dark-gehna btn-sm-gehna">View Details</a>
        </div>
      </div>
    </div>`;
  new bootstrap.Modal(modal).show();
}

// ===== PRODUCT LISTING PAGE =====
function initProductListing() {
  const container = document.getElementById('productGrid');
  if (!container) return;
  let filteredProducts = [...products];

  function render(prods) {
    container.innerHTML = prods.map(renderProductCard).join('');
    wishlist.updateIcons();
    document.getElementById('productCount').textContent = prods.length;
  }

  function applyFilters() {
    let result = [...products];
    // Category filter
    const checkedCats = [...document.querySelectorAll('.filter-category:checked')].map(c => c.value);
    if (checkedCats.length > 0) result = result.filter(p => checkedCats.includes(p.category));
    // Weight filter
    const checkedWeights = [...document.querySelectorAll('.filter-weight:checked')].map(c => c.value);
    if (checkedWeights.length > 0) result = result.filter(p => checkedWeights.includes(p.weight));
    // Price filter
    const maxPrice = parseInt(document.getElementById('priceRange')?.value || 50000);
    result = result.filter(p => p.price <= maxPrice);
    // Sort
    const sort = document.getElementById('sortSelect')?.value;
    if (sort === 'price-low') result.sort((a, b) => a.price - b.price);
    else if (sort === 'price-high') result.sort((a, b) => b.price - a.price);
    else if (sort === 'popularity') result.sort((a, b) => b.reviews - a.reviews);
    else if (sort === 'rating') result.sort((a, b) => b.rating - a.rating);
    else if (sort === 'discount') result.sort((a, b) => b.discount - a.discount);

    render(result);
  }

  // Event listeners
  document.querySelectorAll('.filter-category, .filter-weight').forEach(el => el.addEventListener('change', applyFilters));
  const priceRange = document.getElementById('priceRange');
  if (priceRange) {
    priceRange.addEventListener('input', () => {
      document.getElementById('priceValue').textContent = `₹${parseInt(priceRange.value).toLocaleString()}`;
      applyFilters();
    });
  }
  const sortSelect = document.getElementById('sortSelect');
  if (sortSelect) sortSelect.addEventListener('change', applyFilters);

  applyFilters();
}

// ===== PRODUCT DETAIL PAGE =====
function initProductDetail() {
  const detailWrap = document.getElementById('productDetailWrap');
  if (!detailWrap) return;
  const urlParams = new URLSearchParams(window.location.search);
  const productId = parseInt(urlParams.get('id')) || 1;
  const product = products.find(p => p.id === productId) || products[0];

  // Set page title
  document.title = `${product.name} | GEHNA Fitness`;

  // Gallery
  const mainImg = document.getElementById('mainProductImg');
  mainImg.src = product.image;
  mainImg.alt = product.name;
  document.querySelectorAll('.gallery-thumb').forEach(thumb => {
    thumb.addEventListener('click', () => {
      document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
      thumb.classList.add('active');
      mainImg.src = thumb.querySelector('img').src;
    });
  });

  // Product info
  document.getElementById('pdTitle').textContent = product.name;
  document.getElementById('pdPrice').textContent = `₹${product.price.toLocaleString()}`;
  document.getElementById('pdOriginalPrice').textContent = `₹${product.originalPrice.toLocaleString()}`;
  document.getElementById('pdDiscount').textContent = `${product.discount}% OFF`;
  document.getElementById('pdRating').innerHTML = renderStars(product.rating) + `<span>(${product.reviews} reviews)</span>`;
  document.getElementById('pdDescription').textContent = product.description;

  // Features
  const featuresList = document.getElementById('pdFeatures');
  featuresList.innerHTML = product.features.map(f =>
    `<div class="feature-item"><i class="bi bi-check-circle-fill"></i><span>${f}</span></div>`
  ).join('');

  // Specs
  const specsTable = document.getElementById('pdSpecs');
  specsTable.innerHTML = Object.entries(product.specs).map(([key, val]) =>
    `<tr><td>${key}</td><td>${val}</td></tr>`
  ).join('');

  // Weight selector
  document.querySelectorAll('.weight-option').forEach(opt => {
    opt.addEventListener('click', () => {
      document.querySelectorAll('.weight-option').forEach(o => o.classList.remove('active'));
      opt.classList.add('active');
    });
  });

  // Quantity
  let qty = 1;
  const qtyInput = document.getElementById('pdQty');
  document.getElementById('pdQtyMinus').addEventListener('click', () => {
    qty = Math.max(1, qty - 1);
    qtyInput.value = qty;
  });
  document.getElementById('pdQtyPlus').addEventListener('click', () => {
    qty++;
    qtyInput.value = qty;
  });
  qtyInput.addEventListener('change', () => {
    qty = Math.max(1, parseInt(qtyInput.value) || 1);
    qtyInput.value = qty;
  });

  // Add to cart & Buy now
  document.getElementById('pdAddToCart').addEventListener('click', () => {
    cart.addItem(product.id, qty);
  });
  document.getElementById('pdBuyNow').addEventListener('click', () => {
    cart.addItem(product.id, qty);
    window.location.href = 'cart.html';
  });

  // Related products
  const relatedGrid = document.getElementById('relatedProducts');
  if (relatedGrid) {
    const related = products.filter(p => p.id !== product.id).slice(0, 4);
    relatedGrid.innerHTML = related.map(renderProductCard).join('');
    wishlist.updateIcons();
  }
}

// ===== CART PAGE =====
function initCartPage() {
  const cartWrap = document.getElementById('cartPageWrap');
  if (!cartWrap) return;
  renderCart();
}

function renderCart() {
  const tbody = document.getElementById('cartTableBody');
  const emptyMsg = document.getElementById('cartEmpty');
  const cartContent = document.getElementById('cartContent');
  if (!tbody) return;

  if (cart.items.length === 0) {
    if (cartContent) cartContent.style.display = 'none';
    if (emptyMsg) emptyMsg.style.display = 'block';
    return;
  }
  if (cartContent) cartContent.style.display = 'block';
  if (emptyMsg) emptyMsg.style.display = 'none';

  tbody.innerHTML = cart.items.map(item => `
    <tr>
      <td><img src="${item.image}" class="cart-item-img" alt="${item.name}"></td>
      <td>
        <div class="cart-item-name">${item.name}</div>
        <div class="cart-item-variant">Weight: ${item.weight}</div>
      </td>
      <td>₹${item.price.toLocaleString()}</td>
      <td>
        <div class="qty-control">
          <button onclick="updateCartQty(${item.id}, ${item.qty - 1})">−</button>
          <input type="number" value="${item.qty}" min="1" onchange="updateCartQty(${item.id}, parseInt(this.value))">
          <button onclick="updateCartQty(${item.id}, ${item.qty + 1})">+</button>
        </div>
      </td>
      <td><strong>₹${(item.price * item.qty).toLocaleString()}</strong></td>
      <td><button class="cart-remove-btn" onclick="removeCartItem(${item.id})"><i class="bi bi-trash3"></i></button></td>
    </tr>
  `).join('');

  // Update summary
  const subtotal = cart.getTotal();
  const originalTotal = cart.getOriginalTotal();
  const savings = originalTotal - subtotal;
  const shipping = subtotal > 5000 ? 0 : 499;
  const total = subtotal + shipping;

  document.getElementById('cartSubtotal').textContent = `₹${subtotal.toLocaleString()}`;
  document.getElementById('cartSavings').textContent = `- ₹${savings.toLocaleString()}`;
  document.getElementById('cartShipping').textContent = shipping === 0 ? 'FREE' : `₹${shipping}`;
  document.getElementById('cartTotal').textContent = `₹${total.toLocaleString()}`;
}

function updateCartQty(id, qty) {
  if (qty < 1) { removeCartItem(id); return; }
  cart.updateQty(id, qty);
  renderCart();
}

function removeCartItem(id) {
  cart.removeItem(id);
  renderCart();
  showToast('Item removed from cart');
}

function clearCart() {
  cart.clear();
  renderCart();
  showToast('Cart cleared');
}

// ===== HOMEPAGE FEATURED / BESTSELLERS =====
function initHomepageProducts() {
  // Legacy support
  const featuredGrid = document.getElementById('featuredProducts');
  if (featuredGrid) {
    const featured = products.filter(p => p.category === 'dumbbells').slice(0, 3);
    featuredGrid.innerHTML = featured.map(renderProductCard).join('');
  }
  const bestsellerGrid = document.getElementById('bestsellerProducts');
  if (bestsellerGrid) {
    const bestsellers = [...products].sort((a, b) => b.reviews - a.reviews).slice(0, 4);
    bestsellerGrid.innerHTML = bestsellers.map(renderProductCard).join('');
  }
  wishlist.updateIcons();
}

// ===== PM PRODUCT CARD (PowerMax Style) =====
function renderPMProductCard(product) {
  return `
    <div class="swiper-slide">
      <div class="pm-product-card">
        <div class="pm-product-img-wrap">
          ${product.badge === 'new' ? '<span class="pm-product-badge-new">NEW</span>' : ''}
          ${product.badge === 'sale' ? '<span class="pm-product-badge-new" style="background:#2ECC71">SALE</span>' : ''}
          <a href="/product/${product.slug}">
            <img src="${product.image}" alt="${product.name}" loading="lazy">
          </a>
        </div>
        <div class="pm-product-info">
          <h5>${product.name}</h5>
          <p class="pm-product-desc">${product.description.substring(0, 60)}...</p>
          <div class="pm-product-prices">
            <span class="pm-product-price">Rs ${product.price.toLocaleString()}</span>
            <span class="pm-product-price-old">Rs ${product.originalPrice.toLocaleString()}</span>
          </div>
          <div class="pm-product-actions">
            <button class="pm-action-btn pm-btn-cart" onclick="event.preventDefault(); cart.addItem(${product.id})"><i class="bi bi-cart-plus"></i> Add to Cart</button>
            <button class="pm-action-btn pm-btn-icon" onclick="event.preventDefault(); openQuickView(${product.id})" title="Quick View"><i class="bi bi-eye"></i></button>
            <button class="pm-action-btn pm-btn-icon wishlist-btn" data-product-id="${product.id}" onclick="event.preventDefault(); wishlist.toggle(${product.id});" title="Wishlist"><i class="bi ${wishlist.has(product.id) ? 'bi-heart-fill' : 'bi-heart'}" style="${wishlist.has(product.id) ? 'color: #017075;' : ''}"></i></button>
          </div>
        </div>
      </div>
    </div>`;
}

// ===== POWERMAX HOMEPAGE INIT =====
function initPMHomepage() {
  // Hero Swiper - Full-width image slider with auto-play
  if (document.querySelector('.hero-swiper')) {

    new Swiper('.hero-swiper', {

      loop: true,

      /* =================================
         20% LEFT + 60% CENTER + 20% RIGHT
      ================================= */

      slidesPerView: 'auto',
      centeredSlides: true,
      spaceBetween: 20,

      autoplay: {
        delay: 5000,
        disableOnInteraction: false
      },

      speed: 1000,

      pagination: {
        el: '.swiper-pagination',
        clickable: true
      },

      navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev'
      },

      /* =================================
         RESPONSIVE
      ================================= */

      breakpoints: {

        0: {
          slidesPerView: 'auto',
          centeredSlides: true,
          spaceBetween: 10
        },

        768: {
          slidesPerView: 'auto',
          centeredSlides: true,
          spaceBetween: 50
        }
      }

    });

  }

  // Product Category Section
  const catCards = document.getElementById('productCategoryCards');
  if (catCards) {
    renderPMSection(catCards, 'all', '.product-category-swiper');
    initTabSwitching('productCategoryTabs', catCards, '.product-category-swiper');
  }

  // Top Selling Section
  const topCards = document.getElementById('topSellingCards');
  if (topCards) {
    renderPMSection(topCards, 'popular', '.top-selling-swiper');
    initTabSwitching('topSellingTabs', topCards, '.top-selling-swiper');
  }

  // Counter animation
  initCounters();
}

function renderPMSection(container, tab, swiperSelector) {
  let filtered;
  switch (tab) {
    case 'dumbbells': filtered = products.filter(p => p.category === 'dumbbells'); break;
    case 'benches': filtered = products.filter(p => p.category === 'benches'); break;
    case 'combos':
    case 'bottle':
      filtered = products.filter(p => p.category === 'bottle' || p.category === 'combos');
      break;
    case 'popular': filtered = [...products].sort((a, b) => b.reviews - a.reviews); break;
    case 'new': filtered = products.filter(p => p.badge === 'new'); break;
    case 'hot': filtered = [...products].sort((a, b) => b.rating - a.rating); break;
    case 'offer': filtered = [...products].sort((a, b) => b.discount - a.discount); break;
    default: filtered = [...products];
  }
  container.innerHTML = filtered.map(renderPMProductCard).join('');
  // Destroy existing swiper and re-init
  const swiperEl = document.querySelector(swiperSelector);
  if (swiperEl && swiperEl.swiper) swiperEl.swiper.destroy(true, true);
  new Swiper(swiperSelector, {
    slidesPerView: 4,
    spaceBetween: 20,
    loop: filtered.length > 4,
    navigation: { nextEl: swiperSelector + ' .pm-swiper-next', prevEl: swiperSelector + ' .pm-swiper-prev' },
    breakpoints: { 0: { slidesPerView: 1 }, 576: { slidesPerView: 2 }, 768: { slidesPerView: 3 }, 992: { slidesPerView: 4 } }
  });
}

function initTabSwitching(tabContainerId, cardsContainer, swiperSelector) {
  const tabContainer = document.getElementById(tabContainerId);
  if (!tabContainer) return;
  tabContainer.querySelectorAll('.pm-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      tabContainer.querySelectorAll('.pm-tab-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      renderPMSection(cardsContainer, btn.dataset.tab, swiperSelector);
    });
  });
}

function initCounters() {
  const counters = document.querySelectorAll('.pm-trust-number');
  if (!counters.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {

        const el = entry.target;
        const target = parseFloat(el.dataset.count);
        if (isNaN(target)) return;

        const duration = 2000;
        const isDecimal = el.hasAttribute('data-is-decimal');

        const defaultSuffix = isDecimal ? '' : (target === 5 ? '' : '+');
        const suffix = el.hasAttribute('data-suffix') ? el.dataset.suffix : defaultSuffix;

        let start = null;

        function updateCounter(timestamp) {
          if (!start) start = timestamp;

          const progress = Math.min((timestamp - start) / duration, 1);
          const easeOut = progress * (2 - progress);

          if (isDecimal) {
            el.textContent = (easeOut * target).toFixed(1) + suffix;
          } else {
            el.textContent = Math.floor(easeOut * target).toLocaleString() + suffix;
          }

          if (progress < 1) {
            requestAnimationFrame(updateCounter);
          } else {
            // Final exact value
            el.textContent = isDecimal
              ? target.toFixed(1) + suffix
              : Math.floor(target).toLocaleString() + suffix;
          }
        }

        requestAnimationFrame(updateCounter);
        observer.unobserve(el);
      }
    });
  }, { threshold: 0.5 });

  counters.forEach(c => observer.observe(c));
}


// ===== OFFER POPUP =====
function initOfferPopup() {
  const popup = document.getElementById('offerPopup');
  if (!popup) return;
  const dismissed = sessionStorage.getItem('gehna_popup_dismissed');
  if (dismissed) return;

  const close = () => {
    popup.classList.remove('show');
    sessionStorage.setItem('gehna_popup_dismissed', '1');
  };

  setTimeout(() => { popup.classList.add('show'); }, 5000);

  popup.querySelector('.offer-popup-close').addEventListener('click', close);

  popup.addEventListener('click', (e) => {
    if (e.target === popup) close();
  });

  const form = popup.querySelector('#offerPopupForm');
  if (form) {
    const input = form.querySelector('input[type="email"]');
    const errorEl = form.querySelector('.offer-popup-error');
    const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const email = input.value.trim();
      if (!isValidEmail(email)) {
        input.classList.add('is-invalid');
        if (errorEl) errorEl.hidden = false;
        input.focus();
        return;
      }
      input.classList.remove('is-invalid');
      if (errorEl) errorEl.hidden = true;
      showToast('Coupon code GEHNA10 sent to your email! 🎉');
      form.reset();
      close();
    });

    input.addEventListener('input', () => {
      input.classList.remove('is-invalid');
      if (errorEl) errorEl.hidden = true;
    });
  }
}

// ===== NEWSLETTER =====
function initNewsletter() {
  const form = document.getElementById('newsletterForm');
  if (!form) return;
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const email = form.querySelector('input[type="email"]').value;
    if (email) { showToast('Thank you for subscribing! 🎉'); form.reset(); }
  });
}

function initFeaturesAutoScroll() {
  const wrapper = document.querySelector('.features-scroll-wrapper');
  if (!wrapper) return;

  const isMobile = () => window.innerWidth <= 767;
  let intervalId = null;
  let currentIndex = 0;
  let userInteracted = false;
  let userTimeout = null;

  function getFeatureItems() {
    return wrapper.querySelectorAll('.feature-item');
  }

  function scrollToIndex(index) {
    const items = getFeatureItems();
    if (!items.length) return;
    currentIndex = index % items.length;
    const target = items[currentIndex];
    const scrollLeft = target.offsetLeft - (wrapper.clientWidth - target.offsetWidth) / 2;
    wrapper.scrollTo({ left: Math.max(0, scrollLeft), behavior: 'smooth' });
  }

  function startAutoScroll() {
    if (!isMobile() || intervalId !== null) return;
    currentIndex = 0;
    scrollToIndex(0);
    intervalId = setInterval(() => {
      if (!isMobile()) { stopAutoScroll(); return; }
      if (userInteracted) return;
      currentIndex++;
      const items = getFeatureItems();
      if (currentIndex >= items.length) currentIndex = 0;
      scrollToIndex(currentIndex);
    }, 2500);
  }

  function stopAutoScroll() {
    if (intervalId !== null) {
      clearInterval(intervalId);
      intervalId = null;
    }
  }

  // Pause auto-scroll on touch, resume after 4 seconds
  wrapper.addEventListener('touchstart', () => {
    userInteracted = true;
    clearTimeout(userTimeout);
  }, { passive: true });

  wrapper.addEventListener('touchend', () => {
    clearTimeout(userTimeout);
    userTimeout = setTimeout(() => {
      userInteracted = false;
      // Detect which item is currently visible
      const items = getFeatureItems();
      const wrapperCenter = wrapper.scrollLeft + wrapper.clientWidth / 2;
      let closestIdx = 0;
      let closestDist = Infinity;
      items.forEach((item, i) => {
        const itemCenter = item.offsetLeft + item.offsetWidth / 2;
        const dist = Math.abs(itemCenter - wrapperCenter);
        if (dist < closestDist) { closestDist = dist; closestIdx = i; }
      });
      currentIndex = closestIdx;
    }, 4000);
  }, { passive: true });

  window.addEventListener('resize', () => {
    if (!isMobile()) {
      stopAutoScroll();
      wrapper.scrollLeft = 0;
    } else if (intervalId === null) {
      startAutoScroll();
    }
  });

  startAutoScroll();
}

// ===== SITE SEARCH (live autocomplete) =====
function escapeHtmlSearch(text) {
  return String(text ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function initSiteSearch() {
  const box = document.getElementById('siteSearchBox');
  const input = document.getElementById('siteSearchInput');
  const results = document.getElementById('siteSearchResults');
  const form = document.getElementById('siteSearchForm');
  if (!box || !input || !results || !form) {
    return;
  }

  const searchUrl = '/search';
  let debounceTimer = null;
  let currentResults = [];
  let activeIndex = -1;

  function thumbHtml(src, alt) {
    if (src) {
      return '<img class="site-search-thumb" src="/storage/' + escapeHtmlSearch(src) + '" alt="' + escapeHtmlSearch(alt) + '" loading="lazy">';
    }
    return '<span class="site-search-thumb site-search-thumb--none"><i class="bi bi-image"></i></span>';
  }

  function priceHtml(n) {
    const v = parseFloat(n);
    if (!v || v <= 0) return '';
    return '<span class="site-search-price">Rs ' + v.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</span>';
  }

  function closeResults() {
    results.classList.remove('site-search-open');
    currentResults = [];
    activeIndex = -1;
  }

  function openResults() {
    results.classList.add('site-search-open');
  }

  function renderResults(data) {
    const products = data.products || [];
    const categories = data.categories || [];
    const q = data.query || '';
    const recent = data.recent || [];
    let html = '';
    currentResults = [];
    activeIndex = -1;

    if (products.length) {
      html += '<p class="site-search-group-title">Products</p>';
      products.forEach((p) => {
        const img = thumbHtml(p.image, p.name);
        const sub = p.category ? 'Category: ' + escapeHtmlSearch(p.category) : escapeHtmlSearch(p.name);
        currentResults.push({ type: 'product', slug: p.slug });
        html += '<a class="site-search-result" href="/product/' + escapeHtmlSearch(p.slug) + '" data-type="product">'
          + '<span class="site-search-type site-search-type--product">Product</span>'
          + img
          + '<span class="site-search-text"><strong>' + escapeHtmlSearch(p.name) + '</strong><small>' + sub + '</small></span>'
          + priceHtml(p.price)
          + '</a>';
      });
    }

    if (categories.length) {
      html += '<p class="site-search-group-title">Categories</p>';
      categories.forEach((c) => {
        const countLabel = c.product_count + ' product' + (c.product_count === 1 ? '' : 's');
        currentResults.push({ type: 'category', slug: c.slug });
        html += '<a class="site-search-result" href="/category/' + escapeHtmlSearch(c.slug) + '" data-type="category">'
          + '<span class="site-search-type site-search-type--category">Category</span>'
          + thumbHtml(c.image, c.name)
          + '<span class="site-search-text"><strong>' + escapeHtmlSearch(c.name) + '</strong><small>' + countLabel + '</small></span>'
          + '</a>';
      });
    }

    if (products.length || categories.length) {
      html += '<a class="site-search-footer" href="/products?search=' + encodeURIComponent(q)
        + '">View all results for \u201c' + escapeHtmlSearch(q) + '\u201d <i class="bi bi-arrow-right"></i></a>';
    }

    // Always show recent searches as a separate section at the bottom
    if (recent.length) {
      html += '<p class="site-search-group-title">Recent Searches</p>';
      recent.forEach((term) => {
        const encoded = encodeURIComponent(term);
        currentResults.push({ type: 'recent', slug: encoded, label: term });
        html += '<a class="site-search-result site-search-result--recent" href="/products?search=' + encoded + '" data-type="recent">'
          + '<span class="site-search-type site-search-type--recent">Recent</span>'
          + '<span class="site-search-text"><strong class="site-search-recent-term">' + escapeHtmlSearch(term) + '</strong></span>'
          + '</a>';
      });
    }

    // If nothing at all, show a friendly empty state
    if (!products.length && !categories.length && !recent.length) {
      html = '<div class="site-search-empty">No results for \u201c' + escapeHtmlSearch(q) + '\u201d. Try different keywords.</div>';
    }

    results.innerHTML = html;
  }

  async function fetchResults(q) {
    try {
      const res = await fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
      if (!res.ok) {
        closeResults();
        return;
      }
      const data = await res.json();
      renderResults(data);
    } catch (e) {
      closeResults();
    }
  }

  input.addEventListener('input', () => {
    const q = input.value.trim();
    clearTimeout(debounceTimer);
    if (q.length < 2) { closeResults(); return; }
    results.innerHTML = '<div class="site-search-loading"><span class="spinner-border spinner-border-sm me-2"></span>Searching…</div>';
    openResults();
    debounceTimer = setTimeout(() => fetchResults(q), 300);
  });

  // Prevent the form from submitting on Enter so the AJAX results can be used.
  // If results are available, Enter navigates to the highlighted item or the
  // first result; otherwise it falls through to the full /search page.
  form.addEventListener('submit', (e) => {
    const q = input.value.trim();
    if (q.length >= 2 && currentResults.length > 0) {
      e.preventDefault();
      // If there's an active selection, navigate to it; otherwise go to the first result
      const idx = activeIndex > -1 ? activeIndex : 0;
      const item = currentResults[idx];
      if (item) {
        if (item.type === 'product') {
          window.location.href = '/product/' + item.slug;
        } else if (item.type === 'category') {
          window.location.href = '/category/' + item.slug;
        } else {
          window.location.href = item.slug;
        }
      }
    }
  });

  input.addEventListener('focus', () => {
    if (input.value.trim().length >= 2 && currentResults.length) openResults();
  });

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { closeResults(); return; }

    const items = Array.from(results.querySelectorAll('.site-search-result'));

    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      if (!items.length) return;
      e.preventDefault();
      const dir = e.key === 'ArrowDown' ? 1 : -1;
      activeIndex = (activeIndex + dir + items.length) % items.length;
      items.forEach((el, i) => {
        el.classList.toggle('site-search-active', i === activeIndex);
      });
      const active = items[activeIndex];
      if (active) active.scrollIntoView({ block: 'nearest' });
      return;
    }

    if (e.key === 'Enter' && items.length) {
      e.preventDefault();
      // If there's an active selection, navigate to it; otherwise go to the first result
      const idx = activeIndex > -1 ? activeIndex : 0;
      window.location.href = items[idx].href;
    }
  });

  document.addEventListener('click', (e) => {
    if (!box.contains(e.target)) closeResults();
  });
}

// ===== SHOP BY CATEGORY DROPDOWN =====
function initSiteCategoryDropdown() {
  const dropdown = document.getElementById('siteCatDropdown');
  const trigger = document.getElementById('siteCatTrigger');
  const menu = document.getElementById('siteCatMenu');
  if (!dropdown || !trigger || !menu) return;

  const HOVER_DELAY = 250;
  let closeTimer = null;

  function open() {
    clearTimeout(closeTimer);
    dropdown.classList.add('site-cat-open');
    menu.classList.add('site-cat-open');
    trigger.setAttribute('aria-expanded', 'true');
  }

  function close() {
    dropdown.classList.remove('site-cat-open');
    menu.classList.remove('site-cat-open');
    trigger.setAttribute('aria-expanded', 'false');
  }

  function scheduleClose() {
    clearTimeout(closeTimer);
    closeTimer = setTimeout(close, HOVER_DELAY);
  }

  trigger.addEventListener('click', (e) => {
    e.preventDefault();
    if (dropdown.classList.contains('site-cat-open')) {
      close();
    } else {
      open();
    }
  });

  // Keep the menu open while the cursor is over the trigger OR the menu.
  // Closing only happens after a short grace period once the cursor truly
  // leaves, so it no longer snaps shut the instant you move away.
  dropdown.addEventListener('mouseenter', open);
  dropdown.addEventListener('mouseleave', scheduleClose);

  document.addEventListener('click', (e) => {
    if (!dropdown.contains(e.target)) {
      clearTimeout(closeTimer);
      close();
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      clearTimeout(closeTimer);
      close();
    }
  });
}

// ===== DELIVERY LOCATION (PINCODE) DROPDOWN =====
function initSiteLocation() {
  const wrap = document.getElementById('siteLocationWrap');
  const btn = document.getElementById('siteLocationBtn');
  const menu = document.getElementById('siteLocationMenu');
  if (!wrap || !btn || !menu) return;

  const titleEl = document.getElementById('siteLocationTitle');
  const subtitleEl = document.getElementById('siteLocationSubtitle');
  const choicesEl = document.getElementById('siteLocationChoices');
  const manualEl = document.getElementById('siteLocationManual');
  const manualForm = document.getElementById('siteLocationManualForm');
  const manualInput = document.getElementById('siteLocationPincodeInput');
  const manualErr = document.getElementById('siteLocationPincodeError');
  const manualSub = document.getElementById('siteLocationManualSubmit');
  const statusEl = document.getElementById('siteLocationStatus');
  const statusIcon = document.getElementById('siteLocationStatusIcon');
  const statusMsg = document.getElementById('siteLocationStatusMsg');
  const statusActs = document.getElementById('siteLocationStatusActions');
  const currentEl = document.getElementById('siteLocationCurrent');
  const currentText = document.getElementById('siteLocationCurrentText');
  const removeBtn = document.getElementById('siteLocationRemove');

  const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  const DEFAULT_TITLE = 'Where to Deliver?';
  const DEFAULT_SUBTITLE = 'Update Delivery Pincode';

  let pendingLocation = null;

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function postJson(url, payload) {
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
      },
      body: JSON.stringify(payload),
    }).then(async (res) => {
      try { return await res.json(); }
      catch (e) { return { success: false, message: 'Server error. Please try again.' }; }
    });
  }

  function areaOf(data) {
    return [data.city, data.state].filter(Boolean).join(', ');
  }

  function applyLocation(data) {
    const hasPincode = Boolean(data && data.pincode);

    titleEl.textContent = hasPincode
      ? (data.title || ('Deliver to ' + data.pincode))
      : (data.title || DEFAULT_TITLE);
    subtitleEl.textContent = hasPincode
      ? (data.subtitle || DEFAULT_SUBTITLE)
      : DEFAULT_SUBTITLE;

    if (currentEl && currentText) {
      if (hasPincode) {
        const area = areaOf(data);
        currentText.innerHTML = 'Currently delivering to <strong>' + escapeHtml(data.pincode) + '</strong>'
          + (area ? ' &ndash; ' + escapeHtml(area) : '');
        currentEl.classList.remove('d-none');
      } else {
        currentEl.classList.add('d-none');
      }
    }
  }

  function openMenu() {
    menu.classList.add('site-location-open');
    menu.setAttribute('aria-hidden', 'false');
    btn.setAttribute('aria-expanded', 'true');
    showChoices();
  }

  function closeMenu() {
    menu.classList.remove('site-location-open');
    menu.setAttribute('aria-hidden', 'true');
    btn.setAttribute('aria-expanded', 'false');
  }

  function showChoices() {
    manualEl.classList.add('d-none');
    statusEl.classList.add('d-none');
    choicesEl.classList.remove('d-none');
  }

  function showManual() {
    choicesEl.classList.add('d-none');
    statusEl.classList.add('d-none');
    manualEl.classList.remove('d-none');
    manualInput.focus();
  }

  const STATUS_ICONS = {
    loading: '<i class="bi bi-arrow-repeat"></i>',
    success: '<i class="bi bi-check-circle-fill"></i>',
    error: '<i class="bi bi-exclamation-triangle-fill"></i>',
    info: '<i class="bi bi-geo-alt"></i>',
  };

  function showStatus(mode, message, actionsHtml) {
    choicesEl.classList.add('d-none');
    manualEl.classList.add('d-none');
    statusEl.classList.remove('d-none');
    statusIcon.className = 'site-location-status-icon' + (mode ? ' is-' + mode : '');
    statusIcon.innerHTML = STATUS_ICONS[mode] || STATUS_ICONS.info;
    statusMsg.textContent = message;
    statusActs.innerHTML = actionsHtml || '';
  }

  function saveLocation(payload) {
    return postJson('/location/pincode', payload).then((data) => {
      if (!data.success) throw new Error(data.message || 'Could not update your delivery pincode.');
      applyLocation(data);
      showToast(data.message || 'Delivery pincode updated!');
      setTimeout(closeMenu, 900);
      return data;
    });
  }

  function manualActionsHtml() {
    return '<button type="button" class="site-location-secondary-btn" data-action="manual">'
      + '<i class="bi bi-pencil-square me-1"></i>Enter pincode manually</button>'
      + '<button type="button" class="site-location-secondary-btn" data-action="retry">'
      + '<i class="bi bi-arrow-clockwise me-1"></i>Try again</button>';
  }

  function detectLocation() {
    if (!navigator.geolocation) {
      showStatus('error', 'Geolocation is not supported by your browser. Please enter your pincode manually.', manualActionsHtml());
      return;
    }

    showStatus('loading', 'Detecting your location using GPS...', '');

    navigator.geolocation.getCurrentPosition((position) => {
      postJson('/location/detect', {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
      }).then((data) => {
        if (data.success && data.pincode) {
          pendingLocation = data;
          const area = areaOf(data);
          showStatus('success',
            'We detected your pincode as ' + data.pincode + (area ? ' (' + area + ')' : '') + '. Use this location?',
            '<button type="button" class="site-location-confirm-btn" data-action="confirm">Yes, deliver here</button>'
            + '<button type="button" class="site-location-secondary-btn" data-action="manual">No, enter manually</button>');
        } else {
          showStatus('error', data.message || 'We could not detect a pincode for your location.', manualActionsHtml());
        }
      }).catch(() => showStatus('error', 'Location lookup failed. Please check your connection or enter the pincode manually.', manualActionsHtml()));
    }, (error) => {
      if (error && error.code === error.PERMISSION_DENIED) {
        showStatus('error', 'Location permission was denied. Please allow location access or enter your pincode manually.', manualActionsHtml());
      } else if (error && error.code === error.TIMEOUT) {
        showStatus('error', 'It took too long to detect your location. Please try again or enter it manually.', manualActionsHtml());
      } else {
        showStatus('error', 'We could not detect your location. Please enter your pincode manually.', manualActionsHtml());
      }
    }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 });
  }

  btn.addEventListener('click', (e) => {
    e.preventDefault();
    if (menu.classList.contains('site-location-open')) closeMenu();
    else openMenu();
  });

  choicesEl.addEventListener('click', (e) => {
    const choice = e.target.closest('[data-location-choice]');
    if (!choice) return;
    if (choice.dataset.locationChoice === 'auto') detectLocation();
    else showManual();
  });

  menu.querySelectorAll('[data-location-back]').forEach((el) => {
    el.addEventListener('click', showChoices);
  });

  statusActs.addEventListener('click', (e) => {
    const actionBtn = e.target.closest('[data-action]');
    if (!actionBtn) return;
    const action = actionBtn.dataset.action;

    if (action === 'confirm' && pendingLocation) {
      actionBtn.disabled = true;
      saveLocation({
        pincode: pendingLocation.pincode,
        city: pendingLocation.city || '',
        state: pendingLocation.state || '',
        source: 'auto',
      }).catch(() => {
        actionBtn.disabled = false;
        showToast('Could not save your pincode. Please try again.');
      });
    } else if (action === 'manual') {
      showManual();
    } else if (action === 'retry') {
      detectLocation();
    }
  });

  manualInput.addEventListener('input', () => {
    manualInput.value = manualInput.value.replace(/\D/g, '').slice(0, 6);
    manualErr.classList.add('d-none');
  });

  manualForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const pincode = manualInput.value.trim();

    if (!/^[1-9][0-9]{5}$/.test(pincode)) {
      manualErr.textContent = 'Please enter a valid 6-digit pincode.';
      manualErr.classList.remove('d-none');
      manualInput.focus();
      return;
    }

    manualErr.classList.add('d-none');
    manualSub.disabled = true;

    saveLocation({ pincode: pincode, source: 'manual' })
      .catch((err) => {
        manualErr.textContent = (err && err.message) ? err.message : 'Could not save your pincode. Please try again.';
        manualErr.classList.remove('d-none');
      })
      .finally(() => { manualSub.disabled = false; });
  });

  if (removeBtn) {
    removeBtn.addEventListener('click', () => {
      removeBtn.disabled = true;
      postJson('/location/clear', {}).then((data) => {
        removeBtn.disabled = false;
        if (!data.success) { showToast(data.message || 'Could not remove the pincode.'); return; }
        applyLocation({ pincode: null });
        showToast(data.message || 'Delivery pincode removed.');
        closeMenu();
      }).catch(() => {
        removeBtn.disabled = false;
        showToast('Something went wrong. Please try again.');
      });
    });
  }

  document.addEventListener('click', (e) => {
    if (!wrap.contains(e.target)) closeMenu();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeMenu();
  });
}

// ===== INIT =====
document.addEventListener('DOMContentLoaded', () => {
  initNavbar();
  initSiteCategoryDropdown();
  initSiteSearch();
  initSiteLocation();
  initBackToTop();
  initFloatingOffers();
  initLiveRates();
  initScrollAnimations();
  initHomepageProducts();
  initPMHomepage();
  initCounters();
  initProductListing();
  initProductDetail();
  initCartPage();
  initOfferPopup();
  initFeaturesAutoScroll();
  initNewsletter();
  cart.updateCartBadge();
  wishlist.updateIcons();
});
