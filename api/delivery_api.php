<?php
require_once __DIR__ . '/../auth_guard.php';
requireAnyRole();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../app/Repositories/OrderRepository.php';
require_once __DIR__ . '/../audit.php';

header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

$userId   = (int)($_SESSION['user_id']  ?? 0);
$username = $_SESSION['username'] ?? '';

function jsonOut(array $data): void { echo json_encode($data); exit; }

$conn = getConnection();
$repo = new OrderRepository($conn);

switch ($action) {

    case 'update_status':
        $id     = (int)($input['id']     ?? 0);
        $status = trim($input['status']  ?? '');

        $allowed = ['pending','preparing','ready','out_for_delivery','delivered','cancelled'];
        if (!$id)                              jsonOut(['success' => false, 'error' => 'Invalid order ID.']);
        if (!in_array($status, $allowed, true)) jsonOut(['success' => false, 'error' => 'Invalid status.']);

        $ok = $repo->updateDeliveryStatus($id, $status);
        if (!$ok) jsonOut(['success' => false, 'error' => 'Update failed. Order not found or not a delivery order.']);

        $auditMap = [
            'preparing'        => 'DELIVERY_PREPARING',
            'ready'            => 'DELIVERY_READY',
            'out_for_delivery' => 'DELIVERY_DISPATCHED',
            'delivered'        => 'DELIVERY_COMPLETED',
        ];
        if (isset($auditMap[$status])) {
            logAction($auditMap[$status], $id, 'Status set to ' . $status . ' by ' . $username);
        }

        $conn->close();
        jsonOut(['success' => true, 'status' => $status]);

    case 'get_delivery':
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'error' => 'Invalid order ID.']);

        $order = $repo->findDeliveryOrderById($id);
        $conn->close();
        if (!$order) jsonOut(['success' => false, 'error' => 'Delivery order not found.']);
        jsonOut(['success' => true, 'order' => $order]);

    default:
        $conn->close();
        jsonOut(['success' => false, 'error' => 'Unknown action.']);
}
