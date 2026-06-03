/* =============================================
   A2IU Store — Main JavaScript (Fixed)
   ============================================= */

const API = 'a2iu.php';
let currentDetailProductId = null;
let currentFilter = 'all';
let sliderIndex = 0;
let sliderTimer = null;

// =============================================
// INIT
// =============================================

document.addEventListener('DOMContentLoaded', () => {
  checkSession();
  loadFeaturedBanners();
  loadHomeSections();
  loadHeroBanners().then(() => startSlider()); // ← ubah ini
  startCountdown();
  initScrollHeader();
  initClickOutside();
  // ...
});

// =============================================
// SESSION
// =============================================

async function checkSession() {
  const res = await apiFetch(`${API}?action=get_session`);
  updateCartCount(res.cart_count || 0);
  if (res.user) setLoggedInState(res.user);
  else setLoggedOutState();
}

function setLoggedInState(user) {
  document.getElementById('guestMenu').style.display = 'none';
  document.getElementById('loggedMenu').style.display = 'block';
  document.getElementById('loggedName').textContent = user.name;
  if (user.role === 'admin') document.getElementById('adminLink').style.display = 'flex';
}

function setLoggedOutState() {
  document.getElementById('guestMenu').style.display = 'block';
  document.getElementById('loggedMenu').style.display = 'none';
  document.getElementById('adminLink').style.display = 'none';
}

// =============================================
// NAVIGATION
// =============================================

function navigateTo(page) {
  // Admin: sembunyikan semua page user, tampilkan admin langsung
  const isAdmin = document.getElementById('adminLink')?.style.display !== 'none';
  if (page === 'admin' && !isAdmin) { showToast('error','fa-lock','Akses ditolak'); return; }

  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  const el = document.getElementById(`page-${page}`);
  if (el) el.classList.add('active');
  // Untuk page products tanpa filter spesifik, aktifkan semua nav-link products
  // tapi hanya jika bukan dari filterCategory (yang handle sendiri)
  if (page !== 'products') {
    document.querySelectorAll('.nav-link').forEach(l => {
      l.classList.toggle('active', l.dataset.page === page);
    });
  }
  window.scrollTo({ top: 0, behavior: 'smooth' });
  closeAllMenus();
  if (page === 'products') { currentFilter = 'all'; loadAllProducts(); }
  if (page === 'admin') loadAdmin();
  if (page === 'orders') loadOrders();
}

function filterCategory(cat) {
  currentFilter = cat;

  // Aktifkan page products
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  const pageEl = document.getElementById('page-products');
  if (pageEl) pageEl.classList.add('active');
  window.scrollTo({ top: 0, behavior: 'smooth' });
  closeAllMenus();
  loadAllProducts();

  // Tandai nav-link: hanya link yang memanggilnya dengan cat ini, bukan semua data-page="products"
  document.querySelectorAll('.nav-link').forEach(l => {
    const oc = l.getAttribute('onclick') || '';
    const isThisCat = oc.includes(`filterCategory('${cat}')`);
    l.classList.toggle('active', isThisCat);
  });

  // Sidebar filter
  document.querySelectorAll('#categoryFilter li').forEach(li => {
    li.classList.remove('active');
    const oc = li.getAttribute('onclick') || '';
    if (oc.includes(`'${cat}'`)) li.classList.add('active');
  });
}

function setFilter(cat, el) {
  currentFilter = cat;
  document.querySelectorAll('#categoryFilter li').forEach(l => l.classList.remove('active'));
  el.classList.add('active');
  loadAllProducts();
}

// =============================================
// FEATURED BANNERS — 4 banner 16:9 per kategori
// =============================================

const FEAT_CATS = [
  { cat: 'smartphone', label: 'Smartphone', grad: 'linear-gradient(135deg,#1a1a2e,#0f3460)' },
  { cat: 'tablet',     label: 'Tablet',     grad: 'linear-gradient(135deg,#11998e,#38ef7d)' },
  { cat: 'laptop',     label: 'Laptop',     grad: 'linear-gradient(135deg,#232526,#414345)' },
  { cat: 'smartwatch', label: 'Smartwatch', grad: 'linear-gradient(135deg,#f7971e,#ffd200)' },
];

async function loadFeaturedBanners() {
  const el = document.getElementById('featuredBanners');
  if (!el) return;
  const featured = await apiFetch(`api_user.php?action=get_featured`);
  const html = FEAT_CATS.map(fc => {
    const top = featured[fc.cat];
    const img = top ? top.image : '';
    const name = top ? top.name : fc.label;
    const id = top ? top.id : 0;
    let priceHtml = '';
    if (top) {
      const disc = parseFloat(top.discount_percent) || 0;
      const orig = parseFloat(top.price);
      const final = disc > 0 ? orig * (1 - disc / 100) : orig;
      priceHtml = disc > 0
        ? `<div class="feat-banner-price">${formatRupiah(final)} <span style="font-size:11px;text-decoration:line-through;opacity:0.6">${formatRupiah(orig)}</span> <span style="background:#e53935;color:#fff;font-size:10px;padding:1px 5px;border-radius:3px">-${Math.round(disc)}%</span></div>`
        : `<div class="feat-banner-price">${formatRupiah(orig)}</div>`;
    }
    return `
      <div class="feat-banner" style="background:${fc.grad}" onclick="${id ? `openProductPage(${id})` : `filterCategory('${fc.cat}')`}">
        <div class="feat-banner-img">
          ${img ? `<img src="${img}" alt="${escHtml(name)}" onerror="this.style.display='none'">` : ''}
        </div>
        <div class="feat-banner-info">
          <span class="feat-banner-cat">${fc.label}</span>
          <div class="feat-banner-name">${escHtml(name)}</div>
          ${priceHtml}
          <button onclick="event.stopPropagation();filterCategory('${fc.cat}')">Lihat Semua</button>
        </div>
      </div>`;
  }).join('');
  el.innerHTML = html;
}

// =============================================
// HOME SECTIONS — Promo / Terlaris / Trending / Terbaru
// =============================================

const HOME_SECTION_META = {
  promo:    { icon: '🔥', color: '#e53935', bg: '#fff5f5' },
  terlaris: { icon: '⭐', color: '#f59e0b', bg: '#fffbeb' },
  trending: { icon: '📈', color: '#8b5cf6', bg: '#f5f3ff' },
  terbaru:  { icon: '🆕', color: '#0ea5e9', bg: '#f0f9ff' },
};

async function loadHomeSections() {
  const wrap = document.getElementById('homeSectionsWrap');
  if (!wrap) return;
  wrap.innerHTML = '';
  const sections = await apiFetch('api_user.php?action=get_home_sections');
  if (!Array.isArray(sections) || !sections.length) return;

  sections.forEach(sec => {
    if (!sec.products || !sec.products.length) return;
    const meta = HOME_SECTION_META[sec.key] || { icon: '📦', color: '#1a1a1a', bg: '#f8f8f8' };
    const cards = sec.products.map(p => homeSectionProductCard(p)).join('');
    const html = `
    <section class="home-product-section" id="section-${sec.key}">
      <div class="container">
        <div class="section-header">
          <h2>
            ${escHtml(sec.title)}
          </h2>
          <a href="#" onclick="filterCategory('all'); return false;" class="see-all">Lihat Semua <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="hs-scroll-wrap">
          <div class="hs-product-row">${cards}</div>
        </div>
      </div>
    </section>`;
    wrap.insertAdjacentHTML('beforeend', html);
  });
}

function homeSectionProductCard(p) {
  const disc = parseFloat(p.discount_percent) || 0;
  const orig = parseFloat(p.price);
  const final = disc > 0 ? orig * (1 - disc / 100) : orig;
  const discBadge = disc > 0
    ? `<span class="hs-disc-badge">-${Math.round(disc)}%</span>`
    : '';
  const origPrice = disc > 0
    ? `<span class="hs-orig-price">${formatRupiah(orig)}</span>`
    : '';
  const badgeHtml = p.badge
    ? `<span class="hs-badge">${escHtml(p.badge)}</span>`
    : '';
  return `
  <div class="hs-card" onclick="openProductPage(${p.id})">
    <div class="hs-card-img-wrap">
      ${discBadge}
      ${badgeHtml}
      <img src="${p.image || 'https://via.placeholder.com/200'}" alt="${escHtml(p.name)}" onerror="this.src='https://via.placeholder.com/200'">
    </div>
    <div class="hs-card-body">
      <div class="hs-card-name">${escHtml(p.name)}</div>
      <div class="hs-card-price">
        <span class="hs-final-price">${formatRupiah(final)}</span>
        ${origPrice}
      </div>
      <button class="hs-card-btn" onclick="event.stopPropagation();openProductPage(${p.id})">Lihat Detail</button>
    </div>
  </div>`;
}

// =============================================
// PRODUCTS
// =============================================

