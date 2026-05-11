<?php

class CustomerRepository
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function findByNameAndAddress(string $name, string $address): ?int
    {
        $stmt = $this->conn->prepare(
            'SELECT id FROM customers WHERE name = ? AND address = ? LIMIT 1'
        );
        $stmt->bind_param('ss', $name, $address);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['id'] : null;
    }

    public function insert(string $name, string $address, string $phone): int
    {
        $stmt = $this->conn->prepare(
            'INSERT INTO customers (name, address, phone) VALUES (?, ?, ?)'
        );
        $stmt->bind_param('sss', $name, $address, $phone);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to create customer: ' . $stmt->error);
        }
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }
}
