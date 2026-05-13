<?php
require_once __DIR__ . '/../../app/bootstrap.php';
requireRole('owner');


require_once __DIR__ . '/../../app/Services/InventoryMovementService.php';

header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_POST['action'] ?? '';

// Session context for movement logging
$sessionUserId   = isset($_SESSION['user_id'])  ? (int)$_SESSION['user_id']  : null;
$sessionUsername = $_SESSION['username'] ?? null;

function jsonOut(array $data): void { echo json_encode($data); exit; }

$ALLOWED_UNITS = ['pcs','box','pack','bundle','set','roll','kg','g','ton','meter','ft','inch','cubic','liter','bag','sheet','tube','stick','bar'];

function validateUnit(string $unit, array $allowed): bool {
    return in_array($unit, $allowed, true);
}

switch ($action) {

    case 'add':
        $invUnit    = trim($input['inventory_unit'] ?? 'pcs');
        $allowsDec  = isset($input['allows_decimal']) ? (int)$input['allows_decimal'] : 0;
        $minSellQty = isset($input['min_sell_quantity'])     ? (float)$input['min_sell_quantity']     : 1.0;
        $qtyStep    = isset($input['quantity_step'])         ? (float)$input['quantity_step']         : 1.0;
        $defSellQty = isset($input['default_sell_quantity']) ? (float)$input['default_sell_quantity'] : 1.0;
        if (!validateUnit($invUnit, $ALLOWED_UNITS)) jsonOut(['success'=>false,'error'=>'Invalid inventory unit.']);
        if ($minSellQty <= 0)  jsonOut(['success'=>false,'error'=>'Min sell quantity must be greater than 0.']);
        if ($qtyStep <= 0)     jsonOut(['success'=>false,'error'=>'Quantity step must be greater than 0.']);
        $input['inventory_unit']        = $invUnit;
        $input['allows_decimal']        = $allowsDec;
        $input['min_sell_quantity']     = $minSellQty;
        $input['quantity_step']         = $qtyStep;
        $input['default_sell_quantity'] = $defSellQty;
        $result = addProduct($input);
        if ($result['success']) {
            try {
                $conn = getConnection();
                $svc  = new InventoryMovementService($conn);
                $stock = (int)($input['stock'] ?? 0);
                $svc->logMovement(
                    $result['id'],
                    trim($input['name'] ?? ''),
                    'PRODUCT_CREATED',
                    0, $stock, $stock,
                    $sessionUserId, $sessionUsername,
                    'Product created via inventory management',
                    $invUnit
                );
                $conn->close();
            } catch (Exception $e) { error_log($e->getMessage()); }
        }
        jsonOut($result);

    case 'edit':
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'error' => 'Invalid ID.']);

        $name          = trim($input['name'] ?? '');
        $selling_price = (float)($input['selling_price'] ?? 0);
        $stock         = round((float)($input['stock'] ?? 0), 3);

        if (!$name)            jsonOut(['success' => false, 'error' => 'Product name is required.']);
        if ($selling_price<=0) jsonOut(['success' => false, 'error' => 'Selling price must be greater than 0.']);
        if ($stock < 0)        jsonOut(['success' => false, 'error' => 'Stock cannot be negative.']);

        $conn = getConnection();
        $sku  = trim($input['sku'] ?? '');

        // Check SKU uniqueness (exclude self)
        if ($sku !== '') {
            $st = $conn->prepare('SELECT id FROM products WHERE sku = ? AND id != ? LIMIT 1');
            $st->bind_param('si', $sku, $id);
            $st->execute(); $st->store_result();
            if ($st->num_rows > 0) { $st->close(); $conn->close(); jsonOut(['success'=>false,'error'=>"SKU '{$sku}' already exists."]); }
            $st->close();
        }

        // Fetch current stock AND unit before update
        $st = $conn->prepare('SELECT stock, name, inventory_unit FROM products WHERE id=? LIMIT 1');
        $st->bind_param('i', $id); $st->execute();
        $before = $st->get_result()->fetch_assoc(); $st->close();
        $stockBefore   = $before ? (float)$before['stock'] : 0.0;
        $unitBefore    = $before ? ($before['inventory_unit'] ?? 'pcs') : 'pcs';

        $category      = $input['category'] ?? '';
        $unit          = $input['unit']      ?? '';
        $invUnit       = trim($input['inventory_unit'] ?? 'pcs');
        $allowsDec     = isset($input['allows_decimal']) ? (int)$input['allows_decimal'] : 0;
        $minSellQty    = isset($input['min_sell_quantity'])     ? (float)$input['min_sell_quantity']     : 1.0;
        $quantityStep  = isset($input['quantity_step'])         ? (float)$input['quantity_step']         : 1.0;
        $defaultSellQty= isset($input['default_sell_quantity']) ? (float)$input['default_sell_quantity'] : 1.0;
        $cost_price    = (float)($input['cost_price'] ?? 0);
        $min_stock     = (int)($input['min_stock_alert'] ?? 0);

        if (!validateUnit($invUnit, $ALLOWED_UNITS)) jsonOut(['success'=>false,'error'=>'Invalid inventory unit.']);
        if ($minSellQty <= 0)   jsonOut(['success'=>false,'error'=>'Min sell quantity must be greater than 0.']);
        if ($quantityStep <= 0) jsonOut(['success'=>false,'error'=>'Quantity step must be greater than 0.']);

        $st = $conn->prepare(
            'UPDATE products SET sku=?,name=?,category=?,unit=?,inventory_unit=?,allows_decimal=?,min_sell_quantity=?,quantity_step=?,default_sell_quantity=?,cost_price=?,selling_price=?,stock=?,min_stock_alert=? WHERE id=?'
        );
        $st->bind_param('sssssidddddiii', $sku, $name, $category, $unit, $invUnit, $allowsDec, $minSellQty, $quantityStep, $defaultSellQty, $cost_price, $selling_price, $stock, $min_stock, $id);
        $ok = $st->execute(); $err = $st->error;
        $st->close();

        if ($ok) {
            try {
                $svc = new InventoryMovementService($conn);
                $svc->logMovement(
                    $id, $name, 'PRODUCT_UPDATED',
                    $stockBefore, $stock - $stockBefore, $stock,
                    $sessionUserId, $sessionUsername,
                    'Product details updated',
                    $unitBefore,
                    $invUnit
                );
            } catch (Exception $e) { error_log($e->getMessage()); }
        }
        $conn->close();
        jsonOut($ok ? ['success'=>true] : ['success'=>false,'error'=>$err]);

    case 'adjust_stock':
        $id  = (int)($input['id']  ?? 0);
        $raw = $input['qty'] ?? 0;
        if (!$id) jsonOut(['success'=>false,'error'=>'Invalid ID.']);

        $conn = getConnection();
        $st = $conn->prepare('SELECT stock, name, allows_decimal, min_sell_quantity, inventory_unit FROM products WHERE id=? AND deleted=0 AND deleted_at IS NULL LIMIT 1');
        $st->bind_param('i', $id); $st->execute();
        $res = $st->get_result()->fetch_assoc(); $st->close();
        if (!$res) { $conn->close(); jsonOut(['success'=>false,'error'=>'Product not found.']); }

        $allows  = (bool)$res['allows_decimal'];
        $minSell = (float)$res['min_sell_quantity'];
        $qty     = normalizeQuantity($raw);
        if ($qty === null) { $conn->close(); jsonOut(['success'=>false,'error'=>'Invalid quantity.']); }

        // For removals, validate the absolute value
        $absQty = abs($qty);
        $err = validateQuantityPrecision($absQty, $allows, $minSell);
        if ($err) { $conn->close(); jsonOut(['success'=>false,'error'=>$err]); }

        // Determine direction from original input sign
        $qty = (float)$raw < 0 ? -$absQty : $absQty;

        $stockBefore = (float)$res['stock'];
        $newStock    = round($stockBefore + $qty, 3);
        if ($newStock < 0) { $conn->close(); jsonOut(['success'=>false,'error'=>'Stock cannot go below zero.']); }

        $st = $conn->prepare('UPDATE products SET stock=? WHERE id=?');
        $st->bind_param('di', $newStock, $id);
        $ok = $st->execute(); $st->close();

        if ($ok) {
            try {
                $svc        = new InventoryMovementService($conn);
                $actionType = $qty > 0 ? 'STOCK_ADD' : 'STOCK_REMOVE';
                $notes      = ($qty > 0 ? 'Added' : 'Removed') . ' ' . abs($qty) . ' unit(s) via stock adjustment';
                $svc->logMovement(
                    $id, $res['name'], $actionType,
                    $stockBefore, $qty, $newStock,
                    $sessionUserId, $sessionUsername,
                    $notes,
                    $res['inventory_unit'] ?? 'pcs'
                );
            } catch (Exception $e) { error_log($e->getMessage()); }
        }
        $conn->close();
        jsonOut($ok ? ['success'=>true,'new_stock'=>$newStock] : ['success'=>false,'error'=>'Update failed.']);

    case 'delete':
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonOut(['success'=>false,'error'=>'Invalid ID.']);

        $conn = getConnection();
        $st = $conn->prepare('SELECT name, stock, inventory_unit FROM products WHERE id=? LIMIT 1');
        $st->bind_param('i', $id); $st->execute();
        $prod = $st->get_result()->fetch_assoc(); $st->close();

        $st = $conn->prepare('UPDATE products SET deleted=1, deleted_at=NOW() WHERE id=?');
        $st->bind_param('i', $id);
        $ok = $st->execute(); $st->close();

        if ($ok && $prod) {
            try {
                $svc = new InventoryMovementService($conn);
                $svc->logMovement(
                    $id, $prod['name'], 'PRODUCT_DELETED',
                    (int)$prod['stock'], 0, (int)$prod['stock'],
                    $sessionUserId, $sessionUsername,
                    'Product removed from inventory',
                    $prod['inventory_unit'] ?? 'pcs'
                );
            } catch (Exception $e) { error_log($e->getMessage()); }
        }
        $conn->close();
        jsonOut($ok ? ['success'=>true] : ['success'=>false,'error'=>'Delete failed.']);

    case 'import_csv':
        if (empty($_FILES['csv']['tmp_name'])) jsonOut(['success'=>false,'error'=>'No file uploaded.']);
        $result = importProductsFromCSV($_FILES['csv']['tmp_name']);
        jsonOut($result);

    default:
        jsonOut(['success'=>false,'error'=>'Unknown action.']);
}
