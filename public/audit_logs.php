<?php
require_once __DIR__ . '/../app/bootstrap.php';
requireRole('owner');


$conn = getConnection();

// ── Filters ───────────────────────────────────────────────────────────────────
$today      = date('Y-m-d');
$monthStart = date('Y-m-01');
$startDate  = $_GET['start_date'] ?? $monthStart;
$endDate    = $_GET['end_date']   ?? $today;
$filterAction = $_GET['action_filter'] ?? '';
$search       = trim($_GET['search'] ?? '');

if (!strtotime($startDate)) $startDate = $monthStart;
if (!strtotime($endDate))   $endDate   = $today;
if ($startDate > $endDate)  [$startDate, $endDate] = [$endDate, $startDate];

$startTs = $startDate . ' 00:00:00';
$endTs   = $endDate   . ' 23:59:59';

// ── Summary cards ─────────────────────────────────────────────────────────────
$cardActions = ['LOGIN_SUCCESS', 'ORDER_CREATED', 'VIEW_CLOSING_REPORT'];
$cardCounts  = [];

$stmt = $conn->prepare(
    'SELECT action, COUNT(*) AS cnt FROM audit_logs
     WHERE created_at BETWEEN ? AND ?
     GROUP BY action'
);
$stmt->bind_param('ss', $startTs, $endTs);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$actionMap  = array_column($rows, 'cnt', 'action');
$totalLogs  = array_sum(array_column($rows, 'cnt'));
foreach ($cardActions as $a) {
    $cardCounts[$a] = (int)($actionMap[$a] ?? 0);
}

// ── Main log query ────────────────────────────────────────────────────────────
$where  = ['created_at BETWEEN ? AND ?'];
$types  = 'ss';
$params = [$startTs, $endTs];

if ($filterAction !== '') {
    $where[]  = 'action = ?';
    $types   .= 's';
    $params[] = $filterAction;
}
if ($search !== '') {
    $like     = '%' . $conn->real_escape_string($search) . '%';
    $where[]  = '(username LIKE ? OR action LIKE ? OR reference_id LIKE ?)';
    $types   .= 'sss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql  = 'SELECT id, created_at, username, role, action, reference_id, details
         FROM audit_logs
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY created_at DESC
         LIMIT 100';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// ── Badge helper ──────────────────────────────────────────────────────────────
function actionBadge(string $action): string {
    $map = [
        'LOGIN_SUCCESS'        => 'success',
        'ORDER_CREATED'        => 'primary',
        'VIEW_CLOSING_REPORT'  => 'purple',
    ];
    $color = $map[$action] ?? 'secondary';
    $style = $color === 'purple' ? 'style="background:#6f42c1"' : '';
    $cls   = $color === 'purple' ? 'bg-secondary' : "bg-{$color}";
    return "<span class=\"badge {$cls}\" {$style}>" . htmlspecialchars($action) . "</span>";
}

require_once APP_ROOT . '/app/layout.php';
layoutStart('Hardware POS – Audit Logs');
?>
<?php layoutHeader('Audit Logs', 'bi-shield-check'); ?>
<div class="container-fluid px-4">

  <!-- Summary Cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card total h-100">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-list-ul"></i> Total Logs</div>
          <div class="fs-4 fw-bold"><?= (int)$totalLogs ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card login h-100">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-box-arrow-in-right"></i> Logins</div>
          <div class="fs-4 fw-bold text-success"><?= $cardCounts['LOGIN_SUCCESS'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card order h-100">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-receipt"></i> Orders Created</div>
          <div class="fs-4 fw-bold text-primary"><?= $cardCounts['ORDER_CREATED'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card closing h-100">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-file-earmark-text"></i> Closing Views</div>
          <div class="fs-4 fw-bold" style="color:#6f42c1"><?= $cardCounts['VIEW_CLOSING_REPORT'] ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <form method="get" class="card shadow-sm mb-4">
    <div class="card-body py-2">
      <div class="row g-2 align-items-end">
        <div class="col-auto">
          <label class="form-label mb-1 small fw-semibold">Start Date</label>
          <input type="date" name="start_date" class="form-control form-control-sm"
            value="<?= htmlspecialchars($startDate) ?>">
        </div>
        <div class="col-auto">
          <label class="form-label mb-1 small fw-semibold">End Date</label>
          <input type="date" name="end_date" class="form-control form-control-sm"
            value="<?= htmlspecialchars($endDate) ?>">
        </div>
        <div class="col-auto">
          <label class="form-label mb-1 small fw-semibold">Action</label>
          <select name="action_filter" class="form-select form-select-sm">
            <option value="">All Actions</option>
            <?php foreach (['LOGIN_SUCCESS','ORDER_CREATED','VIEW_CLOSING_REPORT'] as $a): ?>
            <option value="<?= $a ?>" <?= $filterAction === $a ? 'selected' : '' ?>><?= $a ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col">
          <label class="form-label mb-1 small fw-semibold">Search</label>
          <input type="text" name="search" class="form-control form-control-sm"
            placeholder="Username, action, reference ID…"
            value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-auto d-flex gap-2">
          <button class="btn btn-sm btn-dark">Apply</button>
          <a href="audit_logs.php" class="btn btn-sm btn-outline-secondary">Reset</a>
          <a href="export_reports.php?type=audit_logs" class="btn btn-sm btn-outline-success">
            <i class="bi bi-download me-1"></i>Export CSV
          </a>
        </div>
      </div>
    </div>
  </form>

  <!-- Logs Table -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
      <span><i class="bi bi-table"></i> Log Entries</span>
      <span class="text-muted small fw-normal">
        Showing <?= count($logs) ?> of latest 100 &mdash;
        <?= htmlspecialchars($startDate) ?> to <?= htmlspecialchars($endDate) ?>
      </span>
    </div>
    <div class="card-body p-0">
      <?php if (empty($logs)): ?>
        <p class="text-muted text-center py-4 mb-0">No log entries found for the selected filters.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Date / Time</th>
              <th>Username</th>
              <th>Role</th>
              <th>Action</th>
              <th class="text-center">Ref ID</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
              <td class="text-nowrap text-muted" style="font-size:.8rem"><?= htmlspecialchars($log['created_at']) ?></td>
              <td class="fw-semibold"><?= htmlspecialchars($log['username'] ?? '—') ?></td>
              <td>
                <?php if ($log['role']): ?>
                <span class="badge <?= $log['role'] === 'owner' ? 'bg-warning text-dark' : 'bg-secondary' ?>">
                  <?= htmlspecialchars($log['role']) ?>
                </span>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td><?= actionBadge($log['action']) ?></td>
              <td class="text-center"><?= $log['reference_id'] !== null ? (int)$log['reference_id'] : '—' ?></td>
              <td class="text-muted" style="font-size:.8rem"><?= htmlspecialchars($log['details'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="text-muted small text-end mb-4">
    Generated: <?= date('Y-m-d H:i:s') ?> &nbsp;|&nbsp;
    <a href="audit_logs.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&action_filter=<?= urlencode($filterAction) ?>&search=<?= urlencode($search) ?>">Refresh</a>
  </div>

</div>

<?php layoutEnd(); ?>
