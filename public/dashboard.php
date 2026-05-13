<?php
require_once __DIR__ . '/../app/bootstrap.php';
requireRole('owner');

require_once APP_ROOT . '/app/Services/ReportService.php';
require_once APP_ROOT . '/app/Services/InsightService.php';

$today      = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');

$conn    = getConnection();
$report  = new ReportService($conn);

$todaySummary     = $report->getTodaySummary();
$monthlySummary   = $report->getRangeSummary($monthStart, $monthEnd);
$todayBestSellers = $report->getBestSellers($today, $today);
$lowStock         = $report->getLowStockProducts();

// Chart data
$last7DaysTrend   = $report->getLast7DaysRevenueTrend();
$paymentBreakdown = $report->getPaymentMethodBreakdown();
$monthlyTrend     = $report->getMonthlyRevenueTrend();
$topProducts      = $report->getTopSellingProductsChart(5);
$insights         = $report->getDashboardInsights();

// Phase 7B data
$profitMargins       = $report->getProfitMarginsSummary();
$profitTrend30       = $report->getProfitTrendLast30Days();
$topProfitable       = $report->getTopProfitableProducts(8);
$inventoryValuation  = $report->getInventoryValuation();
$fastMoving          = $report->getFastMovingProducts(8);
$deadStock           = $report->getDeadStockProducts();
$stockRisk           = $report->getStockRiskProducts();
$deliveryStats       = $report->getDeliveryCompletionStats();
$deliveryStatusDist  = $report->getDeliveryStatusDistribution();
$muniBreakdown       = $report->getMunicipalityDeliveryBreakdown(8);
$lowestMeasured      = $report->getLowestMeasuredStock();
$mostSoldMeasured    = $report->getMostSoldMeasuredProduct();
$insightSvc          = new InsightService($conn, $report);
$smartInsights       = $insightSvc->generateInsights();

$conn->close();

function peso(float $v): string { return '&#8369;' . number_format($v, 2); }

