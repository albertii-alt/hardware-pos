<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$username = $_SESSION['username'] ?? 'User';
$role     = $_SESSION['role']     ?? 'cashier';
$isOwner  = $role === 'owner';

function isActive(string $page): string {
    return basename($_SERVER['PHP_SELF']) === $page ? 'active' : '';
}

function navLink(string $href, string $icon, string $label, string $active = ''): void {
    $cls = 'sidebar-link' . ($active ? ' active' : '');
    echo "<a href=\"{$href}\" class=\"{$cls}\" title=\"{$label}\">"
       . "<i class=\"bi {$icon} sidebar-icon\"></i>"
       . "<span class=\"sidebar-label\">{$label}</span>"
       . "</a>";
}
?>
<!-- Overlay (mobile) -->
<div id="sidebar-overlay"></div>

<aside id="sidebar">

  <!-- Brand -->
  <div class="sidebar-brand">
    <i class="bi bi-shop-window brand-icon"></i>
    <span class="sidebar-label fw-bold">Lumina POS</span>
  </div>

  <!-- User -->
  <div class="sidebar-user">
    <i class="bi bi-person-circle sidebar-icon"></i>
    <span class="sidebar-label">
      <?= htmlspecialchars($username) ?>
      <span class="badge <?= $isOwner ? 'bg-warning text-dark' : 'bg-secondary' ?> ms-1 d-inline">
        <?= htmlspecialchars($role) ?>
      </span>
    </span>
  </div>

  <nav class="sidebar-nav">

    <!-- MAIN POS — visible to all -->
    <div class="nav-section-label">Main POS</div>
    <?php navLink('index.php',      'bi-shop',  'POS Terminal', isActive('index.php')) ?>
    <?php navLink('deliveries.php', 'bi-truck', 'Deliveries',   isActive('deliveries.php')) ?>

    <?php if ($isOwner): ?>
    <!-- MANAGEMENT — owner only -->
    <div class="nav-section-label">Management</div>
    <?php navLink('dashboard.php',      'bi-speedometer2',      'Dashboard',       isActive('dashboard.php')) ?>
    <?php navLink('report.php',         'bi-bar-chart-line',    'Reports',         isActive('report.php')) ?>
    <?php navLink('closing_report.php', 'bi-file-earmark-text', 'Closing Report',  isActive('closing_report.php')) ?>
    <?php navLink('sales_history.php',  'bi-receipt-cutoff',    'Sales History',   isActive('sales_history.php')) ?>

    <!-- INVENTORY — owner only -->
    <div class="nav-section-label">Inventory</div>
    <?php navLink('products.php',           'bi-box-seam',              'Products',    isActive('products.php')) ?>
    <?php navLink('inventory_history.php',  'bi-clock-history',         'Inv. History',isActive('inventory_history.php')) ?>
    <?php navLink('low_stock.php', 'bi-exclamation-triangle', 'Low Stock', isActive('low_stock.php')) ?>

    <!-- ADMIN — owner only -->
    <div class="nav-section-label">Admin</div>
    <?php navLink('users.php',      'bi-people',        'Users',      isActive('users.php')) ?>
    <?php navLink('audit_logs.php', 'bi-shield-check',  'Audit Logs', isActive('audit_logs.php')) ?>
    <?php navLink('backups.php',    'bi-database-down', 'Backups',    isActive('backups.php')) ?>
    <?php endif; ?>

    <!-- ACCOUNT — visible to all -->
    <div class="nav-section-label">Account</div>
    <?php navLink('logout.php', 'bi-box-arrow-right', 'Logout') ?>

  </nav>
</aside>
