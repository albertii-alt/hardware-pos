<?php
require_once __DIR__ . '/../app/bootstrap.php';
requireRole('owner');

require_once APP_ROOT . '/app/Repositories/OrderRepository.php';
require_once APP_ROOT . '/app/Services/ExportService.php';

const ALLOWED_EXPORT_TYPES = ['today', 'range', 'filtered'];

$type = $_GET['type'] ?? '';
if (!in_array($type, ALLOWED_EXPORT_TYPES, true)) {
    header('Location: index.php');
    exit;
}

$today      = date('Y-m-d');
$monthStart = date('Y-m-01');
$startDate  = $_GET['start_date']     ?? $monthStart;
$endDate    = $_GET['end_date']       ?? $today;
$payFilter  = $_GET['payment_method'] ?? '';
$delivFilter = $_GET['delivery_type'] ?? '';
$search     = trim($_GET['search']    ?? '');

if (!strtotime($startDate)) $startDate = $monthStart;
if (!strtotime($endDate))   $endDate   = $today;
if ($startDate > $endDate)  [$startDate, $endDate] = [$endDate, $startDate];

// Whitelist filter values
$allowedPayment  = ['', 'cash', 'gcash', 'bank_transfer'];
$allowedDelivery = ['', 'pickup', 'delivery'];
if (!in_array($payFilter,   $allowedPayment,  true)) $payFilter   = '';
if (!in_array($delivFilter, $allowedDelivery, true)) $delivFilter = '';

$conn   = getConnection();
$repo   = new OrderRepository($conn);
$export = new ExportService();

$headers = [
    'Order ID', 'Order Date', 'Customer Name', 'Payment Method', 'Reference Number',
    'Delivery Type', 'Subtotal', 'Delivery Fee', 'Total Amount',
    'Cost of Goods', 'Profit', 'Status',
];

switch ($type) {

    case 'today':
        $rows = $repo->findOrdersForExport($today, $today);
        $conn->close();
        $export->exportToCSV(
            $export->formatFilename('Orders_Today'),
            $headers,
            $export->exportOrderRows($rows)
        );
        break;

    case 'range':
        $rows = $repo->findOrdersForExport($startDate, $endDate);
        $conn->close();
        $export->exportToCSV(
            $export->formatFilename('Orders_' . $startDate . '_to_' . $endDate),
            $headers,
            $export->exportOrderRows($rows)
        );
        break;

    case 'filtered':
        $rows = $repo->findOrdersForExport($startDate, $endDate, $payFilter, $delivFilter, $search);
        $conn->close();
        $export->exportToCSV(
            $export->formatFilename('Orders_Filtered'),
            $headers,
            $export->exportOrderRows($rows)
        );
        break;
}

$conn->close();