require_once APP_ROOT . '/app/layout.php';
layoutStart('Lumina POS – Dashboard');
?>
<style>
  .chart-card { border-radius: .5rem; border: none; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
  .chart-card .card-header { background: #fff; border-bottom: 1px solid #f0f0f0; font-size: .85rem; font-weight: 600; color: #6c757d; }
  .chart-container { position: relative; height: 260px; width: 100%; }
  .chart-container-lg { height: 300px; }
  .insight-card { border-left: 3px solid; }
  .insight-card.growth   { border-color: #198754; }
  .insight-card.payment  { border-color: #0d6efd; }
  .insight-card.product  { border-color: #6f42c1; }
  .insight-card.critical { border-color: #dc3545; }
</style>

<?php layoutHeader('Dashboard', 'bi-speedometer2'); ?>
<div class="container-fluid px-4">

  <!-- Today Summary -->
  <div class="section-title"><i class="bi bi-calendar-check"></i> Today — <?= $today ?></div>
  <div class="row g-3 mb-4">
    <?php
    $cards = [
      ['orders',  'bi-receipt',       'Orders Today',   (int)$todaySummary['total_orders'],                    false],
      ['revenue', 'bi-cash-stack',    'Revenue',        (float)$todaySummary['total_revenue'],                 true],
      ['cost',    'bi-box-seam',      'Cost of Goods',  (float)$todaySummary['total_cost'],                    true],
      ['profit',  'bi-graph-up-arrow','Gross Profit',   (float)$todaySummary['gross_profit'],                  true],
    ];
    foreach ($cards as [$type, $icon, $label, $value, $isMoney]): ?>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card <?= $type ?> h-100">
        <div class="card-body py-3">
          <div class="text-muted small mb-1"><i class="bi <?= $icon ?>"></i> <?= $label ?></div>
          <div class="fs-4 fw-bold <?= ($type === 'profit' && $value < 0) ? 'text-danger' : '' ?>">
            <?= $isMoney ? peso($value) : $value ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Monthly Overview -->
  <div class="section-title"><i class="bi bi-calendar-month"></i> This Month — <?= date('F Y') ?></div>
  <div class="row g-3 mb-4">
    <?php
    $mcards = [
      ['orders',  'bi-receipt',       'Total Orders',  (int)$monthlySummary['total_orders'],   false],
      ['revenue', 'bi-cash-stack',    'Revenue',       (float)$monthlySummary['total_revenue'], true],
      ['cost',    'bi-box-seam',      'Cost of Goods', (float)$monthlySummary['total_cost'],    true],
      ['profit',  'bi-graph-up-arrow','Gross Profit',  (float)$monthlySummary['gross_profit'],  true],
    ];
    foreach ($mcards as [$type, $icon, $label, $value, $isMoney]): ?>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card <?= $type ?> h-100">
        <div class="card-body py-3">
          <div class="text-muted small mb-1"><i class="bi <?= $icon ?>"></i> <?= $label ?></div>
          <div class="fs-4 fw-bold <?= ($type === 'profit' && $value < 0) ? 'text-danger' : '' ?>">
            <?= $isMoney ? peso($value) : $value ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Business Insights -->
  <div class="section-title"><i class="bi bi-lightbulb"></i> Business Insights</div>
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card shadow-sm insight-card growth h-100">
        <div class="card-body py-3">
          <div class="text-muted small mb-1"><i class="bi bi-graph-up"></i> Weekly Growth</div>
          <div class="fs-4 fw-bold <?= $insights['weekly_growth_percent'] >= 0 ? 'text-success' : 'text-danger' ?>">
            <?= $insights['weekly_growth_percent'] >= 0 ? '+' : '' ?><?= $insights['weekly_growth_percent'] ?>%
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm insight-card payment h-100">
        <div class="card-body py-3">
          <div class="text-muted small mb-1"><i class="bi bi-credit-card"></i> Top Payment</div>
          <div class="fs-5 fw-bold"><?= htmlspecialchars($insights['top_payment_method']) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm insight-card product h-100">
        <div class="card-body py-3">
          <div class="text-muted small mb-1"><i class="bi bi-star"></i> Best Seller</div>
          <div class="fs-6 fw-bold" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?= htmlspecialchars($insights['top_product']) ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm insight-card critical h-100">
        <div class="card-body py-3">
          <div class="text-muted small mb-1"><i class="bi bi-exclamation-octagon"></i> Out of Stock</div>
          <div class="fs-4 fw-bold <?= $insights['critical_low_stock_count'] > 0 ? 'text-danger' : 'text-success' ?>">
            <?= $insights['critical_low_stock_count'] ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts Row 1 -->
  <div class="row g-4 mb-4">
    <!-- Revenue Trend (Last 7 Days) -->
    <div class="col-lg-8">
      <div class="card chart-card h-100">
        <div class="card-header"><i class="bi bi-graph-up me-1"></i> Revenue (Last 7 Days)</div>
        <div class="card-body">
          <div class="chart-container-lg">
            <canvas id="chartRevenue7Days"></canvas>
          </div>
        </div>
      </div>
    </div>
    <!-- Payment Method Doughnut -->
    <div class="col-lg-4">
      <div class="card chart-card h-100">
        <div class="card-header"><i class="bi bi-pie-chart me-1"></i> Payment Methods (This Month)</div>
        <div class="card-body d-flex align-items-center justify-content-center">
          <div class="chart-container">
            <canvas id="chartPaymentMethods"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts Row 2 -->
  <div class="row g-4 mb-4">
    <!-- Monthly Revenue Bar -->
    <div class="col-lg-7">
      <div class="card chart-card h-100">
        <div class="card-header"><i class="bi bi-bar-chart me-1"></i> Daily Revenue (<?= date('F Y') ?>)</div>
        <div class="card-body">
          <div class="chart-container">
            <canvas id="chartMonthlyRevenue"></canvas>
          </div>
        </div>
      </div>
    </div>
    <!-- Top Selling Products Horizontal Bar -->
    <div class="col-lg-5">
      <div class="card chart-card h-100">
        <div class="card-header"><i class="bi bi-trophy me-1"></i> Top 5 Products (This Month)</div>
        <div class="card-body">
          <div class="chart-container">
            <canvas id="chartTopProducts"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Best Sellers Today & Low Stock -->
  <div class="row g-4 mb-4">

    <!-- Best Sellers Today -->
    <div class="col-lg-7">
      <div class="card shadow-sm h-100">
        <div class="card-header bg-white fw-semibold">
          <i class="bi bi-trophy text-warning"></i> Top 5 Best Sellers Today
        </div>
        <div class="card-body p-0">
          <?php if (empty($todayBestSellers)): ?>
            <p class="text-muted text-center py-4 mb-0">No sales recorded today.</p>
          <?php else: ?>
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr><th>#</th><th>Product</th><th class="text-center">Qty</th><th class="text-end">Revenue</th><th class="text-end">Profit</th></tr>
            </thead>
            <tbody>
              <?php foreach ($todayBestSellers as $i => $row):
                $profit = (float)$row['total_revenue'] - (float)$row['total_cost']; ?>
              <tr>
                <td class="text-muted"><?= $i + 1 ?></td>
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars($row['name']) ?></div>
                  <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($row['sku'] ?? '') ?></div>
                </td>
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

    <!-- Low Stock -->
    <div class="col-lg-5">
      <div class="card shadow-sm h-100">
        <div class="card-header bg-white fw-semibold">
          <i class="bi bi-exclamation-triangle text-warning"></i> Low Stock Alert
          <?php if (!empty($lowStock)): ?>
            <span class="badge bg-danger ms-1"><?= count($lowStock) ?></span>
          <?php endif; ?>
        </div>
        <div class="card-body p-0">
          <?php if (empty($lowStock)): ?>
            <p class="text-success text-center py-4 mb-0"><i class="bi bi-check-circle"></i> All products sufficiently stocked.</p>
          <?php else: ?>
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr><th>Product</th><th class="text-center">Stock</th><th class="text-center">Min</th></tr>
            </thead>
            <tbody>
              <?php foreach ($lowStock as $row): ?>
              <tr class="<?= (int)$row['stock'] === 0 ? 'table-danger' : 'table-warning' ?>">
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars($row['name']) ?></div>
                  <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($row['sku'] ?? '') ?></div>
                </td>
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

  </div>

  <div class="text-muted small text-end mb-4">
    Generated: <?= date('Y-m-d H:i:s') ?> &nbsp;|&nbsp;
    <a href="dashboard.php">Refresh</a>
  </div>

</div>

<script src="/lumina-pos/assets/vendor/chartjs/chart.umd.min.js"></script>
<script>if (typeof Chart === 'undefined') console.error('Chart.js failed to load');</script>
<script>
document.addEventListener('DOMContentLoaded', function () {

  // ── Shared defaults ──────────────────────────────────────────────────────────
  // Register a global easing + animation duration so all charts feel consistent
  // without repeating config on each instance.
  Chart.defaults.animation.duration  = 700;
  Chart.defaults.animation.easing    = 'easeOutQuart';
  Chart.defaults.font.family         = 'inherit';
  Chart.defaults.font.size           = 12;
  Chart.defaults.color               = '#6c757d';

  const PESO = v => '₱' + v.toLocaleString(undefined, { minimumFractionDigits: 2 });

  const baseOpts = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } }
  };

  // ── Intersection Observer — only animate when chart scrolls into view ────────
  // Prevents all 4 charts firing simultaneously on page load,
  // keeping the main thread free while the user reads the top cards.
  const pending = new Map();

  function lazyInit(canvasId, factory) {
    const el = document.getElementById(canvasId);
    if (!el) return;
    if ('IntersectionObserver' in window) {
      const obs = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            obs.unobserve(entry.target);
            factory(entry.target);
          }
        });
      }, { threshold: 0.15 });
      obs.observe(el);
    } else {
      factory(el); // fallback: init immediately
    }
  }

  // ── Revenue Trend (Last 7 Days) ──────────────────────────────────────────────
  const rev7Labels = <?= json_encode(array_column($last7DaysTrend, 'label')) ?>;
  const rev7Data   = <?= json_encode(array_column($last7DaysTrend, 'revenue')) ?>;

  lazyInit('chartRevenue7Days', el => {
    // Build gradient fill inside the factory so canvas dimensions are ready
    const ctx  = el.getContext('2d');
    const grad = ctx.createLinearGradient(0, 0, 0, el.offsetHeight || 300);
    grad.addColorStop(0,   'rgba(13,110,253,0.18)');
    grad.addColorStop(1,   'rgba(13,110,253,0)');

    new Chart(el, {
      type: 'line',
      data: {
        labels: rev7Labels,
        datasets: [{
          label: 'Revenue',
          data: rev7Data,
          borderColor: '#0d6efd',
          backgroundColor: grad,
          fill: true,
          tension: 0.45,
          pointRadius: 4,
          pointBackgroundColor: '#0d6efd',
          pointHoverRadius: 7,
          pointHoverBackgroundColor: '#fff',
          pointHoverBorderColor: '#0d6efd',
          pointHoverBorderWidth: 2,
          borderWidth: 2.5
        }]
      },
      options: {
        ...baseOpts,
        animation: {
          // Line draws itself left-to-right
          x: { duration: 0 },
          y: { duration: 700, easing: 'easeOutQuart', from: el.offsetHeight || 300 }
        },
        interaction: { mode: 'index', intersect: false },
        scales: {
          x: { grid: { display: false } },
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(0,0,0,0.04)' },
            ticks: { callback: v => '₱' + v.toLocaleString() }
          }
        },
        plugins: {
          tooltip: {
            backgroundColor: 'rgba(255,255,255,0.95)',
            borderColor: '#dee2e6',
            borderWidth: 1,
            titleColor: '#212529',
            bodyColor: '#495057',
            padding: 10,
            callbacks: { label: ctx => ' ' + PESO(ctx.parsed.y) }
          }
        }
      }
    });
  });

  // ── Payment Methods Doughnut ─────────────────────────────────────────────────
  const payLabels = <?= json_encode(array_map(fn($p) => ucfirst($p['method']), $paymentBreakdown)) ?>;
  const payData   = <?= json_encode(array_map(fn($p) => (float)$p['total'], $paymentBreakdown)) ?>;
  const payColors = ['#198754', '#6f42c1', '#fd7e14'];

  lazyInit('chartPaymentMethods', el => {
    new Chart(el, {
      type: 'doughnut',
      data: {
        labels: payLabels.length ? payLabels : ['No Data'],
        datasets: [{
          data: payData.length ? payData : [1],
          backgroundColor: payData.length ? payColors : ['#dee2e6'],
          hoverOffset: 8,
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        animation: { animateRotate: true, animateScale: true, duration: 750, easing: 'easeOutBack' },
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: { boxWidth: 11, padding: 14, usePointStyle: true, pointStyleWidth: 10 }
          },
          tooltip: {
            backgroundColor: 'rgba(255,255,255,0.95)',
            borderColor: '#dee2e6',
            borderWidth: 1,
            titleColor: '#212529',
            bodyColor: '#495057',
            padding: 10,
            callbacks: { label: ctx => ' ' + PESO(ctx.parsed) }
          }
        }
      }
    });
  });

  // ── Monthly Revenue Bar ──────────────────────────────────────────────────────
  const monthLabels = <?= json_encode(array_column($monthlyTrend, 'day')) ?>;
  const monthData   = <?= json_encode(array_column($monthlyTrend, 'revenue')) ?>;

  lazyInit('chartMonthlyRevenue', el => {
    new Chart(el, {
      type: 'bar',
      data: {
        labels: monthLabels,
        datasets: [{
          label: 'Revenue',
          data: monthData,
          backgroundColor: monthData.map(v =>
            v > 0 ? 'rgba(13,110,253,0.75)' : 'rgba(13,110,253,0.15)'
          ),
          hoverBackgroundColor: '#0d6efd',
          borderRadius: 4,
          borderSkipped: false
        }]
      },
      options: {
        ...baseOpts,
        animation: { delay: (ctx) => ctx.dataIndex * 18, duration: 500, easing: 'easeOutQuart' },
        interaction: { mode: 'index', intersect: false },
        scales: {
          x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } },
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(0,0,0,0.04)' },
            ticks: { callback: v => '₱' + v.toLocaleString() }
          }
        },
        plugins: {
          tooltip: {
            backgroundColor: 'rgba(255,255,255,0.95)',
            borderColor: '#dee2e6',
            borderWidth: 1,
            titleColor: '#212529',
            bodyColor: '#495057',
            padding: 10,
            callbacks: { label: ctx => ' ' + PESO(ctx.parsed.y) }
          }
        }
      }
    });
  });

  // ── Top Products Horizontal Bar ──────────────────────────────────────────────
  const topLabels = <?= json_encode(array_map(fn($p) => mb_strlen($p['name']) > 18 ? mb_substr($p['name'], 0, 18) . '…' : $p['name'], $topProducts)) ?>;
  const topData   = <?= json_encode(array_map(fn($p) => (int)$p['total_qty'], $topProducts)) ?>;
  const barColors = ['#6f42c1','#7952cc','#8a63d2','#9b74d8','#ac85de'];

  lazyInit('chartTopProducts', el => {
    new Chart(el, {
      type: 'bar',
      data: {
        labels: topLabels,
        datasets: [{
          label: 'Qty Sold',
          data: topData,
          backgroundColor: barColors,
          hoverBackgroundColor: barColors.map(c => c),
          borderRadius: 4,
          borderSkipped: false
        }]
      },
      options: {
        ...baseOpts,
        indexAxis: 'y',
        animation: { delay: (ctx) => ctx.dataIndex * 80, duration: 550, easing: 'easeOutQuart' },
        scales: {
          x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } },
          y: { grid: { display: false } }
        },
        plugins: {
          tooltip: {
            backgroundColor: 'rgba(255,255,255,0.95)',
            borderColor: '#dee2e6',
            borderWidth: 1,
            titleColor: '#212529',
            bodyColor: '#495057',
            padding: 10,
            callbacks: { label: ctx => ' ' + ctx.parsed.x + ' units' }
          }
        }
      }
    });
  });

});
</script>

