<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();
requireRole(['admin','super_admin','admissions_officer','reviewer']);

// Brand variables
$brandColor = '#2563eb'; // Default
$logoPath = '/uploads/logos/logo.png'; // Default
try {
  $db = new Database();
  $pdo = $db->getConnection();
  // Ensure table exists safely
  $pdo->exec("CREATE TABLE IF NOT EXISTS system_config (
      config_key VARCHAR(100) PRIMARY KEY,
      config_value TEXT,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  )");
  $stmt = $pdo->prepare("SELECT config_key, config_value FROM system_config WHERE config_key IN ('brand_color','logo_path')");
  $stmt->execute();
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if ($row['config_key'] === 'brand_color' && !empty($row['config_value'])) { $brandColor = $row['config_value']; }
    if ($row['config_key'] === 'logo_path' && !empty($row['config_value'])) { $logoPath = $row['config_value']; }
  }
} catch (Throwable $e) { /* ignore and use defaults */ }
$hasLogo = file_exists($_SERVER['DOCUMENT_ROOT'] . $logoPath);

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="/../assets/css/dashboard.css">
<style>
  :root { --brand: <?php echo htmlspecialchars($brandColor); ?>; }
</style>

<div class="dashboard-layout">
  <aside class="dashboard-sidebar" id="sidebarNav">
    <a href="/admin/dashboard" class="sidebar-logo" aria-label="Home">
      <?php if ($hasLogo): ?>
        <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Logo" class="sidebar-logo-img">
      <?php else: ?>
        <div class="sidebar-logo-placeholder"></div>
      <?php endif; ?>
    </a>
    <div class="sidebar-title">Navigation</div>
    <a href="#overview" class="sidebar-item active" data-panel="overview"><i class="bi bi-speedometer2"></i> Overview</a>
    <a href="#applications" class="sidebar-item" data-panel="applications"><i class="bi bi-list-check"></i> Applications</a>
    <a href="#students" class="sidebar-item" data-panel="students"><i class="bi bi-people"></i> Students</a>
    <a href="#programs" class="sidebar-item" data-panel="programs"><i class="bi bi-mortarboard"></i> Programs</a>
    <a href="#application_forms" class="sidebar-item" data-panel="application_forms"><i class="bi bi-ui-checks"></i> Application Forms</a>
    <a href="#users" class="sidebar-item" data-panel="users"><i class="bi bi-person-gear"></i> Users</a>
    <a href="#payments" class="sidebar-item" data-panel="payments"><i class="bi bi-credit-card"></i> Payments</a>
    <a href="#reports" class="sidebar-item" data-panel="reports"><i class="bi bi-graph-up"></i> Reports</a>
    <a href="#notifications" class="sidebar-item" data-panel="notifications"><i class="bi bi-bell"></i> Notifications</a>
    <a href="#communications" class="sidebar-item" data-panel="communications"><i class="bi bi-chat-dots"></i> Communications</a>
    <a href="#settings" class="sidebar-item" data-panel="settings"><i class="bi bi-gear"></i> Settings</a>
  </aside>

  <main class="dashboard-content" id="panelHost">
    <div class="toolbar">
      <button class="btn secondary hamburger" id="toggleSidebar"><i class="bi bi-list"></i></button>
      <div class="muted">Dashboard</div>
      <div></div>
    </div>

    <div id="panel-overview" style="display:block">
      <div class="stat-grid">
        <div class="stat-card"><h4 class="stat-card-title">Total Applications</h4><div class="stat-card-value">0</div></div>
        <div class="stat-card"><h4 class="stat-card-title">Pending Review</h4><div class="stat-card-value">0</div></div>
        <div class="stat-card"><h4 class="stat-card-title">Active Programs</h4><div class="stat-card-value">0</div></div>
      </div>
      <div class="panel-card">
        <div class="kpi-grid">
          <div class="kpi-box"><div class="kpi-label">Today</div><div class="kpi-value">0</div></div>
          <div class="kpi-box"><div class="kpi-label">This Week</div><div class="kpi-value">0</div></div>
          <div class="kpi-box"><div class="kpi-label">This Month</div><div class="kpi-value">0</div></div>
        </div>
      </div>
      <div class="panel-card">
        <h3>Performance Overview</h3>
        <div style="height:200px;background:var(--surface-hover);border-radius:10px"></div>
      </div>
      <div class="panel-card">
        <h3>Quick Actions</h3>
        <div class="actions-group">
            <button class="btn"><i class="bi bi-list-check"></i> Review Applications</button>
            <button class="btn secondary"><i class="bi bi-people"></i> Manage Students</button>
        </div>
      </div>
    </div>

    <div id="panel-profile" class="hidden">
      <div class="panel-card">
        <h3>Profile</h3>
        <p class="muted">Manage your account information and avatar.</p>
        <div class="profile-grid">
          <div class="panel-card profile-avatar-wrapper">
            <div class="avatar-lg"></div>
            <button class="btn secondary" disabled>Upload Avatar (coming soon)</button>
          </div>
          <div class="panel-card">
            <div class="muted" style="margin-bottom:12px;">Account</div>
            <div class="grid cols-2">
              <div>
                <label class="form-label-sm">First Name</label>
                <input class="input" value="" placeholder="First name" disabled>
              </div>
              <div>
                <label class="form-label-sm">Last Name</label>
                <input class="input" value="" placeholder="Last name" disabled>
              </div>
              <div>
                <label class="form-label-sm">Email</label>
                <input class="input" value="" placeholder="Email" disabled>
              </div>
              <div>
                <label class="form-label-sm">Phone</label>
                <input class="input" value="" placeholder="Phone" disabled>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php
    $panels = ['applications', 'students', 'programs', 'application_forms', 'users', 'payments', 'reports', 'notifications', 'communications', 'settings'];
    foreach ($panels as $panel) {
      $title = ucwords(str_replace('_', ' ', $panel));
      echo "<div id='panel-{$panel}' class='hidden'>";
      if (file_exists(__DIR__."/panels/{$panel}.php")) {
        include __DIR__."/panels/{$panel}.php";
      } else {
        echo "<div class='card'><h3>{$title}</h3><p class='muted'>Module scaffold for {$title}.</p></div>";
      }
      echo "</div>";
    }
    ?>
  </main>
