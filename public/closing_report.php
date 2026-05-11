<?php
require_once __DIR__ . '/../app/bootstrap.php';
requireRole('owner');

require_once APP_ROOT . '/app/Services/ReportService.php';


logAction('VIEW_CLOSING_REPORT');

$today = date('Y-m-d');

$conn   = getConnection();
$report = new ReportService($conn);

$todaySummary     = $report->getTodaySummary();
$paymentBreakdown = $report->getTodayPaymentBreakdown();
$bestSellers      = $report->getBestSellers($today, $today);
$lowStock         = $report->getLowStockProducts();

$conn->close();

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
<div class="container-fluid px-4">
<div class="report-wrapper">

  <!-- Toolbar -->
  <div class="d-flex justify-content-end mb-3 no-print">
    <a href="export_orders.php?type=today" class="btn btn-sm btn-outline-success">
      <i class="bi bi-download me-1"></i>Export Closing Report CSV
    </a>
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


<?php layoutEnd(); ?>
