<?php
require_once __DIR__ . '/../app/bootstrap.php';
requireRole('owner');

require_once APP_ROOT . '/app/Services/ReportService.php';

// ── Date range ────────────────────────────────────────────────────────────────
$today      = date('Y-m-d');
$monthStart = date('Y-m-01');
$startDate  = $_GET['start_date'] ?? $monthStart;
$endDate    = $_GET['end_date']   ?? $today;

if (!strtotime($startDate)) $startDate = $monthStart;
if (!strtotime($endDate))   $endDate   = $today;
if ($startDate > $endDate)  [$startDate, $endDate] = [$endDate, $startDate];

$conn    = getConnection();
$report  = new ReportService($conn);

$todaySummary     = $report->getTodaySummary();
$todayProfit      = (float)$todaySummary['gross_profit'];
$paymentBreakdown = $report->getTodayPaymentBreakdown();
$lowStock         = $report->getLowStockProducts();
$bestSellers      = $report->getBestSellers($startDate, $endDate);
$rangeSummary     = $report->getRangeSummary($startDate, $endDate);
$rangeProfit      = (float)$rangeSummary['gross_profit'];

$conn->close();

// ── Helpers ───────────────────────────────────────────────────────────────────
function peso(float $v): string { return '₱' . number_format($v, 2); }
function methodLabel(string $m): string {
    return ['cash' => 'Cash', 'gcash' => 'GCash', 'bank_transfer' => 'Bank Transfer'][$m] ?? ucfirst($m);
}