<?php
// ── Phase 7B: Profit Intelligence Row ────────────────────────────────────────
$delivSuccessRate = (float)$deliveryStats['success_rate_pct'];
$retailVal        = (float)$inventoryValuation['retail_value'];
?>

<div class="container-fluid px-4">

  <!-- A. Profit Intelligence Cards -->
  <div class="section-title mt-2"><i class="bi bi-graph-up"></i> Profit Intelligence — <?= date('F Y') ?></div>
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card profit h-100">
        <div class="card-body py-3">
          <div class="text-muted small mb-1"><i class="bi bi-cash-stack me-1"></i>Gross Profit (Month)</div>
          <div class="fs-4 fw-bold <?= $profitMargins['gross_profit'] < 0 ? 'text-danger' : '' ?>">
            &#8369;<?= number_format($profitMargins['gross_profit'], 2) ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card revenue h-100">
        <div class="card-body py-3">
          <div class="text-muted small mb-1"><i class="bi bi-percent me-1"></i>Profit Margin</div>
          <div class="fs-4 fw-bold <?= $profitMargins['profit_margin_pct'] < 10 ? 'text-danger' : 'text-success' ?>">
            <?= $profitMargins['profit_margin_pct'] ?>%
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card orders h-100">
        <div class="card-body py-3">
          <div class="text-muted small mb-1"><i class="bi bi-boxes me-1"></i>Inventory Value (Retail)</div>
          <div class="fs-4 fw-bold">&#8369;<?= number_format($retailVal, 2) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card <?= $delivSuccessRate >= 90 ? 'profit' : ($delivSuccessRate < 70 ? 'cost' : 'revenue') ?> h-100">
        <div class="card-body py-3">
          <div class="text-muted small mb-1"><i class="bi bi-truck me-1"></i>Delivery Success Rate</div>
          <div class="fs-4 fw-bold <?= $delivSuccessRate >= 90 ? 'text-success' : ($delivSuccessRate < 70 ? 'text-danger' : '') ?>">
            <?= $delivSuccessRate ?>%
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Operational Measured Insights -->
  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card shadow-sm h-100" style="border-left:3px solid #0dcaf0">
        <div class="card-body py-3">
          <div class="text-muted small mb-1"><i class="bi bi-droplet me-1"></i>Lowest Stock Product</div>
          <?php if ($lowestMeasured): ?>
          <div class="fw-semibold"><?= htmlspecialchars($lowestMeasured['name']) ?></div>
          <div class="text-info fw-bold"><?= number_format((float)$lowestMeasured['stock'], 3) ?> <?= htmlspecialchars($lowestMeasured['inventory_unit']) ?> remaining</div>
          <?php else: ?>
          <div class="text-muted small">No products in stock.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card shadow-sm h-100" style="border-left:3px solid #6f42c1">
        <div class="card-body py-3">
          <div class="text-muted small mb-1"><i class="bi bi-bar-chart me-1"></i>Most Sold Product (Month)</div>
          <?php if ($mostSoldMeasured): ?>
          <div class="fw-semibold"><?= htmlspecialchars($mostSoldMeasured['name']) ?></div>
          <div class="fw-bold" style="color:#6f42c1"><?= number_format((float)$mostSoldMeasured['total_qty'], 3) ?> <?= htmlspecialchars($mostSoldMeasured['inventory_unit']) ?> sold</div>
          <?php else: ?>
          <div class="text-muted small">No product sales this month.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- B. Operational Charts Row 1: Profit Trend + Fast Moving -->
  <div class="row g-4 mb-4">
    <div class="col-lg-8">
      <div class="card chart-card h-100">
        <div class="card-header"><i class="bi bi-graph-up me-1"></i> 30-Day Profit Trend</div>
        <div class="card-body">
          <div class="chart-container-lg"><canvas id="chartProfitTrend"></canvas></div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card chart-card h-100">
        <div class="card-header"><i class="bi bi-lightning me-1"></i> Fast Moving Products (30 Days)</div>
        <div class="card-body">
          <div class="chart-container"><canvas id="chartFastMoving"></canvas></div>
        </div>
      </div>
    </div>
  </div>

  <!-- B. Operational Charts Row 2: Delivery Status + Municipality -->
  <div class="row g-4 mb-4">
    <div class="col-lg-4">
      <div class="card chart-card h-100">
        <div class="card-header"><i class="bi bi-pie-chart me-1"></i> Delivery Status Distribution</div>
        <div class="card-body d-flex align-items-center justify-content-center">
          <div class="chart-container"><canvas id="chartDeliveryStatus"></canvas></div>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card chart-card h-100">
        <div class="card-header"><i class="bi bi-geo-alt me-1"></i> Deliveries by Municipality</div>
        <div class="card-body">
          <div class="chart-container"><canvas id="chartMuniDelivery"></canvas></div>
        </div>
      </div>
    </div>
  </div>

  <!-- C. Smart Business Insights -->
  <div class="section-title"><i class="bi bi-lightbulb"></i> Smart Business Insights</div>
  <div class="row g-3 mb-4">
    <?php if (empty($smartInsights)): ?>
    <div class="col-12">
      <div class="alert alert-secondary py-2 mb-0">Not enough data to generate insights yet.</div>
    </div>
    <?php else: ?>
    <?php foreach ($smartInsights as $ins):
      $borderMap = ['success'=>'#198754','warning'=>'#ffc107','info'=>'#0d6efd','danger'=>'#dc3545'];
      $border    = $borderMap[$ins['type']] ?? '#6c757d';
      $textMap   = ['success'=>'text-success','warning'=>'text-warning','info'=>'text-primary','danger'=>'text-danger'];
      $textCls   = $textMap[$ins['type']] ?? 'text-secondary';
    ?>
    <div class="col-md-6 col-lg-4">
      <div class="card shadow-sm h-100" style="border-left:3px solid <?= $border ?>">
        <div class="card-body py-3 d-flex align-items-start gap-2">
          <i class="bi <?= htmlspecialchars($ins['icon']) ?> fs-5 <?= $textCls ?> flex-shrink-0 mt-1"></i>
          <span style="font-size:.875rem"><?= htmlspecialchars($ins['message']) ?></span>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- D. Inventory Risk Table -->
  <div class="section-title"><i class="bi bi-exclamation-octagon"></i> Inventory Risk — Active Demand</div>
  <div class="card shadow-sm mb-4">
    <?php if (empty($stockRisk)): ?>
    <div class="card-body text-center text-success py-4">
      <i class="bi bi-check-circle me-1"></i> No critical stock risk products detected.
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Product</th>
            <th>Category</th>
            <th class="text-center">Stock</th>
            <th class="text-center">Min Alert</th>
            <th class="text-center">Sold (14d)</th>
            <th class="text-center">Risk</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($stockRisk as $p):
            $isOut = (int)$p['stock'] === 0;
          ?>
          <tr class="<?= $isOut ? 'table-danger' : 'table-warning' ?>">
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($p['name']) ?></div>
              <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($p['sku'] ?? '') ?></div>
            </td>
            <td class="text-muted"><?= htmlspecialchars($p['category'] ?? '—') ?></td>
            <td class="text-center fw-bold <?= $isOut ? 'text-danger' : 'text-warning' ?>"><?= (int)$p['stock'] ?></td>
            <td class="text-center text-muted"><?= (int)$p['min_stock_alert'] ?></td>
            <td class="text-center fw-semibold"><?= (int)$p['sold_last_14'] ?></td>
            <td class="text-center">
              <span class="badge <?= $isOut ? 'bg-danger' : 'bg-warning text-dark' ?>">
                <?= $isOut ? 'Out of Stock' : 'Low Stock' ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Dead Stock Summary -->
  <?php if (!empty($deadStock)): ?>
  <div class="section-title"><i class="bi bi-archive"></i> Dead Stock (No Sales in 60+ Days)</div>
  <div class="card shadow-sm mb-4">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Product</th>
            <th>Category</th>
            <th class="text-center">Stock</th>
            <th class="text-end">Stock Value (Cost)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_slice($deadStock, 0, 10) as $p): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($p['name']) ?></div>
              <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($p['sku'] ?? '') ?></div>
            </td>
            <td class="text-muted"><?= htmlspecialchars($p['category'] ?? '—') ?></td>
            <td class="text-center"><?= (int)$p['stock'] ?></td>
            <td class="text-end">&#8369;<?= number_format((float)$p['stock_value'], 2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /phase-7b container -->

