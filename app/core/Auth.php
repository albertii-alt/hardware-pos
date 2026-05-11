<?php

class Auth
{
    public static function requireLogin(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /lumina-pos/public/login.php');
            exit;
        }
        if (!empty($_SESSION['force_password_change'])) {
            $current = basename($_SERVER['PHP_SELF']);
            if ($current !== 'change_password.php' && $current !== 'logout.php') {
                header('Location: /lumina-pos/public/change_password.php');
                exit;
            }
        }
    }

    public static function requireRole(string $role): void
    {
        self::requireLogin();
        if (($_SESSION['role'] ?? '') !== $role) {
            header('Location: /lumina-pos/public/index.php?error=unauthorized');
            exit;
        }
    }

    public static function requireAnyRole(): void
    {
        self::requireLogin();
        if (!in_array($_SESSION['role'] ?? '', ['owner', 'cashier'], true)) {
            header('Location: /lumina-pos/public/login.php');
            exit;
        }
    }
}

// Global aliases so existing function calls keep working
function requireLogin(): void  { Auth::requireLogin(); }
function requireRole(string $role): void { Auth::requireRole($role); }
function requireAnyRole(): void { Auth::requireAnyRole(); }
