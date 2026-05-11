<?php

class ProductRepository
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function findNameById(int $id): ?string
    {
        $stmt = $this->conn->prepare(
            'SELECT name FROM products WHERE id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? $row['name'] : null;
    }

    public function existsActive(int $id): bool
    {
        $stmt = $this->conn->prepare(
            'SELECT id FROM products WHERE id = ? AND deleted = 0 AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row !== null;
    }

    /**
     * Atomically deducts stock. Returns false if stock is insufficient.
     */
    public function deductStock(int $productId, int $quantity): bool
    {
        $stmt = $this->conn->prepare(
            'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?'
        );
        $stmt->bind_param('iii', $quantity, $productId, $quantity);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to update stock for product ID ' . $productId . ': ' . $stmt->error);
        }
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected > 0;
    }

    public function restoreStock(int $productId, int $quantity): int
    {
        $stmt = $this->conn->prepare(
            'UPDATE products SET stock = stock + ? WHERE id = ?'
        );
        $stmt->bind_param('ii', $quantity, $productId);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to restore stock for product ID ' . $productId . ': ' . $stmt->error);
        }
        $stmt->close();
        // Return new stock value
        $st = $this->conn->prepare('SELECT stock FROM products WHERE id = ? LIMIT 1');
        $st->bind_param('i', $productId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ? (int)$row['stock'] : 0;
    }

    public function getAllActive(string $search = ''): array
    {
        if ($search !== '') {
            $like = '%' . $this->conn->real_escape_string($search) . '%';
            $stmt = $this->conn->prepare(
                'SELECT * FROM products
                 WHERE deleted = 0 AND deleted_at IS NULL
                   AND (name LIKE ? OR sku LIKE ? OR category LIKE ?)
                 ORDER BY name'
            );
            $stmt->bind_param('sss', $like, $like, $like);
        } else {
            $stmt = $this->conn->prepare(
                'SELECT * FROM products WHERE deleted = 0 AND deleted_at IS NULL ORDER BY name'
            );
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }
}
