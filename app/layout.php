<?php
function layoutStart(string $title = 'Lumina POS'): void {
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?></title>
<link href="/lumina-pos/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="/lumina-pos/assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
<style>
  /* ── Variables ─────────────────────────────────────────── */
  :root {
    --sb-width:       240px;
    --sb-width-col:    70px;
    --sb-bg:          #1a1d23;
    --sb-border:      rgba(255,255,255,0.07);
    --sb-text:        #8b95a1;
    --sb-text-hover:  #ffffff;
    --sb-active-bg:   rgba(13,110,253,0.18);
    --sb-active-border: #0d6efd;
    --transition:     200ms ease-in-out;
  }

  /* ── Base ──────────────────────────────────────────────── */
  body { background:#f4f6f9; font-size:.92rem; }

  /* ── Sidebar ───────────────────────────────────────────── */
  #sidebar {
    position: fixed;
    top: 0; left: 0;
    height: 100vh;
    width: var(--sb-width);
    background: var(--sb-bg);
    display: flex;
    flex-direction: column;
    z-index: 1040;
    overflow: hidden;
    transition: width var(--transition), transform var(--transition);
  }

  /* Brand */
  .sidebar-brand {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: 1rem 1.1rem;
    border-bottom: 1px solid var(--sb-border);
    color: #fff;
    white-space: nowrap;
    min-height: 60px;
  }
  .brand-icon { font-size: 1.4rem; flex-shrink: 0; color: #0d6efd; }

  /* User */
  .sidebar-user {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .65rem 1.1rem;
    border-bottom: 1px solid var(--sb-border);
    color: var(--sb-text);
    white-space: nowrap;
    overflow: hidden;
    font-size: .8rem;
  }

  /* Nav */
  .sidebar-nav {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: .5rem 0;
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.1) transparent;
  }

  .nav-section-label {
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: rgba(255,255,255,0.25);
    padding: .75rem 1.1rem .25rem;
    white-space: nowrap;
    overflow: hidden;
    transition: opacity var(--transition), padding var(--transition);
  }

  .sidebar-link {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .55rem 1.1rem;
    color: var(--sb-text);
    text-decoration: none;
    white-space: nowrap;
    border-left: 3px solid transparent;
    transition: background var(--transition), color var(--transition), border-color var(--transition);
  }
  .sidebar-link:hover {
    background: rgba(255,255,255,0.07);
    color: var(--sb-text-hover);
  }
  .sidebar-link.active {
    background: var(--sb-active-bg);
    color: #fff;
    font-weight: 600;
    border-left-color: var(--sb-active-border);
  }
  .sidebar-link.active .sidebar-icon { color: #0d6efd; }

  .sidebar-icon { font-size: 1.1rem; flex-shrink: 0; }
  .sidebar-label { overflow: hidden; transition: opacity var(--transition), width var(--transition); }

  /* ── Collapsed state ───────────────────────────────────── */
  body.sb-collapsed #sidebar { width: var(--sb-width-col); }
  body.sb-collapsed .sidebar-label { opacity: 0; width: 0; }
  body.sb-collapsed .nav-section-label { opacity: 0; padding-top: .4rem; padding-bottom: .4rem; }
  body.sb-collapsed .sidebar-link { justify-content: center; padding-left: 0; padding-right: 0; border-left-color: transparent; }
  body.sb-collapsed .sidebar-link.active { border-left-color: transparent; border-right: 3px solid var(--sb-active-border); }
  body.sb-collapsed .sidebar-user { justify-content: center; }

  /* ── Main content ──────────────────────────────────────── */
  .main-content {
    margin-left: var(--sb-width);
    min-height: 100vh;
    transition: margin-left var(--transition);
  }
  body.sb-collapsed .main-content { margin-left: var(--sb-width-col); }

  /* ── Page header ───────────────────────────────────────── */
  .page-header {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .85rem 1.5rem;
    background: #fff;
    border-bottom: 1px solid #e5e9f0;
    position: sticky;
    top: 0;
    z-index: 100;
  }
  .page-header h4 { margin: 0; font-size: 1rem; font-weight: 700; }
  .page-header .clock { margin-left: auto; font-size: .82rem; color: #6c757d; white-space: nowrap; }

  /* First element after the sticky header gets breathing room */
  .page-header + * { margin-top: 1.5rem; }

  #btn-sidebar-toggle {
    background: none;
    border: none;
    color: #495057;
    font-size: 1.2rem;
    padding: .2rem .4rem;
    border-radius: .25rem;
    cursor: pointer;
    flex-shrink: 0;
    line-height: 1;
  }
  #btn-sidebar-toggle:hover { background: #f0f2f5; }

  /* ── Stat cards ────────────────────────────────────────── */
  .stat-card { border-left: 4px solid; border-radius: .375rem; }
  .stat-card.orders  { border-color: #6f42c1; }
  .stat-card.revenue { border-color: #0d6efd; }
  .stat-card.cost    { border-color: #fd7e14; }
  .stat-card.profit  { border-color: #198754; }
  .stat-card.total   { border-color: #6c757d; }
  .stat-card.login   { border-color: #198754; }
  .stat-card.order   { border-color: #0d6efd; }
  .stat-card.closing { border-color: #6f42c1; }
  .section-title {
    font-size: .75rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .05em; color: #6c757d; margin-bottom: .75rem;
  }
  .table td, .table th { vertical-align: middle; }

  /* ── Mobile ────────────────────────────────────────────── */
  #sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 1039;
  }

  @media (max-width: 767.98px) {
    #sidebar {
      transform: translateX(-100%);
      width: var(--sb-width) !important;
    }
    #sidebar.mobile-open {
      transform: translateX(0);
    }
    #sidebar.mobile-open ~ #sidebar-overlay,
    #sidebar-overlay.show { display: block; }
    .main-content { margin-left: 0 !important; }
    body.sb-collapsed .main-content { margin-left: 0 !important; }
  }
</style>
</head>
<body>
<?php require_once APP_ROOT . '/app/sidebar.php'; ?>
<div class="main-content">
<?php
}

function layoutHeader(string $title, string $icon = 'bi-circle'): void {
?>
<div class="page-header">
  <button id="btn-sidebar-toggle" title="Toggle sidebar">
    <i class="bi bi-list"></i>
  </button>
  <h4><i class="bi <?= $icon ?> me-2 text-primary"></i><?= htmlspecialchars($title) ?></h4>
  <span class="clock" id="clock"></span>
</div>
<?php
}

function layoutEnd(): void {
?>
</div><!-- /main-content -->
<script src="/lumina-pos/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  const STORAGE_KEY = 'lumina_sidebar_state';
  const sidebar     = document.getElementById('sidebar');
  const overlay     = document.getElementById('sidebar-overlay');
  const toggleBtn   = document.getElementById('btn-sidebar-toggle');
  const isMobile    = () => window.innerWidth < 768;

  // Apply persisted state on load (desktop only)
  if (!isMobile() && localStorage.getItem(STORAGE_KEY) === 'collapsed') {
    document.body.classList.add('sb-collapsed');
  }

  // Toggle
  toggleBtn.addEventListener('click', function () {
    if (isMobile()) {
      sidebar.classList.toggle('mobile-open');
      overlay.classList.toggle('show');
    } else {
      document.body.classList.toggle('sb-collapsed');
      localStorage.setItem(STORAGE_KEY,
        document.body.classList.contains('sb-collapsed') ? 'collapsed' : 'expanded'
      );
    }
  });

  // Close mobile sidebar on overlay click
  overlay.addEventListener('click', function () {
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('show');
  });

  // Clock
  (function tickClock() {
    const el = document.getElementById('clock');
    if (el) el.textContent = new Date().toLocaleString();
    setTimeout(tickClock, 1000);
  })();

  // Bootstrap tooltips for collapsed icons
  function initTooltips() {
    document.querySelectorAll('.sidebar-link').forEach(function (el) {
      if (el._bsTooltip) { el._bsTooltip.dispose(); }
      if (!isMobile() && document.body.classList.contains('sb-collapsed')) {
        el._bsTooltip = new bootstrap.Tooltip(el, { placement: 'right', trigger: 'hover' });
      }
    });
  }

  // Re-init tooltips on toggle
  toggleBtn.addEventListener('click', function () {
    setTimeout(initTooltips, 210);
  });

  initTooltips();
})();
</script>
</body>
</html>
<?php
}
