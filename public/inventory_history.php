<?php
require_once __DIR__ . '/../app/bootstrap.php';
requireRole('owner');

require_once APP_ROOT . '/app/Services/InventoryMovementService.php';

// ── Filters ───────────────────────────────────────────────────────────────────
$today      = date('Y-m-d');
$monthStart = date('Y-m-01');
$startDate  = $_GET['start_date']  ?? $monthStart;
$endDate    = $_GET['end_date']    ?? $today;
$actionFilter = $_GET['action_filter'] ?? '';
$search       = trim($_GET['search'] ?? '');

if (!strtotime($startDate)) $startDate = $monthStart;
if (!strtotime($endDate))   $endDate   = $today;
if ($startDate > $endDate)  [$startDate, $endDate] = [$endDate, $startDate];

$conn = getConnection();
$svc  = new InventoryMovementService($conn);

$summary   = $svc->movementSummary();
$movements = $svc->recentMovements(200, $startDate, $endDate, $actionFilter, $search);

$conn->close();

// ── Helpers ───────────────────────────────────────────────────────────────────
function actionBadge(string $type): string {
    $map = [
        'STOCK_ADD'       => ['bg-success',   'bi-plus-circle',      'Stock Add'],
        'STOCK_REMOVE'    => ['bg-danger',     'bi-dash-circle',      'Stock Remove'],
        'PRODUCT_CREATED' => ['bg-teal',       'bi-box-seam',         'Created'],
        'PRODUCT_UPDATED' => ['bg-primary',    'bi-pencil',           'Updated'],
        'PRODUCT_DELETED' => ['bg-dark',       'bi-trash',            'Deleted'],
    ];
    [$cls, $icon, $label] = $map[$type] ?? ['bg-secondary', 'bi-circle', $type];
    return "<span class=\"badge action-badge {$cls}\"><i class=\"bi {$icon} me-1\"></i>{$label}</span>";
}

function changeDisplay(float $changed, string $type): string {
    $fmt = number_format(abs($changed), 3);
    if ($type === 'STOCK_ADD')    return "<span class='text-success fw-semibold'>+{$fmt}</span>";
    if ($type === 'STOCK_REMOVE') return "<span class='text-danger fw-semibold'>{$fmt}</span>";
    return "<span class='text-muted'>{$fmt}</span>";
}

