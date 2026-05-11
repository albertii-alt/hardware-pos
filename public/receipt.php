<?php
require_once __DIR__ . '/../app/bootstrap.php';



require_once __DIR__ . '/../app/Services/OrderService.php';

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    http_response_code(400);
    exit('Invalid order ID.');
}

$conn    = getConnection();
$service = new OrderService($conn);
$r       = $service->generateReceiptPayload($orderId);
$conn->close();

if (!$r) {
    http_response_code(404);
    exit('Order not found.');
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function esc(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function peso(float $v): string { return '&#8369;' . number_format($v, 2); }
function methodLabel(string $m): string {
    return ['cash' => 'Cash', 'gcash' => 'GCash', 'bank_transfer' => 'Bank Transfer'][$m] ?? ucfirst($m);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Receipt – Order #<?= $r['order_id'] ?></title>
<style>
  /* ── Base ── */
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Courier New', Courier, monospace;
    font-size: 13px;
    background: #f0f0f0;
    color: #111;
    padding: 24px 16px;
  }

  /* ── Receipt wrapper ── */
  .receipt {
    background: #fff;
    width: 100%;
    max-width: 380px;
    margin: 0 auto;
    padding: 24px 20px;
    border: 1px solid #ddd;
    box-shadow: 0 2px 12px rgba(0,0,0,.1);
  }

  /* ── Header ── */
  .receipt-header { text-align: center; margin-bottom: 14px; }
  .receipt-header h1 { font-size: 18px; letter-spacing: 1px; text-transform: uppercase; }
  .receipt-header p  { font-size: 11px; color: #555; margin-top: 2px; }

  /* ── Divider ── */
  .divider { border: none; border-top: 1px dashed #aaa; margin: 10px 0; }

  /* ── Info table ── */
  .info-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
  .info-table td { padding: 2px 0; vertical-align: top; font-size: 12px; }
  .info-table td:first-child { color: #555; width: 42%; }
  .info-table td:last-child  { font-weight: 600; }

  /* ── Items table ── */
  .items-table { width: 100%; border-collapse: collapse; margin: 8px 0; }
  .items-table th {
    font-size: 11px; text-transform: uppercase;
    border-bottom: 1px solid #ccc; padding: 4px 2px; text-align: left;
  }
  .items-table th.r, .items-table td.r { text-align: right; }
  .items-table td { padding: 4px 2px; font-size: 12px; border-bottom: 1px dotted #eee; }
  .items-table .item-name { font-weight: 600; }
  .items-table .item-sku  { font-size: 10px; color: #777; }

  /* ── Totals ── */
  .totals-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
  .totals-table td { padding: 3px 2px; font-size: 12px; }
  .totals-table td:last-child { text-align: right; }
  .totals-table .grand-total td { font-size: 14px; font-weight: 700; border-top: 1px solid #aaa; padding-top: 6px; }

  /* ── Footer ── */
  .receipt-footer { text-align: center; margin-top: 14px; font-size: 11px; color: #555; }

  /* ── Action bar ── */
  .action-bar {
    display: flex; gap: 10px; justify-content: center;
    margin: 20px auto 0; max-width: 380px;
  }
  .btn-action {
    flex: 1; padding: 10px 0; font-size: 13px;
    border: none; border-radius: 4px; cursor: pointer;
    font-family: inherit; font-weight: 600;
  }
  .btn-print { background: #212529; color: #fff; }
  .btn-print:hover { background: #444; }
  .btn-close-tab { background: #6c757d; color: #fff; }
  .btn-close-tab:hover { background: #555; }

  /* ── Print styles ── */
  @media print {
    body { background: #fff; padding: 0; margin: 0; }
    .receipt { box-shadow: none; border: none; max-width: 100%; padding: 0; }
    .no-print { display: none !important; }
    @page { margin: 6mm; }
  }
</style>
</head>
<body>

<div class="receipt">

  <!-- Header -->
  <div class="receipt-header">
    <h1>Lumina Hardware</h1>
    <p>Official Receipt</p>
  </div>

  <hr class="divider">

  <!-- Order meta -->
  <table class="info-table">
    <tr><td>Order #</td><td><?= esc((string)$r['order_id']) ?></td></tr>
    <tr><td>Date</td><td><?= esc($r['date']) ?></td></tr>
    <tr><td>Customer</td><td><?= esc($r['customer_name']) ?></td></tr>
    <tr><td>Address</td><td><?= esc($r['customer_address']) ?></td></tr>
    <?php if (!empty($r['customer_phone'])): ?>
    <tr><td>Phone</td><td><?= esc($r['customer_phone']) ?></td></tr>
    <?php endif; ?>
    <tr><td>Delivery</td><td><?= $r['delivery_type'] === 'delivery' ? 'Delivery' : 'Pickup' ?></td></tr>
    <?php if ($r['delivery_type'] === 'delivery' && !empty($r['delivery_address'])): ?>
    <tr><td>Del. Address</td><td><?= esc($r['delivery_address']) ?></td></tr>
    <?php endif; ?>
  </table>

  <hr class="divider">

  <!-- Items -->
  <table class="items-table">
    <thead>
      <tr>
        <th>Item</th>
        <th class="r">Qty</th>
        <th class="r">Price</th>
        <th class="r">Total</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($r['items'] as $item): ?>
      <tr>
        <td>
          <div class="item-name"><?= esc($item['name']) ?></div>
          <?php if (!empty($item['sku'])): ?>
          <div class="item-sku"><?= esc($item['sku']) ?></div>
          <?php endif; ?>
        </td>
        <td class="r"><?= (int)$item['quantity'] ?></td>
        <td class="r"><?= peso((float)$item['unit_price']) ?></td>
        <td class="r"><?= peso((float)$item['total']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <hr class="divider">

  <!-- Totals -->
  <table class="totals-table">
    <tr><td>Subtotal</td><td><?= peso($r['subtotal']) ?></td></tr>
    <tr><td>Delivery Fee</td><td><?= peso($r['delivery_fee']) ?></td></tr>
    <tr class="grand-total"><td>TOTAL</td><td><?= peso($r['total']) ?></td></tr>
    <tr><td>Payment</td><td><?= esc(methodLabel($r['payment_method'])) ?></td></tr>
    <?php if ($r['payment_method'] === 'cash'): ?>
    <tr><td>Tendered</td><td><?= peso((float)$r['amount_tendered']) ?></td></tr>
    <tr><td>Change</td><td><?= peso((float)$r['change_due']) ?></td></tr>
    <?php else: ?>
    <tr><td>Reference #</td><td><?= esc($r['reference_number'] ?? '') ?></td></tr>
    <?php endif; ?>
  </table>

  <hr class="divider">

  <!-- Footer -->
  <div class="receipt-footer">
    <p>Thank you for your purchase!</p>
    <p style="margin-top:4px">Lumina Hardware &mdash; Your trusted store</p>
  </div>

</div><!-- /receipt -->

<div class="action-bar no-print">
  <button class="btn-action btn-print" onclick="window.print()">&#128438; Print Receipt</button>
  <a class="btn-action btn-close-tab" href="index.php">&#8592; Back to POS</a>
</div>

<script>
  // Auto-print only on first load (printed=0), not on refresh
  const params  = new URLSearchParams(window.location.search);
  const printed = params.get('printed');

  if (printed === '0') {
    // Replace URL with printed=1 so refresh does not re-trigger print
    const newUrl = window.location.href.replace('printed=0', 'printed=1');
    history.replaceState(null, '', newUrl);

    // Wait for full DOM render before printing
    window.addEventListener('load', function () {
      setTimeout(function () { window.print(); }, 1200);
    });
  }
</script>

</body>
</html>
