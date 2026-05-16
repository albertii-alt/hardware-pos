<?php
require_once __DIR__ . '/../app/bootstrap.php';
requireRole('owner');


$conn  = getConnection();
$users = $conn->query(
    'SELECT id, username, full_name, role, is_active, last_login, created_at FROM users ORDER BY created_at DESC'
)->fetch_all(MYSQLI_ASSOC);
$conn->close();

require_once APP_ROOT . '/app/layout.php';
layoutStart('Hardware POS – Users');
?>
<?php layoutHeader('User Management', 'bi-people'); ?>
<div class="container-fluid px-4">

  <!-- Toolbar -->
  <div class="d-flex align-items-center gap-2 mb-4" style="position:sticky;top:53px;z-index:90;background:#f4f6f9;padding:.75rem 0 .5rem">
    <div class="input-group input-group-sm" style="max-width:260px">
      <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
      <input type="text" id="user-search" class="form-control border-start-0 ps-0" placeholder="Search username or name…">
    </div>
    <select id="role-filter" class="form-select form-select-sm" style="max-width:140px">
      <option value="">All Roles</option>
      <option value="owner">Owner</option>
      <option value="cashier">Cashier</option>
    </select>
    <button class="btn btn-sm btn-primary ms-auto" id="btn-add-user">
      <i class="bi bi-person-plus me-1"></i> Add User
    </button>
  </div>

  <!-- Summary -->
  <div class="row g-3 mb-4">
    <?php
      $total    = count($users);
      $active   = count(array_filter($users, fn($u) => $u['is_active']));
      $inactive = $total - $active;
    ?>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card total h-100">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-people"></i> Total Users</div>
          <div class="fs-4 fw-bold"><?= $total ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card login h-100">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-person-check"></i> Active</div>
          <div class="fs-4 fw-bold text-success"><?= $active ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm stat-card total h-100">
        <div class="card-body py-3">
          <div class="text-muted small"><i class="bi bi-person-dash"></i> Inactive</div>
          <div class="fs-4 fw-bold text-secondary"><?= $inactive ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Users Table -->
  <div class="card shadow-sm border-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0" id="users-table">
        <thead class="table-light">
          <tr>
            <th>Username</th>
            <th>Full Name</th>
            <th>Role</th>
            <th class="text-center">Status</th>
            <th>Last Login</th>
            <th>Created At</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr data-id="<?= $u['id'] ?>"
              data-username="<?= htmlspecialchars($u['username']) ?>"
              data-fullname="<?= htmlspecialchars($u['full_name']) ?>"
              data-role="<?= htmlspecialchars($u['role']) ?>"
              data-active="<?= $u['is_active'] ?>">
            <td class="fw-semibold"><?= htmlspecialchars($u['username']) ?></td>
            <td><?= htmlspecialchars($u['full_name'] ?: '—') ?></td>
            <td>
              <span class="badge <?= $u['role'] === 'owner' ? 'bg-warning text-dark' : 'bg-secondary' ?>">
                <?= htmlspecialchars($u['role']) ?>
              </span>
            </td>
            <td class="text-center">
              <span class="badge <?= $u['is_active'] ? 'bg-success' : 'bg-danger' ?>">
                <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
              </span>
            </td>
            <td class="text-muted" style="font-size:.82rem">
              <?= $u['last_login'] ? htmlspecialchars($u['last_login']) : '—' ?>
            </td>
            <td class="text-muted" style="font-size:.82rem"><?= htmlspecialchars($u['created_at']) ?></td>
            <td class="text-center">
              <div class="d-flex gap-1 justify-content-center">
                <button class="btn btn-outline-primary btn-sm btn-edit-user" title="Edit" data-id="<?= $u['id'] ?>">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-outline-warning btn-sm btn-reset-pw" title="Reset Password" data-id="<?= $u['id'] ?>" data-username="<?= htmlspecialchars($u['username']) ?>">
                  <i class="bi bi-key"></i>
                </button>
                <button class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-toggle-active"
                  title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>"
                  data-id="<?= $u['id'] ?>" data-active="<?= $u['is_active'] ?>">
                  <i class="bi <?= $u['is_active'] ? 'bi-person-dash' : 'bi-person-check' ?>"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div id="users-empty" class="text-center text-muted py-5 d-none">No users found.</div>
  </div>

</div>

<!-- Add/Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="userModalTitle"><i class="bi bi-person-plus me-2"></i>Add User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="user-form-error" class="alert alert-danger py-2 d-none"></div>
        <input type="hidden" id="uf-id">
        <div class="mb-2">
          <label class="form-label form-label-sm mb-1">Username <span class="text-danger">*</span></label>
          <input type="text" id="uf-username" class="form-control form-control-sm" placeholder="e.g. juan_dela_cruz">
        </div>
        <div class="mb-2">
          <label class="form-label form-label-sm mb-1">Full Name</label>
          <input type="text" id="uf-fullname" class="form-control form-control-sm" placeholder="e.g. Juan Dela Cruz">
        </div>
        <div class="mb-2">
          <label class="form-label form-label-sm mb-1">Role <span class="text-danger">*</span></label>
          <select id="uf-role" class="form-select form-select-sm">
            <option value="cashier">Cashier</option>
            <option value="owner">Owner</option>
          </select>
        </div>
        <div id="uf-password-group" class="mb-2">
          <label class="form-label form-label-sm mb-1">Password <span class="text-danger">*</span></label>
          <input type="password" id="uf-password" class="form-control form-control-sm" placeholder="Min. 8 characters">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="btn-save-user"><i class="bi bi-check-lg me-1"></i>Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPwModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-key me-2"></i>Reset Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1">Reset password for <strong id="rp-username"></strong>?</p>
        <p class="text-muted small mb-0">A temporary password will be generated. The user must change it on next login.</p>
        <div id="rp-result" class="alert alert-success mt-3 d-none">
          Temporary password: <strong id="rp-temp-pw" class="font-monospace"></strong>
          <br><small class="text-muted">Copy this now — it won't be shown again.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-warning" id="btn-confirm-reset"><i class="bi bi-key me-1"></i>Reset</button>
      </div>
    </div>
  </div>
