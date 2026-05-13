<?php
require_once __DIR__ . '/../app/bootstrap.php';
requireRole('owner');

require_once APP_ROOT . '/app/Repositories/OrderRepository.php';

$today      = date('Y-m-d');
$monthStart = date('Y-m-01');
$startDate  = $_GET['start_date']     ?? $monthStart;
$endDate    = $_GET['end_date']       ?? $today;
$payFilter  = $_GET['payment_method'] ?? '';
$delivFilter= $_GET['delivery_type']  ?? '';
$search     = trim($_GET['search']    ?? '');

if (!strtotime($startDate)) $startDate = $monthStart;
if (!strtotime($endDate))   $endDate   = $today;
if ($startDate > $endDate)  [$startDate, $endDate] = [$endDate, $startDate];

$conn   = getConnection();
$repo   = new OrderRepository($conn);
$orders  = $repo->findOrdersFiltered($startDate, $endDate, $payFilter, $delivFilter, $search);
$summary = $repo->getSalesSummary($startDate, $endDate);
$conn->close();

function statusBadge(string $status): string {
    return match($status) {
        'completed'  => '<span class="badge bg-success">Completed</span>',
        'cancelled'  => '<span class="badge bg-danger">Cancelled</span>',
        'void'       => '<span class="badge bg-secondary">Void</span>',
        default      => '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>',
    };
}
function payBadge(string $method): string {
    return match($method) {
        'cash'          => '<span class="badge bg-success-subtle text-success border border-success-subtle">Cash</span>',
        'gcash'         => '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">GCash</span>',
        'bank_transfer' => '<span class="badge bg-info-subtle text-info border border-info-subtle">Bank</span>',
        default         => '<span class="badge bg-secondary">' . htmlspecialchars($method) . '</span>',
    };
}

