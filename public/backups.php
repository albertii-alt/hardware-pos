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

$backups = $service->listBackups();

require_once APP_ROOT . '/app/layout.php';
layoutStart('Lumina POS – Backups');
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
</script>
<?php layoutEnd(); ?>