</div>

<script>
  (function(){
    // --- Panel Navigation ---
    const navItems = document.querySelectorAll('#sidebarNav .sidebar-item');
    const panelHost = document.getElementById('panelHost');
    function showPanel(panelName) {
      // Hide all panels
      const panels = panelHost.querySelectorAll(':scope > [id^="panel-"]');
      panels.forEach(p => { p.classList.add('hidden'); p.style.display = 'none'; });

      // Show the target panel
      const targetPanel = document.getElementById(`panel-${panelName}`);
      if (targetPanel) {
        targetPanel.classList.remove('hidden');
        targetPanel.style.display = 'block';
      }

      // Update active state in sidebar
      navItems.forEach(item => {
        item.classList.toggle('active', item.dataset.panel === panelName);
      });
      
      // Update URL without reloading
      try {
        const url = new URL(window.location);
        url.searchParams.set('panel', panelName);
        history.replaceState({}, '', url);
      } catch(e) {}
    }

    navItems.forEach(item => {
      item.addEventListener('click', function(e) {
        e.preventDefault();
        showPanel(this.dataset.panel);
      });
    });

    // Show initial panel based on URL
    const initialPanel = new URL(window.location.href).searchParams.get('panel') || 'overview';
    showPanel(initialPanel);

    // --- Mobile Sidebar Toggle ---
    const sidebar = document.getElementById('sidebarNav');
    const toggleButtons = [
        document.getElementById('toggleSidebar'), // Hamburger in content
        document.getElementById('sidebarToggleTop')   // Hamburger in top nav
    ];

    if (sidebar) {
        toggleButtons.forEach(button => {
            if (button) {
                button.addEventListener('click', (e) => {
                    e.stopPropagation();
                    sidebar.classList.toggle('show');
                });
            }
        });

        // Close sidebar when clicking outside of it on mobile
        document.addEventListener('click', () => {
            if (window.innerWidth <= 768 && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });

        // Prevent clicks inside the sidebar from closing it
        sidebar.addEventListener('click', (e) => e.stopPropagation());
    }
  })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>