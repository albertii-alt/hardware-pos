<?php

require_once __DIR__ . '/../Repositories/CustomerRepository.php';
require_once __DIR__ . '/../Repositories/OrderRepository.php';
require_once __DIR__ . '/../Repositories/ProductRepository.php';

class OrderService
{
    private mysqli             $conn;
    private CustomerRepository $customers;
    private OrderRepository    $orders;
    private ProductRepository  $products;

    public function __construct(mysqli $conn)
    {
        $this->conn      = $conn;
        $this->customers = new CustomerRepository($conn);
        $this->orders    = new OrderRepository($conn);
        $this->products  = new ProductRepository($conn);
    }

    public function placeOrder(
        array $customerData,
        array $deliveryData,
        array $paymentData,
        array $cart,
        ?string $requestToken = null
    ): array {
        // ── Idempotency check ────────────────────────────────────────────────
        if ($requestToken !== null && $requestToken !== '') {
            $existing = $this->orders->findByRequestToken($requestToken);
            if ($existing) {
                return [
                    'success'     => true,
                    'order_id'    => $existing['id'],
                    'order_code'  => $existing['order_code'],
                    'receipt_url' => 'receipt.php?id=' . $existing['id'] . '&printed=0',
                    'duplicate'   => true,
                ];
            }
        }

        // ── Validate cart ────────────────────────────────────────────────────
        if (empty($cart)) {
            return ['success' => false, 'error' => 'Cart is empty.'];
        }

        foreach ($cart as $item) {
            $qty = (float)($item['quantity'] ?? 0);
            if ($qty <= 0) {
                return ['success' => false, 'error' => 'Item quantity must be greater than zero.'];
            }
            if ((float)($item['price'] ?? 0) < 0) {
                return ['success' => false, 'error' => 'Item price cannot be negative.'];
            }
        }

        // ── Validate customer ────────────────────────────────────────────────
        $customerName    = trim($customerData['name']    ?? '');
        $customerAddress = trim($customerData['address'] ?? '');
        $customerPhone   = trim($customerData['phone']   ?? '');

        if ($customerName === '' || $customerAddress === '') {
            return ['success' => false, 'error' => 'Customer name and address are required.'];
        }

        // ── Validate delivery ────────────────────────────────────────────────
        $deliveryType = $deliveryData['type'] ?? 'pickup';
        if (!in_array($deliveryType, ['pickup', 'delivery'], true)) {
            return ['success' => false, 'error' => 'Invalid delivery type.'];
        }
        $deliveryFee     = max(0.0, (float)($deliveryData['fee'] ?? 0));
        $deliveryAddress = $deliveryType === 'delivery' ? trim($deliveryData['address'] ?? '') : null;
        $municipalityId  = isset($deliveryData['municipality_id']) ? (int)$deliveryData['municipality_id'] ?: null : null;
        $barangayId      = isset($deliveryData['barangay_id'])     ? (int)$deliveryData['barangay_id']     ?: null : null;

        if ($deliveryType === 'delivery' && empty($deliveryAddress)) {
            return ['success' => false, 'error' => 'Delivery address is required for delivery orders.'];
        }
        $deliveryStatus = 'pending';

        // ── Validate payment ─────────────────────────────────────────────────
        $paymentMethod = $paymentData['method'] ?? '';
        if (!in_array($paymentMethod, ['cash', 'gcash', 'bank_transfer'], true)) {
            return ['success' => false, 'error' => 'Invalid payment method.'];
        }

        $subtotal = round(array_reduce($cart, fn($carry, $item) =>
            $carry + (float)$item['price'] * round((float)$item['quantity'], 3), 0.0
        ), 2);
        $total = $subtotal + $deliveryFee;

        $amountTendered  = null;
        $changeDue       = null;
        $referenceNumber = null;

        if ($paymentMethod === 'cash') {
            $amountTendered = (float)($paymentData['amount_tendered'] ?? 0);
            if ($amountTendered < $total) {
                return ['success' => false, 'error' =>
                    'Insufficient cash. Total: ' . number_format($total, 2) .
                    ', Tendered: ' . number_format($amountTendered, 2)
                ];
            }
            $changeDue = $amountTendered - $total;
        } else {
            $referenceNumber = trim($paymentData['reference_number'] ?? '');
            if ($referenceNumber === '') {
                return ['success' => false, 'error' =>
                    'Reference number is required for ' . strtoupper($paymentMethod)
                ];
            }
        }

        // ── Transaction ──────────────────────────────────────────────────────
        $this->conn->begin_transaction();

        try {
            // Validate each product exists, is not soft-deleted, and quantity is valid
            foreach ($cart as $item) {
                $productId = (int)($item['id'] ?? $item['product_id'] ?? 0);
                $product   = $this->products->findActiveById($productId);
                if (!$product) {
                    $name = htmlspecialchars($item['name'] ?? ('ID ' . $productId));
                    throw new RuntimeException("Product no longer available: {$name}");
                }
                $qty    = round((float)($item['quantity'] ?? 0), 3);
                $allows = (bool)$product['allows_decimal'];
                $minQty = (float)$product['min_sell_quantity'];
                $step   = (float)($product['quantity_step'] ?? 1.0);
                $err    = validateQuantityPrecision($qty, $allows, $minQty, $step);
                if ($err !== null) {
                    throw new RuntimeException(htmlspecialchars($product['name']) . ': ' . $err);
                }
            }

            // Customer — find or create
            $customerId = $this->customers->findByNameAndAddress($customerName, $customerAddress)
                ?? $this->customers->insert($customerName, $customerAddress, $customerPhone);

            // Insert order
            $orderId = $this->orders->insertOrder(
                $customerId, $deliveryType, $deliveryFee, $deliveryAddress, $deliveryStatus,
                $municipalityId, $barangayId,
                $paymentMethod, $amountTendered, $changeDue, $referenceNumber, $total,
                $requestToken
            );

            // Insert items + deduct stock atomically
            foreach ($cart as $item) {
                $productId = (int)($item['id'] ?? $item['product_id'] ?? 0);
                $quantity  = round((float)$item['quantity'], 3);
                $unitPrice = (float)$item['price'];
                $lineTotal = round($unitPrice * $quantity, 2);

                $this->orders->insertOrderItem($orderId, $productId, $quantity, $unitPrice, $lineTotal);

                $deducted = $this->products->deductStock($productId, $quantity);
                if (!$deducted) {
                    $name = $this->products->findNameById($productId) ?? ('ID ' . $productId);
                    throw new RuntimeException("Insufficient stock for product: {$name}");
                }
            }

            $this->conn->commit();

        } catch (RuntimeException $e) {
            $this->conn->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }

        // Fetch generated order_code
        $orderCode = $this->orders->getOrderCode($orderId);

        return [
            'success'      => true,
            'order_id'     => $orderId,
            'order_code'   => $orderCode,
            'receipt_data' => [
                'order_id'         => $orderId,
                'order_code'       => $orderCode,
                'date'             => date('Y-m-d H:i:s'),
                'customer_name'    => $customerName,
                'customer_address' => $customerAddress,
                'customer_phone'   => $customerPhone,
                'delivery_type'    => $deliveryType,
                'delivery_fee'     => $deliveryFee,
                'delivery_address' => $deliveryAddress,
                'items'            => array_values($cart),
                'subtotal'         => $subtotal,
                'total'            => $total,
                'payment_method'   => $paymentMethod,
                'amount_tendered'  => $amountTendered,
                'change_due'       => $changeDue,
                'reference_number' => $referenceNumber,
            ],
        ];
    }