async function loadAllProducts() {
  const el = document.getElementById('allProducts');
  el.innerHTML = '<div class="product-skeleton"></div>'.repeat(6);
  const products = await apiFetch(`${API}?action=get_products&category=${currentFilter}`);
  document.getElementById('productCount').textContent = `${products.length} produk ditemukan`;
  if (!products.length) { el.innerHTML = '<p class="empty-msg">Produk tidak ditemukan.</p>'; return; }

  // Deteksi seri smartphone dari nama (M, X, F, dll.)
  function getSmartphoneSeries(name) {
    const match = name.match(/\bA2IU\s+([A-Z])\d/i);
    return match ? match[1].toUpperCase() : 'Lainnya';
  }

  // Deteksi seri laptop berdasarkan harga
  function getLaptopSeries(price) {
    return price >= 45000000 ? 'Gaming Laptop' : 'Laptop';
  }

  // Kelompokkan produk berdasarkan kategori
  const groups = {};
  products.forEach(p => {
    let s;
    if (p.category === 'laptop') {
      s = getLaptopSeries(parseFloat(p.price));
    } else if (p.category === 'tablet') {
      s = 'Tablet';
    } else if (p.category === 'smartwatch') {
      s = 'Smartwatch';
    } else {
      // smartphone
      s = getSmartphoneSeries(p.name);
    }
    if (!groups[s]) groups[s] = [];
    groups[s].push(p);
  });

  // Urutan: Gaming Laptop → Laptop → Seri M/X/F → Tablet → Smartwatch → Lainnya
  const SMARTPHONE_ORDER = ['M', 'X', 'F'];
  const LAPTOP_ORDER = ['Gaming Laptop', 'Laptop'];
  const seriesOrder = [
    ...LAPTOP_ORDER.filter(s => groups[s]),
    ...SMARTPHONE_ORDER.filter(s => groups[s]),
    ...Object.keys(groups).filter(s =>
      !SMARTPHONE_ORDER.includes(s) &&
      !LAPTOP_ORDER.includes(s) &&
      s !== 'Lainnya' &&
      s !== 'Tablet' &&
      s !== 'Smartwatch'
    ).sort(),
    ...(groups['Tablet'] ? ['Tablet'] : []),
    ...(groups['Smartwatch'] ? ['Smartwatch'] : []),
    ...(groups['Lainnya'] ? ['Lainnya'] : []),
  ];

  // Render dengan divider
  const useGrouping = seriesOrder.length > 1;
  let html = '';
  seriesOrder.forEach(s => {
    if (useGrouping) {
      const label = s.length === 1 ? `Seri ${s}` : s;
      html += `<div class="series-divider">
        <div class="series-divider-label">${label}</div>
      </div>`;
    }
    html += groups[s].map(p => productCard(p)).join('');
  });
  el.innerHTML = html;
}


function productCard(p) {
  const disc = parseFloat(p.discount_percent) || 0;
  const origPrice = parseFloat(p.price);
  const finalPrice = disc > 0 ? origPrice * (1 - disc / 100) : origPrice;
  const priceHtml = disc > 0
    ? `<div class="product-price-wrap">
         <span class="product-price-final">${formatRupiah(finalPrice)}</span>
         <span class="product-price-original">${formatRupiah(origPrice)}</span>
       </div>`
    : `<div class="product-price">${formatRupiah(origPrice)}</div>`;
  const badge = p.badge ? `<span class="product-badge badge-${p.badge.toLowerCase()}">${p.badge}</span>` : '';
  const discBadge = disc > 0 ? `<span class="product-discount-badge">-${Math.round(disc)}%</span>` : '';
  const img = p.image || 'https://via.placeholder.com/400x400?text=No+Image';
  const catLabel = { smartphone:'Smartphone', tablet:'Tablet', laptop:'Laptop', smartwatch:'Smartwatch' }[p.category] || p.category;
  return `
    <div class="product-card" onclick="openProductPage(${p.id})">
      <div class="product-img">
        ${badge}
        ${discBadge}
        <img src="${img}" alt="${escHtml(p.name)}" loading="lazy" onerror="this.src='https://via.placeholder.com/400x400?text=No+Image'">
      </div>
      <div class="product-info">
        <div class="product-cat">${catLabel}</div>
        <div class="product-name">${escHtml(p.name)}</div>
        ${priceHtml}
      </div>
      <div class="product-add-btn" onclick="event.stopPropagation(); quickAdd(${p.id})">
        <i class="fas fa-cart-plus"></i> Tambah Keranjang
      </div>
    </div>`;
}

// =============================================
// PRODUCT PAGE (fullscreen overlay, mirip Xiaomi)
// =============================================

const SPECS_DEFAULT = {
  smartphone: [['Layar','AMOLED 120Hz'],['OS','Android 14'],['Kamera','108MP'],['Baterai','5000mAh'],['Fast Charging','67W']],
  tablet:     [['Layar','IPS 90Hz'],['OS','Android 14'],['Kamera','13MP'],['Baterai','8000mAh']],
  laptop:     [['Prosesor','Intel Core i5/i7'],['Layar','14" IPS FHD'],['Baterai','65Whr'],['OS','Windows 11']],
  smartwatch: [['Layar','AMOLED 1.9"'],['Sensor','HR, SpO2, GPS'],['Baterai','14 hari'],['Water Resist','5ATM']],
};

let ppSelectedColor = null;
let ppSelectedStorage = null;
let ppSelectedRam = null;
let currentVariants = [];

