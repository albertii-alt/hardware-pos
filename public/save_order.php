<?php
require_once __DIR__ . '/../app/bootstrap.php';




require_once __DIR__ . '/../app/Services/OrderService.php';


// ── HTTP endpoint ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.']);
        exit;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON payload.']);
        exit;
    }

    $cart = $body['cart'] ?? null;
    if (!is_array($cart) || empty($cart)) {
        $cart = getCart();
    }

    $requestToken = isset($body['request_token']) && $body['request_token'] !== ''
        ? substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $body['request_token']), 0, 64)
        : null;

    $conn    = getConnection();
    $service = new OrderService($conn);

    $result = $service->placeOrder(
        $body['customer'] ?? [],
        $body['delivery'] ?? [],
        $body['payment']  ?? [],
        $cart,
        $requestToken
    );

    if ($result['success']) {
        clearCart();
        $result['receipt_url'] = 'receipt.php?id=' . $result['order_id'] . '&printed=0';
        logAction('ORDER_CREATED', $result['order_id']);
    }

    $conn->close();
    echo json_encode($result);
    exit;
}
