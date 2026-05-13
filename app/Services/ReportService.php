<?php

class ReportService
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function getTodaySummary(): array
    {
        [$start, $end] = $this->todayRange();

        $stmt = $this->conn->prepare(
            'SELECT
                COUNT(DISTINCT o.id)                                  AS total_orders,
                COALESCE(SUM(o.total_amount), 0)                      AS total_revenue,
                COALESCE(SUM(oi.quantity * COALESCE(p.cost_price,0)), 0) AS total_cost
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             JOIN products    p  ON p.id = oi.product_id
             WHERE o.order_date BETWEEN ? AND ?
               AND o.status = "completed"'
        );
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // total_revenue: sum per order (not per item) to avoid double-counting
        $stmt2 = $this->conn->prepare(
            'SELECT COALESCE(SUM(total_amount), 0) AS total_revenue
             FROM orders
             WHERE order_date BETWEEN ? AND ? AND status = "completed"'
        );
        $stmt2->bind_param('ss', $start, $end);
        $stmt2->execute();
        $rev = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();

        $row['total_revenue'] = $rev['total_revenue'];
        $row['gross_profit']  = (float)$row['total_revenue'] - (float)$row['total_cost'];
        return $row;
    }

    public function getTodayPaymentBreakdown(): array
    {
        [$start, $end] = $this->todayRange();

        $stmt = $this->conn->prepare(
            'SELECT payment_method,
                    COUNT(*)          AS orders,
                    SUM(total_amount) AS revenue
             FROM orders
             WHERE order_date BETWEEN ? AND ?
               AND status = "completed"
             GROUP BY payment_method'
        );
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getRangeSummary(string $startDate, string $endDate): array
    {
        [$start, $end] = $this->dateRange($startDate, $endDate);

        $stmt = $this->conn->prepare(
            'SELECT
                COUNT(DISTINCT o.id)                                       AS total_orders,
                COALESCE(SUM(oi.quantity * COALESCE(p.cost_price,0)), 0)   AS total_cost
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             JOIN products    p  ON p.id = oi.product_id
             WHERE o.order_date BETWEEN ? AND ?
               AND o.status = "completed"'
        );
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // total_revenue: sum per order to avoid double-counting with items JOIN
        $stmt2 = $this->conn->prepare(
            'SELECT COALESCE(SUM(total_amount), 0) AS total_revenue
             FROM orders
             WHERE order_date BETWEEN ? AND ? AND status = "completed"'
        );
        $stmt2->bind_param('ss', $start, $end);
        $stmt2->execute();
        $rev = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();

        $row['total_revenue'] = $rev['total_revenue'];
        $row['gross_profit']  = (float)$row['total_revenue'] - (float)$row['total_cost'];
        return $row;
    }

    public function getBestSellers(string $startDate, string $endDate, int $limit = 5): array
    {
        [$start, $end] = $this->dateRange($startDate, $endDate);

        $stmt = $this->conn->prepare(
            'SELECT p.id, p.sku, p.name, p.category,
                    SUM(oi.quantity)                              AS total_qty,
                    SUM(oi.total)                                 AS total_revenue,
                    SUM(oi.quantity * COALESCE(p.cost_price, 0))  AS total_cost
             FROM order_items oi
             JOIN orders   o ON o.id  = oi.order_id
             JOIN products p ON p.id  = oi.product_id
             WHERE o.order_date BETWEEN ? AND ?
               AND o.status = "completed"
             GROUP BY p.id, p.sku, p.name, p.category
             ORDER BY total_qty DESC
             LIMIT ?'
        );
        $stmt->bind_param('ssi', $start, $end, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getLowStockProducts(): array
    {
        $stmt = $this->conn->prepare(
            'SELECT sku, name, category, stock, min_stock_alert, inventory_unit, allows_decimal, quantity_step
             FROM products
             WHERE stock <= min_stock_alert AND deleted = 0
             ORDER BY stock ASC'
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    // ── Chart / Analytics Methods ───────────────────────────────────────────────

    public function getLast7DaysRevenueTrend(): array
    {
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date  = date('Y-m-d', strtotime("-$i days"));
            $label = date('M d', strtotime($date));
            $start = $date . ' 00:00:00';
            $end   = $date . ' 23:59:59';

            $stmt = $this->conn->prepare(
                'SELECT COALESCE(SUM(total_amount), 0) AS revenue FROM orders
                 WHERE order_date BETWEEN ? AND ? AND status = "completed"'
            );
            $stmt->bind_param('ss', $start, $end);
            $stmt->execute();
            $rev = (float)$stmt->get_result()->fetch_assoc()['revenue'];
            $stmt->close();

            $trend[] = ['label' => $label, 'revenue' => $rev];
        }
        return $trend;
    }

    public function getPaymentMethodBreakdown(?string $startDate = null, ?string $endDate = null): array
    {
        if (!$startDate || !$endDate) {
            $startDate = date('Y-m-01');
            $endDate   = date('Y-m-t');
        }
        [$start, $end] = $this->dateRange($startDate, $endDate);

        $stmt = $this->conn->prepare(
            'SELECT payment_method AS method, SUM(total_amount) AS total
             FROM orders
             WHERE order_date BETWEEN ? AND ? AND status = "completed"
             GROUP BY payment_method'
        );
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getMonthlyRevenueTrend(): array
    {
        $monthStart = date('Y-m-01');
        $monthEnd   = date('Y-m-t');
        $daysInMonth = (int)date('t');

        $trend = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date  = date('Y-m-' . str_pad($d, 2, '0', STR_PAD_LEFT));
            $start = $date . ' 00:00:00';
            $end   = $date . ' 23:59:59';

            $stmt = $this->conn->prepare(
                'SELECT COALESCE(SUM(total_amount), 0) AS revenue FROM orders
                 WHERE order_date BETWEEN ? AND ? AND status = "completed"'
            );
            $stmt->bind_param('ss', $start, $end);
            $stmt->execute();
            $rev = (float)$stmt->get_result()->fetch_assoc()['revenue'];
            $stmt->close();

            $trend[] = ['day' => $d, 'revenue' => $rev];
        }
        return $trend;
    }

    public function getTopSellingProductsChart(int $limit = 5): array
    {
        $monthStart = date('Y-m-01');
        $monthEnd   = date('Y-m-t');
        return $this->getBestSellers($monthStart, $monthEnd, $limit);
    }

    public function getDashboardInsights(): array
    {
        // Weekly growth %
        $thisWeekStart = date('Y-m-d', strtotime('monday this week'));
        $thisWeekEnd   = date('Y-m-d', strtotime('sunday this week'));
        $lastWeekStart = date('Y-m-d', strtotime('monday last week'));
        $lastWeekEnd   = date('Y-m-d', strtotime('sunday last week'));

        $thisWeek = $this->getRangeSummary($thisWeekStart, $thisWeekEnd);
        $lastWeek = $this->getRangeSummary($lastWeekStart, $lastWeekEnd);

        $thisRev = (float)$thisWeek['total_revenue'];
        $lastRev = (float)$lastWeek['total_revenue'];
        $growth  = $lastRev > 0 ? round((($thisRev - $lastRev) / $lastRev) * 100, 1) : 0.0;

        // Top payment method (current month)
        $payments    = $this->getPaymentMethodBreakdown();
        $topPayment  = '—';
        $maxPayTotal = 0;
        foreach ($payments as $p) {
            if ((float)$p['total'] > $maxPayTotal) {
                $maxPayTotal = (float)$p['total'];
                $topPayment  = ucfirst($p['method']);
            }
        }

        // Top product (current month)
        $topProducts = $this->getTopSellingProductsChart(1);
        $topProduct  = !empty($topProducts) ? $topProducts[0]['name'] : '—';

        // Critical low stock count
        $criticalLow = count(array_filter($this->getLowStockProducts(), fn($p) => (int)$p['stock'] === 0));

        return [
            'weekly_growth_percent'    => $growth,
            'top_payment_method'       => $topPayment,
            'top_product'              => $topProduct,
            'critical_low_stock_count' => $criticalLow,
        ];
    }

    public function getLowestMeasuredStock(): ?array
    {
        $stmt = $this->conn->prepare(
            'SELECT name, stock, inventory_unit
             FROM products
             WHERE allows_decimal = 1 AND deleted = 0 AND deleted_at IS NULL AND stock > 0
             ORDER BY stock ASC
             LIMIT 1'
        );
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function getMostSoldMeasuredProduct(): ?array
    {
        $monthStart = date('Y-m-01') . ' 00:00:00';
        $monthEnd   = date('Y-m-t')  . ' 23:59:59';
        $stmt = $this->conn->prepare(
            'SELECT p.name, p.inventory_unit, SUM(oi.quantity) AS total_qty
             FROM order_items oi
             JOIN orders   o ON o.id = oi.order_id
             JOIN products p ON p.id = oi.product_id
             WHERE o.order_date BETWEEN ? AND ?
               AND o.status = "completed"
               AND p.allows_decimal = 1
             GROUP BY p.id, p.name, p.inventory_unit
             ORDER BY total_qty DESC
             LIMIT 1'
        );
        $stmt->bind_param('ss', $monthStart, $monthEnd);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    // ── Phase 7B: Profit Analytics ─────────────────────────────────────────────

    public function getProfitTrendLast30Days(): array
    {
        $stmt = $this->conn->prepare(
            'SELECT DATE(o.order_date) AS day,
                    COALESCE(SUM(o.total_amount), 0)                        AS revenue,
                    COALESCE(SUM(oi.quantity * COALESCE(p.cost_price, 0)), 0) AS cost
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             JOIN products    p  ON p.id = oi.product_id
             WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
               AND o.status = "completed"
             GROUP BY DATE(o.order_date)
             ORDER BY day ASC'
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Fill missing days with zero
        $map = [];
        foreach ($rows as $r) $map[$r['day']] = $r;
        $result = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $result[] = [
                'label'  => date('M d', strtotime($d)),
                'profit' => isset($map[$d]) ? (float)$map[$d]['revenue'] - (float)$map[$d]['cost'] : 0.0,
            ];
        }
        return $result;
    }

    public function getTopProfitableProducts(int $limit = 10): array
    {
        $monthStart = date('Y-m-01') . ' 00:00:00';
        $monthEnd   = date('Y-m-t')  . ' 23:59:59';
        $stmt = $this->conn->prepare(
            'SELECT p.name, p.sku,
                    SUM(oi.total)                                AS revenue,
                    SUM(oi.quantity * COALESCE(p.cost_price,0)) AS cost,
                    SUM(oi.total) - SUM(oi.quantity * COALESCE(p.cost_price,0)) AS profit
             FROM order_items oi
             JOIN orders   o ON o.id = oi.order_id
             JOIN products p ON p.id = oi.product_id
             WHERE o.order_date BETWEEN ? AND ? AND o.status = "completed"
             GROUP BY p.id, p.name, p.sku
             ORDER BY profit DESC
             LIMIT ?'
        );
        $stmt->bind_param('ssi', $monthStart, $monthEnd, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getLeastProfitableProducts(int $limit = 10): array
    {
        $monthStart = date('Y-m-01') . ' 00:00:00';
        $monthEnd   = date('Y-m-t')  . ' 23:59:59';
        $stmt = $this->conn->prepare(
            'SELECT p.name, p.sku,
                    SUM(oi.total)                                AS revenue,
                    SUM(oi.quantity * COALESCE(p.cost_price,0)) AS cost,
                    SUM(oi.total) - SUM(oi.quantity * COALESCE(p.cost_price,0)) AS profit
             FROM order_items oi
             JOIN orders   o ON o.id = oi.order_id
             JOIN products p ON p.id = oi.product_id
             WHERE o.order_date BETWEEN ? AND ? AND o.status = "completed"
             GROUP BY p.id, p.name, p.sku
             ORDER BY profit ASC
             LIMIT ?'
        );
        $stmt->bind_param('ssi', $monthStart, $monthEnd, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getProfitMarginsSummary(): array
    {
        $monthStart = date('Y-m-01') . ' 00:00:00';
        $monthEnd   = date('Y-m-t')  . ' 23:59:59';

        $stmt = $this->conn->prepare(
            'SELECT
                COALESCE(SUM(o.total_amount), 0)                          AS gross_revenue,
                COALESCE(SUM(oi.quantity * COALESCE(p.cost_price, 0)), 0) AS total_cost
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             JOIN products    p  ON p.id = oi.product_id
             WHERE o.order_date BETWEEN ? AND ? AND o.status = "completed"'
        );
        $stmt->bind_param('ss', $monthStart, $monthEnd);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $revenue = (float)$row['gross_revenue'];
        $cost    = (float)$row['total_cost'];
        $profit  = $revenue - $cost;
        $margin  = $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0.0;

        // Highest / lowest single-day profit this month
        $stmt2 = $this->conn->prepare(
            'SELECT DATE(o.order_date) AS day,
                    SUM(o.total_amount) - SUM(oi.quantity * COALESCE(p.cost_price,0)) AS day_profit
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             JOIN products    p  ON p.id = oi.product_id
             WHERE o.order_date BETWEEN ? AND ? AND o.status = "completed"
             GROUP BY DATE(o.order_date)
             ORDER BY day_profit DESC'
        );
        $stmt2->bind_param('ss', $monthStart, $monthEnd);
        $stmt2->execute();
        $days = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt2->close();

        return [
            'gross_revenue'       => $revenue,
            'total_cost'          => $cost,
            'gross_profit'        => $profit,
            'profit_margin_pct'   => $margin,
            'highest_day_profit'  => !empty($days) ? (float)$days[0]['day_profit']  : 0.0,
            'lowest_day_profit'   => !empty($days) ? (float)end($days)['day_profit'] : 0.0,
        ];
    }

    // ── Phase 7B: Inventory Intelligence ─────────────────────────────────────────

    public function getDeadStockProducts(): array
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-60 days'));
        $stmt = $this->conn->prepare(
            'SELECT p.id, p.sku, p.name, p.category, p.stock,
                    p.stock * COALESCE(p.cost_price, 0) AS stock_value
             FROM products p
             WHERE p.stock > 0
               AND p.deleted = 0 AND p.deleted_at IS NULL
               AND p.id NOT IN (
                   SELECT DISTINCT oi.product_id
                   FROM order_items oi
                   JOIN orders o ON o.id = oi.order_id
                   WHERE o.order_date >= ? AND o.status = "completed"
               )
             ORDER BY stock_value DESC'
        );
        $stmt->bind_param('s', $cutoff);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getFastMovingProducts(int $limit = 10): array
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
        $stmt = $this->conn->prepare(
            'SELECT p.name, p.sku, p.category,
                    SUM(oi.quantity) AS total_qty
             FROM order_items oi
             JOIN orders   o ON o.id = oi.order_id
             JOIN products p ON p.id = oi.product_id
             WHERE o.order_date >= ? AND o.status = "completed"
               AND p.deleted = 0 AND p.deleted_at IS NULL
             GROUP BY p.id, p.name, p.sku, p.category
             ORDER BY total_qty DESC
             LIMIT ?'
        );
        $stmt->bind_param('si', $cutoff, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getInventoryValuation(): array
    {
        $stmt = $this->conn->prepare(
            'SELECT
                COUNT(*)                                              AS total_products,
                SUM(stock)                                            AS total_units,
                COALESCE(SUM(stock * COALESCE(cost_price, 0)), 0)    AS cost_value,
                COALESCE(SUM(stock * COALESCE(selling_price, 0)), 0) AS retail_value
             FROM products
             WHERE deleted = 0 AND deleted_at IS NULL AND stock > 0'
        );
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row;
    }

    public function getStockRiskProducts(): array
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-14 days'));
        $stmt = $this->conn->prepare(
            'SELECT p.id, p.sku, p.name, p.category, p.stock, p.min_stock_alert,
                    COALESCE(SUM(oi.quantity), 0) AS sold_last_14
             FROM products p
             LEFT JOIN order_items oi ON oi.product_id = p.id
             LEFT JOIN orders      o  ON o.id = oi.order_id
                 AND o.order_date >= ? AND o.status = "completed"
             WHERE p.stock <= p.min_stock_alert
               AND p.deleted = 0 AND p.deleted_at IS NULL
             GROUP BY p.id, p.sku, p.name, p.category, p.stock, p.min_stock_alert
             HAVING sold_last_14 > 0
             ORDER BY sold_last_14 DESC'
        );
        $stmt->bind_param('s', $cutoff);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    // ── Phase 7B: Delivery Performance ───────────────────────────────────────────

    public function getDeliveryCompletionStats(): array
    {
        $stmt = $this->conn->prepare(
            'SELECT
                COUNT(*) AS total_deliveries,
                SUM(delivery_status = "delivered")   AS delivered,
                SUM(delivery_status = "cancelled")   AS cancelled,
                SUM(delivery_status = "pending")     AS pending,
                SUM(delivery_status = "out_for_delivery") AS out_for_delivery
             FROM orders
             WHERE delivery_type = "delivery" AND status = "completed"'
        );
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $total     = (int)$row['total_deliveries'];
        $delivered = (int)$row['delivered'];
        $row['success_rate_pct'] = $total > 0 ? round(($delivered / $total) * 100, 1) : 0.0;
        return $row;
    }

    public function getAverageDeliveryTime(): float
    {
        $stmt = $this->conn->prepare(
            'SELECT AVG(TIMESTAMPDIFF(HOUR, order_date, delivery_status_updated_at)) AS avg_hours
             FROM orders
             WHERE delivery_type = "delivery"
               AND delivery_status = "delivered"
               AND delivery_status_updated_at IS NOT NULL
               AND status = "completed"'
        );
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return round((float)($row['avg_hours'] ?? 0), 1);
    }

    public function getMunicipalityDeliveryBreakdown(int $limit = 10): array
    {
        $stmt = $this->conn->prepare(
            'SELECT COALESCE(m.municipality, "Unknown") AS municipality,
                    COUNT(*) AS total,
                    SUM(o.delivery_status = "delivered") AS delivered
             FROM orders o
             LEFT JOIN municipalities m ON m.id = o.municipality_id
             WHERE o.delivery_type = "delivery" AND o.status = "completed"
             GROUP BY o.municipality_id, m.municipality
             ORDER BY total DESC
             LIMIT ?'
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getDeliveryStatusDistribution(): array
    {
        $stmt = $this->conn->prepare(
            'SELECT delivery_status, COUNT(*) AS cnt
             FROM orders
             WHERE delivery_type = "delivery" AND status = "completed"
             GROUP BY delivery_status'
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function todayRange(): array
    {
        $today = date('Y-m-d');
        return [$today . ' 00:00:00', $today . ' 23:59:59'];
    }

    private function dateRange(string $startDate, string $endDate): array
    {
        return [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];
    }
}
