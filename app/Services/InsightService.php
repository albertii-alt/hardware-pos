<?php

class InsightService
{
    private mysqli        $conn;
    private ReportService $report;

    public function __construct(mysqli $conn, ReportService $report)
    {
        $this->conn   = $conn;
        $this->report = $report;
    }

    /**
     * Generate up to 8 prioritised business insights from live data.
     * Each insight: ['type' => success|warning|info|danger, 'icon' => 'bi-...', 'message' => '...']
     */
    public function generateInsights(): array
    {
        $insights = [];

        // 1. Weekly revenue growth/decline
        $thisWeekStart = date('Y-m-d', strtotime('monday this week'));
        $thisWeekEnd   = date('Y-m-d', strtotime('sunday this week'));
        $lastWeekStart = date('Y-m-d', strtotime('monday last week'));
        $lastWeekEnd   = date('Y-m-d', strtotime('sunday last week'));
        $thisWeek = $this->report->getRangeSummary($thisWeekStart, $thisWeekEnd);
        $lastWeek = $this->report->getRangeSummary($lastWeekStart, $lastWeekEnd);
        $thisRev  = (float)$thisWeek['total_revenue'];
        $lastRev  = (float)$lastWeek['total_revenue'];
        if ($lastRev > 0) {
            $pct = round((($thisRev - $lastRev) / $lastRev) * 100, 1);
            if ($pct >= 5) {
                $insights[] = ['type' => 'success', 'icon' => 'bi-graph-up-arrow',
                    'message' => "Revenue is up {$pct}% this week compared to last week."];
            } elseif ($pct <= -5) {
                $absPct = abs($pct);
                $insights[] = ['type' => 'danger', 'icon' => 'bi-graph-down-arrow',
                    'message' => "Revenue dropped {$absPct}% compared to last week."];
            }
        }

        // 2. Stock risk products
        $riskProducts = $this->report->getStockRiskProducts();
        if (!empty($riskProducts)) {
            $count = count($riskProducts);
            $insights[] = ['type' => 'warning', 'icon' => 'bi-exclamation-triangle',
                'message' => "{$count} product" . ($count > 1 ? 's are' : ' is') .
                    " at stock risk based on recent demand."];
        }

        // 3. Dead stock
        $deadStock = $this->report->getDeadStockProducts();
        if (!empty($deadStock)) {
            $count = count($deadStock);
            $insights[] = ['type' => 'warning', 'icon' => 'bi-archive',
                'message' => "{$count} product" . ($count > 1 ? 's have' : ' has') .
                    " not sold in over 60 days."];
        }

        // 4. Top payment method this month
        $payments = $this->report->getPaymentMethodBreakdown();
        if (!empty($payments)) {
            $totalRev = array_sum(array_column($payments, 'total'));
            usort($payments, fn($a, $b) => $b['total'] <=> $a['total']);
            $top = $payments[0];
            if ($totalRev > 0) {
                $pct = round(((float)$top['total'] / $totalRev) * 100, 1);
                $label = ['cash' => 'Cash', 'gcash' => 'GCash', 'bank_transfer' => 'Bank Transfer'][$top['method']] ?? ucfirst($top['method']);
                $insights[] = ['type' => 'info', 'icon' => 'bi-credit-card',
                    'message' => "{$label} payments represent {$pct}% of this month's sales."];
            }
        }

        // 5. Top municipality by delivery volume
        $muniBreakdown = $this->report->getMunicipalityDeliveryBreakdown(1);
        if (!empty($muniBreakdown) && $muniBreakdown[0]['municipality'] !== 'Unknown') {
            $muni  = htmlspecialchars($muniBreakdown[0]['municipality']);
            $total = (int)$muniBreakdown[0]['total'];
            $insights[] = ['type' => 'info', 'icon' => 'bi-geo-alt',
                'message' => "{$muni} has the highest delivery volume with {$total} order" . ($total > 1 ? 's' : '') . "."];
        }

        // 6. Best selling product this month
        $topProducts = $this->report->getTopSellingProductsChart(1);
        if (!empty($topProducts)) {
            $name = htmlspecialchars($topProducts[0]['name']);
            $qty  = (int)$topProducts[0]['total_qty'];
            $insights[] = ['type' => 'success', 'icon' => 'bi-trophy',
                'message' => "\"{$name}\" is the best seller this month with {$qty} unit" . ($qty > 1 ? 's' : '') . " sold."];
        }

        // 7. Delivery success rate
        $delivStats = $this->report->getDeliveryCompletionStats();
        $successRate = (float)$delivStats['success_rate_pct'];
        if ((int)$delivStats['total_deliveries'] > 0) {
            if ($successRate >= 90) {
                $insights[] = ['type' => 'success', 'icon' => 'bi-truck',
                    'message' => "Delivery success rate is {$successRate}% — excellent performance."];
            } elseif ($successRate < 70) {
                $insights[] = ['type' => 'danger', 'icon' => 'bi-truck',
                    'message' => "Delivery success rate is {$successRate}% — review cancelled deliveries."];
            }
        }

        // 8. Profit margin health
        $margins = $this->report->getProfitMarginsSummary();
        $margin  = (float)$margins['profit_margin_pct'];
        if ($margins['gross_revenue'] > 0) {
            if ($margin < 10) {
                $insights[] = ['type' => 'danger', 'icon' => 'bi-cash-stack',
                    'message' => "Profit margin is {$margin}% this month — review product pricing or costs."];
            } elseif ($margin >= 30) {
                $insights[] = ['type' => 'success', 'icon' => 'bi-cash-stack',
                    'message' => "Strong profit margin of {$margin}% this month."];
            }
        }

        return array_slice($insights, 0, 8);
    }
}