async function openProductPage(id) {
  currentDetailProductId = id;
  const p = await apiFetch(`${API}?action=get_product&id=${id}`);
  if (!p) return;

  document.getElementById('ppImg').src = p.image || 'https://via.placeholder.com/600x600?text=No+Image';
  document.getElementById('ppImg').alt = p.name;
  document.getElementById('ppName').textContent = p.name;
  const disc = parseFloat(p.discount_percent) || 0;
  const origPrice = parseFloat(p.price);
  const finalPrice = disc > 0 ? origPrice * (1 - disc / 100) : origPrice;
  const ppPriceEl = document.getElementById('ppPrice');
  if (disc > 0) {
    ppPriceEl.innerHTML = `${formatRupiah(finalPrice)} <span style="font-size:13px;color:#aaa;text-decoration:line-through;font-weight:400">${formatRupiah(origPrice)}</span> <span style="background:#e53935;color:#fff;font-size:11px;padding:2px 6px;border-radius:3px;font-weight:700">-${Math.round(disc)}%</span>`;
  } else {
    ppPriceEl.textContent = formatRupiah(origPrice);
  }
  const badgeEl = document.getElementById('ppBadge');
  badgeEl.textContent = p.badge || '';
  badgeEl.style.display = p.badge ? '' : 'none';
  document.getElementById('ppThumbs').innerHTML = p.image
    ? `<div class="pp-thumb active"><img src="${p.image}" onerror="this.src='https://via.placeholder.com/80'"></div>` : '';
  
  // Variants dari DB
  currentVariants = p.variants || [];
  const colors = [...new Set(currentVariants.map(v => v.color).filter(Boolean))];
  const storages = [...new Set(currentVariants.map(v => v.storage).filter(Boolean))];
  const rams = [...new Set(currentVariants.map(v => v.ram).filter(Boolean))];

  ppSelectedColor = colors[0] || null;
  ppSelectedStorage = storages[0] || null;
  ppSelectedRam = rams[0] || null;

  // Colors
  const colorEl = document.getElementById('ppColorGroup');
  if (colors.length > 0) {
    document.getElementById('ppColorSelected').textContent = ppSelectedColor;
    document.getElementById('ppColors').innerHTML = colors.map((c,i) =>
      `<div class="pp-color-chip ${i===0?'active':''}" onclick="selectColor('${c}',this)">${c}</div>`
    ).join('');
    colorEl.style.display = '';
  } else { colorEl.style.display = 'none'; }

  // Storage
  const storEl = document.getElementById('ppStorageGroup');
  if (storages.length > 0) {
    document.getElementById('ppStorageSelected').textContent = ppSelectedStorage;
    document.getElementById('ppStorages').innerHTML = storages.map((s,i) =>
      `<div class="pp-storage-chip ${i===0?'active':''}" onclick="selectStorage('${s}',this)">${s}</div>`
    ).join('');
    storEl.style.display = '';
    document.getElementById('ppStorageLabelText').textContent = 'Storage';
  } else { storEl.style.display = 'none'; }

  // RAM — tampilkan di dalam storage group jika ada
  const ramEl = document.getElementById('ppRamGroup');
  if (ramEl) {
    if (rams.length > 0) {
      document.getElementById('ppRamSelected').textContent = ppSelectedRam;
      document.getElementById('ppRams').innerHTML = rams.map((r,i) =>
        `<div class="pp-storage-chip ${i===0?'active':''}" onclick="selectRam('${r}',this)">${r}</div>`
      ).join('');
      ramEl.style.display = '';
    } else { ramEl.style.display = 'none'; }
  }

  // Stok dari varian terpilih
  updateStockDisplay();

  // Specs dari DB, fallback ke default
  const specs = (p.specs_parsed && p.specs_parsed.length) ? p.specs_parsed : (SPECS_DEFAULT[p.category] || []);
  const descRow = p.description ? [['Deskripsi', p.description]] : [];
  document.getElementById('ppSpecGrid').innerHTML = [...specs, ...descRow].map(([k,v]) =>
  `<div class="pp-spec-row"><span class="pp-spec-key">${k}</span><span class="pp-spec-val">${v}</span></div>`
  ).join('');

  document.getElementById('ppQty').value = 1;
  document.getElementById('productPageOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
  ppTab('spec', document.querySelector('.pp-tab'));
  const res = await apiFetch(`${API}?action=get_session`);
  updateCartCount(res.cart_count || 0);
}

function updateStockDisplay() {
  const v = currentVariants.find(x =>
    (x.color === ppSelectedColor || !ppSelectedColor) &&
    (x.storage === ppSelectedStorage || !ppSelectedStorage) &&
    (x.ram === ppSelectedRam || !ppSelectedRam)
  );
  const stockEl = document.getElementById('ppStockInfo');
  if (stockEl) {
    if (currentVariants.length === 0) { stockEl.textContent = ''; return; }
    stockEl.textContent = v ? (v.stock > 0 ? `Stok: ${v.stock}` : 'Stok Habis') : '';
    stockEl.style.color = (v && v.stock > 0) ? '#4CAF50' : '#f44336';
  }
}

function closeProductPage() {
  document.getElementById('productPageOverlay').classList.remove('open');
  document.body.style.overflow = '';
}

function selectColor(c, el) {
  ppSelectedColor = c;
  document.getElementById('ppColorSelected').textContent = c;
  document.querySelectorAll('.pp-color-chip').forEach(e => e.classList.remove('active'));
  el.classList.add('active');
  updateStockDisplay();
}

function selectStorage(s, el) {
  ppSelectedStorage = s;
  document.getElementById('ppStorageSelected').textContent = s;
  document.querySelectorAll('.pp-storage-chip').forEach(e => e.classList.remove('active'));
  el.classList.add('active');
  updateStockDisplay();
}

function selectRam(r, el) {
  ppSelectedRam = r;
  document.getElementById('ppRamSelected').textContent = r;
  document.querySelectorAll('.pp-ram-chip').forEach(e => e.classList.remove('active'));
  el.classList.add('active');
  updateStockDisplay();
}

function changePPQty(delta) {
  const input = document.getElementById('ppQty');
  let v = parseInt(input.value) + delta;
  if (v < 1) v = 1;
  if (v > 99) v = 99;
  input.value = v;
}

async function addFromPage(buyNow = false) {
  const qty = parseInt(document.getElementById('ppQty').value) || 1;
  await addToCart(currentDetailProductId, qty);
  if (buyNow) { closeProductPage(); openCheckoutModal(); }
}

function ppTab(tab, el) {
  document.querySelectorAll('.pp-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.pp-tab-content').forEach(c => c.classList.remove('active'));
  if (el) el.classList.add('active');
  else document.querySelector('.pp-tab')?.classList.add('active');
  document.getElementById(`pp-${tab}`)?.classList.add('active');
}

// =============================================
// SEARCH
// =============================================

function doSearch() {
  const q = document.getElementById('searchInput').value.trim();
  if (!q) return;

  // Aktifkan page products tanpa loadAllProducts (biar tidak overwrite hasil search)
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  const pageEl = document.getElementById('page-products');
  if (pageEl) pageEl.classList.add('active');
  window.scrollTo({ top: 0, behavior: 'smooth' });

  // Hapus active dari semua nav-link (search bukan filter kategori tertentu)
  document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));

  // Reset sidebar filter ke "Semua"
  document.querySelectorAll('#categoryFilter li').forEach(li => li.classList.remove('active'));
  const allLi = document.querySelector('#categoryFilter li:first-child');
  if (allLi) allLi.classList.add('active');

  closeSearch();
  loadSearchResults(q);
}

async function loadSearchResults(q) {
  const el = document.getElementById('allProducts');
  el.innerHTML = '<div class="product-skeleton"></div>'.repeat(4);
  const products = await apiFetch(`${API}?action=get_products&search=${encodeURIComponent(q)}`);
  document.getElementById('productCount').textContent = `${products.length} hasil untuk "${q}"`;
  el.innerHTML = products.length
    ? products.map(p => productCard(p)).join('')
    : `<p class="empty-msg">Tidak ada produk untuk "${q}"</p>`;
}

// =============================================
// CART
// =============================================

async function addToCart(id, qty) {
  const fd = new FormData();
  fd.append('id', id);
  fd.append('qty', qty);
  const res = await apiPost(`${API}?action=add_to_cart`, fd);
  if (res.success) {
    updateCartCount(res.count);
    showToast('success', 'fa-check-circle', 'Ditambahkan ke keranjang!');
  }
}

async function quickAdd(id) { await addToCart(id, 1); }

