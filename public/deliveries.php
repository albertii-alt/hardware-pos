<?php
require_once __DIR__ . '/../app/bootstrap.php';
requireAnyRole();

require_once APP_ROOT . '/app/Repositories/OrderRepository.php';

$conn    = getConnection();
$repo    = new OrderRepository($conn);
$summary = $repo->getDeliverySummary();

$statusFilter = $_GET['status']    ?? '';
$viewMode     = $_GET['view']      ?? 'table';   // table | board
$sortMode     = $_GET['sort']      ?? 'status';  // status | municipality | barangay
$orders       = $repo->findDeliveryOrders($statusFilter, $sortMode);
$conn->close();

$statusLabels = [
    'pending'          => 'Pending',
    'preparing'        => 'Preparing',
    'ready'            => 'Ready',
    'out_for_delivery' => 'Out for Delivery',
    'delivered'        => 'Delivered',
    'cancelled'        => 'Cancelled',
];
$statusBadge = [
    'pending'          => 'bg-warning text-dark',
    'preparing'        => 'bg-info text-dark',
    'ready'            => 'bg-primary',
    'out_for_delivery' => 'bg-dark',
    'delivered'        => 'bg-success',
    'cancelled'        => 'bg-danger',
];
$nextAction = [
    'pending'          => ['status' => 'preparing',        'label' => 'Start Preparing', 'cls' => 'btn-info'],
    'preparing'        => ['status' => 'ready',            'label' => 'Mark Ready',      'cls' => 'btn-primary'],
    'ready'            => ['status' => 'out_for_delivery', 'label' => 'Dispatch',        'cls' => 'btn-dark'],
    'out_for_delivery' => ['status' => 'delivered',        'label' => 'Mark Delivered',  'cls' => 'btn-success'],
];

// Build municipality/barangay display from DB-joined names, fallback to address for old orders
function extractMunicipality(string $addr): string {
    $parts = array_map('trim', explode(',', $addr));
    return $parts[count($parts) >= 4 ? count($parts)-2 : (count($parts)-1)] ?? '—';
}
foreach ($orders as &$o) {
    $o['municipality'] = $o['municipality_name'] !== ''
        ? $o['municipality_name']
        : extractMunicipality($o['delivery_address'] ?? '');
    $o['barangay'] = $o['barangay_name'] !== '' ? $o['barangay_name'] : '—';
}
unset($o);

// Group for kanban (only active statuses as columns)
$kanbanCols = ['pending','preparing','ready','out_for_delivery','delivered'];
$kanban = array_fill_keys($kanbanCols, []);
foreach ($orders as $o) {
    if (isset($kanban[$o['delivery_status']])) {
        $kanban[$o['delivery_status']][] = $o;
    }
}

// Build base URL preserving current params except the one being toggled
function buildUrl(array $override): string {
    $params = array_merge(['view' => 'table', 'sort' => 'status', 'status' => ''], $override);
    $q = http_build_query(array_filter($params, fn($v) => $v !== ''));
    return 'deliveries.php' . ($q ? '?' . $q : '');
}

