<?php
require_once __DIR__ . '/../app/bootstrap.php';
requireLogin();

$forced  = !empty($_SESSION['force_password_change']);
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$current || !$new || !$confirm) {
        $error = 'All fields are required.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } elseif ($new === $current) {
        $error = 'New password must be different from your current password.';
    } else {
        $conn = getConnection();
        $uid  = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $valid = false;
        if ($row) {
            if (password_verify($current, $row['password'])) {
                $valid = true;
            } elseif ($current === $row['password']) {
                // Legacy plain-text fallback
                $valid = true;
            }
        }

        if (!$valid) {
            $error = 'Current password is incorrect.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET password = ?, force_password_change = 0 WHERE id = ?');
            $stmt->bind_param('si', $hash, $uid);
            $stmt->execute();
            $stmt->close();
            $conn->close();

            $_SESSION['force_password_change'] = 0;
            logAction('PASSWORD_CHANGED');
            header('Location: index.php');
            exit;
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hardware POS – Change Password</title>
<link href="/lumina-pos/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="/lumina-pos/assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
<style>
  body { background:#f4f6f9; min-height:100vh; display:flex; align-items:center; justify-content:center; }
</style>
</head>
<body>
<div class="card shadow" style="width:400px">
  <div class="card-body p-4">

    <div class="text-center mb-4">
      <i class="bi bi-shield-lock fs-1 <?= $forced ? 'text-warning' : 'text-primary' ?>"></i>
      <h5 class="fw-bold mt-2 mb-1">
        <?= $forced ? 'Set New Password' : 'Change Password' ?>
      </h5>
      <?php if ($forced): ?>
      <p class="text-muted small mb-0">
        Your password was reset by an administrator.<br>
        Enter the <strong>temporary password</strong> you were given, then choose a new one.
      </p>
      <?php else: ?>
      <p class="text-muted small mb-0">
        Enter your <strong>current password</strong>, then choose a new one.
      </p>
      <?php endif; ?>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="mb-3">
        <label class="form-label form-label-sm fw-semibold">
          <?= $forced ? 'Temporary Password' : 'Current Password' ?>
        </label>
        <input type="password" name="current_password"
               class="form-control form-control-sm"
               placeholder="<?= $forced ? 'Enter the temporary password given to you' : 'Enter your current password' ?>"
               required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label form-label-sm fw-semibold">
          New Password <span class="text-muted fw-normal">(min. 8 characters)</span>
        </label>
        <input type="password" name="new_password"
               class="form-control form-control-sm"
               placeholder="Choose a strong new password"
               required>
      </div>
      <div class="mb-4">
        <label class="form-label form-label-sm fw-semibold">Confirm New Password</label>
        <input type="password" name="confirm_password"
               class="form-control form-control-sm"
               placeholder="Re-enter your new password"
               required>
      </div>
      <button type="submit" class="btn btn-primary w-100 fw-semibold">
        <i class="bi bi-check-circle me-1"></i>
        <?= $forced ? 'Set New Password' : 'Update Password' ?>
      </button>
      <?php if (!$forced): ?>
      <a href="index.php" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
      <?php endif; ?>
    </form>

  </div>
</div>
</body>
</html>