async function openCart() {
  const res = await apiFetch(`${API}?action=get_cart`);
  renderCart(res.items, res.count);
  document.getElementById('cartOverlay').classList.add('open');
  document.getElementById('cartDrawer').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeCart() {
  document.getElementById('cartOverlay').classList.remove('open');
  document.getElementById('cartDrawer').classList.remove('open');
  document.body.style.overflow = '';
}

function renderCart(items, count) {
  const body = document.getElementById('cartBody');
  const footer = document.getElementById('cartFooter');
  updateCartCount(count);
  if (!items || items.length === 0) {
    body.innerHTML = `<div class="empty-cart"><i class="fas fa-shopping-cart"></i><p>Keranjang Anda kosong</p><button class="btn-primary" onclick="closeCart();navigateTo('products')">Mulai Belanja</button></div>`;
    footer.style.display = 'none';
    return;
  }
  let total = 0;
  body.innerHTML = items.map(item => {
    const p = item.product;
    const disc = parseFloat(p.discount_percent) || 0;
    const unitPrice = disc > 0 ? parseFloat(p.price) * (1 - disc / 100) : parseFloat(p.price);
    const sub = unitPrice * item.qty;
    total += sub;
    const priceLabel = disc > 0
      ? `<div class="cart-item-price">${formatRupiah(sub)} <span style="font-size:11px;text-decoration:line-through;color:#aaa">${formatRupiah(parseFloat(p.price)*item.qty)}</span></div>`
      : `<div class="cart-item-price">${formatRupiah(sub)}</div>`;
    return `
      <div class="cart-item">
        <div class="cart-item-img"><img src="${p.image||'https://via.placeholder.com/80'}" alt="" onerror="this.src='https://via.placeholder.com/80'"></div>
        <div class="cart-item-info">
          <div class="cart-item-name">${escHtml(p.name)}</div>
          ${priceLabel}
          <div class="cart-item-controls">
            <button onclick="updateCart(${p.id},${item.qty-1})">−</button>
            <span class="cart-item-qty">${item.qty}</span>
            <button onclick="updateCart(${p.id},${item.qty+1})">+</button>
            <button class="cart-item-remove" onclick="removeCartItem(${p.id})"><i class="fas fa-trash"></i></button>
          </div>
        </div>
      </div>`;
  }).join('');
  document.getElementById('cartTotal').textContent = formatRupiah(total);
  footer.style.display = 'block';
}

async function updateCart(id, qty) {
  const fd = new FormData(); fd.append('id', id); fd.append('qty', qty);
  await apiPost(`${API}?action=update_cart`, fd);
  const res = await apiFetch(`${API}?action=get_cart`);
  renderCart(res.items, res.count);
}

async function removeCartItem(id) {
  const fd = new FormData(); fd.append('id', id);
  await apiPost(`${API}?action=remove_cart`, fd);
  const res = await apiFetch(`${API}?action=get_cart`);
  renderCart(res.items, res.count);
}

function openCheckoutModal() {
  closeCart();
  document.getElementById('checkoutError').style.display = 'none';
  openModal('checkoutModal');
}

async function doCheckout() {
  const nama   = document.getElementById('ckNama').value.trim();
  const hp     = document.getElementById('ckHp').value.trim();
  const alamat = document.getElementById('ckAlamat').value.trim();
  const kota   = document.getElementById('ckKota').value.trim();
  const kodepos= document.getElementById('ckKodePos').value.trim();
  const catatan= document.getElementById('ckCatatan').value.trim();
  const paymentEl = document.querySelector('input[name="paymentMethod"]:checked');
  const errEl  = document.getElementById('checkoutError');
  errEl.style.display = 'none';
  if (!nama || !hp || !alamat || !kota) {
    errEl.textContent = 'Nama, nomor HP, alamat, dan kota wajib diisi.';
    errEl.style.display = 'block'; return;
  }
  if (!paymentEl) {
    errEl.textContent = 'Pilih metode pembayaran terlebih dahulu.';
    errEl.style.display = 'block';
    document.querySelector('.payment-methods').scrollIntoView({ behavior: 'smooth', block: 'center' });
    return;
  }
  const fd = new FormData();
  fd.append('nama_penerima', nama); fd.append('no_hp', hp);
  fd.append('alamat', alamat); fd.append('kota', kota);
  fd.append('kode_pos', kodepos); fd.append('catatan', catatan);
  fd.append('payment_method', paymentEl.value);
  const res = await apiPost(`${API}?action=checkout`, fd);
  if (res.success) {
    closeModal('checkoutModal');
    updateCartCount(0);
    ['ckNama','ckHp','ckAlamat','ckKota','ckKodePos','ckCatatan'].forEach(id => document.getElementById(id).value = '');
    document.querySelectorAll('input[name="paymentMethod"]').forEach(r => r.checked = false);
    showPaymentInfo(paymentEl.value, res.order_id, res.total);
  } else {
    errEl.textContent = res.message || 'Gagal checkout';
    errEl.style.display = 'block';
  }
}

let _currentOrderId = null;

function handleBuktiPreview(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = ev => {
    document.getElementById('buktiImg').src = ev.target.result;
    document.getElementById('buktiName').textContent = file.name;
    document.getElementById('buktiPreview').style.display = 'block';
    document.getElementById('buktiPlaceholder').style.display = 'none';
  };
  reader.readAsDataURL(file);
}

async function doUploadBukti() {
  const buktiFoto = document.getElementById('buktiFoto');
  const errEl = document.getElementById('buktiError');
  errEl.style.display = 'none';
  if (!buktiFoto || !buktiFoto.files[0]) {
    errEl.textContent = 'Harap pilih foto bukti transfer terlebih dahulu.';
    errEl.style.display = 'block'; return;
  }
  if (!_currentOrderId) {
    errEl.textContent = 'Order ID tidak ditemukan.';
    errEl.style.display = 'block'; return;
  }
  const fd = new FormData();
  fd.append('order_id', _currentOrderId);
  fd.append('bukti_transfer', buktiFoto.files[0]);
  const res = await apiPost(`${API}?action=upload_bukti`, fd);
  if (res.success) {
    document.getElementById('buktiUploadWrap').innerHTML =
      `<div style="text-align:center;padding:16px 0">
        <i class="fas fa-check-circle" style="font-size:36px;color:#1d9e75;margin-bottom:8px;display:block"></i>
        <div style="font-weight:500;color:#0f6e56">Bukti berhasil dikirim!</div>
        <div style="font-size:13px;color:#999;margin-top:4px">Admin akan mengkonfirmasi dalam 1x24 jam.</div>
      </div>`;
  } else {
    errEl.textContent = res.message || 'Gagal upload bukti, coba lagi.';
    errEl.style.display = 'block';
  }
}

// ─── INFO PEMBAYARAN (muncul setelah checkout berhasil) ───
const PAYMENT_INFO = {
  // Transfer Bank
  bca:      { type:'bank', icon:'fas fa-university', color:'#005baa', name:'BCA', norek:'0902492591', atas:'Admin A2IU Store', bank:'Bank Central Asia (BCA)' },
  bri:      { type:'bank', icon:'fas fa-university', color:'#003f91', name:'BRI', norek:'0987654321', atas:'Admin A2IU Store', bank:'Bank BRI' },
  bni:      { type:'bank', icon:'fas fa-university', color:'#ef8200', name:'BNI', norek:'1122334455', atas:'Admin A2IU Store', bank:'Bank BNI' },
  mandiri:  { type:'bank', icon:'fas fa-university', color:'#003087', name:'Mandiri', norek:'5566778899', atas:'Admin A2IU Store', bank:'Bank Mandiri' },
  // E-Wallet
  gopay:    { type:'ewallet', icon:'fas fa-wallet', color:'#00aed6', name:'GoPay',     nomor:'081216693574', atas:'Admin A2IU Store' },
  ovo:      { type:'ewallet', icon:'fas fa-wallet', color:'#4c3494', name:'OVO',       nomor:'081216693574', atas:'Admin A2IU Store' },
  dana:     { type:'ewallet', icon:'fas fa-wallet', color:'#118edd', name:'DANA',      nomor:'081216693574', atas:'Admin A2IU Store' },
  shopeepay:{ type:'ewallet', icon:'fas fa-wallet', color:'#ee4d2d', name:'ShopeePay', nomor:'081216693574', atas:'Admin A2IU Store' },
};

function showPaymentInfo(method, orderId, total) {
  _currentOrderId = orderId;
  const info = PAYMENT_INFO[method];
  const box  = document.getElementById('payInfoBox');
  const oid  = document.getElementById('payInfoOrderId');
  oid.innerHTML = `Pesanan <strong>#${orderId}</strong> menunggu pembayaran via <strong>${info ? info.name : method}</strong>`;
  const nominalRow = total
    ? `<div class="pay-detail-row"><span>Total Transfer</span>
         <strong style="color:var(--primary);font-size:18px">${formatRupiah(total)}</strong>
         <button class="pay-copy-btn" onclick="copyText('${Math.round(total)}',this)"><i class="fas fa-copy"></i></button>
       </div>`
    : '';
  if (!info) { box.innerHTML = `<p>Silakan hubungi admin untuk info pembayaran.</p>`; }
  else if (info.type === 'bank') {
    box.innerHTML = `
      ${nominalRow}
      <div class="pay-detail-row"><span>Bank</span><strong>${escHtml(info.bank)}</strong></div>
      <div class="pay-detail-row"><span>No. Rekening</span>
        <strong class="pay-norek">${escHtml(info.norek)}</strong>
        <button class="pay-copy-btn" onclick="copyText('${info.norek}',this)"><i class="fas fa-copy"></i></button>
      </div>
      <div class="pay-detail-row"><span>Atas Nama</span><strong>${escHtml(info.atas)}</strong></div>`;
  } else {
    box.innerHTML = `
      ${nominalRow}
      <div class="pay-detail-row"><span>Dompet</span><strong>${escHtml(info.name)}</strong></div>
      <div class="pay-detail-row"><span>Nomor</span>
        <strong class="pay-norek">${escHtml(info.nomor)}</strong>
        <button class="pay-copy-btn" onclick="copyText('${info.nomor}',this)"><i class="fas fa-copy"></i></button>
      </div>
      <div class="pay-detail-row"><span>Atas Nama</span><strong>${escHtml(info.atas)}</strong></div>`;
  }
  openModal('paymentInfoModal');
}

function copyText(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    btn.innerHTML = '<i class="fas fa-check"></i>';
    setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy"></i>'; }, 2000);
  });
}

// =============================================
// AUTH
// =============================================

async function doLogin() {
  const email = document.getElementById('loginEmail').value.trim();
  const password = document.getElementById('loginPassword').value;
  const errEl = document.getElementById('loginError');
  errEl.style.display = 'none';
  if (!email || !password) { errEl.textContent='Email dan password wajib diisi.'; errEl.style.display='block'; return; }
  const fd = new FormData(); fd.append('email', email); fd.append('password', password);
  const res = await apiPost(`${API}?action=login`, fd);
  if (res.success) {
    closeModal('loginModal');
    setLoggedInState({ name: res.name, role: res.role });
    showToast('success','fa-user-check',`Selamat datang, ${res.name}!`);
    await checkSession();
  } else { errEl.textContent = res.message||'Login gagal.'; errEl.style.display='block'; }
}

async function doRegister() {
  const name = document.getElementById('regName').value.trim();
  const email = document.getElementById('regEmail').value.trim();
  const password = document.getElementById('regPassword').value;
  const errEl = document.getElementById('registerError');
  const sucEl = document.getElementById('registerSuccess');
  errEl.style.display='none'; sucEl.style.display='none';
  if (!name||!email||!password) { errEl.textContent='Semua field wajib diisi.'; errEl.style.display='block'; return; }
  if (password.length<6) { errEl.textContent='Password minimal 6 karakter.'; errEl.style.display='block'; return; }
  const fd = new FormData(); fd.append('name',name); fd.append('email',email); fd.append('password',password);
  const res = await apiPost(`${API}?action=register`, fd);
  if (res.success) {
    sucEl.textContent='Akun berhasil dibuat! Silakan masuk.'; sucEl.style.display='block';
    ['regName','regEmail','regPassword'].forEach(id=>document.getElementById(id).value='');
    setTimeout(()=>switchModal('registerModal','loginModal'),1500);
  } else { errEl.textContent=res.message||'Registrasi gagal.'; errEl.style.display='block'; }
}

async function doLogout() {
  await apiFetch(`${API}?action=logout`);
  setLoggedOutState(); updateCartCount(0); navigateTo('home');
  showToast('info','fa-sign-out-alt','Anda telah keluar.'); closeAllMenus();
}

// =============================================
// ADMIN
// =============================================