require_once APP_ROOT . '/app/layout.php';
layoutStart('Hardware POS – Reports');
?>
<?php layoutHeader('Sales Reports', 'bi-bar-chart-line'); ?>
<div class="container-fluid px-4">

  <!-- Date range filter -->
  <form method="get" class="card shadow-sm mb-4 no-print">
    <div class="card-body py-2">
      <div class="row g-2 align-items-end">
        <div class="col-auto">
          <label class="form-label mb-1 small fw-semibold">Start Date</label>
          <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate) ?>">
        </div>
        <div class="col-auto">
          <label class="form-label mb-1 small fw-semibold">End Date</label>
          <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate) ?>">
        </div>
        <div class="col-auto d-flex gap-2">
          <button class="btn btn-sm btn-dark">Apply</button>
          <a href="report.php" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
        <div class="col-auto ms-auto text-muted small align-self-center d-flex align-items-center gap-2">
          <span>Showing: <strong><?= htmlspecialchars($startDate) ?></strong> → <strong><?= htmlspecialchars($endDate) ?></strong></span>
          <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
              <i class="bi bi-download me-1"></i>Export Orders
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="export_orders.php?type=today"><i class="bi bi-calendar-day me-2"></i>Today's Orders</a></li>
              <li><a class="dropdown-item" href="export_orders.php?type=range&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>"><i class="bi bi-calendar-range me-2"></i>Range Orders</a></li>
            </ul>
          </div>
          <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
              <i class="bi bi-download me-1"></i>Export Reports
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="export_reports.php?type=today"><i class="bi bi-graph-up me-2"></i>Today Summary</a></li>
              <li><a class="dropdown-item" href="export_reports.php?type=range&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>"><i class="bi bi-graph-up me-2"></i>Range Summary</a></li>
              <li><a class="dropdown-item" href="export_reports.php?type=best_sellers&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>"><i class="bi bi-trophy me-2"></i>Best Sellers</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="export_reports.php?type=low_stock"><i class="bi bi-exclamation-triangle me-2"></i>Low Stock</a></li>
              <li><a class="dropdown-item" href="export_reports.php?type=all_products"><i class="bi bi-box-seam me-2"></i>All Products</a></li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </form>

  <!-- ── TODAY'S SUMMARY ─────────────────────────────────────────────────── -->
  <h6 class="fw-bold text-uppercase text-muted mb-2"><i class="bi bi-calendar-check"></i> Today's Summary — <?= $today ?></h6>
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card orders h-100">
        <div class="card-body py-3">
          <div class="text-muted small">Orders</div>
          <div class="fs-4 fw-bold"><?= (int)$todaySummary['total_orders'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card revenue h-100">
        <div class="card-body py-3">
          <div class="text-muted small">Revenue</div>
          <div class="fs-4 fw-bold"><?= peso((float)$todaySummary['total_revenue']) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card cost h-100">
        <div class="card-body py-3">
          <div class="text-muted small">Cost of Goods</div>
          <div class="fs-4 fw-bold"><?= peso((float)$todaySummary['total_cost']) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card profit h-100">
        <div class="card-body py-3">
          <div class="text-muted small">Gross Profit</div>
          <div class="fs-4 fw-bold <?= $todayProfit < 0 ? 'text-danger' : 'text-success' ?>">
            <?= peso($todayProfit) ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Today payment breakdown -->
  <div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold bg-white">Today's Payment Breakdown</div>
    <div class="card-body p-0">
      <?php if (empty($paymentBreakdown)): ?>
        <p class="text-muted text-center py-3 mb-0">No transactions today.</p>
      <?php else: ?>
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light">
            <tr><th>Method</th><th class="text-center">Orders</th><th class="text-end">Revenue</th></tr>
          </thead>
          <tbody>
            <?php foreach ($paymentBreakdown as $row): ?>
            <tr>
              <td><?= methodLabel($row['payment_method']) ?></td>
              <td class="text-center"><?= (int)$row['orders'] ?></td>
              <td class="text-end"><?= peso((float)$row['revenue']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── DATE RANGE SUMMARY ──────────────────────────────────────────────── -->
  <h6 class="fw-bold text-uppercase text-muted mb-2"><i class="bi bi-calendar-range"></i> Range Summary — <?= htmlspecialchars($startDate) ?> to <?= htmlspecialchars($endDate) ?></h6>
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card orders h-100">
        <div class="card-body py-3">
          <div class="text-muted small">Orders</div>
          <div class="fs-4 fw-bold"><?= (int)$rangeSummary['total_orders'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card revenue h-100">
        <div class="card-body py-3">
          <div class="text-muted small">Revenue</div>
          <div class="fs-4 fw-bold"><?= peso((float)$rangeSummary['total_revenue']) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card cost h-100">
        <div class="card-body py-3">
          <div class="text-muted small">Cost of Goods</div>
          <div class="fs-4 fw-bold"><?= peso((float)$rangeSummary['total_cost']) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card profit h-100">
        <div class="card-body py-3">
          <div class="text-muted small">Gross Profit</div>
          <div class="fs-4 fw-bold <?= $rangeProfit < 0 ? 'text-danger' : 'text-success' ?>">
            <?= peso($rangeProfit) ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">

    <!-- ── BEST SELLERS ──────────────────────────────────────────────────── -->
    <div class="col-lg-7">
      <div class="card shadow-sm h-100">
        <div class="card-header fw-semibold bg-white">
          <i class="bi bi-trophy"></i> Top 5 Best Sellers
          <span class="text-muted fw-normal small ms-1">(<?= htmlspecialchars($startDate) ?> – <?= htmlspecialchars($endDate) ?>)</span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($bestSellers)): ?>
            <p class="text-muted text-center py-3 mb-0">No sales in this period.</p>
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
                  $profit = (float)$row['total_revenue'] - (float)$row['total_cost'];
                ?>
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
    </div>

    <!-- ── LOW STOCK ─────────────────────────────────────────────────────── -->
    <div class="col-lg-5">
      <div class="card shadow-sm h-100">
        <div class="card-header fw-semibold bg-white">
          <i class="bi bi-exclamation-triangle text-warning"></i> Low Stock Alert
          <?php if (!empty($lowStock)): ?>
            <span class="badge bg-danger ms-1"><?= count($lowStock) ?></span>
          <?php endif; ?>
        </div>
        <div class="card-body p-0">
          <?php if (empty($lowStock)): ?>
            <p class="text-success text-center py-3 mb-0"><i class="bi bi-check-circle"></i> All products are sufficiently stocked.</p>
          <?php else: ?>
            <table class="table table-sm table-hover mb-0">
              <thead class="table-light">
                <tr><th>Product</th><th>Category</th><th class="text-center">Stock</th><th class="text-center">Min</th></tr>
              </thead>
              <tbody>
                <?php foreach ($lowStock as $row): ?>
                <tr class="<?= (int)$row['stock'] === 0 ? 'table-danger' : 'table-warning' ?>">
                  <td>
                    <div class="fw-semibold"><?= htmlspecialchars($row['name']) ?></div>
                    <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($row['sku'] ?? '') ?></div>
                  </td>
                  <td><?= htmlspecialchars($row['category'] ?? '—') ?></td>
                  <td class="text-center fw-bold <?= (int)$row['stock'] === 0 ? 'text-danger' : 'text-warning' ?>">
                    <?= (int)$row['stock'] ?>
                  </td>
                  <td class="text-center text-muted"><?= (int)$row['min_stock_alert'] ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div><!-- /row -->

  <div class="text-muted small text-end mb-4 no-print">
    Generated: <?= date('Y-m-d H:i:s') ?> &nbsp;|&nbsp;
    <a href="report.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>">Refresh</a>
  </div>

</div><!-- /container -->


<?php layoutEnd(); ?>
