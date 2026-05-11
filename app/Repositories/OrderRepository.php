<?php

class OrderRepository
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function insertOrder(
        int     $customerId,
        string  $deliveryType,
        float   $deliveryFee,
        ?string $deliveryAddress,
        string  $deliveryStatus,
        ?int    $municipalityId,
        ?int    $barangayId,
        string  $paymentMethod,
        ?float  $amountTendered,
        ?float  $changeDue,
        ?string $referenceNumber,
        float   $totalAmount,
        ?string $requestToken = null
    ): int {
        $stmt = $this->conn->prepare(
            'INSERT INTO orders
                (customer_id, delivery_type, delivery_fee, delivery_address, delivery_status,
                 municipality_id, barangay_id,
                 payment_method, amount_tendered, change_due, reference_number, total_amount,
                 request_token, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "completed")'
        );
        $stmt->bind_param(
            'isdssiisddsds',
            $customerId, $deliveryType, $deliveryFee, $deliveryAddress, $deliveryStatus,
            $municipalityId, $barangayId,
            $paymentMethod, $amountTendered, $changeDue, $referenceNumber, $totalAmount,
            $requestToken
        );
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to save order: ' . $stmt->error);
        }
        $id = $stmt->insert_id;
        $stmt->close();

        // Generate order_code in PHP (trigger not used — avoids MySQL same-table update restriction)
        $code = 'LPO-' . date('Ymd') . '-' . str_pad($id, 6, '0', STR_PAD_LEFT);
        $upd  = $this->conn->prepare('UPDATE orders SET order_code = ? WHERE id = ?');
        $upd->bind_param('si', $code, $id);
        $upd->execute();
        $upd->close();

        return $id;
    }

    public function findByRequestToken(string $token): ?array
    {
        $stmt = $this->conn->prepare(
            'SELECT id, order_code FROM orders WHERE request_token = ? LIMIT 1'
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function getOrderCode(int $orderId): ?string
    {
        $stmt = $this->conn->prepare('SELECT order_code FROM orders WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row['order_code'] ?? null;
    }

    public function insertOrderItem(
        int   $orderId,
        int   $productId,
        int   $quantity,
        float $unitPrice,
        float $total
    ): void {
        $stmt = $this->conn->prepare(
            'INSERT INTO order_items (order_id, product_id, quantity, unit_price, total)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('iiidd', $orderId, $productId, $quantity, $unitPrice, $total);
        if (!$stmt->execute()) {
            throw new RuntimeException(
                'Failed to save order item for product ID ' . $productId . ': ' . $stmt->error
            );
        }
        $stmt->close();
    }

    public function findOrderById(int $orderId): ?array
    {
        $stmt = $this->conn->prepare(
            'SELECT o.*, c.name AS customer_name, c.address AS customer_address, c.phone AS customer_phone
             FROM orders o
             JOIN customers c ON c.id = o.customer_id
             WHERE o.id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function findOrderWithItems(int $orderId): ?array
    {
        $order = $this->findOrderById($orderId);
        if (!$order) return null;
        $order['items'] = $this->findItemsByOrderId($orderId);
        return $order;
    }

    public function cancelOrder(int $orderId): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE orders SET status='cancelled' WHERE id=? AND status='completed'"
        );
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected > 0;
    }

    public function findOrdersFiltered(
        string $startDate,
        string $endDate,
        string $paymentMethod = '',
        string $deliveryType  = '',
        string $search        = '',
        int    $limit         = 100
    ): array {
        $where  = ["o.order_date BETWEEN ? AND ?"];
        $types  = 'ss';
        $params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];

        if ($paymentMethod) {
            $where[]  = 'o.payment_method = ?';
            $types   .= 's';
            $params[] = $paymentMethod;
        }
        if ($deliveryType) {
            $where[]  = 'o.delivery_type = ?';
            $types   .= 's';
            $params[] = $deliveryType;
        }
        if ($search) {
            $like     = '%' . $this->conn->real_escape_string($search) . '%';
            $where[]  = '(o.id LIKE ? OR c.name LIKE ? OR o.reference_number LIKE ?)';
            $types   .= 'sss';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = 'SELECT o.*, c.name AS customer_name
                FROM orders o
                JOIN customers c ON c.id = o.customer_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY o.order_date DESC
                LIMIT ?';
        $types   .= 'i';
        $params[] = $limit;

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getSalesSummary(string $startDate, string $endDate): array
    {
        $start = $startDate . ' 00:00:00';
        $end   = $endDate   . ' 23:59:59';
        $stmt  = $this->conn->prepare(
            "SELECT
                COUNT(*) AS total_orders,
                COALESCE(SUM(total_amount), 0) AS total_revenue,
                COALESCE(SUM(CASE WHEN payment_method='cash' THEN total_amount ELSE 0 END), 0) AS cash_revenue,
                COALESCE(SUM(CASE WHEN payment_method!='cash' THEN total_amount ELSE 0 END), 0) AS noncash_revenue
             FROM orders
             WHERE order_date BETWEEN ? AND ? AND status='completed'"
        );
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row;
    }

    public function findItemsByOrderId(int $orderId): array
    {
        $stmt = $this->conn->prepare(
            'SELECT oi.product_id, oi.quantity, oi.unit_price, oi.total, p.name, p.sku
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ?
             ORDER BY oi.id'
        );
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function findDeliveryOrders(string $statusFilter = '', string $sortMode = 'status'): array
    {
        $statusOrder = "FIELD(o.delivery_status,'pending','preparing','ready','out_for_delivery','delivered','cancelled')";
        $where  = ["o.delivery_type = 'delivery'", "o.status = 'completed'"];
        $types  = '';
        $params = [];

        if ($statusFilter !== '') {
            $where[]  = 'o.delivery_status = ?';
            $types   .= 's';
            $params[] = $statusFilter;
        }

        if ($sortMode === 'municipality') {
            $orderBy = "m.municipality ASC, {$statusOrder}, o.order_date ASC";
        } elseif ($sortMode === 'barangay') {
            $orderBy = "m.municipality ASC, b.name ASC, {$statusOrder}, o.order_date ASC";
        } else {
            $orderBy = "{$statusOrder}, o.order_date ASC";
        }

        $sql = "SELECT o.id, o.order_date, o.delivery_status, o.delivery_status_updated_at,
                       o.delivery_address, o.delivery_fee, o.total_amount,
                       o.payment_method, o.reference_number, o.delivery_notes,
                       o.municipality_id, o.barangay_id,
                       c.name AS customer_name, c.phone AS customer_phone,
                       COALESCE(m.municipality, '') AS municipality_name,
                       COALESCE(b.name, '')         AS barangay_name
                FROM orders o
                JOIN customers c ON c.id = o.customer_id
                LEFT JOIN municipalities m ON m.id = o.municipality_id
                LEFT JOIN barangays b      ON b.id = o.barangay_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$orderBy}";

        $stmt = $this->conn->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function updateDeliveryStatus(int $orderId, string $status): bool
    {
        $allowed = ['pending','preparing','ready','out_for_delivery','delivered','cancelled'];
        if (!in_array($status, $allowed, true)) return false;

        $stmt = $this->conn->prepare(
            "UPDATE orders
             SET delivery_status = ?, delivery_status_updated_at = NOW()
             WHERE id = ? AND delivery_type = 'delivery' AND status = 'completed'"
        );
        $stmt->bind_param('si', $status, $orderId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected > 0;
    }

    public function getDeliverySummary(): array
    {
        $today = date('Y-m-d');
        $stmt  = $this->conn->prepare(
            "SELECT
                SUM(delivery_status = 'pending')          AS pending,
                SUM(delivery_status = 'preparing')        AS preparing,
                SUM(delivery_status = 'ready')            AS ready,
                SUM(delivery_status = 'out_for_delivery') AS out_for_delivery,
                SUM(delivery_status = 'delivered' AND DATE(delivery_status_updated_at) = ?) AS delivered_today
             FROM orders
             WHERE delivery_type = 'delivery' AND status = 'completed'"
        );
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row;
    }

    public function findDeliveryOrderById(int $orderId): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT o.*, c.name AS customer_name, c.address AS customer_address, c.phone AS customer_phone
             FROM orders o
             JOIN customers c ON c.id = o.customer_id
             WHERE o.id = ? AND o.delivery_type = 'delivery' LIMIT 1"
        );
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function findOrdersForExport(
        string $startDate,
        string $endDate,
        string $paymentMethod = '',
        string $deliveryType  = '',
        string $search        = ''
    ): array {
        $where  = ['o.order_date BETWEEN ? AND ?'];
        $types  = 'ss';
        $params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];

        if ($paymentMethod !== '') {
            $where[]  = 'o.payment_method = ?';
            $types   .= 's';
            $params[] = $paymentMethod;
        }
        if ($deliveryType !== '') {
            $where[]  = 'o.delivery_type = ?';
            $types   .= 's';
            $params[] = $deliveryType;
        }
        if ($search !== '') {
            $like     = '%' . $this->conn->real_escape_string($search) . '%';
            $where[]  = '(o.id LIKE ? OR c.name LIKE ? OR o.reference_number LIKE ?)';
            $types   .= 'sss';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = 'SELECT
                    o.id,
                    o.order_date,
                    c.name               AS customer_name,
                    o.payment_method,
                    o.reference_number,
                    o.delivery_type,
                    o.delivery_fee,
                    o.total_amount,
                    o.status,
                    COALESCE(SUM(oi.quantity * COALESCE(p.cost_price, 0)), 0) AS total_cost
                FROM orders o
                JOIN customers c    ON c.id  = o.customer_id
                JOIN order_items oi ON oi.order_id = o.id
                JOIN products p     ON p.id  = oi.product_id
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY o.id, o.order_date, c.name, o.payment_method,
                         o.reference_number, o.delivery_type, o.delivery_fee,
                         o.total_amount, o.status
                ORDER BY o.order_date DESC
                LIMIT 5000';

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }
}