function adminTab(tab) {
  document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.admin-tab').forEach(t => {
    if (t.getAttribute('onclick') === `adminTab('${tab}')`) t.classList.add('active');
  });
  document.querySelectorAll('.admin-panel').forEach(p=>p.classList.remove('active'));
  document.getElementById(`admin-${tab}`).classList.add('active');
  if (tab==='products') loadAdminProducts();
  if (tab==='users') loadAdminUsers();
  if (tab==='orders') loadAdminOrdersTable();
  if (tab === 'returns') loadAdminReturns();
  if (tab === 'homepage') loadAdminHomepage();
}

async function loadAdminReturns() {
  const returns = await apiFetch('api_admin.php?action=admin_get_returns');
  const sc = { pending:'#ff9800', approved:'#4CAF50', rejected:'#f44336' };
  document.getElementById('adminReturnBody').innerHTML = returns.map(r => `
    <tr>
      <td>#${r.id}</td>
      <td>#${r.order_id}<br><small style="color:#999">${formatRupiah(r.total)}</small></td>
      <td>${escHtml(r.user_name)}<br><small style="color:#999">${escHtml(r.email)}</small></td>
      <td>${escHtml(r.alasan)}</td>
      <td>${r.foto_bukti
        ? `<button onclick="openImgLightbox('${r.foto_bukti}')" style="background:none;border:1.5px solid #1565c0;color:#1565c0;border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:all 0.2s" onmouseover="this.style.background='#e3f2fd'" onmouseout="this.style.background='none'"><i class="fas fa-image"></i> Lihat Foto</button>`
        : '<span style="color:#aaa;font-size:12px">-</span>'}</td>
      <td><span style="color:${sc[r.status]};font-weight:600;text-transform:capitalize">${r.status}</span></td>
      <td>${formatDate(r.created_at)}</td>
      <td>
        <select onchange="updateReturn(${r.id}, this.value)"
          style="border:2px solid ${sc[r.status]};color:${sc[r.status]};font-weight:600;
                 border-radius:6px;padding:4px 8px;cursor:pointer;background:#fff;font-size:12px">
          ${['pending','approved','rejected'].map(s =>
            `<option value="${s}" ${r.status===s?'selected':''}>${
              {pending:'Pending',approved:'Disetujui',rejected:'Ditolak'}[s]
            }</option>`
          ).join('')}
        </select>
      </td>
    </tr>`).join('');
}

async function updateReturn(id, status) {
  const fd = new FormData();
  fd.append('id', id);
  fd.append('status', status);
  const res = await apiPost('api_admin.php?action=admin_update_return', fd);
  if (res.success) {
    showToast('success', 'fa-check-circle', 'Status retur diperbarui!');
    loadAdminReturns();
  } else {
    showToast('error', 'fa-times-circle', 'Gagal update status retur');
    loadAdminReturns();
  }
}
// =============================================
// ADMIN — VARIANT & SPECS MANAGEMENT
// =============================================

let currentVariantProductId = null;
let currentSpecsProductId = null;

async function manageVariants(pid, name) {
  currentVariantProductId = pid;
  currentSpecsProductId = pid;
  document.getElementById('variantProductName').textContent = name;
  document.getElementById('variantPanel').style.display = 'block';
  document.getElementById('variantPanel').scrollIntoView({ behavior: 'smooth' });
  await loadVariants(pid);
  await loadSpecsEditor(pid);
}

async function loadVariants(pid) {
  const variants = await apiFetch(`${API}?action=get_variants&product_id=${pid}`);
  const tbody = document.getElementById('variantBody');
  tbody.innerHTML = variants.length ? variants.map(v => `
    <tr>
      <td>${v.id}</td>
      <td>${escHtml(v.color)||'-'}</td>
      <td>${escHtml(v.storage)||'-'}</td>
      <td>${escHtml(v.ram)||'-'}</td>
      <td><span style="color:${v.stock>0?'#4CAF50':'#f44336'};font-weight:700">${v.stock}</span></td>
      <td>
        <button class="btn-edit" onclick="editVariant(${v.id},'${escHtml(v.color)}','${escHtml(v.storage)}','${escHtml(v.ram)}',${v.stock})"><i class="fas fa-edit"></i> Edit</button>
        <button class="btn-delete" onclick="deleteVariant(${v.id})"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`).join('') : '<tr><td colspan="6" style="text-align:center;color:#999;padding:20px">Belum ada varian — tambahkan di atas</td></tr>';
}

