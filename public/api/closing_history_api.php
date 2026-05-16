<?php
require_once __DIR__ . '/../../app/bootstrap.php';
requireRole('owner');

require_once APP_ROOT . '/app/Services/ReportService.php';

header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';

function jsonOut(array $data): void { echo json_encode($data); exit; }

$sessionUserId = (int)($_SESSION['user_id'] ?? 0);

switch ($action) {

    // ── Auto-archive any past dates not yet archived ────────────────────────
    case 'auto_archive':
        $today = date('Y-m-d');
        $conn  = getConnection();

        // Find all past dates that have orders but no closure record
        $stmt = $conn->prepare(
            'SELECT DISTINCT DATE(order_date) AS business_date
             FROM orders
             WHERE DATE(order_date) < ?
               AND status = "completed"
               AND DATE(order_date) NOT IN (
                   SELECT business_date FROM daily_closures
               )
             ORDER BY business_date ASC'
        );
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $missingDates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($missingDates)) {
            $conn->close();
            jsonOut(['success' => true, 'archived' => 0]);
        }

        $report  = new ReportService($conn);
        $archived = 0;

        $insertStmt = $conn->prepare(
            'INSERT IGNORE INTO daily_closures
                (business_date, summary_json, payment_breakdown_json, best_sellers_json, low_stock_json, closed_by_user_id, closing_notes)
             VALUES (?, ?, ?, ?, ?, NULL, "Auto-archived by system")'
        );

        foreach ($missingDates as $row) {
            $date    = $row['business_date'];
            $summary = $report->getRangeSummary($date, $date);
            $payment = $report->getPaymentMethodBreakdown($date, $date);
            $sellers = $report->getBestSellers($date, $date, 5);
            $low     = $report->getLowStockProducts();

            $sJson = json_encode($summary, JSON_UNESCAPED_UNICODE);
            $pJson = json_encode($payment, JSON_UNESCAPED_UNICODE);
            $bJson = json_encode($sellers, JSON_UNESCAPED_UNICODE);
            $lJson = json_encode($low,     JSON_UNESCAPED_UNICODE);

            $insertStmt->bind_param('sssss', $date, $sJson, $pJson, $bJson, $lJson);
            if ($insertStmt->execute()) $archived++;
        }

        $insertStmt->close();
        $conn->close();

        if ($archived > 0) logAction('AUTO_ARCHIVE_CLOSURES', $archived);
        jsonOut(['success' => true, 'archived' => $archived]);

    // ── Finalize today's closing ──────────────────────────────────────────────
    case 'finalize_closure':
        $today = date('Y-m-d');
        $conn  = getConnection();

        // Check if already finalized
        $chk = $conn->prepare('SELECT id FROM daily_closures WHERE business_date = ? LIMIT 1');
        $chk->bind_param('s', $today);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $chk->close(); $conn->close();
            jsonOut(['success' => false, 'error' => 'Today\'s closing has already been finalized.', 'already_finalized' => true]);
        }
        $chk->close();

        // Collect live snapshots using same ReportService methods as closing_report.php
        $report           = new ReportService($conn);
        $summary          = $report->getTodaySummary();
        $paymentBreakdown = $report->getTodayPaymentBreakdown();
        $bestSellers      = $report->getBestSellers($today, $today);
        $lowStock         = $report->getLowStockProducts();

        $notes = isset($input['closing_notes']) ? trim($input['closing_notes']) : null;

        $summaryJson  = json_encode($summary,          JSON_UNESCAPED_UNICODE);
        $paymentJson  = json_encode($paymentBreakdown, JSON_UNESCAPED_UNICODE);
        $sellersJson  = json_encode($bestSellers,      JSON_UNESCAPED_UNICODE);
        $lowStockJson = json_encode($lowStock,         JSON_UNESCAPED_UNICODE);

        $stmt = $conn->prepare(
            'INSERT INTO daily_closures
                (business_date, summary_json, payment_breakdown_json, best_sellers_json, low_stock_json, closed_by_user_id, closing_notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssssss', $today, $summaryJson, $paymentJson, $sellersJson, $lowStockJson,
            $sessionUserId, $notes);

        if (!$stmt->execute()) {
            $err = $stmt->error; $stmt->close(); $conn->close();
            jsonOut(['success' => false, 'error' => 'Failed to save closure.']);
        }
        $closureId = $stmt->insert_id;
        $stmt->close();
        $conn->close();

        logAction('FINALIZE_DAILY_CLOSURE', $closureId);
        jsonOut(['success' => true, 'closure_id' => $closureId, 'business_date' => $today]);

    // ── Get single closure by id ──────────────────────────────────────────────
    case 'get_closure':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'error' => 'Invalid closure ID.']);

        $conn = getConnection();
        $stmt = $conn->prepare(
            'SELECT dc.*, u.username AS closed_by_username
             FROM daily_closures dc
             LEFT JOIN users u ON u.id = dc.closed_by_user_id
             WHERE dc.id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close(); $conn->close();

        if (!$row) jsonOut(['success' => false, 'error' => 'Closure not found.']);

        jsonOut([
            'success'          => true,
            'id'               => (int)$row['id'],
            'business_date'    => $row['business_date'],
            'summary'          => json_decode($row['summary_json'],          true),
            'payment_breakdown'=> json_decode($row['payment_breakdown_json'], true),
            'best_sellers'     => json_decode($row['best_sellers_json'],      true),
            'low_stock'        => json_decode($row['low_stock_json'],         true),
            'closed_by'        => $row['closed_by_username'] ?? '—',
            'closing_notes'    => $row['closing_notes'],
            'created_at'       => $row['created_at'],
        ]);

    // ── Check if today is already finalized ───────────────────────────────────
    case 'check_today':
        $today = date('Y-m-d');
        $conn  = getConnection();
        $stmt  = $conn->prepare('SELECT id, created_at FROM daily_closures WHERE business_date = ? LIMIT 1');
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close(); $conn->close();
        jsonOut(['finalized' => (bool)$row, 'closure_id' => $row ? (int)$row['id'] : null, 'created_at' => $row['created_at'] ?? null]);

    default:
        jsonOut(['success' => false, 'error' => 'Unknown action.']);
}