<div class="container-fluid px-4">

  <!-- System Health -->
  <div class="section-title"><i class="bi bi-heart-pulse"></i> System Health</div>
  <div class="card shadow-sm mb-4">
    <div class="card-body py-3">
      <?php
        $backupDir  = APP_ROOT . '/storage/backups';
        $logsDir    = APP_ROOT . '/storage/logs';
        $dbOk       = false;
        try { $hc = getConnection(); $hc->query('SELECT 1'); $hc->close(); $dbOk = true; } catch(Exception $e) {}
        $checks = [
          ['PHP Version',          PHP_VERSION,                                                    true],
          ['MySQL Connection',     $dbOk ? 'OK' : 'Failed',                                       $dbOk],
          ['Backup Dir Writable',  is_writable($backupDir) ? 'Writable' : 'Not writable / missing', is_writable($backupDir)],
          ['Logs Dir Writable',    is_writable($logsDir)   ? 'Writable' : 'Not writable / missing', is_writable($logsDir)],
          ['Server Time',          date('Y-m-d H:i:s'),                                           true],
        ];
      ?>
      <div class="row g-2">
        <?php foreach ($checks as [$label, $value, $ok]): ?>
        <div class="col-sm-6 col-md-4">
          <div class="d-flex align-items-center gap-2">
            <i class="bi <?= $ok ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?>"></i>
            <span class="text-muted small"><?= htmlspecialchars($label) ?>:</span>
            <span class="small fw-semibold"><?= htmlspecialchars($value) ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="text-muted small text-end mb-4">
    Generated: <?= date('Y-m-d H:i:s') ?> &nbsp;|&nbsp;
    <a href="dashboard.php">Refresh</a>
  </div>