function openAddVariant() {
  document.getElementById('variantModalTitle').textContent = 'Tambah Varian';
  document.getElementById('editVariantId').value = '';
  ['vColor','vStorage','vRam'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('vStock').value = 0;
  openModal('variantModal');
}

function editVariant(id, color, storage, ram, stock) {
  document.getElementById('variantModalTitle').textContent = 'Edit Varian';
  document.getElementById('editVariantId').value = id;
  document.getElementById('vColor').value = color;
  document.getElementById('vStorage').value = storage;
  document.getElementById('vRam').value = ram;
  document.getElementById('vStock').value = stock;
  openModal('variantModal');
}

async function saveVariant() {
  const vid = document.getElementById('editVariantId').value;
  const fd = new FormData();
  if (vid) fd.append('id', vid);
  else fd.append('product_id', currentVariantProductId);
  fd.append('color', document.getElementById('vColor').value.trim());
  fd.append('storage', document.getElementById('vStorage').value.trim());
  fd.append('ram', document.getElementById('vRam').value.trim());
  fd.append('stock', document.getElementById('vStock').value);
  const action = vid ? 'update_variant' : 'add_variant';
  const res = await apiPost(`${API}?action=${action}`, fd);
  if (res.success) {
    closeModal('variantModal');
    loadVariants(currentVariantProductId);
    showToast('success','fa-check-circle', vid ? 'Varian diperbarui!' : 'Varian ditambahkan!');
  } else showToast('error','fa-times-circle','Gagal simpan varian');
}

async function deleteVariant(id) {
  if (!confirm('Hapus varian ini?')) return;
  const fd = new FormData(); fd.append('id', id);
  const res = await apiPost(`${API}?action=delete_variant`, fd);
  if (res.success) { loadVariants(currentVariantProductId); showToast('success','fa-trash','Varian dihapus'); }
}

// SPECS EDITOR
async function loadSpecsEditor(pid) {
  const p = await apiFetch(`${API}?action=get_product&id=${pid}`);
  const specs = (p.specs_parsed && p.specs_parsed.length) ? p.specs_parsed : [];
  const el = document.getElementById('specsEditor');
  el.innerHTML = '';
  specs.forEach(([k,v]) => addSpecRow(k, v));
}

function addSpecRow(key='', val='') {
  const el = document.getElementById('specsEditor');
  const row = document.createElement('div');
  row.className = 'spec-editor-row';
  row.innerHTML = `
    <input type="text" placeholder="Label (misal: Layar)" value="${escHtml(key)}" class="spec-key-input">
    <input type="text" placeholder="Nilai (misal: AMOLED 6.7&quot; 120Hz)" value="${escHtml(val)}" class="spec-val-input">
    <button class="btn-delete" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>`;
  el.appendChild(row);
}

async function saveSpecs() {
  const rows = document.querySelectorAll('.spec-editor-row');
  const specs = [];
  rows.forEach(r => {
    const k = r.querySelector('.spec-key-input').value.trim();
    const v = r.querySelector('.spec-val-input').value.trim();
    if (k && v) specs.push([k, v]);
  });
  const fd = new FormData();
  fd.append('product_id', currentSpecsProductId);
  fd.append('specs', JSON.stringify(specs));
  const res = await apiPost(`${API}?action=save_specs`, fd);
  if (res.success) showToast('success','fa-check-circle','Spesifikasi disimpan!');
  else showToast('error','fa-times-circle','Gagal simpan spesifikasi');
}

async function loadAdmin() {
  const res = await apiFetch(`${API}?action=get_session`);
  if (!res.user||res.user.role!=='admin') { navigateTo('home'); showToast('error','fa-times-circle','Akses ditolak'); return; }
  loadAdminProducts();
}

async function loadAdminProducts() {
  const products = await apiFetch(`${API}?action=get_products`);
  const tbody = document.getElementById('adminProductBody');
  // Hide variant panel when reloading
  document.getElementById('variantPanel').style.display = 'none';
  tbody.innerHTML = products.map((p, i) => {
    const disc = parseFloat(p.discount_percent) || 0;
    const discLabel = disc > 0
      ? `<span style="background:#e53935;color:#fff;border-radius:4px;padding:2px 7px;font-size:12px;font-weight:700">-${Math.round(disc)}%</span>`
      : `<span style="color:#aaa;font-size:12px">–</span>`;
    return `
    <tr>
      <td>${i + 1}</td>
      <td><img src="${p.image||'https://via.placeholder.com/48'}" alt="" onerror="this.src='https://via.placeholder.com/48'" style="width:48px;height:48px;object-fit:cover;border-radius:6px"></td>
      <td>${escHtml(p.name)}</td>
      <td style="text-transform:capitalize">${p.category}</td>
      <td>${formatRupiah(p.price)}</td>
      <td>
        ${discLabel}
        <button onclick="promptDiscount(${p.id},${disc})" style="background:#fff3e0;color:#e65100;border:1px solid #ffcc80;border-radius:5px;padding:3px 8px;font-size:11px;font-weight:600;cursor:pointer;margin-left:4px"><i class="fas fa-percent"></i></button>
      </td>
      <td>
        <button class="btn-featured" onclick="toggleFeatured(${p.id})" title="${p.is_featured=='1'?'Hapus dari Unggulan':'Jadikan Unggulan'}" style="background:${p.is_featured=='1'?'#f59e0b':'#d1d5db'};color:${p.is_featured=='1'?'#fff':'#555'};border-radius:6px;padding:6px 10px;font-size:13px;font-weight:600;border:none;cursor:pointer">
          ${p.is_featured=='1'?'⭐ Unggulan':'☆ Unggulan'}
        </button>
        <button class="btn-edit" onclick="editProduct(${p.id})"><i class="fas fa-edit"></i> Edit</button>
        <button class="btn-variant" onclick="manageVariants(${p.id},'${escHtml(p.name)}')"><i class="fas fa-layer-group"></i> Varian</button>
        <button class="btn-delete" onclick="deleteProduct(${p.id},'${escHtml(p.name)}')"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`;
  }).join('');
}

async function promptDiscount(id, current) {
  const val = prompt(`Masukkan diskon (%) untuk produk ini:\n(0 = tidak ada diskon, max 100)`, current);
  if (val === null) return;
  const disc = parseFloat(val);
  if (isNaN(disc) || disc < 0 || disc > 100) { showToast('error','fa-times-circle','Nilai diskon tidak valid (0–100)'); return; }
  const fd = new FormData(); fd.append('id', id); fd.append('discount_percent', disc);
  const res = await apiPost(`api_admin.php?action=set_discount`, fd);
  if (res.success) { loadAdminProducts(); loadFeaturedBanners(); showToast('success','fa-percent', disc > 0 ? `Diskon ${Math.round(disc)}% diterapkan!` : 'Diskon dihapus.'); }
  else showToast('error','fa-times-circle','Gagal mengubah diskon.');
}

async function loadAdminUsers() {
  const users = await apiFetch(`${API}?action=admin_get_users`);
  document.getElementById('adminUserBody').innerHTML = users.map(u=>`
    <tr>
      <td>${u.id}</td><td>${escHtml(u.name)}</td><td>${escHtml(u.email)}</td>
      <td><span style="color:${u.role==='admin'?'#ff6600':'#333'};font-weight:600">${u.role}</span></td>
      <td>${formatDate(u.created_at)}</td>
    </tr>`).join('');
}

async function loadAdminOrdersTable() {
  const orders = await apiFetch(`${API}?action=admin_get_orders`);
  const sc = {pending:'#ff9800',processing:'#2196F3',shipped:'#9C27B0',delivered:'#4CAF50',cancelled:'#f44336'};
  document.getElementById('adminOrderBody').innerHTML = orders.map(o => {
    const items = (o.items&&o.items.length)
      ? o.items.map(i=>`<div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
          <img src="${i.image||'https://via.placeholder.com/32'}" style="width:32px;height:32px;object-fit:cover;border-radius:4px" onerror="this.src='https://via.placeholder.com/32'">
          <span>${escHtml(i.product_name)} ×${i.qty} — <strong>${formatRupiah(i.price*i.qty)}</strong></span></div>`).join('')
      : '-';
    const addr = o.nama_penerima
      ? `<strong>${escHtml(o.nama_penerima)}</strong><br><small>${escHtml(o.no_hp)}</small><br><small>${escHtml(o.alamat)}, ${escHtml(o.kota)}${o.kode_pos?' '+o.kode_pos:''}</small>${o.catatan?`<br><small style="color:#888">📝 ${escHtml(o.catatan)}</small>`:''}`
      : '-';
      const buktiHtml = o.bukti_transfer
  ? `<br><a href="${escHtml(o.bukti_transfer)}" target="_blank"
       style="display:inline-block;margin-top:6px;padding:4px 10px;
              background:#e3f2fd;color:#1565c0;border-radius:5px;
              font-size:12px;font-weight:600;text-decoration:none">
       <i class="fas fa-image"></i> Lihat Bukti
     </a>`
  : `<br><span style="color:#f44336;font-size:12px;margin-top:4px;display:inline-block">
       <i class="fas fa-times-circle"></i> Belum ada bukti
     </span>`;
     
    return `<tr>
      <td>#${o.id}</td>
      <td>${escHtml(o.user_name)}<br><small style="color:#999">${escHtml(o.email)}</small></td>
      <td>${items}</td><td>${addr}${buktiHtml}</td>
      <td>${formatRupiah(o.total)}</td>
      <td>
     <select onchange="updateOrderStatus(${o.id}, this.value)" 
         style="border:2px solid ${sc[o.status]||'#ccc'};color:${sc[o.status]||'#333'};
           font-weight:600;border-radius:6px;padding:5px 8px;cursor:pointer;
           background:#fff;font-size:13px;outline:none">
    ${['pending','processing','shipped','delivered','cancelled'].map(s =>
      `<option value="${s}" ${o.status===s?'selected':''} style="color:${sc[s]}">${
        {pending:'Pending',processing:'Diproses',shipped:'Dikirim',delivered:'Selesai',cancelled:'Dibatalkan'}[s]
      }</option>`
    ).join('')}
  </select>
</td>
      <td>${formatDate(o.created_at)}</td></tr>`;
  }).join('');
}

async function updateOrderStatus(id, status) {
  const fd = new FormData();
  fd.append('id', id);
  fd.append('status', status);
  const res = await apiPost('api_admin.php?action=admin_update_order_status', fd);
  if (res.success) {
    showToast('success', 'fa-check-circle', 'Status pesanan diperbarui!');
    loadAdminOrdersTable();
  } else {
    showToast('error', 'fa-times-circle', res.message || 'Gagal mengubah status');
    loadAdminOrdersTable();
  }
}

async function loadOrders() {
  const res = await apiFetch(`${API}?action=get_session`);
  const el  = document.getElementById('ordersContent');
  if (!res.user) {
    el.innerHTML = `<p class="empty-msg">Silakan <a href="#" onclick="openModal('loginModal')" style="color:var(--primary)">login</a> untuk melihat pesanan.</p>`;
    return;
  }
  el.innerHTML = `<p class="empty-msg"><i class="fas fa-spinner fa-spin"></i> Memuat pesanan...</p>`;
  const [data, returData] = await Promise.all([
    apiFetch(`api_user.php?action=get_my_orders`),
    apiFetch(`api_user.php?action=get_my_returns`)
  ]);
  if (!data.success || !data.orders || data.orders.length === 0) {
    el.innerHTML = `<div class="orders-empty"><i class="fas fa-box-open"></i><p>Belum ada pesanan</p><button class="btn-primary" onclick="navigateTo('products')">Mulai Belanja</button></div>`;
    return;
  }
  // Buat map order_id → data retur
  const returMap = {};
  if (returData.success && returData.returns) {
    returData.returns.forEach(r => { returMap[r.order_id] = r; });
  }
  const statusLabel = { pending:'Menunggu Pembayaran', processing:'Diproses', shipped:'Dikirim', delivered:'Selesai', cancelled:'Dibatalkan' };
  const statusClass = { pending:'status-pending', processing:'status-processing', shipped:'status-shipped', delivered:'status-delivered', cancelled:'status-cancelled' };
  const returStatusLabel = { pending:'Retur Diajukan', approved:'Retur Disetujui', rejected:'Retur Ditolak' };
  const returStatusColor = { pending:'#ff9800', approved:'#4CAF50', rejected:'#f44336' };
  el.innerHTML = data.orders.map(o => {
    const items = o.items.map(i =>
      `<div class="order-item-row">
        <img src="${escHtml(i.image||'')}" onerror="this.src='https://via.placeholder.com/50x50?text=?'" alt="">
        <span>${escHtml(i.product_name)} <small>×${i.qty}</small></span>
        <span>${formatRupiah(i.price * i.qty)}</span>
      </div>`
    ).join('');
    const payLabel = {bca:'BCA',bri:'BRI',bni:'BNI',mandiri:'Mandiri',gopay:'GoPay',ovo:'OVO',dana:'DANA',shopeepay:'ShopeePay'};
    const pm = o.payment_method ? `<span class="order-pay-badge"><i class="fas fa-wallet"></i> ${payLabel[o.payment_method]||o.payment_method}</span>` : '';

    // Cek status retur untuk order ini
    const retur = returMap[o.id];
    const returHtml = retur
      ? `<div class="order-retur-status" style="margin-top:10px;padding:10px 14px;border-radius:8px;background:#f5f5f5;display:flex;align-items:center;gap:8px;font-size:13px">
           <i class="fas fa-undo-alt" style="color:${returStatusColor[retur.status]}"></i>
           <span style="font-weight:600;color:${returStatusColor[retur.status]}">${returStatusLabel[retur.status]}</span>
           ${retur.catatan_admin ? `<span style="color:#666;margin-left:4px">— ${escHtml(retur.catatan_admin)}</span>` : ''}
         </div>`
      : o.status === 'delivered'
        ? `<div class="order-retur-btn" onclick="openReturModal(${o.id})"><i class="fas fa-undo-alt"></i> Ajukan Retur</div>`
        : '';

    return `<div class="order-card">
      <div class="order-card-header">
        <div>
          <span class="order-id">Pesanan #${o.id}</span>
          <span class="order-date">${formatDate(o.created_at)}</span>
        </div>
        <div class="order-card-header-right">
          ${pm}
          <span class="order-status-badge ${statusClass[o.status]||''}">${statusLabel[o.status]||o.status}</span>
        </div>
      </div>
      <div class="order-items-list">${items}</div>
      <div class="order-card-footer">
        <span class="order-address"><i class="fas fa-map-marker-alt"></i> ${escHtml(o.nama_penerima)} · ${escHtml(o.kota)}</span>
        <span class="order-total">${formatRupiah(o.total)}</span>
      </div>
      ${o.status==='pending' ? `<div class="order-pay-reminder" onclick="showPaymentInfo('${o.payment_method}','${o.id}',${o.total})"><i class="fas fa-exclamation-circle"></i> Lihat info pembayaran</div>` : ''}
      ${returHtml}
    </div>`;
  }).join('');
}

function openReturModal(orderId) {
  document.getElementById('returOrderId').value = orderId;
  document.getElementById('returAlasan').value = '';
  document.getElementById('returFoto').value = '';
  document.getElementById('returFotoPreview').style.display = 'none';
  document.getElementById('returError').style.display = 'none';
  openModal('returModal');
}

async function submitRetur() {
  const orderId = document.getElementById('returOrderId').value;
  const alasan  = document.getElementById('returAlasan').value.trim();
  const foto    = document.getElementById('returFoto').files[0];
  const errEl   = document.getElementById('returError');
  errEl.style.display = 'none';
  if (!alasan) {
    errEl.textContent = 'Alasan retur wajib diisi.';
    errEl.style.display = 'block'; return;
  }
  const fd = new FormData();
  fd.append('order_id', orderId);
  fd.append('alasan', alasan);
  if (foto) fd.append('foto_bukti', foto);
  const res = await apiPost('api_user.php?action=submit_return', fd);
  if (res.success) {
    closeModal('returModal');
    showToast('success', 'fa-check-circle', 'Klaim retur berhasil diajukan!');
  } else {
    errEl.textContent = res.message || 'Gagal mengajukan retur';
    errEl.style.display = 'block';
  }
}

document.addEventListener('change', function(e) {
  if (e.target.id === 'returFoto') {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = ev => {
      document.getElementById('returFotoImg').src = ev.target.result;
      document.getElementById('returFotoPreview').style.display = 'block';
    };
    reader.readAsDataURL(file);
  }
});

// ADMIN CRUD — BUG FIX: openAddProduct() reset form sebelum buka modal
function openAddProduct() {
  document.getElementById('addProductTitle').textContent = 'Tambah Produk';
  document.getElementById('editProductId').value = '';
  document.getElementById('pName').value = '';
  document.getElementById('pPrice').value = '';
  document.getElementById('pCategory').value = 'smartphone';
  document.getElementById('pImage').value = '';
  document.getElementById('pBadge').value = '';
  document.getElementById('pDesc').value = '';
  openModal('addProductModal');
}

async function editProduct(id) {
  const p = await apiFetch(`${API}?action=get_product&id=${id}`);
  if (!p) return;
  document.getElementById('addProductTitle').textContent = 'Edit Produk';
  document.getElementById('editProductId').value = p.id;
  document.getElementById('pName').value = p.name;
  document.getElementById('pPrice').value = p.price;
  document.getElementById('pCategory').value = p.category;
  document.getElementById('pImage').value = p.image||'';
  document.getElementById('pBadge').value = p.badge||'';
  openModal('addProductModal');
}

async function saveProduct() {
  const id = document.getElementById('editProductId').value;
  const fd = new FormData();
  if (id) fd.append('id', id);
  fd.append('name', document.getElementById('pName').value.trim());
  fd.append('price', document.getElementById('pPrice').value);
  fd.append('category', document.getElementById('pCategory').value);
  fd.append('image', document.getElementById('pImage').value.trim());
  fd.append('badge', document.getElementById('pBadge').value.trim());
  fd.append('description', document.getElementById('pDesc').value.trim());
  const action = id ? 'admin_update_product' : 'admin_add_product';
  const res = await apiPost(`${API}?action=${action}`, fd);
  if (res.success) {
    closeModal('addProductModal');
    loadAdminProducts();
    loadFeaturedBanners();
    showToast('success','fa-check-circle', id?'Produk diperbarui!':'Produk ditambahkan!');
  } else {
    showToast('error','fa-times-circle', res.message||'Gagal menyimpan');
  }
}

async function deleteProduct(id, name) {
  if (!confirm(`Hapus produk "${name}"?`)) return;
  const fd = new FormData(); fd.append('id', id);
  const res = await apiPost(`${API}?action=admin_delete_product`, fd);
  if (res.success) { loadAdminProducts(); loadFeaturedBanners(); showToast('success','fa-trash','Produk dihapus.'); }
  else showToast('error','fa-times-circle','Gagal menghapus.');
}

async function toggleFeatured(id) {
  const fd = new FormData(); fd.append('id', id);
  const res = await apiPost(`api_admin.php?action=toggle_featured`, fd);
  if (res.success) {
    loadAdminProducts();
    loadFeaturedBanners();
    showToast('success', 'fa-star', res.featured ? 'Produk dijadikan unggulan!' : 'Produk dihapus dari unggulan.');
  } else {
    showToast('error', 'fa-times-circle', 'Gagal mengubah status unggulan.');
  }
}

// =============================================
// HERO SLIDER
// =============================================

function startSlider() { sliderTimer = setInterval(nextSlide, 5000); }
function goSlide(index) {
  const slides = document.querySelectorAll('.hero-slide');
  const dots = document.querySelectorAll('.dot');
  slides[sliderIndex].classList.remove('active'); dots[sliderIndex].classList.remove('active');
  sliderIndex = (index+slides.length)%slides.length;
  slides[sliderIndex].classList.add('active'); dots[sliderIndex].classList.add('active');
}
function nextSlide() { goSlide(sliderIndex+1); }
function prevSlide() { goSlide(sliderIndex-1); clearInterval(sliderTimer); startSlider(); }

// =============================================
// COUNTDOWN
// =============================================

function startCountdown() {
  const end = new Date(); end.setHours(23,59,59,0);
  function update() {
    const diff = Math.max(0,end-new Date());
    const pad = n=>String(n).padStart(2,'0');
    const h=document.getElementById('ch'),m=document.getElementById('cm'),s=document.getElementById('cs');
    if(h){h.textContent=pad(Math.floor(diff/3600000));m.textContent=pad(Math.floor(diff%3600000/60000));s.textContent=pad(Math.floor(diff%60000/1000));}
  }
  update(); setInterval(update,1000);
}

// =============================================
// UI HELPERS
// =============================================

document.getElementById('searchToggle').addEventListener('click',()=>{
  const bar=document.getElementById('searchBar'); bar.classList.toggle('open');
  if(bar.classList.contains('open')) setTimeout(()=>document.getElementById('searchInput').focus(),300);
});
document.getElementById('searchInput').addEventListener('keydown', e => {
  if (e.key === 'Enter') doSearch();
  if (e.key === 'Escape') closeSearch();
});
function closeSearch(){document.getElementById('searchBar').classList.remove('open');document.getElementById('searchInput').value='';}
document.getElementById('hamburger').addEventListener('click',()=>{document.getElementById('mainNav').classList.toggle('open');});
function initScrollHeader(){const h=document.getElementById('siteHeader');window.addEventListener('scroll',()=>h.classList.toggle('scrolled',window.scrollY>50));}
function initClickOutside(){document.addEventListener('click',e=>{if(!e.target.closest('.user-wrap'))document.querySelector('.user-dropdown')?.classList.remove('open-force');});}
function closeAllMenus(){document.getElementById('mainNav').classList.remove('open');document.getElementById('searchBar').classList.remove('open');}
function togglePass(id){const i=document.getElementById(id);i.type=i.type==='password'?'text':'password';}

function openModal(id){closeAllMenus();document.getElementById(id).classList.add('open');document.body.style.overflow='hidden';}
function closeModal(id){document.getElementById(id).classList.remove('open');document.body.style.overflow='';}
function switchModal(from,to){closeModal(from);setTimeout(()=>openModal(to),200);}
document.querySelectorAll('.modal-overlay').forEach(o=>o.addEventListener('click',function(e){if(e.target===this)closeModal(this.id);}));

function showToast(type,icon,message){
  const c=document.getElementById('toastContainer');
  const t=document.createElement('div'); t.className=`toast ${type}`;
  t.innerHTML=`<i class="fas ${icon}"></i><span>${escHtml(message)}</span>`;
  c.appendChild(t); setTimeout(()=>t.classList.add('show'),50);
  setTimeout(()=>{t.classList.remove('show');setTimeout(()=>t.remove(),400);},3500);
}

function updateCartCount(count){
  const el=document.getElementById('cartCount');
  el.textContent=count; el.style.display=count>0?'flex':'none';
  const el2=document.getElementById('cartCount2');
  if(el2){el2.textContent=count;el2.style.display=count>0?'flex':'none';}
}

function openImgLightbox(src) {
  const lb = document.getElementById('imgLightbox');
  const img = document.getElementById('imgLightboxImg');
  img.src = src;
  lb.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeImgLightbox() {
  document.getElementById('imgLightbox').classList.remove('open');
  document.body.style.overflow = '';
}

async function apiFetch(url){try{return await(await fetch(url)).json();}catch(e){return {};}}
async function apiPost(url,fd){try{return await(await fetch(url,{method:'POST',body:fd})).json();}catch(e){return{success:false};}}
function formatRupiah(n){return 'Rp '+Number(n).toLocaleString('id-ID');}
function formatDate(s){return s?new Date(s).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}):'-';}
function escHtml(s){return s?String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])):'';}
// =============================================
// HERO BANNERS — dari tabel banners di DB
// =============================================

