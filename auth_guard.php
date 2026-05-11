<?php

function requireLogin(): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    // Force password change intercept
    if (!empty($_SESSION['force_password_change'])) {
        $current = basename($_SERVER['PHP_SELF']);
        if ($current !== 'change_password.php' && $current !== 'logout.php') {
            header('Location: change_password.php');
            exit;
        }
    }
}

function requireRole(string $role): void
{
    requireLogin();
    if (($_SESSION['role'] ?? '') !== $role) {
        header('Location: index.php?error=unauthorized');
        exit;
    }
}

// Allows both owner and cashier — just requires a valid login
function requireAnyRole(): void
{
    requireLogin();
    if (!in_array($_SESSION['role'] ?? '', ['owner', 'cashier'], true)) {
        header('Location: login.php');
        exit;
    }
}
