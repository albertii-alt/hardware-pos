<?php
require_once __DIR__ . '/../app/bootstrap.php';
requireRole('owner');

require_once APP_ROOT . '/app/Services/ReportService.php';


$today = date('Y-m-d');

$conn   = getConnection();
$report = new ReportService($conn);

$todaySummary     = $report->getTodaySummary();
$paymentBreakdown = $report->getTodayPaymentBreakdown();
$bestSellers      = $report->getBestSellers($today, $today);
$lowStock         = $report->getLowStockProducts();

// Check if today already finalized
$chkStmt = $conn->prepare('SELECT id, created_at FROM daily_closures WHERE business_date = ? LIMIT 1');
$chkStmt->bind_param('s', $today);
$chkStmt->execute();
$existingClosure = $chkStmt->get_result()->fetch_assoc();
$chkStmt->close();

$conn->close();

// ── Silently auto-archive any missed past days ────────────────────────────────
$autoArchiveResult = null;
try {
    $autoConn   = getConnection();
    $autoReport = new ReportService($autoConn);
    $autoToday  = date('Y-m-d');

    $autoStmt = $autoConn->prepare(
        'SELECT DISTINCT DATE(order_date) AS business_date
         FROM orders
         WHERE DATE(order_date) < ?
           AND status = "completed"
           AND DATE(order_date) NOT IN (SELECT business_date FROM daily_closures)
         ORDER BY business_date ASC'
    );
    $autoStmt->bind_param('s', $autoToday);
    $autoStmt->execute();
    $missingDates = $autoStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $autoStmt->close();

    if (!empty($missingDates)) {
        $autoInsert = $autoConn->prepare(
            'INSERT IGNORE INTO daily_closures
                (business_date, summary_json, payment_breakdown_json, best_sellers_json, low_stock_json, closed_by_user_id, closing_notes)
             VALUES (?, ?, ?, ?, ?, NULL, "Auto-archived by system")'
        );
        $autoArchived = 0;
        foreach ($missingDates as $row) {
            $d    = $row['business_date'];
            $s    = json_encode($autoReport->getRangeSummary($d, $d),          JSON_UNESCAPED_UNICODE);
            $p    = json_encode($autoReport->getPaymentMethodBreakdown($d, $d), JSON_UNESCAPED_UNICODE);
            $b    = json_encode($autoReport->getBestSellers($d, $d, 5),         JSON_UNESCAPED_UNICODE);
            $l    = json_encode($autoReport->getLowStockProducts(),             JSON_UNESCAPED_UNICODE);
            $autoInsert->bind_param('sssss', $d, $s, $p, $b, $l);
            if ($autoInsert->execute()) $autoArchived++;
        }
        $autoInsert->close();
        if ($autoArchived > 0) logAction('AUTO_ARCHIVE_CLOSURES', $autoArchived);
    }
    $autoConn->close();
} catch (Exception $e) {
    error_log('Auto-archive failed: ' . $e->getMessage());
}

function peso(float $v): string { return '&#8369;' . number_format($v, 2); }
function methodLabel(string $m): string {
    return ['cash' => 'Cash', 'gcash' => 'GCash', 'bank_transfer' => 'Bank Transfer'][$m] ?? ucfirst($m);
}

// Build payment lookup for quick access
$paymentMap = [];
foreach ($paymentBreakdown as $row) {
    $paymentMap[$row['payment_method']] = $row;
}

require_once APP_ROOT . '/app/layout.php';
layoutStart('Daily Closing Report');
?>
<?php layoutHeader('Daily Closing Report', 'bi-file-earmark-text'); ?>
<style>
  @media print {
    .no-print, .page-header { display: none !important; }
  }
