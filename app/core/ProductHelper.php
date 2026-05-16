<?php

function addProduct(array $data): array {
    if (empty(trim($data['name'] ?? ''))) {
        return ['success' => false, 'error' => 'Product name is required.'];
    }
    if (($data['selling_price'] ?? 0) <= 0) {
        return ['success' => false, 'error' => 'Selling price must be greater than 0.'];
    }
    if ((float)($data['stock'] ?? -1) < 0) {
        return ['success' => false, 'error' => 'Stock cannot be negative.'];
    }

    $conn = getConnection();
    $sku  = trim($data['sku'] ?? '');

    if ($sku !== '') {
        $stmt = $conn->prepare('SELECT id FROM products WHERE sku = ? LIMIT 1');
        $stmt->bind_param('s', $sku);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close(); $conn->close();
            return ['success' => false, 'error' => "SKU '{$sku}' already exists."];
        }
        $stmt->close();
    }

    $name            = trim($data['name']);
    $category        = $data['category']        ?? '';
    $unit            = $data['unit']             ?? '';
    $inventory_unit       = $data['inventory_unit']        ?? 'pcs';
    $allows_decimal       = isset($data['allows_decimal'])         ? (int)$data['allows_decimal']         : 0;
    $min_sell_qty         = isset($data['min_sell_quantity'])       ? (float)$data['min_sell_quantity']     : 1.0;
    $quantity_step        = isset($data['quantity_step'])           ? (float)$data['quantity_step']         : 1.0;
    $default_sell_qty     = isset($data['default_sell_quantity'])   ? (float)$data['default_sell_quantity'] : 1.0;
    $cost_price           = $data['cost_price']       ?? 0;
    $selling_price        = $data['selling_price'];
    $stock                = $data['stock'];
    $min_stock_alert      = $data['min_stock_alert']  ?? 0;

    $stmt = $conn->prepare(
        'INSERT INTO products (sku, name, category, unit, inventory_unit, allows_decimal, min_sell_quantity, quantity_step, default_sell_quantity, cost_price, selling_price, stock, min_stock_alert)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('sssssidddddii', $sku, $name, $category, $unit, $inventory_unit, $allows_decimal, $min_sell_qty, $quantity_step, $default_sell_qty, $cost_price, $selling_price, $stock, $min_stock_alert);

    if (!$stmt->execute()) {
        $error = $stmt->error; $stmt->close(); $conn->close();
        return ['success' => false, 'error' => $error];
    }

    $id = $stmt->insert_id;
    $stmt->close(); $conn->close();
    return ['success' => true, 'id' => $id];
}

function getAllProducts(string $search = ''): array {
    $conn = getConnection();

    if ($search !== '') {
        $like = '%' . $conn->real_escape_string($search) . '%';
        $stmt = $conn->prepare(
            'SELECT * FROM products
             WHERE deleted = 0 AND deleted_at IS NULL
               AND (name LIKE ? OR sku LIKE ? OR category LIKE ?)
             ORDER BY name'
        );
        $stmt->bind_param('sss', $like, $like, $like);
    } else {
        $stmt = $conn->prepare(
            'SELECT * FROM products WHERE deleted = 0 AND deleted_at IS NULL ORDER BY name'
        );
    }

    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close(); $conn->close();
    return $products;
}