</div>

<script>
const UAPI = 'api/users_api.php';

// ── Search / filter ───────────────────────────────────────────────────────────
function filterUsers() {
  const q    = document.getElementById('user-search').value.toLowerCase();
  const role = document.getElementById('role-filter').value;
  let visible = 0;
  document.querySelectorAll('#users-table tbody tr').forEach(tr => {
    const matchQ    = !q    || tr.dataset.username.toLowerCase().includes(q) || tr.dataset.fullname.toLowerCase().includes(q);
    const matchRole = !role || tr.dataset.role === role;
    const show = matchQ && matchRole;
    tr.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  document.getElementById('users-empty').classList.toggle('d-none', visible > 0);
}
document.getElementById('user-search').addEventListener('input', filterUsers);
document.getElementById('role-filter').addEventListener('change', filterUsers);

// ── Add user ──────────────────────────────────────────────────────────────────
document.getElementById('btn-add-user').addEventListener('click', () => {
  document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-person-plus me-2"></i>Add User';
  document.getElementById('uf-id').value = '';
  document.getElementById('uf-username').value  = '';
  document.getElementById('uf-fullname').value  = '';
  document.getElementById('uf-role').value      = 'cashier';
  document.getElementById('uf-password').value  = '';
  document.getElementById('uf-username').disabled = false;
  document.getElementById('uf-password-group').classList.remove('d-none');
  document.getElementById('user-form-error').classList.add('d-none');
  new bootstrap.Modal(document.getElementById('userModal')).show();
});

// ── Edit user ─────────────────────────────────────────────────────────────────
document.addEventListener('click', e => {
  const editBtn = e.target.closest('.btn-edit-user');
  if (editBtn) {
    const tr = editBtn.closest('tr');
    document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit User';
    document.getElementById('uf-id').value        = tr.dataset.id;
    document.getElementById('uf-username').value  = tr.dataset.username;
    document.getElementById('uf-fullname').value  = tr.dataset.fullname;
    document.getElementById('uf-role').value      = tr.dataset.role;
    document.getElementById('uf-username').disabled = true;
    document.getElementById('uf-password-group').classList.add('d-none');
    document.getElementById('user-form-error').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('userModal')).show();
  }
});

// ── Save user ─────────────────────────────────────────────────────────────────
document.getElementById('btn-save-user').addEventListener('click', async () => {
  const id      = document.getElementById('uf-id').value;
  const errEl   = document.getElementById('user-form-error');
  errEl.classList.add('d-none');

  const payload = id
    ? { action: 'update_user', id: parseInt(id),
        full_name: document.getElementById('uf-fullname').value.trim(),
        role:      document.getElementById('uf-role').value }
    : { action: 'create_user',
        username:  document.getElementById('uf-username').value.trim(),
        full_name: document.getElementById('uf-fullname').value.trim(),
        role:      document.getElementById('uf-role').value,
        password:  document.getElementById('uf-password').value };

  const btn = document.getElementById('btn-save-user');
  btn.disabled = true;
  try {
    const res  = await fetch(UAPI, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) { errEl.textContent = data.error; errEl.classList.remove('d-none'); return; }
    bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
    location.reload();
  } catch(e) {
    errEl.textContent = 'Network error.'; errEl.classList.remove('d-none');
  } finally { btn.disabled = false; }
});

// ── Reset password ────────────────────────────────────────────────────────────
let _resetId = null;
document.addEventListener('click', e => {
  const btn = e.target.closest('.btn-reset-pw');
  if (btn) {
    _resetId = btn.dataset.id;
    document.getElementById('rp-username').textContent = btn.dataset.username;
    document.getElementById('rp-result').classList.add('d-none');
    document.getElementById('btn-confirm-reset').disabled = false;
    new bootstrap.Modal(document.getElementById('resetPwModal')).show();
  }
});

document.getElementById('btn-confirm-reset').addEventListener('click', async function () {
  this.disabled = true;
  try {
    const res  = await fetch(UAPI, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'reset_password', id: parseInt(_resetId) }) });
    const data = await res.json();
    if (!data.success) { alert(data.error); return; }
    document.getElementById('rp-temp-pw').textContent = data.temp_password;
    document.getElementById('rp-result').classList.remove('d-none');
    document.querySelector('#resetPwModal .btn-secondary').textContent = 'Close';
  } catch(e) { alert('Network error.'); }
});

// ── Toggle active ─────────────────────────────────────────────────────────────
document.addEventListener('click', async e => {
  const btn = e.target.closest('.btn-toggle-active');
  if (!btn) return;
  const id     = btn.dataset.id;
  const active = btn.dataset.active === '1';
  const action = active ? 'deactivate' : 'activate';
  if (!confirm(`${active ? 'Deactivate' : 'Activate'} this user?`)) return;
  try {
    const res  = await fetch(UAPI, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'toggle_active', id: parseInt(id) }) });
    const data = await res.json();
    if (!data.success) { alert(data.error); return; }
    location.reload();
  } catch(e) { alert('Network error.'); }
});
</script>
<?php layoutEnd(); ?>
