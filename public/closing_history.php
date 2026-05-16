<?php
require_once __DIR__ . '/../app/bootstrap.php';
requireRole('owner');

require_once APP_ROOT . '/app/Services/ExportService.php';

// ── Export single closure CSV ─────────────────────────────────────────────────
if (isset($_GET['export_id'])) {
    $id   = (int)$_GET['export_id'];
    $conn = getConnection();
    $stmt = $conn->prepare(
        'SELECT dc.*, u.username AS closed_by_username
         FROM daily_closures dc
         LEFT JOIN users u ON u.id = dc.closed_by_user_id
         WHERE dc.id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close(); $conn->close();

    if ($row) {
        $summary  = json_decode($row['summary_json'], true);
        $sellers  = json_decode($row['best_sellers_json'], true);
        $export   = new ExportService();
        $headers  = ['Business Date', 'Orders', 'Revenue', 'Cost', 'Gross Profit', 'Closed By', 'Finalized At'];
        $rows     = [[
            $row['business_date'],
            $summary['total_orders'],
            number_format((float)$summary['total_revenue'], 2, '.', ''),
            number_format((float)$summary['total_cost'],    2, '.', ''),
            number_format((float)$summary['gross_profit'],  2, '.', ''),
            $row['closed_by_username'] ?? '—',
            $row['created_at'],
        ]];
        $export->exportToCSV(
            $export->formatFilename('Closure_' . $row['business_date']),
            $headers, $rows
        );
    }
    exit;
}

// ── Export history table CSV ──────────────────────────────────────────────────
if (isset($_GET['export_all'])) {
    $conn  = getConnection();
    $res   = $conn->query(
        'SELECT dc.business_date, dc.summary_json, dc.created_at, u.username AS closed_by_username
         FROM daily_closures dc
         LEFT JOIN users u ON u.id = dc.closed_by_user_id
         ORDER BY dc.business_date DESC'
    );
    $allRows = $res->fetch_all(MYSQLI_ASSOC);
    $conn->close();

    $export  = new ExportService();
    $headers = ['Business Date', 'Orders', 'Revenue', 'Cost', 'Gross Profit', 'Closed By', 'Finalized At'];
    $csvRows = [];
    foreach ($allRows as $r) {
        $s = json_decode($r['summary_json'], true);
        $csvRows[] = [
            $r['business_date'],
            $s['total_orders'],
            number_format((float)$s['total_revenue'], 2, '.', ''),
            number_format((float)$s['total_cost'],    2, '.', ''),
            number_format((float)$s['gross_profit'],  2, '.', ''),
            $r['closed_by_username'] ?? '—',
            $r['created_at'],
        ];
    }
    $export->exportToCSV($export->formatFilename('Closing_History'), $headers, $csvRows);
    exit;
}

// ── Page data ─────────────────────────────────────────────────────────────────
$conn = getConnection();

// Summary cards
$cardStmt = $conn->query(
    'SELECT
        COUNT(*) AS total_closures,
        SUM(JSON_UNQUOTE(JSON_EXTRACT(summary_json, "$.total_revenue"))) AS total_revenue,
        MAX(JSON_UNQUOTE(JSON_EXTRACT(summary_json, "$.total_revenue"))) AS highest_revenue,
        MAX(business_date) AS latest_date
     FROM daily_closures'
);
$cards = $cardStmt->fetch_assoc();
$avgRevenue = ($cards['total_closures'] > 0)
    ? (float)$cards['total_revenue'] / (int)$cards['total_closures']
    : 0.0;

// History table
$closures = $conn->query(
    'SELECT dc.id, dc.business_date, dc.summary_json, dc.created_at, u.username AS closed_by_username
     FROM daily_closures dc
     LEFT JOIN users u ON u.id = dc.closed_by_user_id
     ORDER BY dc.business_date DESC'
)->fetch_all(MYSQLI_ASSOC);

$conn->close();

function peso(float $v): string { return '&#8369;' . number_format($v, 2); }

require_once APP_ROOT . '/app/layout.php';
layoutStart('Lumina POS – Closing History');
?>
<style>
  .ch-toolbar { position: sticky; top: 53px; z-index: 90; background: #f4f6f9; padding: .75rem 0 .5rem; }
  .tbl th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #6c757d; white-space: nowrap; }
  .tbl td { vertical-align: middle; font-size: .875rem; }
  .tbl tbody tr:hover { background: #f8f9ff; }
  .section-title { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #6c757d; margin-bottom: .75rem; }
  @media print {
    .no-print { display: none !important; }
    #closureModal { display: none !important; }
  }
</style>

<?php layoutHeader('Closing History', 'bi-clock-history'); ?>
<div class="container-fluid px-4 no-print">

  <!-- Summary Cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card orders h-100">
        <div class="card-body py-3">
          <div class="text-muted small mb-1"><i class="bi bi-archive me-1"></i>Total Closures</div>
          <div class="fs-4 fw-bold"><?= (int)$cards['total_closures'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card revenue h-100">
        <div class="card-body py-3">
          <div class="text-muted small mb-1"><i class="bi bi-cash-stack me-1"></i>Total Revenue</div>
          <div class="fs-4 fw-bold"><?= peso((float)($cards['total_revenue'] ?? 0)) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card profit h-100">
        <div class="card-body py-3">
          <div class="text-muted small mb-1"><i class="bi bi-graph-up me-1"></i>Avg Daily Revenue</div>
          <div class="fs-4 fw-bold"><?= peso($avgRevenue) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card cost h-100">
        <div class="card-body py-3">
          <div class="text-muted small mb-1"><i class="bi bi-trophy me-1"></i>Highest Sales Day</div>
          <div class="fs-4 fw-bold"><?= peso((float)($cards['highest_revenue'] ?? 0)) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="ch-toolbar">
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <div class="input-group input-group-sm" style="max-width:240px">
        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
        <input type="text" id="ch-search" class="form-control border-start-0 ps-0" placeholder="Search by date…">
      </div>
      <input type="date" id="ch-date-from" class="form-control form-control-sm" style="max-width:150px" placeholder="From">
      <input type="date" id="ch-date-to"   class="form-control form-control-sm" style="max-width:150px" placeholder="To">
      <button class="btn btn-sm btn-outline-secondary" id="btn-ch-clear">Clear</button>
      <div class="ms-auto">
        <a href="closing_history.php?export_all=1" class="btn btn-sm btn-outline-success">
          <i class="bi bi-download me-1"></i>Export All CSV
        </a>
      </div>
    </div>
  </div>

  <!-- History Table -->
  <div class="card shadow-sm border-0 mt-3">
    <?php if (empty($closures)): ?>
    <div class="card-body text-center py-5">
      <i class="bi bi-archive fs-1 text-muted d-block mb-2"></i>
      <p class="text-muted mb-0">No historical closing reports yet.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table tbl mb-0" id="ch-table">
        <thead class="table-light">
          <tr>
            <th>Business Date</th>
            <th class="text-center">Orders</th>
            <th class="text-end">Revenue</th>
            <th class="text-end">Gross Profit</th>
            <th>Closed By</th>
            <th>Finalized At</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($closures as $c):
            $s      = json_decode($c['summary_json'], true);
            $profit = (float)$s['gross_profit'];
          ?>
          <tr data-date="<?= $c['business_date'] ?>">
            <td class="fw-semibold"><?= htmlspecialchars($c['business_date']) ?></td>
            <td class="text-center"><?= (int)$s['total_orders'] ?></td>
            <td class="text-end fw-semibold text-primary"><?= peso((float)$s['total_revenue']) ?></td>
            <td class="text-end">
              <span class="badge <?= $profit >= 0 ? 'bg-success' : 'bg-danger' ?>">
                <?= peso($profit) ?>
              </span>
            </td>
            <td class="text-muted"><?= htmlspecialchars($c['closed_by_username'] ?? '—') ?></td>
            <td class="text-muted" style="font-size:.8rem"><?= htmlspecialchars($c['created_at']) ?></td>
            <td class="text-center">
              <div class="d-flex gap-1 justify-content-center">
                <button class="btn btn-sm btn-outline-primary btn-view-closure"
                        data-id="<?= $c['id'] ?>" title="View">
                  <i class="bi bi-eye"></i>
                </button>
                <button class="btn btn-sm btn-outline-secondary btn-print-closure"
                        data-id="<?= $c['id'] ?>" title="Print">
                  <i class="bi bi-printer"></i>
                </button>
                <a href="closing_history.php?export_id=<?= $c['id'] ?>"
                   class="btn btn-sm btn-outline-success" title="Export CSV">
                  <i class="bi bi-download"></i>
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <div class="text-muted small text-end mt-3 mb-4">
    <?= count($closures) ?> closure(s) on record &nbsp;|&nbsp;
    Generated: <?= date('Y-m-d H:i:s') ?>
  </div>

</div>

<!-- View Closure Modal -->
<div class="modal fade no-print" id="closureModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0"><i class="bi bi-file-earmark-text me-2"></i>Closing Report</h5>
          <div class="text-muted small" id="modal-meta"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="modal-body">
        <div class="text-center py-4"><span class="spinner-border"></span></div>
      </div>
      <div class="modal-footer no-print">
        <button class="btn btn-outline-secondary btn-sm" id="btn-modal-print">
          <i class="bi bi-printer me-1"></i>Print
        </button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Hidden print area -->
<div id="closure-print-area"></div>

<!-- Print-only wrapper (same pattern as closing_report.php) -->
<div class="d-none d-print-block" id="closure-print-wrapper">
  <div class="report-wrapper px-4">
    <div class="text-center mb-4">
      <h4 class="fw-bold mb-0">LUMINA HARDWARE</h4>
      <div class="text-muted" id="pw-title"></div>
      <div class="text-muted small" id="pw-meta"></div>
      <div class="text-muted small">Printed: <?= date('Y-m-d H:i:s') ?></div>
    </div>
    <div id="pw-content"></div>
  </div>
</div>

<script>
function peso(v) { return '₱' + parseFloat(v).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}); }
function esc(s)  { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function methodLabel(m) {
  return {cash:'Cash', gcash:'GCash', bank_transfer:'Bank Transfer'}[m] || m;
}

function renderClosure(data) {
  const s  = data.summary;
  const pm = data.payment_breakdown;
  const bs = data.best_sellers;
  const ls = data.low_stock;

  const pmMap = {};
  (pm || []).forEach(r => pmMap[r.payment_method] = r);

  // Payment rows
  const pmRows = ['cash','gcash','bank_transfer'].map(m => {
    const r = pmMap[m];
    return `<tr>
      <td>${methodLabel(m)}</td>
      <td class="text-center">${r ? r.orders : '—'}</td>
      <td class="text-end">${r ? peso(r.revenue) : '—'}</td>
    </tr>`;
  }).join('');

  // Best sellers rows
  const bsRows = (bs || []).length === 0
    ? `<tr><td colspan="6" class="text-center text-muted py-3">No sales recorded.</td></tr>`
    : (bs || []).map((r, i) => {
        const profit = parseFloat(r.total_revenue) - parseFloat(r.total_cost);
        return `<tr>
          <td class="text-muted">${i+1}</td>
          <td><div class="fw-semibold">${esc(r.name)}</div><div class="text-muted" style="font-size:.75rem">${esc(r.sku||'')}</div></td>
          <td>${esc(r.category||'—')}</td>
          <td class="text-center fw-bold">${parseInt(r.total_qty)}</td>
          <td class="text-end">${peso(r.total_revenue)}</td>
          <td class="text-end ${profit < 0 ? 'text-danger' : 'text-success'}">${peso(profit)}</td>
        </tr>`;
      }).join('');

  // Low stock rows
  const lsRows = (ls || []).length === 0
    ? `<tr><td colspan="5" class="text-center text-success py-3"><i class="bi bi-check-circle"></i> All products sufficiently stocked.</td></tr>`
    : (ls || []).map(r => {
        const isOut = parseInt(r.stock) === 0;
        return `<tr class="${isOut ? 'table-danger' : 'table-warning'}">
          <td><div class="fw-semibold">${esc(r.name)}</div><div class="text-muted" style="font-size:.75rem">${esc(r.sku||'')}</div></td>
          <td>${esc(r.category||'—')}</td>
          <td class="text-center fw-bold ${isOut ? 'text-danger' : 'text-warning'}">${parseInt(r.stock)}</td>
          <td class="text-center">${parseInt(r.min_stock_alert)}</td>
          <td class="text-center"><span class="badge ${isOut ? 'bg-danger' : 'bg-warning text-dark'}">${isOut ? 'Out of Stock' : 'Low Stock'}</span></td>
        </tr>`;
      }).join('');

  return `
    <!-- A. Daily Summary -->
    <div class="section-title"><i class="bi bi-calendar-check"></i> Daily Summary — ${esc(data.business_date)}</div>
    <div class="row g-3 mb-4">
      ${[
        ['orders','Total Orders', parseInt(s.total_orders), false],
        ['revenue','Revenue', s.total_revenue, true],
        ['cost','Cost of Goods', s.total_cost, true],
        ['profit','Gross Profit', s.gross_profit, true],
      ].map(([type, label, value, isMoney]) => `
        <div class="col-6 col-md-3">
          <div class="card shadow-sm stat-card ${type} h-100">
            <div class="card-body py-3">
              <div class="text-muted small">${label}</div>
              <div class="fs-5 fw-bold ${type === 'profit' && parseFloat(value) < 0 ? 'text-danger' : ''}">
                ${isMoney ? peso(value) : value}
              </div>
            </div>
          </div>
        </div>`).join('')}
    </div>

    <!-- B. Payment Breakdown -->
    <div class="section-title"><i class="bi bi-cash-coin"></i> Payment Breakdown</div>
    <div class="card shadow-sm mb-4">
      <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light"><tr><th>Method</th><th class="text-center">Orders</th><th class="text-end">Revenue</th></tr></thead>
          <tbody>${pmRows}</tbody>
          <tfoot class="table-light fw-semibold">
            <tr><td>Total</td><td class="text-center">${parseInt(s.total_orders)}</td><td class="text-end">${peso(s.total_revenue)}</td></tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- C. Top Products -->
    <div class="section-title"><i class="bi bi-trophy"></i> Top Products</div>
    <div class="card shadow-sm mb-4">
      <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light"><tr><th>#</th><th>Product</th><th>Category</th><th class="text-center">Qty Sold</th><th class="text-end">Revenue</th><th class="text-end">Profit</th></tr></thead>
          <tbody>${bsRows}</tbody>
        </table>
      </div>
    </div>

    <!-- D. Low Stock Snapshot -->
    <div class="section-title"><i class="bi bi-exclamation-triangle text-warning"></i> Low Stock Snapshot</div>
    <div class="card shadow-sm mb-2">
      <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light"><tr><th>Product</th><th>Category</th><th class="text-center">Stock</th><th class="text-center">Min Alert</th><th class="text-center">Status</th></tr></thead>
          <tbody>${lsRows}</tbody>
        </table>
      </div>
    </div>

    <div class="text-muted small text-end mt-3">
      Closed by: <strong>${esc(data.closed_by)}</strong> &nbsp;|&nbsp;
      Finalized at: ${esc(data.created_at)}
      ${data.closing_notes ? `<br>Notes: ${esc(data.closing_notes)}` : ''}
    </div>`;
}

// ── View closure ──────────────────────────────────────────────────────────────
let currentClosureData = null;

async function loadClosure(id) {
  document.getElementById('modal-body').innerHTML = '<div class="text-center py-4"><span class="spinner-border"></span></div>';
  document.getElementById('modal-meta').textContent = '';
  new bootstrap.Modal(document.getElementById('closureModal')).show();

  const res  = await fetch(`api/closing_history_api.php?action=get_closure&id=${id}`);
  const data = await res.json();
  if (!data.success) {
    document.getElementById('modal-body').innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
    return;
  }
  currentClosureData = data;
  document.getElementById('modal-meta').textContent =
    `Closed by: ${data.closed_by}  |  Finalized: ${data.created_at}`;
  document.getElementById('modal-body').innerHTML = renderClosure(data);
}

document.addEventListener('click', function (e) {
  const viewBtn  = e.target.closest('.btn-view-closure');
  const printBtn = e.target.closest('.btn-print-closure');
  if (viewBtn)  loadClosure(viewBtn.dataset.id);
  if (printBtn) loadAndPrint(printBtn.dataset.id);
});

// ── Print ─────────────────────────────────────────────────────────────────────
async function loadAndPrint(id) {
  const res  = await fetch(`api/closing_history_api.php?action=get_closure&id=${id}`);
  const data = await res.json();
  if (!data.success) { alert(data.error); return; }
  triggerPrint(data);
}

function triggerPrint(data) {
  document.getElementById('pw-title').textContent = `Daily Closing Report — ${data.business_date}`;
  document.getElementById('pw-meta').textContent  = `Closed by: ${data.closed_by} | Finalized: ${data.created_at}`;
  document.getElementById('pw-content').innerHTML = renderClosure(data);
  window.print();
}

document.getElementById('btn-modal-print').addEventListener('click', function () {
  if (currentClosureData) triggerPrint(currentClosureData);
});

// ── Search / filter ───────────────────────────────────────────────────────────
function filterTable() {
  const q    = document.getElementById('ch-search').value.trim().toLowerCase();
  const from = document.getElementById('ch-date-from').value;
  const to   = document.getElementById('ch-date-to').value;
  document.querySelectorAll('#ch-table tbody tr').forEach(row => {
    const date = row.dataset.date;
    const matchQ    = !q    || date.includes(q);
    const matchFrom = !from || date >= from;
    const matchTo   = !to   || date <= to;
    row.style.display = (matchQ && matchFrom && matchTo) ? '' : 'none';
  });
}

document.getElementById('ch-search').addEventListener('input', filterTable);
document.getElementById('ch-date-from').addEventListener('change', filterTable);
document.getElementById('ch-date-to').addEventListener('change', filterTable);
document.getElementById('btn-ch-clear').addEventListener('click', function () {
  document.getElementById('ch-search').value   = '';
  document.getElementById('ch-date-from').value = '';
  document.getElementById('ch-date-to').value   = '';
  filterTable();
});
</script>
<?php layoutEnd(); ?>
