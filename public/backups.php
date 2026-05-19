<?php
require_once __DIR__ . '/../app/bootstrap.php';
requireRole('owner');
require_once APP_ROOT . '/app/Services/BackupService.php';


$service = new BackupService();
$message = null;
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $result = $service->createBackup();
    if ($result['success']) {
        logAction('BACKUP_CREATED', null, $result['filename']);
        $message = 'Backup created: <strong>' . htmlspecialchars($result['filename']) . '</strong>'
                 . ' (' . $service->formatBytes($result['size']) . ')';
    } else {
        $msgType = 'danger';
        $message = 'Backup failed: ' . htmlspecialchars($result['error']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'demo_reset') {
    $currentUserId = (int)$_SESSION['user_id'];
    $conn = getConnection();
    $conn->begin_transaction();
    try {
        $conn->query('SET FOREIGN_KEY_CHECKS = 0');
        $conn->query('TRUNCATE TABLE order_items');
        $conn->query('TRUNCATE TABLE orders');
        $conn->query('TRUNCATE TABLE daily_closures');
        $conn->query('TRUNCATE TABLE audit_logs');
        $conn->query('TRUNCATE TABLE inventory_movements');
        $conn->query('TRUNCATE TABLE products');
        $stmt = $conn->prepare('DELETE FROM users WHERE id != ?');
        $stmt->bind_param('i', $currentUserId);
        $stmt->execute();
        $stmt->close();
        $conn->query('SET FOREIGN_KEY_CHECKS = 1');
        $conn->commit();
        logAction('DEMO_RESET', null, 'Full demo reset performed.');
        $message = 'Demo reset complete. All data cleared except your account.';
    } catch (Throwable $e) {
        $conn->rollback();
        $conn->query('SET FOREIGN_KEY_CHECKS = 1');
        $msgType = 'danger';
        $message = 'Reset failed: ' . $e->getMessage();
    }
    $conn->close();
}

$backups = $service->listBackups();

require_once APP_ROOT . '/app/layout.php';
layoutStart('Hardware POS – Backups');
?>
<?php layoutHeader('Database Backups', 'bi-database-down'); ?>
<div class="container-fluid px-4">

  <!-- Sticky toolbar -->
  <div class="d-flex align-items-center gap-3 mb-4" style="position:sticky;top:53px;z-index:90;background:#f4f6f9;padding:.75rem 0 .5rem">
    <form method="post" onsubmit="return confirmBackup()">
      <input type="hidden" name="action" value="create">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-database-add me-1"></i> Create Backup Now
      </button>
    </form>
    <span class="text-muted small">Backups stored in <code>storage/backups/</code></span>
    <div class="ms-auto">
      <form method="post" onsubmit="return confirmDemoReset()">
        <input type="hidden" name="action" value="demo_reset">
        <button type="submit" class="btn btn-outline-danger btn-sm">
          <i class="bi bi-trash3 me-1"></i> Demo Reset
        </button>
      </form>
    </div>
  </div>

  <?php if ($message): ?>
  <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
    <?= $message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <!-- Summary card -->
  <div class="row g-3 mb-4">
    <div class="col-sm-4 col-md-3">
      <div class="card shadow-sm stat-card total h-100">
        <div class="card-body py-3">
          <div class="text-muted small mb-1"><i class="bi bi-archive"></i> Total Backups</div>
          <div class="fs-4 fw-bold"><?= count($backups) ?></div>
        </div>
      </div>
    </div>
    <?php if (!empty($backups)): ?>
    <div class="col-sm-4 col-md-3">
      <div class="card shadow-sm stat-card login h-100">
        <div class="card-body py-3">
          <div class="text-muted small mb-1"><i class="bi bi-clock"></i> Latest Backup</div>
          <div class="fw-semibold" style="font-size:.9rem"><?= htmlspecialchars($backups[0]['created_at']) ?></div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Backup history -->
  <div class="card shadow-sm border-0">
    <div class="card-header bg-white fw-semibold">
      <i class="bi bi-clock-history me-1"></i> Backup History
    </div>
    <?php if (empty($backups)): ?>
    <div class="card-body text-center text-muted py-5">
      <i class="bi bi-database-slash fs-1 d-block mb-2"></i>
      No backups yet. Click <strong>Create Backup Now</strong> to get started.
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Filename</th>
            <th>Created At</th>
            <th class="text-end">Size</th>
            <th class="text-center">Download</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($backups as $i => $b): ?>
          <tr>
            <td class="text-muted"><?= $i + 1 ?></td>
            <td><code style="font-size:.8rem"><?= htmlspecialchars($b['filename']) ?></code></td>
            <td><?= htmlspecialchars($b['created_at']) ?></td>
            <td class="text-end"><?= htmlspecialchars($b['size_human']) ?></td>
            <td class="text-center">
              <a href="download_backup.php?file=<?= urlencode($b['filename']) ?>"
                 class="btn btn-sm btn-outline-primary">
                <i class="bi bi-download"></i> Download
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div>

<script>
function confirmBackup() {
  return confirm('Create a new database backup now?');
}
function confirmDemoReset() {
  return confirm('This will delete ALL orders, products, closures, audit logs, and other user accounts.\n\nYour account will be kept.\n\nThis cannot be undone. Continue?');
}
</script>
<?php layoutEnd(); ?>
