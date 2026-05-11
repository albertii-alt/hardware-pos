<?php
require_once __DIR__ . '/../auth_guard.php';
requireRole('owner');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../app/Services/OrderService.php';
require_once __DIR__ . '/../audit.php';

header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

$userId   = (int)($_SESSION['user_id']  ?? 0);
$username = $_SESSION['username'] ?? '';

function jsonOut(array $data): void { echo json_encode($data); exit; }

switch ($action) {

    case 'get_order':
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'error' => 'Invalid ID.']);

        $conn    = getConnection();
        $service = new OrderService($conn);
        $order   = $service->generateReceiptPayload($id);
        $conn->close();

        if (!$order) jsonOut(['success' => false, 'error' => 'Order not found.']);

        // Also fetch status
        $conn2  = getConnection();
        $st     = $conn2->prepare('SELECT status FROM orders WHERE id = ? LIMIT 1');
        $st->bind_param('i', $id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        $conn2->close();

        $order['status'] = $row['status'] ?? 'completed';
        jsonOut(['success' => true, 'order' => $order]);

    case 'cancel_order':
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'error' => 'Invalid ID.']);

        $conn    = getConnection();
        $service = new OrderService($conn);
        $result  = $service->cancelOrder($id, $userId, $username);
        $conn->close();

        if ($result['success']) {
            logAction('ORDER_CANCELLED', $id, 'Cancelled by ' . $username);
        }
        jsonOut($result);

    default:
        jsonOut(['success' => false, 'error' => 'Unknown action.']);
}
