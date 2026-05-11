<?php

require_once __DIR__ . '/db.php';

function logAction(string $action, ?int $referenceId = null, ?string $details = null): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();

    $userId   = isset($_SESSION['user_id'])  ? (int)$_SESSION['user_id']      : null;
    $username = isset($_SESSION['username']) ? $_SESSION['username']           : null;
    $role     = isset($_SESSION['role'])     ? $_SESSION['role']               : null;

    try {
        $conn = getConnection();
        $stmt = $conn->prepare(
            'INSERT INTO audit_logs (user_id, username, role, action, reference_id, details)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('isssss', $userId, $username, $role, $action, $referenceId, $details);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        error_log('Audit log failed: ' . $e->getMessage());
    }
}
