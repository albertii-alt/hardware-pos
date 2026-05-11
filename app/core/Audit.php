<?php

class Audit
{
    public static function log(string $action, ?int $referenceId = null, ?string $details = null): void
    {
        $userId   = isset($_SESSION['user_id'])  ? (int)$_SESSION['user_id']  : null;
        $username = $_SESSION['username'] ?? null;
        $role     = $_SESSION['role']     ?? null;

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
}

// Global alias so existing logAction() calls keep working
function logAction(string $action, ?int $referenceId = null, ?string $details = null): void
{
    Audit::log($action, $referenceId, $details);
}
