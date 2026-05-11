<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Repositories/OrderRepository.php';

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) { http_response_code(400); exit('Invalid order ID.'); }

$conn  = getConnection();
$repo  = new OrderRepository($conn);
$order = $repo->findDeliveryOrderById($orderId);
$items = $order ? $repo->findItemsByOrderId($orderId) : [];
$conn->close();

if (!$order) { http_response_code(404); exit('Delivery order not found.'); }

function esc(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function peso(float $v): string { return '&#8369;' . number_format($v, 2); }
function methodLabel(string $m): string {
    return ['cash' => 'Cash', 'gcash' => 'GCash', 'bank_transfer' => 'Bank Transfer'][$m] ?? ucfirst($m);
}

$statusLabels = [
    'pending'          => 'Pending',
    'preparing'        => 'Preparing',
    'ready'            => 'Ready',
    'out_for_delivery' => 'Out for Delivery',
    'delivered'        => 'Delivered',
    'cancelled'        => 'Cancelled',
];
$deliveryStatus = $statusLabels[$order['delivery_status']] ?? $order['delivery_status'];
$subtotal = (float)$order['total_amount'] - (float)$order['delivery_fee'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Delivery Slip – Order #<?= $orderId ?></title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Courier New', Courier, monospace;
    font-size: 13px;
    background: #f0f0f0;
    color: #111;
    padding: 24px 16px;
  }
  .slip {
    background: #fff;
    width: 100%;
    max-width: 420px;
    margin: 0 auto;
    padding: 24px 20px;
    border: 1px solid #ddd;
    box-shadow: 0 2px 12px rgba(0,0,0,.1);
  }
  .slip-header { text-align: center; margin-bottom: 14px; }
  .slip-header h1 { font-size: 17px; letter-spacing: 1px; text-transform: uppercase; }
  .slip-header p  { font-size: 11px; color: #555; margin-top: 2px; }
  .divider { border: none; border-top: 1px dashed #aaa; margin: 10px 0; }
  .info-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
  .info-table td { padding: 3px 0; vertical-align: top; font-size: 12px; }
  .info-table td:first-child { color: #555; width: 38%; }
  .info-table td:last-child  { font-weight: 600; }
  .items-table { width: 100%; border-collapse: collapse; margin: 8px 0; }
  .items-table th { font-size: 11px; text-transform: uppercase; border-bottom: 1px solid #ccc; padding: 4px 2px; text-align: left; }
  .items-table th.r, .items-table td.r { text-align: right; }
  .items-table td { padding: 4px 2px; font-size: 12px; border-bottom: 1px dotted #eee; }
  .items-table .item-name { font-weight: 600; }
  .totals-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
  .totals-table td { padding: 3px 2px; font-size: 12px; }
  .totals-table td:last-child { text-align: right; }
  .totals-table .grand-total td { font-size: 14px; font-weight: 700; border-top: 1px solid #aaa; padding-top: 6px; }
  .status-badge {
    display: inline-block;
    padding: 3px 10px;
    border: 1px solid #333;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
  }
  .notes-box {
    border: 1px dashed #aaa;
    min-height: 48px;
    padding: 6px 8px;
    font-size: 11px;
    color: #888;
    margin-top: 4px;
  }
  .slip-footer { text-align: center; margin-top: 14px; font-size: 11px; color: #555; }
  .action-bar {
    display: flex; gap: 10px; justify-content: center;
    margin: 20px auto 0; max-width: 420px;
  }
  .btn-action {
    flex: 1; padding: 10px 0; font-size: 13px;
    border: none; border-radius: 4px; cursor: pointer;
    font-family: inherit; font-weight: 600; text-align: center;
    text-decoration: none;
  }
  .btn-print { background: #212529; color: #fff; }
  .btn-print:hover { background: #444; }
  .btn-back  { background: #6c757d; color: #fff; }
  .btn-back:hover { background: #555; }
  @media print {
    body { background: #fff; padding: 0; }
    .slip { box-shadow: none; border: none; max-width: 100%; padding: 0; }
    .no-print { display: none !important; }
    @page { margin: 6mm; }
  }
</style>
</head>
<body>

<div class="slip">

  <div class="slip-header">
    <h1>Lumina Hardware</h1>
    <p>Delivery Slip</p>
  </div>

  <hr class="divider">

  <table class="info-table">
    <tr><td>Order #</td><td><?= esc((string)$orderId) ?></td></tr>
    <tr><td>Date</td><td><?= esc($order['order_date']) ?></td></tr>
    <tr><td>Status</td><td><span class="status-badge"><?= esc($deliveryStatus) ?></span></td></tr>
  </table>

  <hr class="divider">

  <table class="info-table">
    <tr><td>Customer</td><td><?= esc($order['customer_name']) ?></td></tr>
    <?php if (!empty($order['customer_phone'])): ?>
    <tr><td>Contact</td><td><?= esc($order['customer_phone']) ?></td></tr>
    <?php endif; ?>
    <tr><td>Address</td><td><?= esc($order['customer_address']) ?></td></tr>
    <?php if (!empty($order['delivery_address']) && $order['delivery_address'] !== $order['customer_address']): ?>
    <tr><td>Del. Address</td><td><?= esc($order['delivery_address']) ?></td></tr>
    <?php endif; ?>
  </table>

  <hr class="divider">

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
      <?php foreach ($items as $item): ?>
      <tr>
        <td>
          <div class="item-name"><?= esc($item['name']) ?></div>
          <?php if (!empty($item['sku'])): ?>
          <div style="font-size:10px;color:#777"><?= esc($item['sku']) ?></div>
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

  <table class="totals-table">
    <tr><td>Subtotal</td><td><?= peso($subtotal) ?></td></tr>
    <tr><td>Delivery Fee</td><td><?= peso((float)$order['delivery_fee']) ?></td></tr>
    <tr class="grand-total"><td>TOTAL</td><td><?= peso((float)$order['total_amount']) ?></td></tr>
    <tr><td>Payment</td><td><?= esc(methodLabel($order['payment_method'])) ?></td></tr>
    <?php if ($order['payment_method'] === 'cash'): ?>
    <tr><td>Tendered</td><td><?= peso((float)$order['amount_tendered']) ?></td></tr>
    <tr><td>Change</td><td><?= peso((float)$order['change_due']) ?></td></tr>
    <?php elseif (!empty($order['reference_number'])): ?>
    <tr><td>Reference #</td><td><?= esc($order['reference_number']) ?></td></tr>
    <?php endif; ?>
  </table>

  <hr class="divider">

  <div style="font-size:11px;color:#555;margin-bottom:4px;font-weight:700;text-transform:uppercase">
    Notes / Instructions
  </div>
  <div class="notes-box">
    <?php if (!empty($order['delivery_notes'])): ?>
    <?= htmlspecialchars($order['delivery_notes']) ?>
    <?php else: ?>&nbsp;<?php endif; ?>
  </div>

  <div class="slip-footer" style="margin-top:12px">
    <p>Lumina Hardware &mdash; Delivery Copy</p>
    <p style="margin-top:3px">Printed: <?= date('Y-m-d H:i:s') ?></p>
  </div>

</div>

<div class="action-bar no-print">
  <button class="btn-action btn-print" onclick="window.print()">&#128438; Print Slip</button>
  <a class="btn-action btn-back" href="deliveries.php">&#8592; Back to Deliveries</a>
</div>

<script>
  const params  = new URLSearchParams(window.location.search);
  const printed = params.get('printed');
  if (printed === '0') {
    const newUrl = window.location.href.replace('printed=0', 'printed=1');
    history.replaceState(null, '', newUrl);
    window.addEventListener('load', function () {
      setTimeout(function () { window.print(); }, 1200);
    });
  }
</script>

</body>
</html>
