<?php
require_once __DIR__ . '/../app/bootstrap.php';
requireRole('owner');



$products = getAllProducts();

// Group by category, uncategorised goes last
$grouped = [];
foreach ($products as $p) {
    $cat = trim($p['category'] ?? '') ?: 'Uncategorised';
    $grouped[$cat][] = $p;
}
ksort($grouped);

$categories = array_keys($grouped);
$total   = count($products);
$outOf   = count(array_filter($products, fn($p) => (int)$p['stock'] === 0));
$lowSt   = count(array_filter($products, fn($p) => (int)$p['stock'] > 0 && (int)$p['stock'] <= (int)($p['min_stock_alert'] ?: 5)));
$healthy = $total - $outOf - $lowSt;

require_once APP_ROOT . '/app/layout.php';
layoutStart('Lumina POS – Products');
?>
<style>
  /* ── Toolbar ── */
  .inv-toolbar {
    position: sticky;
    top: 53px;
    z-index: 90;
    background: #f4f6f9;
    padding: .75rem 0 .5rem;
  }

  /* ── Category pills ── */
  #cat-pills .btn { border-radius: 2rem; font-size: .78rem; padding: .25rem .75rem; }
  #cat-pills .btn.active-cat { background: #1a1d23; color: #fff; border-color: #1a1d23; }

  /* ── Category label row ── */
  .cat-section { margin-bottom: 0; }
  .cat-section-header {
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #495057;
    padding: .45rem .75rem !important;
    background: #f0f2f5 !important;
    border-left: 3px solid #0d6efd;
    border-top: 2px solid #dee2e6 !important;
  }

  /* ── Table ── */
  .inv-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #6c757d; white-space: nowrap; }
  .inv-table td { vertical-align: middle; font-size: .875rem; }
  .inv-table tbody tr:hover { background: #f8f9ff; }
  .inv-table tbody tr.row-danger  { background: #fff5f5; }
  .inv-table tbody tr.row-warning { background: #fffbf0; }

  /* ── Stock badges ── */
  .stock-ok  { background: #d1fae5; color: #065f46; }
  .stock-low { background: #fef3c7; color: #92400e; }
  .stock-out { background: #fee2e2; color: #991b1b; }

  /* ── Action buttons ── */
  .btn-act { width: 30px; height: 30px; padding: 0; font-size: .8rem; }

  /* ── Empty state ── */
  #empty-state { display: none; }

  /* ── Modal field groups ── */
  .field-group-label {
    font-size: .68rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .06em; color: #9aa0ac; margin-bottom: .35rem; margin-top: .85rem;
  }
</style>

<?php layoutHeader('Products', 'bi-box-seam'); ?>
<div class="container-fluid px-4">

  <!-- Toolbar -->
  <div class="inv-toolbar">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
      <div class="input-group input-group-sm" style="max-width:280px">
        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
        <input type="text" id="inv-search" class="form-control border-start-0 ps-0" placeholder="Search name, SKU, category…">
      </div>
      <button class="btn btn-sm btn-outline-warning" id="btn-low-stock-filter" title="Show low/out of stock only">
        <i class="bi bi-exclamation-triangle"></i> Low Stock
      </button>
      <div class="ms-auto d-flex gap-2">
        <a href="export_reports.php?type=all_products" class="btn btn-sm btn-outline-success">
          <i class="bi bi-download me-1"></i>Products CSV
        </a>
        <a href="export_reports.php?type=low_stock" class="btn btn-sm btn-outline-success">
          <i class="bi bi-download me-1"></i>Low Stock CSV
        </a>
        <button class="btn btn-sm btn-outline-secondary" id="btn-csv-import">
          <i class="bi bi-upload"></i> Import CSV
        </button>
        <button class="btn btn-sm btn-primary" id="btn-add-product">
          <i class="bi bi-plus-lg"></i> Add Product
        </button>
      </div>
    </div>
    <!-- Category pills -->
    <div id="cat-pills" class="d-flex flex-wrap gap-1 pb-1">
      <button class="btn btn-sm btn-outline-secondary active-cat" data-cat="">All</button>
      <?php foreach ($categories as $cat): ?>
      <button class="btn btn-sm btn-outline-secondary" data-cat="<?= htmlspecialchars($cat) ?>">
        <?= htmlspecialchars($cat) ?>
        <span class="badge bg-secondary ms-1" style="font-size:.65rem"><?= count($grouped[$cat]) ?></span>
      </button>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Summary badges -->
  <div class="d-flex gap-3 mb-3 flex-wrap">
    <span class="badge rounded-pill bg-light text-dark border"><i class="bi bi-box-seam me-1"></i><?= $total ?> Total</span>
    <span class="badge rounded-pill stock-ok"><i class="bi bi-check-circle me-1"></i><?= $healthy ?> Healthy</span>
    <span class="badge rounded-pill stock-low"><i class="bi bi-exclamation-circle me-1"></i><?= $lowSt ?> Low Stock</span>
    <span class="badge rounded-pill stock-out"><i class="bi bi-x-circle me-1"></i><?= $outOf ?> Out of Stock</span>
  </div>

  <!-- Product table (single table, category label rows inside) -->
  <div class="card shadow-sm border-0">
    <div class="table-responsive">
      <table class="table inv-table mb-0" id="inv-table">
        <thead class="table-light">
          <tr>
            <th>SKU</th>
            <th>Name</th>
            <th>Unit</th>
            <th class="text-end">Cost</th>
            <th class="text-end">Price</th>
            <th class="text-center">Stock</th>
            <th class="text-center">Status</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <?php foreach ($grouped as $cat => $catProducts):
          $catOut = count(array_filter($catProducts, fn($p) => (int)$p['stock'] === 0));
          $catLow = count(array_filter($catProducts, fn($p) => (int)$p['stock'] > 0 && (int)$p['stock'] <= (int)($p['min_stock_alert'] ?: 5)));
        ?>
        <tbody class="cat-section" data-cat="<?= htmlspecialchars($cat) ?>">
          <!-- Category label row -->
          <tr class="cat-label-row">
            <td colspan="8" class="cat-section-header">
              <?= htmlspecialchars($cat) ?>
              <span class="text-muted fw-normal ms-2"><?= count($catProducts) ?> item<?= count($catProducts) !== 1 ? 's' : '' ?></span>
              <?php if ($catOut > 0): ?><span class="badge stock-out ms-2"><?= $catOut ?> out</span><?php endif; ?>
              <?php if ($catLow > 0): ?><span class="badge stock-low ms-1"><?= $catLow ?> low</span><?php endif; ?>
            </td>
          </tr>
          <?php foreach ($catProducts as $p):
            $stock    = (int)$p['stock'];
            $minAlert = (int)($p['min_stock_alert'] ?: 5);
            $isOut    = $stock === 0;
            $isLow    = !$isOut && $stock <= $minAlert;
            $rowCls   = $isOut ? 'row-danger' : ($isLow ? 'row-warning' : '');
            $badgeCls = $isOut ? 'stock-out' : ($isLow ? 'stock-low' : 'stock-ok');
            $badgeTxt = $isOut ? 'Out of Stock' : ($isLow ? 'Low Stock' : 'Healthy');
          ?>
          <tr class="<?= $rowCls ?>"
              data-id="<?= $p['id'] ?>"
              data-name="<?= htmlspecialchars($p['name']) ?>"
              data-sku="<?= htmlspecialchars($p['sku'] ?? '') ?>"
              data-category="<?= htmlspecialchars($p['category'] ?? '') ?>"
              data-unit="<?= htmlspecialchars($p['unit'] ?? '') ?>"
              data-cost="<?= $p['cost_price'] ?>"
              data-price="<?= $p['selling_price'] ?>"
              data-stock="<?= $stock ?>"
              data-min="<?= $minAlert ?>"
              data-status="<?= $isOut ? 'out' : ($isLow ? 'low' : 'ok') ?>">
            <td class="text-muted" style="font-size:.78rem"><?= htmlspecialchars($p['sku'] ?? '—') ?></td>
            <td class="fw-semibold"><?= htmlspecialchars($p['name']) ?></td>
            <td class="text-muted"><?= htmlspecialchars($p['unit'] ?? '—') ?></td>
            <td class="text-end">₱<?= number_format((float)$p['cost_price'], 2) ?></td>
            <td class="text-end fw-semibold text-primary">₱<?= number_format((float)$p['selling_price'], 2) ?></td>
            <td class="text-center fw-bold <?= $isOut ? 'text-danger' : ($isLow ? 'text-warning' : '') ?>"><?= $stock ?></td>
            <td class="text-center"><span class="badge rounded-pill <?= $badgeCls ?>"><?= $badgeTxt ?></span></td>
            <td class="text-center">
              <div class="d-flex gap-1 justify-content-center">
                <button class="btn btn-outline-success btn-act btn-stock-add" title="Add stock" data-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>"><i class="bi bi-plus"></i></button>
                <button class="btn btn-outline-danger btn-act btn-stock-sub" title="Remove stock" data-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>"><i class="bi bi-dash"></i></button>
                <button class="btn btn-outline-primary btn-act btn-edit" title="Edit product" data-id="<?= $p['id'] ?>"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-outline-secondary btn-act btn-delete" title="Delete product" data-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>"><i class="bi bi-trash"></i></button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php endforeach; ?>
      </table>
    </div>

    <!-- Empty state -->
    <div id="empty-state" class="text-center py-5">
      <i class="bi bi-box-open fs-1 text-muted d-block mb-2"></i>
      <p class="text-muted mb-0">No products found.</p>
    </div>
  </div>

</div><!-- /container -->

<!-- ═══════════════════════════════════════════════════════
     ADD / EDIT PRODUCT MODAL
════════════════════════════════════════════════════════ -->
<div class="modal fade" id="productModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="productModalTitle"><i class="bi bi-box-seam me-2"></i>Add Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="product-form-error" class="alert alert-danger py-2 d-none"></div>
        <input type="hidden" id="pf-id">

        <div class="field-group-label">Identification</div>
        <div class="row g-2">
          <div class="col-sm-4">
            <label class="form-label form-label-sm mb-1">SKU</label>
            <input type="text" id="pf-sku" class="form-control form-control-sm" placeholder="e.g. CEMENT001">
          </div>
          <div class="col-sm-8">
            <label class="form-label form-label-sm mb-1">Product Name <span class="text-danger">*</span></label>
            <input type="text" id="pf-name" class="form-control form-control-sm" placeholder="e.g. Cement Red">
          </div>
        </div>

        <div class="field-group-label">Classification</div>
        <div class="row g-2">
          <div class="col-sm-6">
            <label class="form-label form-label-sm mb-1">Category</label>
            <input type="text" id="pf-category" class="form-control form-control-sm" placeholder="e.g. Cement">
          </div>
          <div class="col-sm-6">
            <label class="form-label form-label-sm mb-1">Unit</label>
            <input type="text" id="pf-unit" class="form-control form-control-sm" placeholder="e.g. bag, pcs, box">
          </div>
        </div>

        <div class="field-group-label">Pricing</div>
        <div class="row g-2">
          <div class="col-sm-6">
            <label class="form-label form-label-sm mb-1">Cost Price (₱)</label>
            <input type="number" id="pf-cost" class="form-control form-control-sm" min="0" step="0.01" placeholder="0.00">
          </div>
          <div class="col-sm-6">
            <label class="form-label form-label-sm mb-1">Selling Price (₱) <span class="text-danger">*</span></label>
            <input type="number" id="pf-price" class="form-control form-control-sm" min="0.01" step="0.01" placeholder="0.00">
          </div>
        </div>

        <div class="field-group-label">Inventory</div>
        <div class="row g-2">
          <div class="col-sm-6">
            <label class="form-label form-label-sm mb-1">Stock Quantity <span class="text-danger">*</span></label>
            <input type="number" id="pf-stock" class="form-control form-control-sm" min="0" step="1" placeholder="0">
          </div>
          <div class="col-sm-6">
            <label class="form-label form-label-sm mb-1">Min Stock Alert</label>
            <input type="number" id="pf-min" class="form-control form-control-sm" min="0" step="1" placeholder="5">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="btn-save-product"><i class="bi bi-check-lg me-1"></i>Save Product</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     STOCK ADJUSTMENT MODAL
════════════════════════════════════════════════════════ -->
<div class="modal fade" id="stockModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="stockModalTitle">Adjust Stock</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="stock-form-error" class="alert alert-danger py-2 d-none"></div>
        <input type="hidden" id="sf-id">
        <input type="hidden" id="sf-direction">
        <p class="text-muted small mb-2" id="sf-product-name"></p>
        <label class="form-label form-label-sm mb-1">Quantity <span class="text-danger">*</span></label>
        <input type="number" id="sf-qty" class="form-control" min="1" step="1" placeholder="Enter quantity">
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="btn-save-stock"><i class="bi bi-check-lg me-1"></i>Confirm</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     TYPED CONFIRM DELETE MODAL
════════════════════════════════════════════════════════ -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title text-danger"><i class="bi bi-trash me-2"></i>Delete Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1">You are about to delete:</p>
        <p class="fw-bold" id="del-product-name"></p>
        <p class="text-muted small mb-2">Type <strong>DELETE</strong> to confirm. This cannot be undone.</p>
        <input type="text" id="del-confirm-input" class="form-control" placeholder="Type DELETE">
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger" id="btn-confirm-delete" disabled>Delete</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     CSV IMPORT MODAL
════════════════════════════════════════════════════════ -->
<div class="modal fade" id="csvModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-upload me-2"></i>Import CSV</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="csv-form-error" class="alert alert-danger py-2 d-none"></div>
        <div id="csv-form-success" class="alert alert-success py-2 d-none"></div>
        <p class="text-muted small mb-3">
          Required columns: <code>sku, name, category, unit, cost_price, selling_price, stock, min_stock_alert</code><br>
          Existing SKUs will be updated. New SKUs will be inserted.
        </p>
        <input type="file" id="csv-file" class="form-control" accept=".csv">
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="btn-csv-upload"><i class="bi bi-upload me-1"></i>Upload</button>
      </div>
    </div>
  </div>
</div>

<script>
const API = 'api/products_api.php';
let lowStockOnly  = false;
let activeCat     = '';

// ── Category pills ────────────────────────────────────────────────────────────
document.querySelectorAll('#cat-pills button').forEach(btn => {
  btn.addEventListener('click', function () {
    document.querySelectorAll('#cat-pills button').forEach(b => {
      b.classList.remove('active-cat');
      b.classList.add('btn-outline-secondary');
      b.classList.remove('btn-secondary');
    });
    this.classList.add('active-cat');
    activeCat = this.dataset.cat;
    filterTable();
  });
});

// ── Search + low-stock filter ─────────────────────────────────────────────────
document.getElementById('inv-search').addEventListener('input', filterTable);
document.getElementById('btn-low-stock-filter').addEventListener('click', function () {
  lowStockOnly = !lowStockOnly;
  this.classList.toggle('btn-warning', lowStockOnly);
  this.classList.toggle('btn-outline-warning', !lowStockOnly);
  filterTable();
});

function filterTable() {
  const q = document.getElementById('inv-search').value.trim().toLowerCase();
  let totalVisible = 0;

  document.querySelectorAll('.cat-section').forEach(section => {
    const cat      = section.dataset.cat;
    const matchCat = !activeCat || cat === activeCat;
    let rowVisible = 0;

    section.querySelectorAll('tr:not(.cat-label-row)').forEach(row => {
      const text   = row.textContent.toLowerCase();
      const status = row.dataset.status;
      const matchQ = !q || text.includes(q);
      const matchF = !lowStockOnly || status === 'low' || status === 'out';
      const show   = matchCat && matchQ && matchF;
      row.style.display = show ? '' : 'none';
      if (show) rowVisible++;
    });

    // Always show label row when section is visible
    const labelRow = section.querySelector('.cat-label-row');
    const showSection = matchCat && rowVisible > 0;
    section.style.display = showSection ? '' : 'none';
    if (labelRow) labelRow.style.display = showSection ? '' : 'none';
    totalVisible += rowVisible;
  });

  document.getElementById('empty-state').style.display = totalVisible === 0 ? 'block' : 'none';
}

// ── Shared: get all row tbodies for event delegation ──────────────────────────
document.addEventListener('click', function (e) {

  // Edit
  const editBtn = e.target.closest('.btn-edit');
  if (editBtn) {
    const row = editBtn.closest('tr');
    document.getElementById('productModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit Product';
    document.getElementById('pf-id').value       = row.dataset.id;
    document.getElementById('pf-sku').value      = row.dataset.sku;
    document.getElementById('pf-name').value     = row.dataset.name;
    document.getElementById('pf-category').value = row.dataset.category;
    document.getElementById('pf-unit').value     = row.dataset.unit;
    document.getElementById('pf-cost').value     = row.dataset.cost;
    document.getElementById('pf-price').value    = row.dataset.price;
    document.getElementById('pf-stock').value    = row.dataset.stock;
    document.getElementById('pf-min').value      = row.dataset.min;
    document.getElementById('product-form-error').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('productModal')).show();
    return;
  }

  // Stock add/sub
  const addBtn = e.target.closest('.btn-stock-add');
  const subBtn = e.target.closest('.btn-stock-sub');
  const stockBtn = addBtn || subBtn;
  if (stockBtn) {
    const dir = addBtn ? 1 : -1;
    document.getElementById('sf-id').value            = stockBtn.dataset.id;
    document.getElementById('sf-direction').value     = dir;
    document.getElementById('sf-product-name').textContent = stockBtn.dataset.name;
    document.getElementById('sf-qty').value           = '';
    document.getElementById('stockModalTitle').innerHTML =
      dir === 1 ? '<i class="bi bi-plus-circle text-success me-2"></i>Add Stock'
                : '<i class="bi bi-dash-circle text-danger me-2"></i>Remove Stock';
    document.getElementById('stock-form-error').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('stockModal')).show();
    return;
  }

  // Delete
  const delBtn = e.target.closest('.btn-delete');
  if (delBtn) {
    typedConfirmDelete(delBtn.dataset.id, delBtn.dataset.name);
    return;
  }
});

// ── Add Product ───────────────────────────────────────────────────────────────
document.getElementById('btn-add-product').addEventListener('click', () => {
  document.getElementById('productModalTitle').innerHTML = '<i class="bi bi-plus-lg me-2"></i>Add Product';
  ['pf-id','pf-sku','pf-name','pf-category','pf-unit','pf-cost','pf-price','pf-stock'].forEach(id => {
    document.getElementById(id).value = '';
  });
  document.getElementById('pf-min').value = '5';
  document.getElementById('product-form-error').classList.add('d-none');
  new bootstrap.Modal(document.getElementById('productModal')).show();
});

// ── Save Product ──────────────────────────────────────────────────────────────
document.getElementById('btn-save-product').addEventListener('click', async () => {
  const id    = document.getElementById('pf-id').value;
  const errEl = document.getElementById('product-form-error');
  errEl.classList.add('d-none');
  const payload = {
    action:          id ? 'edit' : 'add',
    id:              id ? parseInt(id) : undefined,
    sku:             document.getElementById('pf-sku').value.trim(),
    name:            document.getElementById('pf-name').value.trim(),
    category:        document.getElementById('pf-category').value.trim(),
    unit:            document.getElementById('pf-unit').value.trim(),
    cost_price:      parseFloat(document.getElementById('pf-cost').value)  || 0,
    selling_price:   parseFloat(document.getElementById('pf-price').value) || 0,
    stock:           parseInt(document.getElementById('pf-stock').value)   || 0,
    min_stock_alert: parseInt(document.getElementById('pf-min').value)     || 0,
  };
  const btn = document.getElementById('btn-save-product');
  btn.disabled = true;
  try {
    const res  = await fetch(API, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) { errEl.textContent = data.error; errEl.classList.remove('d-none'); return; }
    bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
    location.reload();
  } catch(e) {
    errEl.textContent = 'Network error.'; errEl.classList.remove('d-none');
  } finally { btn.disabled = false; }
});

// ── Save Stock Adjustment ─────────────────────────────────────────────────────
document.getElementById('btn-save-stock').addEventListener('click', async () => {
  const id    = document.getElementById('sf-id').value;
  const dir   = parseInt(document.getElementById('sf-direction').value);
  const qty   = parseInt(document.getElementById('sf-qty').value);
  const errEl = document.getElementById('stock-form-error');
  errEl.classList.add('d-none');
  if (!qty || qty <= 0) { errEl.textContent = 'Enter a valid quantity.'; errEl.classList.remove('d-none'); return; }
  const btn = document.getElementById('btn-save-stock');
  btn.disabled = true;
  try {
    const res  = await fetch(API, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'adjust_stock', id: parseInt(id), qty: dir * qty }) });
    const data = await res.json();
    if (!data.success) { errEl.textContent = data.error; errEl.classList.remove('d-none'); return; }
    bootstrap.Modal.getInstance(document.getElementById('stockModal')).hide();
    location.reload();
  } catch(e) {
    errEl.textContent = 'Network error.'; errEl.classList.remove('d-none');
  } finally { btn.disabled = false; }
});

// ── CSV Import ────────────────────────────────────────────────────────────────
document.getElementById('btn-csv-import').addEventListener('click', () => {
  document.getElementById('csv-file').value = '';
  document.getElementById('csv-form-error').classList.add('d-none');
  document.getElementById('csv-form-success').classList.add('d-none');
  new bootstrap.Modal(document.getElementById('csvModal')).show();
});

document.getElementById('btn-csv-upload').addEventListener('click', async () => {
  const file  = document.getElementById('csv-file').files[0];
  const errEl = document.getElementById('csv-form-error');
  const okEl  = document.getElementById('csv-form-success');
  errEl.classList.add('d-none'); okEl.classList.add('d-none');
  if (!file) { errEl.textContent = 'Please select a CSV file.'; errEl.classList.remove('d-none'); return; }
  const fd = new FormData();
  fd.append('action', 'import_csv');
  fd.append('csv', file);
  const btn = document.getElementById('btn-csv-upload');
  btn.disabled = true;
  try {
    const res  = await fetch(API, { method:'POST', body: fd });
    const data = await res.json();
    if (!data.success) {
      errEl.textContent = data.error + (data.row ? ` (row ${data.row})` : '');
      errEl.classList.remove('d-none'); return;
    }
    okEl.textContent = `Import complete: ${data.inserted} inserted, ${data.updated} updated.`;
    okEl.classList.remove('d-none');
    setTimeout(() => { bootstrap.Modal.getInstance(document.getElementById('csvModal')).hide(); location.reload(); }, 1500);
  } catch(e) {
    errEl.textContent = 'Network error.'; errEl.classList.remove('d-none');
  } finally { btn.disabled = false; }
});
// ── Typed-confirm delete ─────────────────────────────────────────────────────
let _deleteTargetId   = null;
let _deleteTargetRow  = null;

function typedConfirmDelete(id, name) {
  _deleteTargetId = id;
  _deleteTargetRow = document.querySelector(`tr[data-id="${id}"]`);
  document.getElementById('del-product-name').textContent = name;
  document.getElementById('del-confirm-input').value = '';
  document.getElementById('btn-confirm-delete').disabled = true;
  new bootstrap.Modal(document.getElementById('deleteModal')).show();
  setTimeout(() => document.getElementById('del-confirm-input').focus(), 300);
}

document.getElementById('del-confirm-input').addEventListener('input', function () {
  document.getElementById('btn-confirm-delete').disabled = this.value !== 'DELETE';
});

document.getElementById('btn-confirm-delete').addEventListener('click', async function () {
  if (!_deleteTargetId) return;
  this.disabled = true;
  try {
    const res  = await fetch(API, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'delete', id: parseInt(_deleteTargetId) }) });
    const data = await res.json();
    if (!data.success) { alert(data.error); return; }
    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
    if (_deleteTargetRow) {
      const section = _deleteTargetRow.closest('.cat-section');
      _deleteTargetRow.remove();
      if (section && !section.querySelector('tr:not(.cat-label-row)')) section.remove();
    }
    filterTable();
  } catch(e) {
    alert('Network error.');
  } finally {
    this.disabled = false;
    _deleteTargetId = null;
    _deleteTargetRow = null;
  }
});
</script>
<?php layoutEnd(); ?>