function importProductsFromCSV(string $filePath): array {
    $required = ['sku', 'name', 'category', 'unit', 'cost_price', 'selling_price', 'stock', 'min_stock_alert'];

    if (!is_readable($filePath)) {
        return ['success' => false, 'error' => 'File not found or not readable.', 'row' => 0];
    }

    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        return ['success' => false, 'error' => 'Failed to open CSV file.', 'row' => 0];
    }

    $headers = array_map('trim', fgetcsv($handle));
    $missing = array_diff($required, $headers);
    if (!empty($missing)) {
        fclose($handle);
        return ['success' => false, 'error' => 'Missing columns: ' . implode(', ', $missing), 'row' => 0];
    }

    $conn = getConnection();
    $conn->begin_transaction();

    $insertStmt = $conn->prepare(
        'INSERT INTO products (sku, name, category, unit, inventory_unit, allows_decimal, min_sell_quantity, quantity_step, default_sell_quantity, cost_price, selling_price, stock, min_stock_alert)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $updateStmt = $conn->prepare(
        'UPDATE products
         SET name=?, category=?, unit=?, inventory_unit=?, allows_decimal=?, min_sell_quantity=?, quantity_step=?, default_sell_quantity=?, cost_price=?, selling_price=?, stock=?, min_stock_alert=?
         WHERE sku=?'
    );
    $checkStmt = $conn->prepare('SELECT id FROM products WHERE sku = ? LIMIT 1');

    $inserted = 0;
    $updated  = 0;
    $rowNum   = 1;

    while (($raw = fgetcsv($handle)) !== false) {
        $rowNum++;
        if (count($raw) === 1 && trim($raw[0]) === '') continue;

        $row = array_combine($headers, array_map('trim', $raw));

        if (empty($row['name'])) {
            $conn->rollback(); fclose($handle);
            return ['success' => false, 'error' => 'Product name is required.', 'row' => $rowNum];
        }
        if ((float)$row['selling_price'] <= 0) {
            $conn->rollback(); fclose($handle);
            return ['success' => false, 'error' => 'Selling price must be greater than 0.', 'row' => $rowNum];
        }
        if ((int)$row['stock'] < 0) {
            $conn->rollback(); fclose($handle);
            return ['success' => false, 'error' => 'Stock cannot be negative.', 'row' => $rowNum];
        }

        $sku           = $row['sku'];
        $name          = $row['name'];
        $category      = $row['category'];
        $unit          = $row['unit'];
        $inv_unit      = $row['inventory_unit'] ?? $unit ?: 'pcs';
        $measured      = in_array($inv_unit, ['kg','g','ton','sack','meter','ft','inch','cubic','m2','m3','liter','gallon','roll'], true);
        $allows_dec    = $measured ? 1 : 0;
        $min_sell      = $measured ? 0.001 : 1.0;
        $qty_step      = $measured ? (match($inv_unit) {
            'kg','g','ton','sack' => 0.100,
            'meter','ft','inch','roll' => 0.500,
            'cubic','m2','m3','liter','gallon' => 0.250,
            default => 0.100
        }) : 1.0;
        $def_sell      = $measured ? (match($inv_unit) {
            'cubic','m3','liter','gallon' => 0.500,
            default => 1.0
        }) : 1.0;
        $cost_price    = (float)$row['cost_price'];
        $selling_price = (float)$row['selling_price'];
        $stock         = (float)$row['stock'];
        $min_stock     = (int)$row['min_stock_alert'];

        $checkStmt->bind_param('s', $sku);
        $checkStmt->execute();
        $checkStmt->store_result();
        $exists = $checkStmt->num_rows > 0;
        $checkStmt->free_result();

        if ($exists) {
            $updateStmt->bind_param('sssidddddiis', $name, $category, $unit, $inv_unit, $allows_dec, $min_sell, $qty_step, $def_sell, $cost_price, $selling_price, $stock, $min_stock, $sku);
            if (!$updateStmt->execute()) {
                $error = $updateStmt->error; $conn->rollback(); fclose($handle);
                return ['success' => false, 'error' => $error, 'row' => $rowNum];
            }
            $updated++;
        } else {
            $insertStmt->bind_param('sssssidddddii', $sku, $name, $category, $unit, $inv_unit, $allows_dec, $min_sell, $qty_step, $def_sell, $cost_price, $selling_price, $stock, $min_stock);
            if (!$insertStmt->execute()) {
                $error = $insertStmt->error; $conn->rollback(); fclose($handle);
                return ['success' => false, 'error' => $error, 'row' => $rowNum];
            }
            $inserted++;
        }
    }

    fclose($handle);
    $checkStmt->close(); $insertStmt->close(); $updateStmt->close();
    $conn->commit(); $conn->close();
    return ['success' => true, 'inserted' => $inserted, 'updated' => $updated];
}