require_once APP_ROOT . '/app/layout.php';
layoutStart('Lumina POS – Sales History');
?>
<style>
  .sales-toolbar { position:sticky; top:53px; z-index:90; background:#f4f6f9; padding:.75rem 0 .5rem; }
  .tbl th { font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; color:#6c757d; white-space:nowrap; }
  .tbl td { vertical-align:middle; font-size:.875rem; }
  .tbl tbody tr:hover { background:#f8f9ff; }
  .tbl tbody tr.row-cancelled { opacity:.6; }
  .btn-act { width:30px; height:30px; padding:0; font-size:.8rem; display:inline-flex; align-items:center; justify-content:center; }
  .summary-card { border-left:4px solid; border-radius:.375rem; }
  .summary-card.orders  { border-color:#6f42c1; }
  .summary-card.revenue { border-color:#0d6efd; }
  .summary-card.cash    { border-color:#198754; }
  .summary-card.noncash { border-color:#fd7e14; }
</style>

<?php layoutHeader('Sales History', 'bi-receipt-cutoff'); ?>
<div class="container-fluid px-4">

  <!-- Summary cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card shadow-sm summary-card orders h-100">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-receipt me-1"></i>Total Orders</div>
          <div class="fs-4 fw-bold"><?= (int)$summary['total_orders'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm summary-card revenue h-100">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-cash-stack me-1"></i>Total Revenue</div>
          <div class="fs-4 fw-bold">₱<?= number_format((float)$summary['total_revenue'], 2) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm summary-card cash h-100">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-cash me-1"></i>Cash Sales</div>
          <div class="fs-4 fw-bold text-success">₱<?= number_format((float)$summary['cash_revenue'], 2) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm summary-card noncash h-100">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-phone me-1"></i>Non-Cash Sales</div>
          <div class="fs-4 fw-bold text-warning">₱<?= number_format((float)$summary['noncash_revenue'], 2) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div class="sales-toolbar">
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
            <label class="form-label mb-1 small fw-semibold">Payment</label>
            <select name="payment_method" class="form-select form-select-sm">
              <option value="">All</option>
              <option value="cash"          <?= $payFilter==='cash'          ? 'selected':'' ?>>Cash</option>
              <option value="gcash"         <?= $payFilter==='gcash'         ? 'selected':'' ?>>GCash</option>
              <option value="bank_transfer" <?= $payFilter==='bank_transfer' ? 'selected':'' ?>>Bank Transfer</option>
            </select>
          </div>
          <div class="col-auto">
            <label class="form-label mb-1 small fw-semibold">Delivery</label>
            <select name="delivery_type" class="form-select form-select-sm">
              <option value="">All</option>
              <option value="pickup"   <?= $delivFilter==='pickup'   ? 'selected':'' ?>>Pickup</option>
              <option value="delivery" <?= $delivFilter==='delivery' ? 'selected':'' ?>>Delivery</option>
            </select>
          </div>
          <div class="col">
            <label class="form-label mb-1 small fw-semibold">Search</label>
            <input type="text" name="search" class="form-control form-control-sm"
              placeholder="Order ID, customer, reference…" value="<?= htmlspecialchars($search) ?>">
          </div>
          <div class="col-auto d-flex gap-2">
            <button class="btn btn-sm btn-dark">Apply</button>
            <a href="sales_history.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            <a href="export_orders.php?type=filtered&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&payment_method=<?= urlencode($payFilter) ?>&delivery_type=<?= urlencode($delivFilter) ?>&search=<?= urlencode($search) ?>" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-download me-1"></i>Export Orders CSV
            </a>
          </div>
        </div>
      </div>
    </form>
  </div>

  <!-- Orders table -->
  <div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <span class="fw-semibold"><i class="bi bi-table me-1"></i>Orders</span>
      <span class="text-muted small"><?= count($orders) ?> result(s) — <?= htmlspecialchars($startDate) ?> to <?= htmlspecialchars($endDate) ?></span>
    </div>
    <div class="card-body p-0">
      <?php if (empty($orders)): ?>
        <div class="text-center py-5">
          <i class="bi bi-receipt fs-1 text-muted d-block mb-2"></i>
          <p class="text-muted mb-0">No orders found for the selected filters.</p>
        </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table tbl mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Date / Time</th>
              <th>Customer</th>
              <th>Payment</th>
              <th>Delivery</th>
              <th class="text-end">Total</th>
              <th class="text-center">Status</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $o): ?>
            <tr class="<?= $o['status'] === 'cancelled' ? 'row-cancelled' : '' ?>"
                data-id="<?= $o['id'] ?>">
              <td class="fw-semibold text-muted">#<?= $o['id'] ?></td>
              <td class="text-muted" style="font-size:.78rem;white-space:nowrap"><?= htmlspecialchars($o['order_date']) ?></td>
              <td class="fw-semibold"><?= htmlspecialchars($o['customer_name']) ?></td>
              <td><?= payBadge($o['payment_method']) ?></td>
              <td class="text-muted"><?= ucfirst($o['delivery_type']) ?></td>
              <td class="text-end fw-semibold text-primary">₱<?= number_format((float)$o['total_amount'], 2) ?></td>
              <td class="text-center"><?= statusBadge($o['status']) ?></td>
              <td class="text-center">
                <div class="d-flex gap-1 justify-content-center">
                  <button class="btn btn-outline-primary btn-act btn-view-order"
                    title="View Details" data-id="<?= $o['id'] ?>">
                    <i class="bi bi-eye"></i>
                  </button>
                  <a class="btn btn-outline-secondary btn-act"
                    href="receipt.php?id=<?= $o['id'] ?>&printed=0"
                    target="_blank" title="Reprint Receipt">
                    <i class="bi bi-printer"></i>
                  </a>
                  <?php if ($o['status'] === 'completed'): ?>
                  <button class="btn btn-outline-danger btn-act btn-cancel-order"
                    title="Cancel Order" data-id="<?= $o['id'] ?>" data-customer="<?= htmlspecialchars($o['customer_name']) ?>">
                    <i class="bi bi-x-circle"></i>
                  </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /container -->

<!-- ── Order Details Modal ─────────────────────────────────────────────────── -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Order Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="order-details-body">
        <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
      </div>
      <div class="modal-footer">
        <a id="btn-reprint-modal" href="#" target="_blank" class="btn btn-outline-secondary">
          <i class="bi bi-printer me-1"></i>Reprint
        </a>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Cancel Confirmation Modal ───────────────────────────────────────────── -->
<div class="modal fade" id="cancelModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-danger"><i class="bi bi-x-circle me-2"></i>Cancel Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1">Cancel order <strong id="cancel-order-label"></strong>?</p>
        <p class="text-muted small mb-0">Stock will be automatically restored. This cannot be undone.</p>
        <div id="cancel-error" class="alert alert-danger py-2 mt-2 d-none"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">No, Keep It</button>
        <button class="btn btn-danger" id="btn-confirm-cancel">
          <i class="bi bi-x-circle me-1"></i>Yes, Cancel
        </button>
      </div>
    </div>
  </div>
</div>

<script>
const API = 'api/orders_api.php';

// ── View Order Details ────────────────────────────────────────────────────────
document.querySelectorAll('.btn-view-order').forEach(btn => {
  btn.addEventListener('click', async function () {
    const id   = this.dataset.id;
    const body = document.getElementById('order-details-body');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    document.getElementById('btn-reprint-modal').href = `receipt.php?id=${id}&printed=0`;
    new bootstrap.Modal(document.getElementById('orderDetailsModal')).show();

    try {
      const res  = await fetch(API, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'get_order', id: parseInt(id)}) });
      const data = await res.json();
      if (!data.success) { body.innerHTML = `<p class="text-danger">${data.error}</p>`; return; }
      body.innerHTML = buildOrderHtml(data.order);
    } catch(e) {
      body.innerHTML = '<p class="text-danger">Network error.</p>';
    }
  });
});

function buildOrderHtml(o) {
  const statusCls = o.status === 'cancelled' ? 'text-danger' : 'text-success';
  const items = (o.items || []).map(i => `
    <tr>
      <td>${escHtml(i.name)}<br><small class="text-muted">${escHtml(i.sku||'')}</small></td>
      <td class="text-center">${parseFloat(i.quantity).toFixed(i.inventory_unit && ['kg','g','ton','meter','ft','inch','cubic','liter','roll'].includes(i.inventory_unit) ? 3 : 0)} <span class="text-muted" style="font-size:.75rem">${escHtml(i.inventory_unit||'pcs')}</span></td>
      <td class="text-end">₱${parseFloat(i.unit_price).toFixed(2)}</td>
      <td class="text-end">₱${parseFloat(i.total).toFixed(2)}</td>
    </tr>`).join('');

  const payRow = o.payment_method === 'cash'
    ? `<tr><td class="text-muted">Tendered</td><td class="text-end">₱${parseFloat(o.amount_tendered||0).toFixed(2)}</td></tr>
       <tr><td class="text-muted">Change</td><td class="text-end">₱${parseFloat(o.change_due||0).toFixed(2)}</td></tr>`
    : `<tr><td class="text-muted">Reference #</td><td class="text-end">${escHtml(o.reference_number||'')}</td></tr>`;

  return `
    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <div class="small text-muted fw-semibold mb-1">CUSTOMER</div>
        <div class="fw-semibold">${escHtml(o.customer_name)}</div>
        <div class="text-muted small">${escHtml(o.customer_address)}</div>
        ${o.customer_phone ? `<div class="text-muted small">${escHtml(o.customer_phone)}</div>` : ''}
      </div>
      <div class="col-sm-6">
        <div class="small text-muted fw-semibold mb-1">ORDER INFO</div>
        <div class="text-muted small">Order #${o.order_id}</div>
        <div class="text-muted small">${escHtml(o.date)}</div>
        <div class="text-muted small">${o.delivery_type === 'delivery' ? 'Delivery' : 'Pickup'}</div>
        ${o.delivery_address ? `<div class="text-muted small">${escHtml(o.delivery_address)}</div>` : ''}
        <div class="mt-1"><span class="${statusCls} fw-semibold small">${o.status.toUpperCase()}</span></div>
      </div>
    </div>
    <table class="table table-sm mb-2">
      <thead class="table-light">
        <tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr>
      </thead>
      <tbody>${items}</tbody>
    </table>
    <table class="table table-sm table-borderless mb-0" style="max-width:280px;margin-left:auto">
      <tr><td class="text-muted">Subtotal</td><td class="text-end">₱${parseFloat(o.subtotal).toFixed(2)}</td></tr>
      <tr><td class="text-muted">Delivery Fee</td><td class="text-end">₱${parseFloat(o.delivery_fee||0).toFixed(2)}</td></tr>
      <tr class="fw-bold"><td>Total</td><td class="text-end text-primary">₱${parseFloat(o.total).toFixed(2)}</td></tr>
      <tr><td class="text-muted">Payment</td><td class="text-end">${escHtml(o.payment_method.toUpperCase())}</td></tr>
      ${payRow}
    </table>`;
}

// ── Cancel Order ──────────────────────────────────────────────────────────────
let cancelTargetId = null;

document.querySelectorAll('.btn-cancel-order').forEach(btn => {
  btn.addEventListener('click', function () {
    cancelTargetId = parseInt(this.dataset.id);
    document.getElementById('cancel-order-label').textContent = `#${cancelTargetId} (${this.dataset.customer})`;
    document.getElementById('cancel-error').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('cancelModal')).show();
  });
});

document.getElementById('btn-confirm-cancel').addEventListener('click', async function () {
  if (!cancelTargetId) return;
  const errEl = document.getElementById('cancel-error');
  errEl.classList.add('d-none');
  this.disabled = true;
  this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Cancelling…';

  try {
    const res  = await fetch(API, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'cancel_order', id: cancelTargetId}) });
    const data = await res.json();
    if (!data.success) {
      errEl.textContent = data.error;
      errEl.classList.remove('d-none');
      return;
    }
    bootstrap.Modal.getInstance(document.getElementById('cancelModal')).hide();
    location.reload();
  } catch(e) {
    errEl.textContent = 'Network error.';
    errEl.classList.remove('d-none');
  } finally {
    this.disabled = false;
    this.innerHTML = '<i class="bi bi-x-circle me-1"></i>Yes, Cancel';
  }
});

function escHtml(str) {
  return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
<?php layoutEnd(); ?>
