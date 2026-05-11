<?php
require_once __DIR__ . '/../app/bootstrap.php';
requireRole('owner');

require_once APP_ROOT . '/app/Services/ReportService.php';

$conn    = getConnection();
$report  = new ReportService($conn);
$lowStock = $report->getLowStockProducts();
$conn->close();

$outOfStock = array_filter($lowStock, fn($p) => (int)$p['stock'] === 0);
$lowOnly    = array_filter($lowStock, fn($p) => (int)$p['stock'] > 0);

require_once APP_ROOT . '/app/layout.php';
layoutStart('Lumina POS – Low Stock');
?>
<style>
  .ls-toolbar { position:sticky; top:53px; z-index:90; background:#f4f6f9; padding:.75rem 0 .5rem; }
  .tbl th { font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; color:#6c757d; white-space:nowrap; }
  .tbl td { vertical-align:middle; font-size:.875rem; }
  .tbl tbody tr:hover { background:#f8f9ff; }
  .stock-out { background:#fee2e2; color:#991b1b; }
  .stock-low { background:#fef3c7; color:#92400e; }
</style>

<?php layoutHeader('Low Stock Alert', 'bi-exclamation-triangle'); ?>
<div class="container-fluid px-4">

  <!-- Summary cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card total h-100">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-exclamation-triangle"></i> Total Alerts</div>
          <div class="fs-4 fw-bold"><?= count($lowStock) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm h-100" style="border-left:4px solid #dc3545">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-x-circle"></i> Out of Stock</div>
          <div class="fs-4 fw-bold text-danger"><?= count($outOfStock) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm h-100" style="border-left:4px solid #f59e0b">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-exclamation-circle"></i> Low Stock</div>
          <div class="fs-4 fw-bold text-warning"><?= count($lowOnly) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm h-100" style="border-left:4px solid #198754">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-download"></i> Export</div>
          <a href="export_reports.php?type=low_stock" class="btn btn-sm btn-outline-success mt-1">
            <i class="bi bi-download me-1"></i>Low Stock CSV
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="ls-toolbar">
    <div class="d-flex gap-2 align-items-center">
      <div class="input-group input-group-sm" style="max-width:280px">
        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
        <input type="text" id="ls-search" class="form-control border-start-0 ps-0" placeholder="Search product or SKU…">
      </div>
      <div class="btn-group btn-group-sm" id="ls-filter-btns">
        <button class="btn btn-dark active" data-filter="all">All</button>
        <button class="btn btn-outline-danger" data-filter="out">Out of Stock</button>
        <button class="btn btn-outline-warning" data-filter="low">Low Stock</button>
      </div>
      <span class="text-muted small ms-auto" id="ls-count"><?= count($lowStock) ?> item(s)</span>
    </div>
  </div>

  <!-- Table -->
  <?php if (empty($lowStock)): ?>
  <div class="card shadow-sm border-0 mt-3">
    <div class="card-body text-center py-5">
      <i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>
      <p class="text-success fw-semibold mb-0">All products are sufficiently stocked.</p>
    </div>
  </div>
  <?php else: ?>
  <div class="card shadow-sm border-0 mt-3">
    <div class="table-responsive">
      <table class="table tbl mb-0" id="ls-table">
        <thead class="table-light">
          <tr>
            <th>SKU</th>
            <th>Product</th>
            <th>Category</th>
            <th class="text-center">Stock</th>
            <th class="text-center">Min Alert</th>
            <th class="text-center">Status</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lowStock as $p):
            $isOut  = (int)$p['stock'] === 0;
            $status = $isOut ? 'out' : 'low';
          ?>
          <tr data-status="<?= $status ?>" data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>" data-sku="<?= htmlspecialchars(strtolower($p['sku'] ?? '')) ?>">
            <td class="text-muted" style="font-size:.78rem"><?= htmlspecialchars($p['sku'] ?? '—') ?></td>
            <td class="fw-semibold"><?= htmlspecialchars($p['name']) ?></td>
            <td class="text-muted"><?= htmlspecialchars($p['category'] ?? '—') ?></td>
            <td class="text-center fw-bold <?= $isOut ? 'text-danger' : 'text-warning' ?>">
              <?= (int)$p['stock'] ?>
            </td>
            <td class="text-center text-muted"><?= (int)$p['min_stock_alert'] ?></td>
            <td class="text-center">
              <span class="badge rounded-pill <?= $isOut ? 'stock-out' : 'stock-low' ?>">
                <?= $isOut ? 'Out of Stock' : 'Low Stock' ?>
              </span>
            </td>
            <td class="text-center">
              <a href="products.php" class="btn btn-sm btn-outline-primary" title="Manage in Products">
                <i class="bi bi-pencil"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div id="ls-empty" class="text-center text-muted py-4 d-none">No products match your filter.</div>
  </div>
  <?php endif; ?>

  <div class="text-muted small text-end mt-3 mb-4">
    Generated: <?= date('Y-m-d H:i:s') ?> &nbsp;|&nbsp;
    <a href="low_stock.php">Refresh</a>
  </div>

</div>

<script>
const rows      = document.querySelectorAll('#ls-table tbody tr');
const countEl   = document.getElementById('ls-count');
let activeFilter = 'all';

function applyFilters() {
  const q = document.getElementById('ls-search').value.toLowerCase();
  let visible = 0;
  rows.forEach(tr => {
    const matchFilter = activeFilter === 'all' || tr.dataset.status === activeFilter;
    const matchSearch = !q || tr.dataset.name.includes(q) || tr.dataset.sku.includes(q);
    const show = matchFilter && matchSearch;
    tr.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  if (countEl) countEl.textContent = visible + ' item(s)';
  const empty = document.getElementById('ls-empty');
  if (empty) empty.classList.toggle('d-none', visible > 0);
}

document.getElementById('ls-search').addEventListener('input', applyFilters);

document.querySelectorAll('#ls-filter-btns button').forEach(btn => {
  btn.addEventListener('click', function () {
    document.querySelectorAll('#ls-filter-btns button').forEach(b => {
      b.classList.remove('active', 'btn-dark', 'btn-danger', 'btn-warning');
      b.classList.add(
        b.dataset.filter === 'out' ? 'btn-outline-danger' :
        b.dataset.filter === 'low' ? 'btn-outline-warning' : 'btn-outline-secondary'
      );
    });
    activeFilter = this.dataset.filter;
    this.classList.remove('btn-outline-danger', 'btn-outline-warning', 'btn-outline-secondary');
    this.classList.add(
      activeFilter === 'out' ? 'btn-danger' :
      activeFilter === 'low' ? 'btn-warning' : 'btn-dark'
    );
    this.classList.add('active');
    applyFilters();
  });
});
</script>
<?php layoutEnd(); ?>
