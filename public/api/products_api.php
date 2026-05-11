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

switch ($action) {

    case 'add':
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
                    'Product created via inventory management'
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
        $stock         = (int)($input['stock'] ?? 0);

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

        // Fetch current stock before update
        $st = $conn->prepare('SELECT stock, name FROM products WHERE id=? LIMIT 1');
        $st->bind_param('i', $id); $st->execute();
        $before = $st->get_result()->fetch_assoc(); $st->close();
        $stockBefore = $before ? (int)$before['stock'] : 0;

        $category   = $input['category']       ?? '';
        $unit       = $input['unit']            ?? '';
        $cost_price = (float)($input['cost_price'] ?? 0);
        $min_stock  = (int)($input['min_stock_alert'] ?? 0);

        $st = $conn->prepare(
            'UPDATE products SET sku=?,name=?,category=?,unit=?,cost_price=?,selling_price=?,stock=?,min_stock_alert=? WHERE id=?'
        );
        $st->bind_param('ssssddiii', $sku, $name, $category, $unit, $cost_price, $selling_price, $stock, $min_stock, $id);
        $ok = $st->execute(); $err = $st->error;
        $st->close();

        if ($ok) {
            try {
                $svc = new InventoryMovementService($conn);
                $svc->logMovement(
                    $id, $name, 'PRODUCT_UPDATED',
                    $stockBefore, $stock - $stockBefore, $stock,
                    $sessionUserId, $sessionUsername,
                    'Product details updated'
                );
            } catch (Exception $e) { error_log($e->getMessage()); }
        }
        $conn->close();
        jsonOut($ok ? ['success'=>true] : ['success'=>false,'error'=>$err]);

    case 'adjust_stock':
        $id  = (int)($input['id']  ?? 0);
        $qty = (int)($input['qty'] ?? 0);
        if (!$id)  jsonOut(['success'=>false,'error'=>'Invalid ID.']);
        if (!$qty) jsonOut(['success'=>false,'error'=>'Quantity cannot be zero.']);

        $conn = getConnection();
        $st = $conn->prepare('SELECT stock, name FROM products WHERE id=? AND deleted=0 AND deleted_at IS NULL LIMIT 1');
        $st->bind_param('i', $id); $st->execute();
        $res = $st->get_result()->fetch_assoc(); $st->close();
        if (!$res) { $conn->close(); jsonOut(['success'=>false,'error'=>'Product not found.']); }

        $stockBefore = (int)$res['stock'];
        $newStock    = $stockBefore + $qty;
        if ($newStock < 0) { $conn->close(); jsonOut(['success'=>false,'error'=>'Stock cannot go below zero.']); }

        $st = $conn->prepare('UPDATE products SET stock=? WHERE id=?');
        $st->bind_param('ii', $newStock, $id);
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
                    $notes
                );
            } catch (Exception $e) { error_log($e->getMessage()); }
        }
        $conn->close();
        jsonOut($ok ? ['success'=>true,'new_stock'=>$newStock] : ['success'=>false,'error'=>'Update failed.']);

    case 'delete':
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonOut(['success'=>false,'error'=>'Invalid ID.']);

        $conn = getConnection();
        $st = $conn->prepare('SELECT name, stock FROM products WHERE id=? LIMIT 1');
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
                    'Product removed from inventory'
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