async function loadHeroBanners() {
  const slider = document.getElementById('heroSlider');
  const dots   = document.getElementById('heroDots');
  if (!slider) return;

  const banners = await apiFetch('api_user.php?action=get_banners');
  if (!banners || !banners.length) return;

  slider.innerHTML = banners.map((b, i) => `
    <div class="hero-slide ${i === 0 ? 'active' : ''}"
         onclick="${b.link ? `window.location='${b.link}'` : ''}"
         style="${b.link ? 'cursor:pointer' : ''}">
      <img src="${b.image}" alt="${escHtml(b.title || '')}"
           style="width:100%;height:100%;object-fit:cover;object-position:center;display:block">
    </div>`).join('');

  dots.innerHTML = banners.map((_, i) =>
    `<span class="dot ${i === 0 ? 'active' : ''}" onclick="goSlide(${i})"></span>`
  ).join('');

  sliderIndex = 0;
}
// =============================================
// ADMIN: HOMEPAGE SECTION MANAGEMENT
// =============================================

let _homeSectionData = [];

async function loadAdminHomepage() {
  const sections = await apiFetch('api_admin.php?action=admin_get_home_sections');
  _homeSectionData = sections;
  const cards = document.getElementById('homeSectionCards');
  if (!cards) return;

  const LABELS = {
    promo:    { desc: 'Tampil otomatis: produk dengan diskon > 0%, diurut dari diskon terbesar.' },
    terlaris: { desc: 'Tampil produk yang diflag "Terlaris" oleh admin (lihat tabel di bawah).' },
    trending: { desc: 'Tampil produk yang diflag "Trending" oleh admin (lihat tabel di bawah).' },
    terbaru:  { desc: 'Tampil otomatis: produk terbaru berdasarkan tanggal input.' },
  };

  cards.innerHTML = sections.map((sec, idx) => {
    const meta = LABELS[sec.section_key] || { icon: '📦', desc: '' };
    return `
    <div class="hs-admin-card" style="background:#fff;border:1.5px solid #e5e7eb;border-radius:12px;padding:18px;box-shadow:0 2px 8px rgba(0,0,0,0.05)">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
        <strong style="font-size:15px;flex:1">${escHtml(sec.section_key.toUpperCase())}</strong>
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
          <input type="checkbox" data-key="${sec.section_key}" data-field="is_active" ${sec.is_active=='1'?'checked':''}>
          Aktif
        </label>
      </div>
      <p style="font-size:12px;color:#888;margin-bottom:12px">${meta.desc}</p>
      <div class="form-group" style="margin-bottom:10px">
        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px">Judul Section</label>
        <input type="text" data-key="${sec.section_key}" data-field="title" value="${escHtml(sec.title)}" style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:7px;font-size:13px">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <div class="form-group">
          <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px">Urutan</label>
          <input type="number" min="1" max="10" data-key="${sec.section_key}" data-field="sort_order" value="${sec.sort_order}" style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:7px;font-size:13px">
        </div>
        <div class="form-group">
          <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px">Max Produk</label>
          <input type="number" min="1" max="20" data-key="${sec.section_key}" data-field="max_items" value="${sec.max_items}" style="width:100%;padding:7px 10px;border:1px solid #ddd;border-radius:7px;font-size:13px">
        </div>
      </div>
    </div>`;
  }).join('');

  // Load flag product table
  loadFlagProductTable();
}

