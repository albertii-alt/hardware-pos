<?php

class InventoryMovementService
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Log an inventory movement. Failures are silently caught so they never
     * break the calling business operation.
     */
    public function logMovement(
        int     $productId,
        string  $productName,
        string  $actionType,
        float   $quantityBefore,
        float   $quantityChanged,
        float   $quantityAfter,
        ?int    $userId            = null,
        ?string $username          = null,
        ?string $notes             = null,
        string  $unitSnapshot      = 'pcs',
        string  $unitAfterSnapshot = ''
    ): void {
        $unitAfterSnapshot = $unitAfterSnapshot !== '' ? $unitAfterSnapshot : $unitSnapshot;
        try {
            $stmt = $this->conn->prepare(
                'INSERT INTO inventory_movements
                    (product_id, product_name_snapshot, unit_snapshot, unit_after_snapshot, action_type,
                     quantity_before, quantity_changed, quantity_after,
                     user_id, username, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'issssdddiss',
                $productId, $productName, $unitSnapshot, $unitAfterSnapshot, $actionType,
                $quantityBefore, $quantityChanged, $quantityAfter,
                $userId, $username, $notes
            );
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            error_log('InventoryMovementService::logMovement failed: ' . $e->getMessage());
        }
    }

    /**
     * Fetch recent movements with optional filters.
     */
    public function recentMovements(
        int     $limit      = 100,
        string  $startDate  = '',
        string  $endDate    = '',
        string  $actionType = '',
        string  $search     = ''
    ): array {
        $where  = ['1=1'];
        $types  = '';
        $params = [];

        if ($startDate && $endDate) {
            $where[]  = 'created_at BETWEEN ? AND ?';
            $types   .= 'ss';
            $params[] = $startDate . ' 00:00:00';
            $params[] = $endDate   . ' 23:59:59';
        }
        if ($actionType) {
            $where[]  = 'action_type = ?';
            $types   .= 's';
            $params[] = $actionType;
        }
        if ($search) {
            $like     = '%' . $this->conn->real_escape_string($search) . '%';
            $where[]  = '(product_name_snapshot LIKE ? OR username LIKE ? OR notes LIKE ?)';
            $types   .= 'sss';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql  = 'SELECT im.*, im.unit_snapshot AS inventory_unit, im.unit_after_snapshot
                 FROM inventory_movements im
                 WHERE ' . implode(' AND ', $where)
              . ' ORDER BY im.created_at DESC LIMIT ?';
        $types   .= 'i';
        $params[] = $limit;

        $stmt = $this->conn->prepare($sql);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Summary counts for today.
     */
    public function movementSummary(): array
    {
        $start = date('Y-m-d') . ' 00:00:00';
        $end   = date('Y-m-d') . ' 23:59:59';

        $stmt = $this->conn->prepare(
            'SELECT
                COUNT(*)                                              AS total_today,
                SUM(action_type = "STOCK_ADD")                        AS stock_added_count,
                SUM(action_type = "STOCK_REMOVE")                     AS stock_removed_count,
                SUM(action_type IN ("PRODUCT_CREATED","PRODUCT_UPDATED","PRODUCT_DELETED")) AS product_changes,
                COALESCE(SUM(CASE WHEN action_type="STOCK_ADD"    THEN quantity_changed ELSE 0 END), 0) AS units_added,
                COALESCE(SUM(CASE WHEN action_type="STOCK_REMOVE" THEN ABS(quantity_changed) ELSE 0 END), 0) AS units_removed
             FROM inventory_movements
             WHERE created_at BETWEEN ? AND ?'
        );
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row;
    }
}
