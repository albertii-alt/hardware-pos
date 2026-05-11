<?php
require_once __DIR__ . '/../../app/bootstrap.php';
requireRole('owner');



header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

function jsonOut(array $data): void { echo json_encode($data); exit; }

function validatePassword(string $pw): ?string {
    if (strlen($pw) < 8) return 'Password must be at least 8 characters.';
    return null;
}

$conn = getConnection();

switch ($action) {

    case 'create_user': {
        $username  = trim($input['username']  ?? '');
        $fullName  = trim($input['full_name'] ?? '');
        $role      = $input['role']     ?? 'cashier';
        $password  = $input['password'] ?? '';

        if (!$username || !$password) jsonOut(['success' => false, 'error' => 'Username and password are required.']);
        if (!in_array($role, ['owner', 'cashier'], true)) jsonOut(['success' => false, 'error' => 'Invalid role.']);
        $pwErr = validatePassword($password);
        if ($pwErr) jsonOut(['success' => false, 'error' => $pwErr]);

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('INSERT INTO users (username, full_name, password, role) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $username, $fullName, $hash, $role);
        if (!$stmt->execute()) {
            $err = $conn->errno === 1062 ? 'Username already exists.' : 'Database error.';
            jsonOut(['success' => false, 'error' => $err]);
        }
        $newId = $conn->insert_id;
        $stmt->close();
        logAction('USER_CREATED', $newId, "username={$username} role={$role}");
        jsonOut(['success' => true]);
    }

    case 'update_user': {
        $id       = (int)($input['id'] ?? 0);
        $fullName = trim($input['full_name'] ?? '');
        $role     = $input['role'] ?? 'cashier';

        if (!$id) jsonOut(['success' => false, 'error' => 'Invalid user.']);
        if (!in_array($role, ['owner', 'cashier'], true)) jsonOut(['success' => false, 'error' => 'Invalid role.']);

        $stmt = $conn->prepare('UPDATE users SET full_name = ?, role = ? WHERE id = ?');
        $stmt->bind_param('ssi', $fullName, $role, $id);
        $stmt->execute();
        $stmt->close();
        logAction('USER_UPDATED', $id, "full_name={$fullName} role={$role}");
        jsonOut(['success' => true]);
    }

    case 'reset_password': {
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'error' => 'Invalid user.']);

        // Generate 10-char temporary password
        $tmp  = bin2hex(random_bytes(5));
        $hash = password_hash($tmp, PASSWORD_DEFAULT);

        $stmt = $conn->prepare('UPDATE users SET password = ?, force_password_change = 1 WHERE id = ?');
        $stmt->bind_param('si', $hash, $id);
        $stmt->execute();
        $stmt->close();
        logAction('PASSWORD_RESET', $id);
        jsonOut(['success' => true, 'temp_password' => $tmp]);
    }

    case 'toggle_active': {
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'error' => 'Invalid user.']);

        // Prevent owner from deactivating themselves
        if (session_status() === PHP_SESSION_NONE) session_start();
        if ($id === (int)($_SESSION['user_id'] ?? 0)) {
            jsonOut(['success' => false, 'error' => 'You cannot deactivate your own account.']);
        }

        $stmt = $conn->prepare('SELECT is_active FROM users WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) jsonOut(['success' => false, 'error' => 'User not found.']);

        $newState = $row['is_active'] ? 0 : 1;
        $stmt = $conn->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        $stmt->bind_param('ii', $newState, $id);
        $stmt->execute();
        $stmt->close();

        $action = $newState ? 'USER_ACTIVATED' : 'USER_DEACTIVATED';
        logAction($action, $id);
        jsonOut(['success' => true, 'is_active' => $newState]);
    }

    default:
        jsonOut(['success' => false, 'error' => 'Unknown action.']);
}

$conn->close();