</div>


<script>
(function() {
  const PESO = v => '₱' + v.toLocaleString(undefined, { minimumFractionDigits: 2 });

  function lazyInit(id, factory) {
    const el = document.getElementById(id);
    if (!el) return;
    if ('IntersectionObserver' in window) {
      const obs = new IntersectionObserver((entries, obs) => {
        entries.forEach(e => { if (e.isIntersecting) { obs.unobserve(e.target); factory(e.target); } });
      }, { threshold: 0.15 });
      obs.observe(el);
    } else { factory(el); }
  }

  const baseOpts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } };
  const tooltipDefaults = {
    backgroundColor: 'rgba(255,255,255,0.95)', borderColor: '#dee2e6', borderWidth: 1,
    titleColor: '#212529', bodyColor: '#495057', padding: 10
  };

  // Profit Trend 30 Days
  const profitLabels = <?= json_encode(array_column($profitTrend30, 'label')) ?>;
  const profitData   = <?= json_encode(array_column($profitTrend30, 'profit')) ?>;
  lazyInit('chartProfitTrend', el => {
    const ctx  = el.getContext('2d');
    const grad = ctx.createLinearGradient(0, 0, 0, el.offsetHeight || 300);
    grad.addColorStop(0, 'rgba(25,135,84,0.18)');
    grad.addColorStop(1, 'rgba(25,135,84,0)');
    new Chart(el, {
      type: 'line',
      data: { labels: profitLabels, datasets: [{
        label: 'Profit', data: profitData,
        borderColor: '#198754', backgroundColor: grad, fill: true,
        tension: 0.4, pointRadius: 2, pointHoverRadius: 5, borderWidth: 2
      }]},
      options: { ...baseOpts,
        interaction: { mode: 'index', intersect: false },
        scales: {
          x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } },
          y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' },
               ticks: { callback: v => '₱' + v.toLocaleString() } }
        },
        plugins: { legend: { display: false },
          tooltip: { ...tooltipDefaults, callbacks: { label: ctx => ' ' + PESO(ctx.parsed.y) } } }
      }
    });
  });

  // Fast Moving Products
  const fmLabels = <?= json_encode(array_map(fn($p) => mb_strlen($p['name']) > 18 ? mb_substr($p['name'],0,18).'...' : $p['name'], $fastMoving)) ?>;
  const fmData   = <?= json_encode(array_map(fn($p) => (int)$p['total_qty'], $fastMoving)) ?>;
  lazyInit('chartFastMoving', el => {
    new Chart(el, {
      type: 'bar',
      data: { labels: fmLabels, datasets: [{
        label: 'Qty Sold', data: fmData,
        backgroundColor: '#0d6efd', borderRadius: 4, borderSkipped: false
      }]},
      options: { ...baseOpts, indexAxis: 'y',
        scales: { x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } }, y: { grid: { display: false } } },
        plugins: { legend: { display: false },
          tooltip: { ...tooltipDefaults, callbacks: { label: ctx => ' ' + ctx.parsed.x + ' units' } } }
      }
    });
  });

  // Delivery Status Doughnut
  const dsLabels = <?= json_encode(array_map(fn($r) => ucwords(str_replace('_',' ',$r['delivery_status'])), $deliveryStatusDist)) ?>;
  const dsData   = <?= json_encode(array_map(fn($r) => (int)$r['cnt'], $deliveryStatusDist)) ?>;
  const dsColors = { pending:'#ffc107', preparing:'#0dcaf0', ready:'#0d6efd', out_for_delivery:'#212529', delivered:'#198754', cancelled:'#dc3545' };
  const dsBg     = <?= json_encode(array_map(fn($r) => match($r['delivery_status']) {
    'pending'=>'#ffc107','preparing'=>'#0dcaf0','ready'=>'#0d6efd',
    'out_for_delivery'=>'#212529','delivered'=>'#198754','cancelled'=>'#dc3545', default=>'#6c757d'
  }, $deliveryStatusDist)) ?>;
  lazyInit('chartDeliveryStatus', el => {
    new Chart(el, {
      type: 'doughnut',
      data: { labels: dsLabels.length ? dsLabels : ['No Data'],
              datasets: [{ data: dsData.length ? dsData : [1],
                backgroundColor: dsBg.length ? dsBg : ['#dee2e6'], borderWidth: 0, hoverOffset: 6 }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '65%',
        plugins: { legend: { display: true, position: 'bottom',
          labels: { boxWidth: 11, padding: 12, usePointStyle: true } },
          tooltip: { ...tooltipDefaults } }
      }
    });
  });

  // Municipality Delivery Bar
  const muniLabels = <?= json_encode(array_column($muniBreakdown, 'municipality')) ?>;
  const muniData   = <?= json_encode(array_column($muniBreakdown, 'total')) ?>;
  lazyInit('chartMuniDelivery', el => {
    new Chart(el, {
      type: 'bar',
      data: { labels: muniLabels, datasets: [{
        label: 'Deliveries', data: muniData,
        backgroundColor: 'rgba(111,66,193,0.75)', hoverBackgroundColor: '#6f42c1',
        borderRadius: 4, borderSkipped: false
      }]},
      options: { ...baseOpts,
        scales: { x: { grid: { display: false } },
          y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { precision: 0 } } },
        plugins: { legend: { display: false },
          tooltip: { ...tooltipDefaults, callbacks: { label: ctx => ' ' + ctx.parsed.y + ' orders' } } }
      }
    });
  });
})();
</script>
<?php layoutEnd(); ?>
