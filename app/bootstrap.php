<?php
/**
 * Application bootstrap.
 * Every public page requires this ONE file.
 * Defines APP_ROOT for absolute path resolution.
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/app/helpers/error_handler.php';
require_once APP_ROOT . '/app/helpers/quantity_helper.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/Auth.php';
require_once APP_ROOT . '/app/core/Audit.php';
require_once APP_ROOT . '/app/core/Cart.php';
require_once APP_ROOT . '/app/core/ProductHelper.php';

// Start session once here so every page has access to $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once APP_ROOT . '/app/core/Cart.php';
require_once APP_ROOT . '/app/core/ProductHelper.php';