</style>
<div class="container-fluid px-4">
<div class="report-wrapper">

  <!-- Toolbar -->
  <div class="d-flex justify-content-end gap-2 mb-3 no-print flex-wrap">
    <a href="closing_report.php" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-clockwise me-1"></i>Refresh
    </a>
    <a href="export_orders.php?type=today" class="btn btn-sm btn-outline-success">
      <i class="bi bi-download me-1"></i>Export CSV
    </a>
    <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
      <i class="bi bi-printer me-1"></i>Print
    </button>
    <a href="closing_history.php" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-clock-history me-1"></i>Closing History
    </a>
    <?php if ($existingClosure): ?>
    <span class="btn btn-sm btn-success disabled">
      <i class="bi bi-check-circle me-1"></i>Day Already Finalized
    </span>
    <?php else: ?>
    <button class="btn btn-sm btn-danger" id="btn-finalize" data-bs-toggle="modal" data-bs-target="#finalizeModal">
      <i class="bi bi-lock me-1"></i>Finalize Day Closing
    </button>
    <?php endif; ?>
  </div>

  <!-- Print header (visible on print only) -->
  <div class="d-none d-print-block text-center mb-4">
    <h4 class="fw-bold mb-0">LUMINA HARDWARE</h4>
    <div class="text-muted">Daily Closing Report &mdash; <?= $today ?></div>
    <div class="text-muted small">Printed: <?= date('Y-m-d H:i:s') ?></div>
  </div>

  <!-- A. Daily Summary -->
  <div class="section-title"><i class="bi bi-calendar-check"></i> Daily Summary — <?= $today ?></div>
  <div class="row g-3 mb-4">
    <?php
    $cards = [
      ['orders',  'Total Orders',   (int)$todaySummary['total_orders'],   false],
      ['revenue', 'Revenue',        (float)$todaySummary['total_revenue'], true],
      ['cost',    'Cost of Goods',  (float)$todaySummary['total_cost'],    true],
      ['profit',  'Gross Profit',   (float)$todaySummary['gross_profit'],  true],
    ];
    foreach ($cards as [$type, $label, $value, $isMoney]): ?>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card <?= $type ?> h-100">
        <div class="card-body py-3">
          <div class="text-muted small"><?= $label ?></div>
          <div class="fs-5 fw-bold <?= ($type === 'profit' && $value < 0) ? 'text-danger' : '' ?>">
            <?= $isMoney ? peso($value) : $value ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- B. Cash Breakdown -->
  <div class="section-title"><i class="bi bi-cash-coin"></i> Payment Breakdown</div>
  <div class="card shadow-sm mb-4">
    <div class="card-body p-0">
      <?php if (empty($paymentBreakdown)): ?>
        <p class="text-muted text-center py-3 mb-0">No transactions today.</p>
      <?php else: ?>
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr><th>Method</th><th class="text-center">Orders</th><th class="text-end">Revenue</th></tr>
        </thead>
        <tbody>
          <?php foreach (['cash', 'gcash', 'bank_transfer'] as $method):
            $row = $paymentMap[$method] ?? null; ?>
          <tr>
            <td><?= methodLabel($method) ?></td>
            <td class="text-center"><?= $row ? (int)$row['orders'] : '—' ?></td>
            <td class="text-end"><?= $row ? peso((float)$row['revenue']) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light fw-semibold">
          <tr>
            <td>Total</td>
            <td class="text-center"><?= (int)$todaySummary['total_orders'] ?></td>
            <td class="text-end"><?= peso((float)$todaySummary['total_revenue']) ?></td>
          </tr>
        </tfoot>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- C. Top Products Today -->
  <div class="section-title"><i class="bi bi-trophy"></i> Top 5 Products Today</div>
  <div class="card shadow-sm mb-4">
    <div class="card-body p-0">
      <?php if (empty($bestSellers)): ?>
        <p class="text-muted text-center py-3 mb-0">No sales recorded today.</p>
      <?php else: ?>
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th><th>Product</th><th>Category</th>
            <th class="text-center">Qty Sold</th>
            <th class="text-end">Revenue</th>
            <th class="text-end">Profit</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bestSellers as $i => $row):
            $profit = (float)$row['total_revenue'] - (float)$row['total_cost']; ?>
          <tr>
            <td class="text-muted"><?= $i + 1 ?></td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($row['name']) ?></div>
              <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($row['sku'] ?? '') ?></div>
            </td>
            <td><?= htmlspecialchars($row['category'] ?? '—') ?></td>
            <td class="text-center fw-bold"><?= (int)$row['total_qty'] ?></td>
            <td class="text-end"><?= peso((float)$row['total_revenue']) ?></td>
            <td class="text-end <?= $profit < 0 ? 'text-danger' : 'text-success' ?>"><?= peso($profit) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- D. Low Stock Snapshot -->
  <div class="section-title"><i class="bi bi-exclamation-triangle text-warning"></i> Low Stock Snapshot</div>
  <div class="card shadow-sm mb-4">
    <div class="card-body p-0">
      <?php if (empty($lowStock)): ?>
        <p class="text-success text-center py-3 mb-0"><i class="bi bi-check-circle"></i> All products sufficiently stocked.</p>
      <?php else: ?>
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr><th>Product</th><th>Category</th><th class="text-center">Stock</th><th class="text-center">Min Alert</th><th class="text-center">Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($lowStock as $row): ?>
          <tr class="<?= (int)$row['stock'] === 0 ? 'table-danger' : 'table-warning' ?>">
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($row['name']) ?></div>
              <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($row['sku'] ?? '') ?></div>
            </td>
            <td><?= htmlspecialchars($row['category'] ?? '—') ?></td>
            <td class="text-center fw-bold"><?= (int)$row['stock'] ?></td>
            <td class="text-center"><?= (int)$row['min_stock_alert'] ?></td>
            <td class="text-center">
              <?php if ((int)$row['stock'] === 0): ?>
                <span class="badge bg-danger">Out of Stock</span>
              <?php else: ?>
                <span class="badge bg-warning text-dark">Low Stock</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <div class="text-muted small text-end mb-4 no-print">
    Generated: <?= date('Y-m-d H:i:s') ?> &nbsp;|&nbsp;
    <a href="closing_report.php">Refresh</a>
  </div>