    public function generateReceiptPayload(int $orderId): ?array
    {
        $order = $this->orders->findOrderById($orderId);
        if (!$order) return null;

        $items    = $this->orders->findItemsByOrderId($orderId);
        $subtotal = (float)$order['total_amount'] - (float)$order['delivery_fee'];

        return [
            'order_id'         => $orderId,
            'order_code'       => $order['order_code'] ?? null,
            'date'             => $order['order_date'],
            'customer_name'    => $order['customer_name'],
            'customer_address' => $order['customer_address'],
            'customer_phone'   => $order['customer_phone'],
            'delivery_type'    => $order['delivery_type'],
            'delivery_fee'     => (float)$order['delivery_fee'],
            'delivery_address' => $order['delivery_address'],
            'items'            => $items,
            'subtotal'         => $subtotal,
            'total'            => (float)$order['total_amount'],
            'payment_method'   => $order['payment_method'],
            'amount_tendered'  => $order['amount_tendered'] !== null ? (float)$order['amount_tendered'] : null,
            'change_due'       => $order['change_due'] !== null ? (float)$order['change_due'] : null,
            'reference_number' => $order['reference_number'],
        ];
    }

    public function cancelOrder(int $orderId, int $userId, string $username): array
    {
        $order = $this->orders->findOrderWithItems($orderId);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }
        if ($order['status'] !== 'completed') {
            return ['success' => false, 'error' => 'Only completed orders can be cancelled.'];
        }

        $this->conn->begin_transaction();
        try {
            $cancelled = $this->orders->cancelOrder($orderId);
            if (!$cancelled) {
                throw new RuntimeException('Order could not be cancelled.');
            }

            require_once __DIR__ . '/InventoryMovementService.php';
            $movSvc = new InventoryMovementService($this->conn);

            foreach ($order['items'] as $item) {
                $productId   = (int)$item['product_id'];
                $qty         = round((float)$item['quantity'], 3);
                $stockAfter  = $this->products->restoreStock($productId, $qty);
                $stockBefore = round($stockAfter - $qty, 3);
                $movSvc->logMovement(
                    $productId, $item['name'], 'STOCK_RESTORE',
                    $stockBefore, $qty, $stockAfter,
                    $userId, $username,
                    'Order cancellation #' . $orderId,
                    $item['inventory_unit'] ?? 'pcs'
                );
            }

            $this->conn->commit();
        } catch (RuntimeException $e) {
            $this->conn->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }

        return ['success' => true];
    }
}
