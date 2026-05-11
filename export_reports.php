<?php
require_once __DIR__ . '/auth_guard.php';
requireRole('owner');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app/Services/ReportService.php';
require_once __DIR__ . '/app/Services/ExportService.php';

// ── Whitelist ─────────────────────────────────────────────────────────────────
const ALLOWED_TYPES = ['today', 'range', 'best_sellers', 'low_stock', 'all_products', 'audit_logs'];

$type = $_GET['type'] ?? '';

if (!in_array($type, ALLOWED_TYPES, true)) {
    http_response_code(403);
    header('Location: dashboard.php');
    exit;
}

// ── Date params (used by range / best_sellers) ────────────────────────────────
$today      = date('Y-m-d');
$monthStart = date('Y-m-01');
$startDate  = $_GET['start_date'] ?? $monthStart;
$endDate    = $_GET['end_date']   ?? $today;

if (!strtotime($startDate)) $startDate = $monthStart;
if (!strtotime($endDate))   $endDate   = $today;
if ($startDate > $endDate)  [$startDate, $endDate] = [$endDate, $startDate];

$conn    = getConnection();
$report  = new ReportService($conn);
$export  = new ExportService();

switch ($type) {

    case 'today': {
        $data = $report->getTodaySummary();
        $conn->close();
        $export->exportToCSV(
            $export->formatFilename('Today_Report'),
            ['Date', 'Orders', 'Revenue', 'Cost of Goods', 'Gross Profit'],
            [[
                $today,
                (int)$data['total_orders'],
                number_format((float)$data['total_revenue'], 2, '.', ''),
                number_format((float)$data['total_cost'],    2, '.', ''),
                number_format((float)$data['gross_profit'],  2, '.', ''),
            ]]
        );
    }

    case 'range': {
        $data = $report->getRangeSummary($startDate, $endDate);
        $conn->close();
        $export->exportToCSV(
            $export->formatFilename('Range_Report'),
            ['Date Range', 'Orders', 'Revenue', 'Cost of Goods', 'Gross Profit'],
            [[
                $startDate . ' to ' . $endDate,
                (int)$data['total_orders'],
                number_format((float)$data['total_revenue'], 2, '.', ''),
                number_format((float)$data['total_cost'],    2, '.', ''),
                number_format((float)$data['gross_profit'],  2, '.', ''),
            ]]
        );
    }

    case 'best_sellers': {
        $rows = $report->getBestSellers($startDate, $endDate, 50);
        $conn->close();
        $out = array_map(fn($r) => [
            $r['name'],
            $r['sku']  ?? '',
            (int)$r['total_qty'],
            number_format((float)$r['total_revenue'], 2, '.', ''),
            number_format((float)$r['total_revenue'] - (float)$r['total_cost'], 2, '.', ''),
        ], $rows);
        $export->exportToCSV(
            $export->formatFilename('Best_Sellers'),
            ['Product', 'SKU', 'Quantity Sold', 'Revenue', 'Profit'],
            $out
        );
    }

    case 'low_stock': {
        $rows = $report->getLowStockProducts();
        $conn->close();
        $out = array_map(fn($r) => [
            $r['sku']  ?? '',
            $r['name'],
            (int)$r['stock'],
            (int)$r['min_stock_alert'],
            (int)$r['stock'] === 0 ? 'Out of Stock' : 'Low Stock',
        ], $rows);
        $export->exportToCSV(
            $export->formatFilename('Low_Stock_Inventory'),
            ['SKU', 'Product', 'Stock', 'Min Stock', 'Status'],
            $out
        );
    }

    case 'all_products': {
        $conn->close();
        require_once __DIR__ . '/product.php';
        $rows = getAllProducts();
        $out  = array_map(fn($r) => [
            $r['sku']              ?? '',
            $r['name'],
            $r['category']         ?? '',
            $r['unit']             ?? '',
            number_format((float)$r['cost_price'],    2, '.', ''),
            number_format((float)$r['selling_price'], 2, '.', ''),
            (int)$r['stock'],
            (int)$r['min_stock_alert'],
        ], $rows);
        $export->exportToCSV(
            $export->formatFilename('Products_Inventory'),
            ['SKU', 'Product', 'Category', 'Unit', 'Cost Price', 'Selling Price', 'Stock', 'Min Stock Alert'],
            $out
        );
    }

    case 'audit_logs': {
        $conn2  = getConnection();
        $result = $conn2->query(
            'SELECT created_at, username, role, action, reference_id, details
             FROM audit_logs ORDER BY created_at DESC LIMIT 1000'
        );
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $conn2->close();
        $conn->close();
        $out = array_map(fn($r) => [
            $r['created_at'],
            $r['username']     ?? '',
            $r['role']         ?? '',
            $r['action'],
            $r['reference_id'] ?? '',
            $r['details']      ?? '',
        ], $rows);
        $export->exportToCSV(
            $export->formatFilename('Audit_Logs'),
            ['Timestamp', 'Username', 'Role', 'Action', 'Reference ID', 'Details'],
            $out
        );
    }
}

$conn->close();
