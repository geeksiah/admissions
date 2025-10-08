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
  // Ensure required schemas (idempotent)
  $pdo->exec("CREATE TABLE IF NOT EXISTS users (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      username VARCHAR(100) UNIQUE,
      email VARCHAR(150) UNIQUE,
      password VARCHAR(255),
      role VARCHAR(50) DEFAULT 'admin',
      is_active TINYINT(1) DEFAULT 1,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS students (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      first_name VARCHAR(100), last_name VARCHAR(100), email VARCHAR(150), phone VARCHAR(50),
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS programs (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(150), status VARCHAR(30) DEFAULT 'active',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS applications (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      student_id INT UNSIGNED, program_id INT UNSIGNED,
      status VARCHAR(30) DEFAULT 'pending',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_status(status), INDEX idx_created(created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      application_id INT UNSIGNED, amount DECIMAL(10,2) DEFAULT 0,
      status VARCHAR(30) DEFAULT 'pending', method VARCHAR(50) DEFAULT '',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_pstatus(status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS vouchers (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      serial VARCHAR(100) UNIQUE, pin VARCHAR(100), is_used TINYINT(1) DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id INT UNSIGNED, title VARCHAR(150), body TEXT, is_read TINYINT(1) DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      sender_id INT UNSIGNED, recipient_id INT UNSIGNED, subject VARCHAR(150), body TEXT,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
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
    <a href="#vouchers" class="sidebar-item" data-panel="vouchers"><i class="bi bi-ticket"></i> Vouchers</a>
    <a href="#fee_structures" class="sidebar-item" data-panel="fee_structures"><i class="bi bi-currency-dollar"></i> Fee Structures</a>
    <a href="#backup_recovery" class="sidebar-item" data-panel="backup_recovery"><i class="bi bi-cloud-arrow-down"></i> Backup & Recovery</a>
    <a href="#audit_trail" class="sidebar-item" data-panel="audit_trail"><i class="bi bi-shield-check"></i> Audit Trail</a>
    <a href="#settings" class="sidebar-item" data-panel="settings"><i class="bi bi-gear"></i> Settings</a>
  </aside>

  <main class="dashboard-content" id="panelHost">
    <div class="toolbar">
      <button class="btn secondary hamburger" id="toggleSidebar"><i class="bi bi-list"></i></button>
      <div class="muted">Dashboard</div>
      <div></div>
    </div>

    <?php
      // Overview stats
      $totalApps = 0; $pendingApps = 0; $activePrograms = 0; $today = 0; $week = 0; $month = 0;
      try {
        $totalApps = (int)$pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
        $pendingApps = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetchColumn();
        $activePrograms = (int)$pdo->query("SELECT COUNT(*) FROM programs WHERE status='active'")->fetchColumn();
        $today = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE DATE(created_at)=CURDATE()")->fetchColumn();
        $week = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE YEARWEEK(created_at,1)=YEARWEEK(CURDATE(),1)")->fetchColumn();
        $month = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())")->fetchColumn();
      } catch (Throwable $e) { /* show zeros */ }
    ?>
    <div id="panel-overview">
      <div class="stat-grid">
        <div class="stat-card"><h4 class="stat-card-title">Total Applications</h4><div class="stat-card-value"><?php echo number_format($totalApps); ?></div></div>
        <div class="stat-card"><h4 class="stat-card-title">Pending Review</h4><div class="stat-card-value"><?php echo number_format($pendingApps); ?></div></div>
        <div class="stat-card"><h4 class="stat-card-title">Active Programs</h4><div class="stat-card-value"><?php echo number_format($activePrograms); ?></div></div>
      </div>
      <div class="panel-card">
        <div class="kpi-grid">
          <div class="kpi-box"><div class="kpi-label">Today</div><div class="kpi-value"><?php echo number_format($today); ?></div></div>
          <div class="kpi-box"><div class="kpi-label">This Week</div><div class="kpi-value"><?php echo number_format($week); ?></div></div>
          <div class="kpi-box"><div class="kpi-label">This Month</div><div class="kpi-value"><?php echo number_format($month); ?></div></div>
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


    <?php
    $panels = ['applications', 'students', 'programs', 'application_forms', 'users', 'payments', 'reports', 'notifications', 'communications', 'settings', 'vouchers', 'fee_structures', 'backup_recovery', 'audit_trail', 'profile'];
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
      // Hide all panels (avoid :scope for broader compatibility)
      const panels = panelHost.querySelectorAll('[id^="panel-"]');
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

    // Initialize all panels as hidden first (avoid :scope)
    const allPanels = panelHost.querySelectorAll('[id^="panel-"]');
    allPanels.forEach(p => { p.classList.add('hidden'); p.style.display = 'none'; });
    
    // Show initial panel based on URL
    const initialPanel = new URL(window.location.href).searchParams.get('panel') || 'overview';
    showPanel(initialPanel);

    // --- Mobile Sidebar Toggle ---
    const sidebar = document.getElementById('sidebarNav');
    const toggleSelectors = '#toggleSidebar, #sidebarToggleTop';

    function toggleSidebar(e){
      if (!sidebar) return;
      if (e) e.stopPropagation();
      sidebar.classList.toggle('show');
    }

    if (sidebar) {
      // Delegate clicks from either toggle button
      document.addEventListener('click', (e) => {
        const trigger = e.target.closest(toggleSelectors);
        if (trigger) {
          toggleSidebar(e);
          return;
        }
        // Close when clicking outside on mobile
        if (window.innerWidth <= 768 && sidebar.classList.contains('show')) {
          sidebar.classList.remove('show');
        }
      });

      // Prevent clicks inside the sidebar from closing it
      sidebar.addEventListener('click', (e) => e.stopPropagation());

      // Close with ESC
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar.classList.contains('show')) {
          sidebar.classList.remove('show');
        }
      });
    }
  })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>