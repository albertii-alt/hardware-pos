<?php
require_once __DIR__ . '/../app/bootstrap.php';





if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } else {
        $conn = getConnection();
        $stmt = $conn->prepare(
            'SELECT id, username, full_name, password, role, is_active, force_password_change
             FROM users WHERE username = ? LIMIT 1'
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $authenticated = false;
        if ($row) {
            if (password_verify($password, $row['password'])) {
                $authenticated = true;
            } elseif ($password === $row['password']) {
                // Legacy plain-text — rehash silently
                $hash  = password_hash($password, PASSWORD_DEFAULT);
                $upd   = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                $upd->bind_param('si', $hash, $row['id']);
                $upd->execute();
                $upd->close();
                $authenticated = true;
            }
        }

        if (!$authenticated) {
            $error = 'Invalid username or password.';
        } elseif (!(int)$row['is_active']) {
            $error = 'Invalid username or password.'; // generic — don't reveal account status
        } else {
            // Update last_login
            $upd = $conn->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
            $upd->bind_param('i', $row['id']);
            $upd->execute();
            $upd->close();

            $conn->close();

            // Regenerate session to prevent fixation
            session_regenerate_id(true);

            $_SESSION['user_id']               = $row['id'];
            $_SESSION['username']              = $row['username'];
            $_SESSION['full_name']             = $row['full_name'];
            $_SESSION['role']                  = $row['role'];
            $_SESSION['force_password_change'] = (int)$row['force_password_change'];

            logAction('LOGIN_SUCCESS');

            if ((int)$row['force_password_change']) {
                header('Location: change_password.php');
            } else {
                header('Location: index.php');
            }
            exit;
        }

        if (isset($conn) && $conn instanceof mysqli) $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lumina POS – Login</title>
<link href="/lumina-pos/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="/lumina-pos/assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
<style>
  body {
    background: #f4f6f9;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
  }
</style>
</head>
<body>

<div class="card shadow" style="width:360px">
  <div class="card-body p-4">

    <div class="text-center mb-4">
      <i class="bi bi-shop fs-1 text-dark"></i>
      <h4 class="fw-bold mt-1 mb-0">Lumina POS</h4>
      <span class="text-muted small">Sign in to continue</span>
    </div>

    <?php if ($error !== ''): ?>
      <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="mb-3">
        <label class="form-label fw-semibold small">Username</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-person"></i></span>
          <input type="text" name="username" class="form-control"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
            placeholder="Enter username" autofocus required>
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label fw-semibold small">Password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" name="password" id="password" class="form-control"
            placeholder="Enter password" required>
          <button type="button" class="btn btn-outline-secondary"
            onclick="togglePassword()" tabindex="-1">
            <i class="bi bi-eye" id="eye-icon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-dark w-100 fw-semibold">
        <i class="bi bi-box-arrow-in-right"></i> Sign In
      </button>
    </form>

  </div>
</div>

<script>
function togglePassword() {
  const input   = document.getElementById('password');
  const icon    = document.getElementById('eye-icon');
  const visible = input.type === 'text';
  input.type    = visible ? 'password' : 'text';
  icon.className = visible ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>

</body>
</html>
