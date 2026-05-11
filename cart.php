<?php

function addToCart(int $productId, string $productName, float $price, int $quantity = 1): void {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['quantity'] += $quantity;
    } else {
        $_SESSION['cart'][$productId] = [
            'name'     => $productName,
            'price'    => $price,
            'quantity' => $quantity,
        ];
    }
}

function removeFromCart(int $productId): void {
    unset($_SESSION['cart'][$productId]);
}

function updateCartQuantity(int $productId, int $quantity): void {
    if ($quantity <= 0) {
        removeFromCart($productId);
        return;
    }
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['quantity'] = $quantity;
    }
}

function getCart(): array {
    return $_SESSION['cart'] ?? [];
}

function getCartSubtotal(): float {
    $subtotal = 0.0;
    foreach (getCart() as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    return $subtotal;
}

function clearCart(): void {
    $_SESSION['cart'] = [];
}

function validateCartStock(array $cart, mysqli $dbConnection): array {
    if (empty($cart)) {
        return ['valid' => true];
    }

    $ids    = implode(',', array_map('intval', array_keys($cart)));
    $result = $dbConnection->query(
        "SELECT id, name, stock FROM products WHERE id IN ({$ids}) AND deleted = 0"
    );

    $stockMap = [];
    while ($row = $result->fetch_assoc()) {
        $stockMap[(int)$row['id']] = ['name' => $row['name'], 'stock' => (int)$row['stock']];
    }
    $result->free();

    $errors = [];
    foreach ($cart as $productId => $item) {
        $id        = (int)$productId;
        $requested = $item['quantity'];

        if (!isset($stockMap[$id])) {
            $errors[] = ['productId' => $id, 'name' => $item['name'], 'requested' => $requested, 'available' => 0];
            continue;
        }
        $available = $stockMap[$id]['stock'];
        if ($requested > $available) {
            $errors[] = ['productId' => $id, 'name' => $stockMap[$id]['name'], 'requested' => $requested, 'available' => $available];
        }
    }

    return empty($errors) ? ['valid' => true] : ['valid' => false, 'errors' => $errors];
}
