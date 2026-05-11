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
            'SELECT sku, name, category, stock, min_stock_alert
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