require_once APP_ROOT . '/app/layout.php';
layoutStart('Hardware POS – Inventory History');
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

  /* ── Summary cards ── */
  .summary-card { border-left: 4px solid; border-radius: .375rem; }
  .summary-card.movements { border-color: #6c757d; }
  .summary-card.added     { border-color: #198754; }
  .summary-card.removed   { border-color: #dc3545; }
  .summary-card.changes   { border-color: #0d6efd; }

  /* ── Action badges ── */
  .action-badge { font-size: .75rem; padding: .3em .6em; }
  .bg-teal { background-color: #0d9488 !important; }

  /* ── Table ── */
  .hist-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #6c757d; white-space: nowrap; }
  .hist-table td { vertical-align: middle; font-size: .875rem; }
  .hist-table tbody tr:hover { background: #f8f9ff; }
  .qty-cell { font-family: 'Courier New', monospace; font-size: .85rem; }
</style>

<?php layoutHeader('Inventory History', 'bi-clock-history'); ?>
<div class="container-fluid px-4">

  <!-- Summary cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card shadow-sm summary-card movements h-100">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-list-ul me-1"></i>Total Movements Today</div>
          <div class="fs-4 fw-bold"><?= (int)$summary['total_today'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm summary-card added h-100">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-plus-circle me-1"></i>Units Added Today</div>
          <div class="fs-4 fw-bold text-success"><?= (int)$summary['units_added'] ?></div>
          <div class="text-muted" style="font-size:.75rem"><?= (int)$summary['stock_added_count'] ?> adjustment(s)</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm summary-card removed h-100">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-dash-circle me-1"></i>Units Removed Today</div>
          <div class="fs-4 fw-bold text-danger"><?= (int)$summary['units_removed'] ?></div>
          <div class="text-muted" style="font-size:.75rem"><?= (int)$summary['stock_removed_count'] ?> adjustment(s)</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm summary-card changes h-100">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-pencil me-1"></i>Product Changes Today</div>
          <div class="fs-4 fw-bold text-primary"><?= (int)$summary['product_changes'] ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div class="inv-toolbar">
    <form method="get" class="card shadow-sm mb-3">
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
          <div class="col-auto">
            <label class="form-label mb-1 small fw-semibold">Action Type</label>
            <select name="action_filter" class="form-select form-select-sm">
              <option value="">All Actions</option>
              <?php foreach (['STOCK_ADD','STOCK_REMOVE','PRODUCT_CREATED','PRODUCT_UPDATED','PRODUCT_DELETED'] as $a): ?>
              <option value="<?= $a ?>" <?= $actionFilter === $a ? 'selected' : '' ?>><?= $a ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col">
            <label class="form-label mb-1 small fw-semibold">Search</label>
            <input type="text" name="search" class="form-control form-control-sm"
              placeholder="Product name, user, notes…" value="<?= htmlspecialchars($search) ?>">
          </div>
          <div class="col-auto d-flex gap-2">
            <button class="btn btn-sm btn-dark">Apply</button>
            <a href="inventory_history.php" class="btn btn-sm btn-outline-secondary">Reset</a>
          </div>
        </div>
      </div>
    </form>
  </div>

  <!-- Movement table -->
  <div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <span class="fw-semibold"><i class="bi bi-table me-1"></i>Movement Log</span>
      <span class="text-muted small">
        Showing <?= count($movements) ?> of latest 200 &mdash;
        <?= htmlspecialchars($startDate) ?> to <?= htmlspecialchars($endDate) ?>
      </span>
    </div>
    <div class="card-body p-0">
      <?php if (empty($movements)): ?>
        <div class="text-center py-5">
          <i class="bi bi-clock-history fs-1 text-muted d-block mb-2"></i>
          <p class="text-muted mb-0">No movements found for the selected filters.</p>
        </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table hist-table mb-0">
          <thead class="table-light">
            <tr>
              <th>Timestamp</th>
              <th>Product</th>
              <th>Action</th>
              <th class="text-center">Before</th>
              <th class="text-center">Change</th>
              <th class="text-center">After</th>
              <th>User</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($movements as $m): ?>
            <tr>
              <td class="text-muted" style="font-size:.78rem;white-space:nowrap"><?= htmlspecialchars($m['created_at']) ?></td>
              <td class="fw-semibold"><?= htmlspecialchars($m['product_name_snapshot']) ?></td>
              <td><?= actionBadge($m['action_type']) ?></td>
              <td class="text-center qty-cell"><?= number_format((float)$m['quantity_before'], 3) ?> <span class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($m['unit_snapshot'] ?? 'pcs') ?></span></td>
              <td class="text-center qty-cell"><?= changeDisplay((float)$m['quantity_changed'], $m['action_type']) ?> <span class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($m['unit_after_snapshot'] ?? $m['unit_snapshot'] ?? 'pcs') ?></span></td>
              <td class="text-center qty-cell fw-semibold"><?= number_format((float)$m['quantity_after'], 3) ?> <span class="text-muted fw-normal" style="font-size:.75rem"><?= htmlspecialchars($m['unit_after_snapshot'] ?? $m['unit_snapshot'] ?? 'pcs') ?></span></td>
              <td class="text-muted"><?= htmlspecialchars($m['username'] ?? '—') ?></td>
              <td class="text-muted" style="font-size:.8rem"><?= htmlspecialchars($m['notes'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /container -->
<?php layoutEnd(); ?>
