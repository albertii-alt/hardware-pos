<?php
require_once __DIR__ . '/../app/bootstrap.php';
requireAnyRole();



$userRole     = $_SESSION['role']     ?? 'cashier';
$username     = $_SESSION['username'] ?? '';
$isOwner      = $userRole === 'owner';
$products     = getAllProducts();
$productsJson = json_encode($products, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

require_once APP_ROOT . '/app/layout.php';
layoutStart('Lumina POS');
?>
<link rel="stylesheet" href="/lumina-pos/assets/vendor/tom-select/tom-select.bootstrap5.min.css">
<script src="/lumina-pos/assets/vendor/tom-select/tom-select.complete.min.js"></script>
<script>if (typeof TomSelect === 'undefined') console.error('Tom Select failed to load');</script>
<style>
  #product-grid .card { cursor:pointer; transition:box-shadow .15s; }
  #product-grid .card:hover { box-shadow:0 4px 14px rgba(0,0,0,.13); }
  .badge-stock { font-size:.72rem; }
  .pos-cat-label {
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: #495057;
    padding: .3rem 0;
    border-bottom: 2px solid #dee2e6;
    margin-bottom: .5rem;
  }
  #cart-panel { position:sticky; top:53px; max-height:calc(100vh - 63px); overflow-y:auto; }
  .cart-qty-btn { width:28px; height:28px; padding:0; line-height:1; }
  #receipt-body { font-family:'Courier New',monospace; font-size:.85rem; }
  
  /* IMPROVEMENT: Larger total typography */
  #order-total { font-size: 1.75rem !important; font-weight: 700; }
  .total-label { font-size: 1.1rem; font-weight: 600; }
  
  /* IMPROVEMENT: Autocomplete dropdown */
  .autocomplete-items {
    position: absolute;
    border: 1px solid #ddd;
    border-bottom: none;
    border-top: none;
    z-index: 99;
    top: 100%;
    left: 0;
    right: 0;
    max-height: 320px;
    overflow-y: auto;
    background: #fff;
    border-radius: 0 0 4px 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
  }
  .autocomplete-item {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .autocomplete-item:hover, .autocomplete-item-active {
    background-color: #e7f1ff;
  }
  .autocomplete-item.disabled {
    opacity: 0.5;
    background-color: #f8f9fa;
  }
  .autocomplete-item.disabled:hover {
    background-color: #f8f9fa;
    cursor: not-allowed;
  }
  .highlight {
    background-color: #ffeb3b;
    font-weight: bold;
  }
  .item-name { flex: 1; font-size: 0.9rem; }
  .item-sku { font-size: 0.7rem; color: #6c757d; margin-left: 8px; }
  .item-price { font-weight: bold; color: #0d6efd; margin-left: 12px; }
  .low-stock-badge { background-color: #ffc107; color: #000; font-size: 0.7rem; padding: 2px 6px; border-radius: 12px; margin-left: 8px; }
  
  /* IMPROVEMENT: Mobile improvements */
  @media (max-width: 768px) {
    .cart-table-container { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .cart-table-container table { min-width: 500px; }
    .cart-qty-btn { min-width: 44px; min-height: 44px; }
    .modal-fullscreen-mobile .modal-dialog { margin: 0; max-width: none; width: 100%; height: 100%; }
    .modal-fullscreen-mobile .modal-content { height: 100%; border-radius: 0; }
    .sticky-checkout-mobile { position: fixed; bottom: 0; left: 0; right: 0; z-index: 1000; padding: 12px; background: white; box-shadow: 0 -2px 10px rgba(0,0,0,.1); }
    body.has-sticky-checkout { padding-bottom: 80px; }
  }
  
  /* Cart row low stock highlight */
  .cart-row-low-stock { background-color: #fff3cd !important; }
  
  @media print {
    body > *:not(#receipt-print) { display:none !important; }
    #receipt-print { display:block !important; position:fixed; top:0; left:0; width:100%; font-family:'Courier New',monospace; font-size:.85rem; }
  }
  #receipt-print { display:none; }

  /* Tom Select overrides */
  .ts-wrapper { font-size: .875rem; }
  .ts-wrapper.single .ts-control { padding: .25rem .5rem; min-height: 31px; border-radius: .25rem; }
  .ts-wrapper.disabled .ts-control { background-color: #e9ecef; opacity: 1; cursor: not-allowed; }
  .ts-dropdown { z-index: 9999; font-size: .875rem; }
  .ts-dropdown .option { padding: .35rem .75rem; }
  .ts-dropdown .option:hover, .ts-dropdown .option.active { background: #e7f1ff; color: #0d6efd; }
  .ts-control input { font-size: .875rem !important; }
  .ts-wrapper.loading .ts-control::after { content: ''; display: inline-block; width: 12px; height: 12px; border: 2px solid #ccc; border-top-color: #0d6efd; border-radius: 50%; animation: ts-spin .6s linear infinite; margin-left: 6px; vertical-align: middle; }
  @keyframes ts-spin { to { transform: rotate(360deg); } }
</style>
<?php layoutHeader('POS Terminal', 'bi-shop'); ?>
<div class="container-fluid px-3">
<div class="row g-3">

  <!-- LEFT: Products -->
  <div class="col-lg-7 col-md-12">
    <!-- Search with autocomplete wrapper -->
    <div class="input-group mb-3" style="position: relative;">
      <span class="input-group-text"><i class="bi bi-search"></i></span>
      <input type="text" id="search-input" class="form-control" placeholder="Search by name, SKU, or category… (Press Enter for SKU quick-add)" autocomplete="off">
      <button class="btn btn-outline-secondary" id="btn-clear-search" title="Clear"><i class="bi bi-x-lg"></i></button>
      <div id="autocomplete-list" class="autocomplete-items" style="display: none;"></div>
    </div>

    <!-- Category pills -->
    <div id="category-pills" class="mb-3 d-flex flex-wrap gap-2"></div>

    <!-- Grid -->
    <div id="product-grid"></div>
    <p id="no-results" class="text-muted text-center mt-4 d-none">No products found.</p>
  </div>

  <!-- RIGHT: Cart + Order Form -->
  <div class="col-lg-5 col-md-12">
    <div id="cart-panel" class="card shadow-sm">
      <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <span><i class="bi bi-cart3"></i> Cart <span class="badge bg-warning text-dark ms-1" id="cart-count">0</span></span>
        <button class="btn btn-sm btn-outline-light" id="btn-clear-cart"><i class="bi bi-trash"></i> Clear</button>
      </div>

      <!-- Cart items with scrollable container -->
      <div class="card-body p-2 cart-table-container" id="cart-items-container">
        <p class="text-muted text-center py-3" id="cart-empty-msg">Cart is empty.</p>
        <table class="table table-sm mb-0 d-none" id="cart-table">
          <thead class="table-light"><tr>
            <th>Item</th><th class="text-center">Qty</th><th class="text-end">Total</th><th></th>
          </thead>
          <tbody id="cart-tbody"></tbody>
          <tfoot>
            <tr class="fw-semibold">
              <td colspan="2" class="text-end">Subtotal</td>
              <td class="text-end" id="cart-subtotal">₱0.00</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="card-body border-top pt-2 pb-3">
        <!-- Customer -->
        <h6 class="fw-bold mb-2"><i class="bi bi-person"></i> Customer</h6>
        <div class="mb-2">
          <input type="text" id="cust-name" class="form-control form-control-sm" placeholder="Full Name *">
        </div>
        <div class="mb-3">
          <input type="text" id="cust-phone" class="form-control form-control-sm" placeholder="Phone">
        </div>

        <!-- Address (shared for both pickup and delivery) -->
        <h6 class="fw-bold mb-2"><i class="bi bi-geo-alt"></i> Address</h6>
        <div class="mb-1">
          <select id="delivery-municipality" class="form-select form-select-sm" autocomplete="off">
            <option value="">Select municipality...</option>
          </select>
        </div>
        <div class="mb-1">
          <select id="delivery-barangay" class="form-select form-select-sm" autocomplete="off">
            <option value="">Select municipality first</option>
          </select>
        </div>
        <div class="mb-3">
          <input type="text" id="delivery-street" class="form-control form-control-sm" placeholder="Street / Landmark (optional)">
        </div>

        <!-- Delivery -->
        <h6 class="fw-bold mb-2"><i class="bi bi-truck"></i> Delivery</h6>
        <div class="btn-group w-100 mb-2" role="group">
          <input type="radio" class="btn-check" name="delivery-type" id="dt-pickup" value="pickup" checked>
          <label class="btn btn-sm btn-outline-secondary" for="dt-pickup"><i class="bi bi-bag-check"></i> Pickup</label>
          <input type="radio" class="btn-check" name="delivery-type" id="dt-delivery" value="delivery">
          <label class="btn btn-sm btn-outline-secondary" for="dt-delivery"><i class="bi bi-truck"></i> Delivery</label>
        </div>
        <div id="delivery-fields" class="d-none">
          <div class="mb-2">
            <input type="number" id="delivery-fee" class="form-control form-control-sm" placeholder="Delivery Fee (₱)" min="0" step="0.01" value="0">
          </div>
        </div>

        <!-- Payment -->
        <h6 class="fw-bold mb-2 mt-1"><i class="bi bi-credit-card"></i> Payment</h6>
        <div class="btn-group w-100 mb-2" role="group">
          <input type="radio" class="btn-check" name="pay-method" id="pm-cash" value="cash" checked>
          <label class="btn btn-sm btn-outline-secondary" for="pm-cash"><i class="bi bi-cash"></i> Cash</label>
          <input type="radio" class="btn-check" name="pay-method" id="pm-gcash" value="gcash">
          <label class="btn btn-sm btn-outline-secondary" for="pm-gcash"><i class="bi bi-phone"></i> GCash</label>
          <input type="radio" class="btn-check" name="pay-method" id="pm-bank" value="bank_transfer">
          <label class="btn btn-sm btn-outline-secondary" for="pm-bank"><i class="bi bi-bank"></i> Bank</label>
        </div>

        <!-- Cash fields -->
        <div id="cash-fields">
          <div class="input-group input-group-sm mb-1">
            <span class="input-group-text">₱</span>
            <input type="number" id="amount-tendered" class="form-control" placeholder="Amount Tendered" min="0" step="0.01">
          </div>
          <div class="d-flex justify-content-between px-1 mb-2">
            <span class="text-muted">Change:</span>
            <span class="fw-semibold text-success" id="change-display">₱0.00</span>
          </div>
        </div>

        <!-- Reference fields -->
        <div id="ref-fields" class="d-none mb-2">
          <input type="text" id="reference-number" class="form-control form-control-sm" placeholder="Reference Number *">
        </div>

        <!-- Sticky total row with larger typography -->
        <div class="d-flex justify-content-between align-items-center border-top pt-2 mb-3">
          <span class="total-label">Grand Total</span>
          <span class="fw-bold text-primary" id="order-total">₱0.00</span>
        </div>

        <!-- Place Order button with sticky on mobile -->
        <div class="sticky-checkout-mobile d-lg-block">
          <button class="btn btn-success w-100 fw-bold" id="btn-place-order">
            <i class="bi bi-check-circle"></i> Place Order
          </button>
        </div>
        <div id="order-error" class="alert alert-danger mt-2 py-2 d-none"></div>
      </div>
    </div>
  </div><!-- /right col -->

</div><!-- /row -->
</div><!-- /container -->

<!-- Toast Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1055;">
  <div id="success-toast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="3000">
    <div class="d-flex">
      <div class="toast-body" id="toast-message">
        Order completed!
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<!-- Receipt print target (outside modal to avoid double print) -->
<div id="receipt-print"></div>

<!-- Receipt Modal with mobile fullscreen -->
<div class="modal fade" id="receiptModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-mobile">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-receipt"></i> Order Receipt</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3" id="receipt-body">
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary" id="btn-print"><i class="bi bi-printer"></i> Print</button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
// ─── DOM Element Cache (Performance) ─────────────────────────────────────────
const DOM = {
  searchInput: document.getElementById('search-input'),
  clearSearch: document.getElementById('btn-clear-search'),
  productGrid: document.getElementById('product-grid'),
  noResults: document.getElementById('no-results'),
  cartTbody: document.getElementById('cart-tbody'),
  cartTable: document.getElementById('cart-table'),
  cartEmptyMsg: document.getElementById('cart-empty-msg'),
  cartCount: document.getElementById('cart-count'),
  cartSubtotal: document.getElementById('cart-subtotal'),
  orderTotal: document.getElementById('order-total'),
  changeDisplay: document.getElementById('change-display'),
  deliveryFee: document.getElementById('delivery-fee'),
  amountTendered: document.getElementById('amount-tendered'),
  btnClearCart: document.getElementById('btn-clear-cart'),
  btnPlaceOrder: document.getElementById('btn-place-order'),
  custName: document.getElementById('cust-name'),
  custPhone: document.getElementById('cust-phone'),
  deliveryMunicipality: document.getElementById('delivery-municipality'),
  deliveryBarangay:     document.getElementById('delivery-barangay'),
  deliveryStreet:       document.getElementById('delivery-street'),
  orderError: document.getElementById('order-error'),
  dtPickup: document.getElementById('dt-pickup'),
  dtDelivery: document.getElementById('dt-delivery'),
  pmCash: document.getElementById('pm-cash'),
  deliveryFields: document.getElementById('delivery-fields'),
  cashFields: document.getElementById('cash-fields'),
  refFields: document.getElementById('ref-fields')
};

// ─── Data ────────────────────────────────────────────────────────────────────
const ALL_PRODUCTS = <?= $productsJson ?>;
const userRole     = <?= json_encode($userRole) ?>;
const isOwner      = userRole === 'owner';
let cart = {}; // { productId: { id, name, price, quantity, stock } }
let searchTimeout = null;
let currentFocus = -1;

// ─── Category pills ──────────────────────────────────────────────────────────
(function buildCategories() {
  const cats = [...new Set(ALL_PRODUCTS.map(p => p.category).filter(Boolean))].sort();
  const container = document.getElementById('category-pills');
  const all = document.createElement('button');
  all.className = 'btn btn-sm btn-dark active-cat';
  all.dataset.cat = '';
  all.textContent = 'All';
  all.onclick = () => selectCat(all, '');
  container.appendChild(all);
  cats.forEach(c => {
    const btn = document.createElement('button');
    btn.className = 'btn btn-sm btn-outline-secondary';
    btn.dataset.cat = c;
    btn.textContent = c;
    btn.onclick = () => selectCat(btn, c);
    container.appendChild(btn);
  });
})();

let activeCategory = '';
function selectCat(el, cat) {
  document.querySelectorAll('#category-pills button').forEach(b => {
    b.classList.remove('btn-dark', 'active-cat');
    b.classList.add('btn-outline-secondary');
  });
  el.classList.remove('btn-outline-secondary');
  el.classList.add('btn-dark', 'active-cat');
  activeCategory = cat;
  renderProducts();
}

// ─── Product grid (unchanged but efficient) ──────────────────────────────────
function renderProducts() {
  const q = DOM.searchInput.value.trim().toLowerCase();
  const grid = DOM.productGrid;
  const noRes = DOM.noResults;
  grid.innerHTML = '';

  const filtered = ALL_PRODUCTS.filter(p => {
    const matchCat = !activeCategory || p.category === activeCategory;
    const matchQ   = !q ||
      (p.name     || '').toLowerCase().includes(q) ||
      (p.sku      || '').toLowerCase().includes(q) ||
      (p.category || '').toLowerCase().includes(q);
    return matchCat && matchQ;
  });

  noRes.classList.toggle('d-none', filtered.length > 0);
  if (!filtered.length) return;

  function makeCard(p) {
    const inCart     = cart[p.id] ? cart[p.id].quantity : 0;
    const outOfStock = parseInt(p.stock) <= 0;
    const isLowStock = parseInt(p.stock) <= parseInt(p.min_stock_alert || 0);
    const col = document.createElement('div');
    col.className = 'col';
    col.innerHTML = `
      <div class="card h-100 ${outOfStock ? 'opacity-50' : ''}" data-id="${p.id}">
        <div class="card-body p-2 d-flex flex-column">
          <div class="d-flex justify-content-between align-items-start mb-1">
            <span class="badge bg-secondary badge-stock">${escHtml(p.category || '—')}</span>
            <span class="badge ${isLowStock ? 'bg-warning text-dark' : (parseInt(p.stock) <= 0 ? 'bg-danger' : 'bg-success')} badge-stock">
              ${parseInt(p.stock)} left ${isLowStock ? '⚠️' : ''}
            </span>
          </div>
          <div class="fw-semibold lh-sm mb-1" style="font-size:.85rem">${escHtml(p.name)}</div>
          <div class="text-muted" style="font-size:.75rem">${escHtml(p.sku || '')}</div>
          <div class="mt-auto pt-2 d-flex justify-content-between align-items-center">
            <span class="fw-bold text-primary">₱${parseFloat(p.selling_price).toFixed(2)}</span>
            ${inCart > 0 ? `<span class="badge bg-warning text-dark">${inCart} in cart</span>` : ''}
          </div>
        </div>
        <div class="card-footer p-1">
          <button class="btn btn-sm btn-primary w-100" onclick="addToCart(${p.id})"
            ${outOfStock ? 'disabled' : ''}>
            <i class="bi bi-plus-circle"></i> Add
          </button>
        </div>
      </div>`;
    return col;
  }

  if (activeCategory || q) {
    const row = document.createElement('div');
    row.className = 'row row-cols-2 row-cols-sm-3 row-cols-xl-4 g-2';
    filtered.forEach(p => row.appendChild(makeCard(p)));
    grid.appendChild(row);
  } else {
    const groups = {};
    filtered.forEach(p => {
      const cat = p.category || 'Uncategorised';
      if (!groups[cat]) groups[cat] = [];
      groups[cat].push(p);
    });
    Object.keys(groups).sort().forEach(cat => {
      const section = document.createElement('div');
      section.className = 'mb-3';
      const heading = document.createElement('div');
      heading.className = 'pos-cat-label';
      heading.textContent = cat;
      const row = document.createElement('div');
      row.className = 'row row-cols-2 row-cols-sm-3 row-cols-xl-4 g-2';
      groups[cat].forEach(p => row.appendChild(makeCard(p)));
      section.appendChild(heading);
      section.appendChild(row);
      grid.appendChild(section);
    });
  }
}

// ─── Autocomplete Feature ────────────────────────────────────────────────────
function showAutocomplete(filterText) {
  if (!filterText || filterText.length < 1) {
    document.getElementById('autocomplete-list').style.display = 'none';
    return;
  }
  
  const matches = ALL_PRODUCTS.filter(p => 
    (p.name || '').toLowerCase().includes(filterText) ||
    (p.sku || '').toLowerCase().includes(filterText) ||
    (p.category || '').toLowerCase().includes(filterText)
  ).slice(0, 8);
  
  if (matches.length === 0) {
    document.getElementById('autocomplete-list').style.display = 'none';
    return;
  }
  
  const listDiv = document.getElementById('autocomplete-list');
  listDiv.innerHTML = '';
  currentFocus = -1;
  
  matches.forEach((p, idx) => {
    const item = document.createElement('div');
    item.className = 'autocomplete-item';
    if (parseInt(p.stock) <= 0) item.classList.add('disabled');
    
    const nameHighlighted = (p.name || '').replace(new RegExp(`(${filterText})`, 'gi'), '<span class="highlight">$1</span>');
    const skuHighlighted = (p.sku || '').replace(new RegExp(`(${filterText})`, 'gi'), '<span class="highlight">$1</span>');
    
    item.innerHTML = `
      <div class="item-name">
        ${nameHighlighted}
        <small class="item-sku">${skuHighlighted}</small>
        ${parseInt(p.stock) <= parseInt(p.min_stock_alert || 0) ? '<span class="low-stock-badge">Low Stock</span>' : ''}
      </div>
      <div class="item-price">₱${parseFloat(p.selling_price).toFixed(2)}</div>
    `;
    
    item.onclick = () => {
      if (parseInt(p.stock) > 0) {
        addToCart(p.id);
        DOM.searchInput.value = '';
        listDiv.style.display = 'none';
      }
    };
    
    listDiv.appendChild(item);
  });
  
  listDiv.style.display = 'block';
}

function closeAutocomplete() {
  document.getElementById('autocomplete-list').style.display = 'none';
  currentFocus = -1;
}

function navigateAutocomplete(direction) {
  const items = document.querySelectorAll('.autocomplete-item:not(.disabled)');
  if (items.length === 0) return false;
  
  if (direction === 'down') {
    currentFocus = (currentFocus + 1) % items.length;
  } else if (direction === 'up') {
    currentFocus = (currentFocus - 1 + items.length) % items.length;
  }
  
  items.forEach((item, idx) => {
    if (idx === currentFocus) {
      item.classList.add('autocomplete-item-active');
      item.scrollIntoView({ block: 'nearest' });
    } else {
      item.classList.remove('autocomplete-item-active');
    }
  });
  
  return true;
}

function selectCurrentAutocomplete() {
  const items = document.querySelectorAll('.autocomplete-item.active, .autocomplete-item-autocomplete-item-active');
  const activeItem = document.querySelector('.autocomplete-item-active');
  if (activeItem && !activeItem.classList.contains('disabled')) {
    activeItem.click();
    return true;
  }
  return false;
}

// ─── Barcode Quick Entry ─────────────────────────────────────────────────────
function quickSkuAdd(sku) {
  const product = ALL_PRODUCTS.find(p => p.sku && p.sku.toLowerCase() === sku.toLowerCase());
  if (product) {
    addToCart(product.id);
    DOM.searchInput.value = '';
    closeAutocomplete();
    return true;
  }
  return false;
}

// ─── Cart logic with low stock warnings ──────────────────────────────────────
function addToCart(productId) {
  const p = ALL_PRODUCTS.find(x => x.id == productId);
  if (!p) return;
  const maxStock = parseInt(p.stock);
  const isLowStock = maxStock <= parseInt(p.min_stock_alert || 0);
  
  if (cart[productId]) {
    if (cart[productId].quantity >= maxStock) {
      showOrderError(`Only ${maxStock} unit(s) of "${p.name}" available.`);
      return;
    }
    cart[productId].quantity++;
  } else {
    cart[productId] = { 
      id: productId, 
      name: p.name, 
      price: parseFloat(p.selling_price), 
      quantity: 1,
      stock: maxStock,
      lowStock: isLowStock
    };
  }
  
  if (isLowStock && cart[productId].quantity === 1) {
    showOrderError(`⚠️ Low stock warning: Only ${maxStock} left of "${p.name}"`, 2000);
  }
  
  renderCart();
  renderProducts();
}

function removeFromCart(productId) {
  delete cart[productId];
  renderCart();
  renderProducts();
}

function setQty(productId, qty) {
  qty = parseInt(qty);
  if (isNaN(qty) || qty <= 0) { removeFromCart(productId); return; }
  const p = ALL_PRODUCTS.find(x => x.id == productId);
  const maxStock = p ? parseInt(p.stock) : Infinity;
  if (qty > maxStock) {
    showOrderError(`Only ${maxStock} available. Setting to max.`);
    qty = maxStock;
  }
  cart[productId].quantity = qty;
  renderCart();
  renderProducts();
}

function cartSubtotal() {
  return Object.values(cart).reduce((s, i) => s + i.price * i.quantity, 0);
}

function renderCart() {
  const items    = Object.values(cart);
  const isEmpty  = items.length === 0;
  const emptyMsg = DOM.cartEmptyMsg;
  const table    = DOM.cartTable;
  const tbody    = DOM.cartTbody;
  const count    = DOM.cartCount;

  emptyMsg.classList.toggle('d-none', !isEmpty);
  table.classList.toggle('d-none', isEmpty);
  count.textContent = items.reduce((s, i) => s + i.quantity, 0);

  tbody.innerHTML = '';
  items.forEach(item => {
    const p = ALL_PRODUCTS.find(x => x.id == item.id);
    const isLowStock = p && parseInt(p.stock) <= parseInt(p.min_stock_alert || 0);
    const rowClass = isLowStock && item.quantity > 0 ? 'cart-row-low-stock' : '';
    
    const tr = document.createElement('tr');
    tr.className = rowClass;
    tr.innerHTML = `
      <td class="align-middle" style="max-width:110px;word-break:break-word">
        ${escHtml(item.name)}
        ${isLowStock ? '<br><small class="text-warning">⚠️ Low Stock</small>' : ''}
      </td>
      <td class="align-middle text-center" style="min-width:110px">
        <div class="d-flex align-items-center justify-content-center gap-1">
          <button class="btn btn-outline-secondary cart-qty-btn" style="min-width:44px;min-height:44px;" onclick="setQty(${item.id}, ${item.quantity - 1})">−</button>
          <input type="number" class="form-control form-control-sm text-center p-0" style="width:50px" value="${item.quantity}" min="1" onchange="setQty(${item.id}, this.value)">
          <button class="btn btn-outline-secondary cart-qty-btn" style="min-width:44px;min-height:44px;" onclick="setQty(${item.id}, ${item.quantity + 1})">+</button>
        </div>
       </td>
      <td class="align-middle text-end">₱${(item.price * item.quantity).toFixed(2)}</td>
      <td class="align-middle text-center">
        <button class="btn btn-sm btn-outline-danger p-0 px-1" style="min-width:44px;" onclick="removeFromCart(${item.id})">
          <i class="bi bi-x"></i>
        </button>
      </td>`;
    tbody.appendChild(tr);
  });

  DOM.cartSubtotal.textContent = '₱' + cartSubtotal().toFixed(2);
  updateTotals();
}

// ─── Clear Cart with Confirmation ────────────────────────────────────────────
DOM.btnClearCart.addEventListener('click', () => {
  if (!Object.keys(cart).length) return;
  if (confirm('Clear the entire cart?')) { cart = {}; renderCart(); renderProducts(); }
});

// ─── Delivery / Payment toggles ───────────────────────────────────────────────
document.querySelectorAll('input[name="delivery-type"]').forEach(r => {
  r.addEventListener('change', () => {
    const isDelivery = r.value === 'delivery' && r.checked;
    DOM.deliveryFields.classList.toggle('d-none', !isDelivery);
    updateTotals();
  });
});

DOM.deliveryFee.addEventListener('input', updateTotals);

document.querySelectorAll('input[name="pay-method"]').forEach(r => {
  r.addEventListener('change', () => {
    const isCash = DOM.pmCash.checked;
    DOM.cashFields.classList.toggle('d-none', !isCash);
    DOM.refFields.classList.toggle('d-none', isCash);
    updateTotals();
  });
});

DOM.amountTendered.addEventListener('input', updateTotals);

function deliveryFee() {
  const isDelivery = DOM.dtDelivery.checked;
  return isDelivery ? (parseFloat(DOM.deliveryFee.value) || 0) : 0;
}

function updateTotals() {
  const total = cartSubtotal() + deliveryFee();
  DOM.orderTotal.textContent = '₱' + total.toFixed(2);
  if (DOM.pmCash.checked) {
    const tendered = parseFloat(DOM.amountTendered.value) || 0;
    const change   = tendered - total;
    DOM.changeDisplay.textContent = '₱' + (change >= 0 ? change.toFixed(2) : '0.00');
    DOM.changeDisplay.className   = 'fw-semibold ' + (change >= 0 ? 'text-success' : 'text-danger');
  }
}

// ─── Toast Notification ──────────────────────────────────────────────────────
function showSuccessToast(message, redirectUrl = null) {
  const toastEl = document.getElementById('success-toast');
  const toastBody = document.getElementById('toast-message');
  toastBody.textContent = message;
  const toast = new bootstrap.Toast(toastEl, { autohide: true, delay: 2000 });
  toast.show();
  
  if (redirectUrl) {
    setTimeout(() => {
      window.location.href = redirectUrl;
    }, 2000);
  }
}

// ─── Place Order with Keyboard Support ───────────────────────────────────────
async function submitOrder() {
  hideOrderError();

  if (!Object.keys(cart).length) { showOrderError('Cart is empty.'); return false; }

  const custName = DOM.custName.value.trim();
  const custPhone = DOM.custPhone.value.trim();
  if (!custName) { showOrderError('Customer name is required.'); return false; }

  const munName = tsMunicipality && tsMunicipality.getValue()
    ? (tsMunicipality.options[tsMunicipality.getValue()] || {}).text || ''
    : '';
  const barName = tsBarangay && tsBarangay.getValue()
    ? (tsBarangay.options[tsBarangay.getValue()] || {}).text || ''
    : '';
  const street  = DOM.deliveryStreet ? DOM.deliveryStreet.value.trim() : '';
  if (!tsMunicipality || !tsMunicipality.getValue()) { showOrderError('Please select a municipality.'); return false; }
  if (!tsBarangay    || !tsBarangay.getValue())    { showOrderError('Please select a barangay.'); return false; }
  const composedAddress = composeAddress(street, barName, munName);

  const isDelivery = DOM.dtDelivery.checked;

  const payMethod = document.querySelector('input[name="pay-method"]:checked').value;
  const total     = cartSubtotal() + deliveryFee();

  let paymentData = { method: payMethod };
  if (payMethod === 'cash') {
    const tendered = parseFloat(DOM.amountTendered.value) || 0;
    if (tendered < total) { showOrderError('Amount tendered is less than the total.'); return false; }
    paymentData.amount_tendered = tendered;
  } else {
    const ref = document.getElementById('reference-number').value.trim();
    if (!ref) { showOrderError('Reference number is required.'); return false; }
    paymentData.reference_number = ref;
  }

  const cartItems = Object.values(cart).map(i => ({
    id: i.id, name: i.name, price: i.price, quantity: i.quantity
  }));

  const btn = DOM.btnPlaceOrder;
  const originalHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing…';

  // Generate idempotency token for this submission
  const requestToken = 'rt-' + Date.now() + '-' + Math.random().toString(36).slice(2, 9);

  try {
    const res = await fetch('save_order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({
        customer:      { name: custName, address: composedAddress, phone: custPhone },
        delivery:      { type: isDelivery ? 'delivery' : 'pickup', fee: deliveryFee(), address: composedAddress,
                         municipality_id: tsMunicipality ? parseInt(tsMunicipality.getValue()) || null : null,
                         barangay_id:     tsBarangay     ? parseInt(tsBarangay.getValue())     || null : null },
        payment:       paymentData,
        cart:          cartItems,
        request_token: requestToken,
      }),
    });
    const data = await res.json();

    if (!data.success) {
      showOrderError(data.error || 'Order failed.');
      btn.disabled = false;
      btn.innerHTML = originalHtml;
      return false;
    }

    // Store order ID for toast
    const orderId = data.order_id || '';

    // Clear cart + reset form
    cart = {};
    renderCart();
    renderProducts();
    resetForm();

    // Show success toast and redirect
    if (data.receipt_url) {
      showSuccessToast(`Order #${orderId} completed`, data.receipt_url);
    } else if (data.receipt_data) {
      showSuccessToast(`Order #${orderId} completed`);
      setTimeout(() => showReceipt(data.receipt_data), 2000);
    }
    
    return true;

  } catch (err) {
    showOrderError('Network error: ' + err.message);
    btn.disabled = false;
    btn.innerHTML = originalHtml;
    return false;
  }
}

function resetForm() {
  ['cust-name','cust-phone','delivery-fee','delivery-street',
   'amount-tendered','reference-number'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  if (tsMunicipality) tsMunicipality.clear(true);
  if (tsBarangay) {
    tsBarangay.clear(true);
    tsBarangay.clearOptions();
    tsBarangay.settings.placeholder = 'Select municipality first';
    tsBarangay.inputState();
    tsBarangay.disable();
  }
  DOM.dtPickup.checked = true;
  DOM.pmCash.checked   = true;
  DOM.deliveryFields.classList.add('d-none');
  DOM.cashFields.classList.remove('d-none');
  DOM.refFields.classList.add('d-none');
  updateTotals();
}

// ─── Receipt ──────────────────────────────────────────────────────────────────
function showReceipt(r) {
  const items = (r.items || []).map(i =>
    `<tr><td class="text-start">${escHtml(i.name)}</td><td class="text-center">${i.quantity}</td>
     <td class="text-end">₱${parseFloat(i.price).toFixed(2)}</td>
     <td class="text-end">₱${(i.price * i.quantity).toFixed(2)}</td></tr>`
  ).join('');

  const payLine = r.payment_method === 'cash'
    ? `<tr><td>Tendered</td><td colspan="3" class="text-end">₱${parseFloat(r.amount_tendered||0).toFixed(2)}</td></tr>
        <tr><td>Change</td><td colspan="3" class="text-end">₱${parseFloat(r.change_due||0).toFixed(2)}</td></tr>`
    : `<tr><td>Ref #</td><td colspan="3" class="text-end">${escHtml(r.reference_number||'')}</td></tr>`;

  const html = `
    <div class="text-center mb-2">
      <strong style="font-size:1.1rem">LUMINA HARDWARE</strong><br>
      <small>Official Receipt</small>
    </div>
    <hr class="my-1">
    <table class="table table-borderless table-sm mb-1" style="font-size:.8rem">
      <tr><td><b>Order #</b></td><td colspan="3">${r.order_id}</td></tr>
      <tr><td><b>Date</b></td><td colspan="3">${r.date}</td></tr>
      <tr><td><b>Customer</b></td><td colspan="3">${escHtml(r.customer_name)}</td></tr>
      <tr><td><b>Address</b></td><td colspan="3">${escHtml(r.customer_address)}</td></tr>
      ${r.customer_phone ? `<tr><td><b>Phone</b></td><td colspan="3">${escHtml(r.customer_phone)}</td></tr>` : ''}
      <tr><td><b>Delivery</b></td><td colspan="3">${r.delivery_type === 'delivery' ? 'Delivery' : 'Pickup'}</td></tr>
      ${r.delivery_type === 'delivery' ? `<tr><td><b>Del. Addr</b></td><td colspan="3">${escHtml(r.delivery_address||'')}</td></tr>` : ''}
    </table>
    <hr class="my-1">
    <table class="table table-sm mb-1" style="font-size:.8rem">
      <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead>
      <tbody>${items}</tbody>
    </table>
    <hr class="my-1">
    <table class="table table-borderless table-sm mb-0" style="font-size:.8rem">
      <tr><td>Subtotal</td><td colspan="3" class="text-end">₱${parseFloat(r.subtotal).toFixed(2)}</td></tr>
      <tr><td>Delivery Fee</td><td colspan="3" class="text-end">₱${parseFloat(r.delivery_fee||0).toFixed(2)}</td></tr>
      <tr class="fw-bold"><td>TOTAL</td><td colspan="3" class="text-end">₱${parseFloat(r.total).toFixed(2)}</td></tr>
      <tr><td>Payment</td><td colspan="3" class="text-end">${r.payment_method.toUpperCase()}</td></tr>
      ${payLine}
    </table>
    <hr class="my-1">
    <div class="text-center small mt-2">Thank you for your purchase!</div>`;

  document.getElementById('receipt-body').innerHTML  = html;
  document.getElementById('receipt-print').innerHTML = html;
  new bootstrap.Modal(document.getElementById('receiptModal')).show();
}

document.getElementById('btn-print').addEventListener('click', () => window.print());

// ─── Keyboard Shortcuts ──────────────────────────────────────────────────────
function isTypingActive() {
  const activeEl = document.activeElement;
  return activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.isContentEditable);
}

document.addEventListener('keydown', (e) => {
  // F2 → Focus search
  if (e.key === 'F2') {
    e.preventDefault();
    DOM.searchInput.focus();
    return;
  }
  
  // F4 → Open receipt modal (if last order exists)
  if (e.key === 'F4') {
    e.preventDefault();
    const receiptModal = document.getElementById('receiptModal');
    if (receiptModal && document.getElementById('receipt-body').innerHTML) {
      new bootstrap.Modal(receiptModal).show();
    }
    return;
  }
  
  // ESC → Close modals and autocomplete
  if (e.key === 'Escape') {
    closeAutocomplete();
    const modals = document.querySelectorAll('.modal.show');
    modals.forEach(modal => {
      const instance = bootstrap.Modal.getInstance(modal);
      if (instance) instance.hide();
    });
    return;
  }
  
  // CTRL + DELETE → Clear cart confirmation
  if (e.ctrlKey && e.key === 'Delete') {
    e.preventDefault();
    if (!isTypingActive() && Object.keys(cart).length > 0) {
      if (confirm('Clear entire cart?')) {
        cart = {};
        renderCart();
        renderProducts();
        showSuccessToast('Cart cleared');
      }
    }
    return;
  }
  
  // CTRL + ENTER → Submit order
  if (e.ctrlKey && e.key === 'Enter') {
    e.preventDefault();
    if (!isTypingActive()) {
      submitOrder();
    }
    return;
  }
  
  // Autocomplete navigation
  if (document.getElementById('autocomplete-list').style.display === 'block') {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      navigateAutocomplete('down');
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      navigateAutocomplete('up');
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (selectCurrentAutocomplete()) return;
    }
  }
  
  // Barcode quick-add on Enter in search (if no autocomplete selection)
  if (e.key === 'Enter' && document.activeElement === DOM.searchInput) {
    const val = DOM.searchInput.value.trim();
    if (val && !selectCurrentAutocomplete()) {
      e.preventDefault();
      if (!quickSkuAdd(val)) {
        // Not a SKU, just search normally
        renderProducts();
      }
    }
  }
});

// ─── Search with Debouncing (150ms) ─────────────────────────────────────────
DOM.searchInput.addEventListener('input', (e) => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    const val = e.target.value.trim().toLowerCase();
    if (val.length >= 1) {
      showAutocomplete(val);
    } else {
      closeAutocomplete();
    }
    renderProducts();
  }, 150);
});

// Click outside closes autocomplete
document.addEventListener('click', (e) => {
  if (!DOM.searchInput.contains(e.target) && !document.getElementById('autocomplete-list')?.contains(e.target)) {
    closeAutocomplete();
  }
});

DOM.clearSearch.addEventListener('click', () => {
  DOM.searchInput.value = '';
  closeAutocomplete();
  renderProducts();
});

// ─── Place Order button handler ─────────────────────────────────────────────
DOM.btnPlaceOrder.addEventListener('click', submitOrder);

// ─── Helpers ──────────────────────────────────────────────────────────────────
function escHtml(str) {
  return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function showOrderError(msg, duration = 5000) {
  const el = DOM.orderError;
  el.textContent = msg;
  el.classList.remove('d-none');
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  if (duration) setTimeout(() => hideOrderError(), duration);
}
function hideOrderError() {
  DOM.orderError.classList.add('d-none');
}

// ─── Bohol Address Cascade (Tom Select) ─────────────────────────────────────
const LOC_API = 'api/locations.php';

// Compose final address string, skipping empty parts
function composeAddress(street, barangay, municipality) {
  return [street, barangay, municipality, 'Bohol']
    .map(s => (s || '').trim())
    .filter(Boolean)
    .join(', ');
}

// ── Load barangays on municipality change ─────────────────────────────────────
async function loadBarangays(municipalityId) {
  if (!tsBarangay) return;
  const wrapper = tsBarangay.wrapper;
  wrapper.classList.add('loading');
  tsBarangay.disable();
  tsBarangay.settings.placeholder = 'Loading…';
  tsBarangay.inputState();
  try {
    const res  = await fetch(`${LOC_API}?action=barangays&municipality_id=${encodeURIComponent(municipalityId)}`);
    const rows = await res.json();
    tsBarangay.clear(true);
    tsBarangay.clearOptions();
    if (rows.length) {
      const opts = rows.map(b => ({ value: String(b.id), text: b.name }));
      tsBarangay.settings.maxOptions = opts.length;
      tsBarangay.addOptions(opts);
      tsBarangay.settings.placeholder = 'Select barangay...';
      tsBarangay.enable();
    } else {
      tsBarangay.settings.placeholder = 'No barangays found';
    }
    tsBarangay.inputState();
  } catch(e) {
    tsBarangay.settings.placeholder = 'Failed to load';
    tsBarangay.inputState();
    tsBarangay.enable();
  } finally {
    wrapper.classList.remove('loading');
  }
}

// ── Auto-fill helpers (for programmatic selection) ───────────────────────────
function tsSetMunicipality(id) {
  if (!tsMunicipality) return;
  tsMunicipality.setValue(String(id), true);
}
async function tsSetBarangay(municipalityId, barangayId) {
  if (!tsBarangay) return;
  await loadBarangays(municipalityId);
  tsBarangay.setValue(String(barangayId), true);
}

// ─── Init ─────────────────────────────────────────────────────────────────────
renderProducts();
renderCart();

// ── Tom Select init (AFTER renderProducts to avoid DOM side-effects) ──────────
let tsMunicipality = null;
let tsBarangay     = null;

tsMunicipality = new TomSelect('#delivery-municipality', {
  valueField:  'value',
  labelField:  'text',
  searchField: ['text'],
  placeholder: 'Select municipality...',
  maxItems:    1,
  maxOptions:  500,
  preload:     true,
  load(query, callback) {
    fetch(LOC_API + '?action=municipalities')
      .then(r => r.json())
      .then(rows => callback(rows.map(m => ({ value: String(m.id), text: m.name }))))
      .catch(() => callback());
  },
  render: {
    no_results: () => '<div class="no-results px-3 py-2">No municipality found</div>'
  },
  onChange(mid) {
    if (!tsBarangay) return;
    tsBarangay.clear(true);
    tsBarangay.clearOptions();
    tsBarangay.settings.placeholder = mid ? 'Loading…' : 'Select municipality first';
    tsBarangay.inputState();
    tsBarangay.disable();
    if (mid) loadBarangays(mid);
  }
});

tsBarangay = new TomSelect('#delivery-barangay', {
  valueField:  'value',
  labelField:  'text',
  searchField: ['text'],
  placeholder: 'Select municipality first',
  maxItems:    1,
  maxOptions:  500,
  render: {
    no_results: () => '<div class="no-results px-3 py-2">No barangay found</div>'
  }
});
tsBarangay.disable();

// Add mobile body class
if (window.innerWidth <= 768) {
  document.body.classList.add('has-sticky-checkout');
}
</script>
<?php layoutEnd(); ?>