require_once APP_ROOT . '/app/layout.php';
layoutStart('Hardware POS – Deliveries');
?>
<style>
  .del-toolbar { position:sticky; top:53px; z-index:90; background:#f4f6f9; padding:.75rem 0 .5rem; }
  .tbl th { font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; color:#6c757d; white-space:nowrap; }
  .tbl td { vertical-align:middle; font-size:.875rem; }
  .tbl tbody tr:hover { background:#f8f9ff; }
  .btn-act { height:30px; padding:0 .5rem; font-size:.78rem; white-space:nowrap; display:inline-flex; align-items:center; justify-content:center; }
  .summary-card { border-left:4px solid; border-radius:.375rem; }
  .summary-card.s-pending   { border-color:#ffc107; }
  .summary-card.s-preparing { border-color:#0dcaf0; }
  .summary-card.s-outfor    { border-color:#212529; }
  .summary-card.s-delivered { border-color:#198754; }

  /* Kanban */
  .kanban-board { display:flex; gap:1rem; overflow-x:auto; padding-bottom:1rem; align-items:flex-start; }
  .kanban-col { flex:0 0 240px; min-width:240px; }
  .kanban-col-header {
    font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em;
    padding:.4rem .75rem; border-radius:.375rem .375rem 0 0; margin-bottom:.5rem;
  }
  .kanban-card {
    background:#fff; border:1px solid #e5e9f0; border-radius:.375rem;
    padding:.65rem .75rem; margin-bottom:.5rem;
    box-shadow:0 1px 3px rgba(0,0,0,.06);
  }
  .kanban-card:hover { box-shadow:0 2px 8px rgba(0,0,0,.1); }
  .kanban-card .order-id { font-size:.7rem; color:#6c757d; }
  .kanban-card .cust-name { font-weight:600; font-size:.85rem; margin:.1rem 0; }
  .kanban-card .muni { font-size:.75rem; color:#6c757d; }
  .kanban-card .card-footer-row { display:flex; justify-content:space-between; align-items:center; margin-top:.5rem; gap:.25rem; flex-wrap:wrap; }
  .kanban-empty { font-size:.8rem; color:#adb5bd; text-align:center; padding:1rem 0; }

  /* Municipality group header */
  .muni-group-header {
    font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em;
    color:#495057; background:#f0f2f5; border-left:3px solid #0d6efd;
    padding:.4rem .75rem; margin-top:.75rem;
  }

  /* Notes badge */
  .notes-badge { font-size:.65rem; cursor:default; }
</style>

<?php layoutHeader('Deliveries', 'bi-truck'); ?>
<div class="container-fluid px-4">

  <!-- Summary cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card shadow-sm summary-card s-pending h-100">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-hourglass-split me-1"></i>Pending</div>
          <div class="fs-4 fw-bold text-warning"><?= (int)$summary['pending'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm summary-card s-preparing h-100">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-box-seam me-1"></i>Preparing</div>
          <div class="fs-4 fw-bold text-info"><?= (int)$summary['preparing'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm summary-card s-outfor h-100">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-truck me-1"></i>Out for Delivery</div>
          <div class="fs-4 fw-bold"><?= (int)$summary['out_for_delivery'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm summary-card s-delivered h-100">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-check-circle me-1"></i>Delivered Today</div>
          <div class="fs-4 fw-bold text-success"><?= (int)$summary['delivered_today'] ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="del-toolbar">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">

      <!-- Status filter pills -->
      <div class="btn-group btn-group-sm">
        <a href="<?= buildUrl(['view'=>$viewMode,'sort'=>$sortMode,'status'=>'']) ?>"
           class="btn <?= $statusFilter==='' ? 'btn-dark' : 'btn-outline-secondary' ?>">All</a>
        <?php foreach ($statusLabels as $key => $label): ?>
        <a href="<?= buildUrl(['view'=>$viewMode,'sort'=>$sortMode,'status'=>$key]) ?>"
           class="btn <?= $statusFilter===$key ? 'btn-dark' : 'btn-outline-secondary' ?>">
          <?= $label ?>
        </a>
        <?php endforeach; ?>
      </div>

      <div class="ms-auto d-flex gap-2 align-items-center flex-wrap">
        <!-- Sort toggle -->
        <div class="btn-group btn-group-sm">
          <a href="<?= buildUrl(['view'=>$viewMode,'sort'=>'status','status'=>$statusFilter]) ?>"
             class="btn <?= $sortMode==='status' ? 'btn-secondary' : 'btn-outline-secondary' ?>"
             title="Sort by status">
            <i class="bi bi-sort-down me-1"></i>Status
          </a>
          <a href="<?= buildUrl(['view'=>$viewMode,'sort'=>'municipality','status'=>$statusFilter]) ?>"
             class="btn <?= $sortMode==='municipality' ? 'btn-secondary' : 'btn-outline-secondary' ?>"
             title="Sort by municipality">
            <i class="bi bi-geo-alt me-1"></i>Municipality
          </a>
          <a href="<?= buildUrl(['view'=>$viewMode,'sort'=>'barangay','status'=>$statusFilter]) ?>"
             class="btn <?= $sortMode==='barangay' ? 'btn-secondary' : 'btn-outline-secondary' ?>"
             title="Group by barangay">
            <i class="bi bi-pin-map me-1"></i>Barangay
          </a>
        </div>

        <!-- View toggle -->
        <div class="btn-group btn-group-sm">
          <a href="<?= buildUrl(['view'=>'table','sort'=>$sortMode,'status'=>$statusFilter]) ?>"
             class="btn <?= $viewMode==='table' ? 'btn-dark' : 'btn-outline-secondary' ?>"
             title="Table view">
            <i class="bi bi-table"></i>
          </a>
          <a href="<?= buildUrl(['view'=>'board','sort'=>$sortMode,'status'=>$statusFilter]) ?>"
             class="btn <?= $viewMode==='board' ? 'btn-dark' : 'btn-outline-secondary' ?>"
             title="Board view">
            <i class="bi bi-kanban"></i>
          </a>
        </div>

        <span class="text-muted small"><?= count($orders) ?> order(s)</span>
      </div>
    </div>
  </div>

  <?php if (empty($orders)): ?>
  <div class="card shadow-sm border-0 mt-3">
    <div class="card-body text-center py-5">
      <i class="bi bi-truck fs-1 text-muted d-block mb-2"></i>
      <p class="text-muted mb-0">No delivery orders found.</p>
    </div>
  </div>

  <?php elseif ($viewMode === 'board'): ?>
  <!-- ── KANBAN BOARD VIEW ──────────────────────────────────────────────── -->
  <div class="kanban-board mt-3">
    <?php
    $colHeaderCls = [
      'pending'          => 'bg-warning text-dark',
      'preparing'        => 'bg-info text-dark',
      'ready'            => 'bg-primary text-white',
      'out_for_delivery' => 'bg-dark text-white',
      'delivered'        => 'bg-success text-white',
    ];
    foreach ($kanbanCols as $col):
      $colOrders = $kanban[$col];
    ?>
    <div class="kanban-col">
      <div class="kanban-col-header <?= $colHeaderCls[$col] ?>">
        <?= $statusLabels[$col] ?>
        <span class="ms-1 opacity-75">(<?= count($colOrders) ?>)</span>
      </div>
      <?php if (empty($colOrders)): ?>
        <div class="kanban-empty">No orders</div>
      <?php endif; ?>
      <?php foreach ($colOrders as $o):
        $na    = $nextAction[$o['delivery_status']] ?? null;
        $notes = trim($o['delivery_notes'] ?? '');
        $notePreview = $notes ? (mb_strlen($notes) > 40 ? mb_substr($notes,0,40).'…' : $notes) : '';
        if ($sortMode === 'municipality') {
            $locationLine = htmlspecialchars($o['municipality']);
            $locationIcon = 'bi-geo-alt';
        } elseif ($sortMode === 'barangay') {
            $locationLine = htmlspecialchars($o['municipality']) . ' &rsaquo; ' . htmlspecialchars($o['barangay']);
            $locationIcon = 'bi-pin-map';
        } else {
            $locationLine = htmlspecialchars($o['delivery_address'] ?? '—');
            $locationIcon = 'bi-geo-alt';
        }
      ?>
      <div class="kanban-card" id="card-<?= $o['id'] ?>">
        <div class="order-id">#<?= $o['id'] ?> &middot; <?= htmlspecialchars(date('M j H:i', strtotime($o['order_date']))) ?></div>
        <div class="cust-name"><?= htmlspecialchars($o['customer_name']) ?></div>
        <div class="muni"><i class="bi <?= $locationIcon ?>" style="font-size:.7rem"></i> <?= $locationLine ?></div>
        <?php if ($notePreview): ?>
        <div class="mt-1">
          <span class="badge bg-secondary notes-badge" title="<?= htmlspecialchars($notes) ?>">
            <i class="bi bi-sticky me-1"></i><?= htmlspecialchars($notePreview) ?>
          </span>
        </div>
        <?php endif; ?>
        <div class="card-footer-row">
          <span class="fw-bold text-primary" style="font-size:.82rem">₱<?= number_format((float)$o['total_amount'],2) ?></span>
          <?php $pm=$o['payment_method']; ?>
          <?php if ($pm==='cash'): ?>
            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.65rem">Cash</span>
          <?php elseif ($pm==='gcash'): ?>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size:.65rem">GCash</span>
          <?php else: ?>
            <span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size:.65rem">Bank</span>
          <?php endif; ?>
        </div>
        <div class="d-flex gap-1 mt-2 flex-wrap">
          <?php if ($na): ?>
          <button class="btn btn-sm <?= $na['cls'] ?> btn-act btn-next-status"
                  data-id="<?= $o['id'] ?>" data-status="<?= $na['status'] ?>"
                  data-label="<?= htmlspecialchars($na['label']) ?>">
            <?= htmlspecialchars($na['label']) ?>
          </button>
          <?php endif; ?>
          <a href="delivery_slip.php?id=<?= $o['id'] ?>&printed=0" target="_blank"
             class="btn btn-sm btn-outline-secondary btn-act" title="Print Slip">
            <i class="bi bi-printer"></i>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <?php else: ?>
  <!-- ── TABLE VIEW ────────────────────────────────────────────────────── -->
  <div class="card shadow-sm border-0 mt-3">
    <div class="table-responsive">
      <table class="table tbl mb-0" id="del-table">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Created</th>
            <th>Customer</th>
            <th>Address</th>
            <th>Contact</th>
            <th>Payment</th>
            <th class="text-end">Total</th>
            <th class="text-center">Status</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $lastGroup = null;
          foreach ($orders as $o):
            $notes       = trim($o['delivery_notes'] ?? '');
            $notePreview = $notes ? (mb_strlen($notes) > 40 ? mb_substr($notes,0,40).'…' : $notes) : '';
            if ($sortMode === 'municipality' || $sortMode === 'barangay'):
              $groupKey = $sortMode === 'barangay'
                ? $o['municipality'] . ' › ' . $o['barangay']
                : $o['municipality'];
              $groupIcon = $sortMode === 'barangay' ? 'bi-pin-map' : 'bi-geo-alt';
              if ($groupKey !== $lastGroup):
                $lastGroup = $groupKey;
          ?>
          <tr>
            <td colspan="9" class="muni-group-header">
              <i class="bi <?= $groupIcon ?> me-1"></i><?= htmlspecialchars($lastGroup) ?>
            </td>
          </tr>
          <?php endif; endif; ?>
          <tr id="row-<?= $o['id'] ?>" data-status="<?= htmlspecialchars($o['delivery_status']) ?>">
            <td class="fw-semibold text-muted">#<?= $o['id'] ?></td>
            <td class="text-muted" style="font-size:.78rem;white-space:nowrap"><?= htmlspecialchars($o['order_date']) ?></td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($o['customer_name']) ?></div>
              <?php if ($notePreview): ?>
              <span class="badge bg-secondary notes-badge mt-1" title="<?= htmlspecialchars($notes) ?>">
                <i class="bi bi-sticky me-1"></i><?= htmlspecialchars($notePreview) ?>
              </span>
              <?php endif; ?>
            </td>
            <td style="max-width:200px;font-size:.8rem"><?= htmlspecialchars($o['delivery_address'] ?? '—') ?></td>
            <td class="text-muted" style="font-size:.8rem"><?= htmlspecialchars($o['customer_phone'] ?? '—') ?></td>
            <td>
              <?php $pm=$o['payment_method']; ?>
              <?php if ($pm==='cash'): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle">Cash</span>
              <?php elseif ($pm==='gcash'): ?>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">GCash</span>
              <?php else: ?>
                <span class="badge bg-info-subtle text-info border border-info-subtle">Bank</span>
              <?php endif; ?>
            </td>
            <td class="text-end fw-semibold text-primary">₱<?= number_format((float)$o['total_amount'],2) ?></td>
            <td class="text-center">
              <span class="badge <?= $statusBadge[$o['delivery_status']] ?? 'bg-secondary' ?>" id="badge-<?= $o['id'] ?>">
                <?= $statusLabels[$o['delivery_status']] ?? $o['delivery_status'] ?>
              </span>
              <?php if ($o['delivery_status_updated_at']): ?>
              <div class="text-muted" style="font-size:.68rem"><?= htmlspecialchars($o['delivery_status_updated_at']) ?></div>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <div class="d-flex gap-1 justify-content-center flex-wrap">
                <?php if (isset($nextAction[$o['delivery_status']])): $na=$nextAction[$o['delivery_status']]; ?>
                <button class="btn btn-sm <?= $na['cls'] ?> btn-act btn-next-status"
                        data-id="<?= $o['id'] ?>" data-status="<?= $na['status'] ?>"
                        data-label="<?= htmlspecialchars($na['label']) ?>">
                  <?= htmlspecialchars($na['label']) ?>
                </button>
                <?php endif; ?>
                <a href="delivery_slip.php?id=<?= $o['id'] ?>&printed=0" target="_blank"
                   class="btn btn-sm btn-outline-secondary btn-act" title="Print Delivery Slip">
                  <i class="bi bi-printer"></i>
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <div class="text-muted small text-end mt-3 mb-4">
    Generated: <?= date('Y-m-d H:i:s') ?> &nbsp;|&nbsp;
    <a href="<?= buildUrl(['view'=>$viewMode,'sort'=>$sortMode,'status'=>$statusFilter]) ?>">Refresh</a>
  </div>

</div>

<script>
const DAPI         = 'api/delivery_api.php';
const STATUS_LABELS = <?= json_encode($statusLabels) ?>;
const STATUS_BADGES = <?= json_encode($statusBadge) ?>;
const NEXT_ACTION   = <?= json_encode($nextAction) ?>;

document.addEventListener('click', async function (e) {
  const btn = e.target.closest('.btn-next-status');
  if (!btn) return;

  const id     = btn.dataset.id;
  const status = btn.dataset.status;
  const label  = btn.dataset.label;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

  try {
    const res  = await fetch(DAPI, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'update_status', id: parseInt(id), status })
    });
    const data = await res.json();

    if (!data.success) {
      alert(data.error);
      btn.disabled = false;
      btn.innerHTML = label;
      return;
    }

    // Update table badge
    const badge = document.getElementById('badge-' + id);
    if (badge) {
      badge.className  = 'badge ' + (STATUS_BADGES[status] || 'bg-secondary');
      badge.textContent = STATUS_LABELS[status] || status;
    }

    // Update action button
    const na = NEXT_ACTION[status];
    if (na) {
      btn.className      = 'btn btn-sm ' + na.cls + ' btn-act btn-next-status';
      btn.dataset.status = na.status;
      btn.dataset.label  = na.label;
      btn.innerHTML      = na.label;
      btn.disabled       = false;
    } else {
      btn.remove();
    }

    // In board view: move card to correct column
    const card = document.getElementById('card-' + id);
    if (card) {
      const targetCol = document.querySelector('.kanban-col [data-col="' + status + '"]');
      // Simple approach: reload page after board status change for accuracy
      location.reload();
    }

  } catch(err) {
    alert('Network error.');
    btn.disabled = false;
    btn.innerHTML = label;
  }
});
</script>
<?php layoutEnd(); ?>