</div><!-- /report-wrapper -->
</div><!-- /container -->

<!-- Finalize Confirmation Modal -->
<?php if (!$existingClosure): ?>
<div class="modal fade" id="finalizeModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-lock me-2 text-danger"></i>Finalize Day Closing</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>You are about to finalize the closing report for <strong><?= $today ?></strong>.</p>
        <p class="text-muted small">This will create an immutable snapshot of today's sales data. This action cannot be undone.</p>
        <div id="finalize-error" class="alert alert-danger py-2 d-none"></div>
        <div class="mb-2">
          <label class="form-label form-label-sm">Closing Notes <span class="text-muted">(optional)</span></label>
          <textarea id="closing-notes" class="form-control form-control-sm" rows="2" placeholder="Any notes for this closing..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger" id="btn-confirm-finalize">
          <i class="bi bi-lock me-1"></i>Confirm Finalize
        </button>
      </div>
    </div>
  </div>
</div>
<script>
document.getElementById('btn-confirm-finalize').addEventListener('click', async function () {
  const btn   = this;
  const errEl = document.getElementById('finalize-error');
  const notes = document.getElementById('closing-notes').value.trim();
  errEl.classList.add('d-none');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Finalizing…';
  try {
    const res  = await fetch('api/closing_history_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'finalize_closure', closing_notes: notes })
    });
    const data = await res.json();
    if (!data.success) {
      errEl.textContent = data.error;
      errEl.classList.remove('d-none');
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-lock me-1"></i>Confirm Finalize';
      return;
    }
    bootstrap.Modal.getInstance(document.getElementById('finalizeModal')).hide();
    // Replace finalize button with green badge
    const finalizeBtn = document.getElementById('btn-finalize');
    const badge = document.createElement('span');
    badge.className = 'btn btn-sm btn-success disabled';
    badge.innerHTML = '<i class="bi bi-check-circle me-1"></i>Day Already Finalized';
    finalizeBtn.replaceWith(badge);
  } catch (e) {
    errEl.textContent = 'Network error.';
    errEl.classList.remove('d-none');
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-lock me-1"></i>Confirm Finalize';
  }
});
</script>
<?php endif; ?>

<?php layoutEnd(); ?>