async function loadFlagProductTable() {
  const tbody = document.getElementById('flagProductBody');
  if (!tbody) return;
  const products = await apiFetch(`${API}?action=get_products`);
  tbody.innerHTML = products.map(p => `
  <tr id="flag-row-${p.id}">
    <td><img src="${p.image||'https://via.placeholder.com/40'}" alt="" onerror="this.src='https://via.placeholder.com/40'" style="width:40px;height:40px;object-fit:cover;border-radius:6px"></td>
    <td>${escHtml(p.name)}</td>
    <td style="text-transform:capitalize;font-size:12px;color:#888">${p.category}</td>
    <td>
      <button onclick="toggleFlagProduct(${p.id},'terlaris')" id="btn-terlaris-${p.id}"
        style="background:${p.is_terlaris=='1'?'#f59e0b':'#f3f4f6'};color:${p.is_terlaris=='1'?'#fff':'#555'};border:none;border-radius:7px;padding:5px 12px;font-size:12px;font-weight:600;cursor:pointer">
         ${p.is_terlaris=='1'?'Terlaris':'Tandai'}
      </button>
    </td>
    <td>
      <button onclick="toggleFlagProduct(${p.id},'trending')" id="btn-trending-${p.id}"
        style="background:${p.is_trending=='1'?'#8b5cf6':'#f3f4f6'};color:${p.is_trending=='1'?'#fff':'#555'};border:none;border-radius:7px;padding:5px 12px;font-size:12px;font-weight:600;cursor:pointer">
         ${p.is_trending=='1'?'Trending':'Tandai'}
      </button>
    </td>
  </tr>`).join('');
}

async function toggleFlagProduct(id, type) {
  const action = type === 'terlaris' ? 'toggle_terlaris' : 'toggle_trending';
  const fd = new FormData(); fd.append('id', id);
  const res = await apiPost(`api_admin.php?action=${action}`, fd);
  if (res.success) {
    const btn = document.getElementById(`btn-${type}-${id}`);
    const isOn = res[`is_${type}`] == 1;
    const colors = { terlaris: '#f59e0b', trending: '#8b5cf6' };
    btn.style.background = isOn ? colors[type] : '#f3f4f6';
    btn.style.color = isOn ? '#fff' : '#555';
    btn.textContent = isOn ? type.charAt(0).toUpperCase()+type.slice(1) : 'Tandai';
    showToast('success', 'fa-check-circle', isOn ? `Produk ditandai sebagai ${type}!` : `Flag ${type} dihapus.`);
    loadHomeSections(); // refresh beranda
  } else showToast('error','fa-times-circle','Gagal mengubah flag');
}

async function saveHomeSections() {
  const cards = document.getElementById('homeSectionCards');
  if (!cards) return;
  const sections = _homeSectionData.map(sec => {
    const key = sec.section_key;
    const titleEl = cards.querySelector(`[data-key="${key}"][data-field="title"]`);
    const activeEl = cards.querySelector(`[data-key="${key}"][data-field="is_active"]`);
    const sortEl = cards.querySelector(`[data-key="${key}"][data-field="sort_order"]`);
    const maxEl = cards.querySelector(`[data-key="${key}"][data-field="max_items"]`);
    return {
      section_key: key,
      title: titleEl ? titleEl.value : sec.title,
      is_active: activeEl ? (activeEl.checked ? 1 : 0) : sec.is_active,
      sort_order: sortEl ? parseInt(sortEl.value) : sec.sort_order,
      max_items: maxEl ? parseInt(maxEl.value) : sec.max_items,
    };
  });
  const fd = new FormData();
  fd.append('sections', JSON.stringify(sections));
  const res = await apiPost('api_admin.php?action=admin_save_home_sections', fd);
  if (res.success) {
    showToast('success', 'fa-check-circle', 'Pengaturan beranda disimpan!');
    loadHomeSections(); // refresh tampilan beranda
  } else showToast('error','fa-times-circle','Gagal menyimpan pengaturan');
}