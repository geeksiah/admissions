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
      application_id INT UNSIGNED,
      student_id INT UNSIGNED,
      amount DECIMAL(10,2) NOT NULL DEFAULT 0,
      currency VARCHAR(3) DEFAULT 'GHS',
      payment_method VARCHAR(50),
      gateway VARCHAR(50),
      transaction_id VARCHAR(100),
      reference VARCHAR(100),
      receipt_number VARCHAR(50),
      status VARCHAR(30) DEFAULT 'pending',
      payment_date TIMESTAMP NULL,
      verified_at TIMESTAMP NULL,
      verified_by INT UNSIGNED,
      notes TEXT,
      proof_of_payment VARCHAR(255),
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_status(status),
      INDEX idx_application(application_id),
      INDEX idx_student(student_id),
      INDEX idx_receipt(receipt_number)
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

// Determine current panel (server-side fallback if JS fails)
$allPanels = ['overview','applications','students','programs','application_forms','users','payments','reports','notifications','communications','settings','vouchers','fee_structures','backup_recovery','audit_trail','profile'];
$currentPanel = isset($_GET['panel']) && in_array($_GET['panel'], $allPanels, true) ? $_GET['panel'] : 'overview';

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/dashboard.css">
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
    <a href="?panel=overview" class="sidebar-item <?php echo $currentPanel==='overview'?'active':''; ?>" data-panel="overview"><i class="bi bi-speedometer2"></i> Overview</a>
    <a href="?panel=applications" class="sidebar-item <?php echo $currentPanel==='applications'?'active':''; ?>" data-panel="applications"><i class="bi bi-list-check"></i> Applications</a>
    <a href="?panel=students" class="sidebar-item <?php echo $currentPanel==='students'?'active':''; ?>" data-panel="students"><i class="bi bi-people"></i> Students</a>
    <a href="?panel=programs" class="sidebar-item <?php echo $currentPanel==='programs'?'active':''; ?>" data-panel="programs"><i class="bi bi-mortarboard"></i> Programs</a>
    <a href="?panel=application_forms" class="sidebar-item <?php echo $currentPanel==='application_forms'?'active':''; ?>" data-panel="application_forms"><i class="bi bi-ui-checks"></i> Application Forms</a>
    <a href="?panel=users" class="sidebar-item <?php echo $currentPanel==='users'?'active':''; ?>" data-panel="users"><i class="bi bi-person-gear"></i> Users</a>
    <a href="?panel=payments" class="sidebar-item <?php echo $currentPanel==='payments'?'active':''; ?>" data-panel="payments"><i class="bi bi-credit-card"></i> Payments</a>
    <a href="?panel=reports" class="sidebar-item <?php echo $currentPanel==='reports'?'active':''; ?>" data-panel="reports"><i class="bi bi-graph-up"></i> Reports</a>
    <a href="?panel=notifications" class="sidebar-item <?php echo $currentPanel==='notifications'?'active':''; ?>" data-panel="notifications"><i class="bi bi-bell"></i> Notifications</a>
    <a href="?panel=communications" class="sidebar-item <?php echo $currentPanel==='communications'?'active':''; ?>" data-panel="communications"><i class="bi bi-chat-dots"></i> Communications</a>
    <a href="?panel=vouchers" class="sidebar-item <?php echo $currentPanel==='vouchers'?'active':''; ?>" data-panel="vouchers"><i class="bi bi-ticket"></i> Vouchers</a>
    <a href="?panel=fee_structures" class="sidebar-item <?php echo $currentPanel==='fee_structures'?'active':''; ?>" data-panel="fee_structures"><i class="bi bi-currency-dollar"></i> Fee Structures</a>
    <a href="?panel=backup_recovery" class="sidebar-item <?php echo $currentPanel==='backup_recovery'?'active':''; ?>" data-panel="backup_recovery"><i class="bi bi-cloud-arrow-down"></i> Backup & Recovery</a>
    <a href="?panel=audit_trail" class="sidebar-item <?php echo $currentPanel==='audit_trail'?'active':''; ?>" data-panel="audit_trail"><i class="bi bi-shield-check"></i> Audit Trail</a>
    <a href="?panel=settings" class="sidebar-item <?php echo $currentPanel==='settings'?'active':''; ?>" data-panel="settings"><i class="bi bi-gear"></i> Settings</a>
  </aside>

  <main class="dashboard-content" id="panelHost">
    <div class="toolbar">
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
    <div id="panel-overview" class="<?php echo $currentPanel==='overview'?'':'hidden'; ?>" style="<?php echo $currentPanel==='overview'?'display:block':''; ?>">
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
      $isActive = ($currentPanel === $panel);
      echo "<div id='panel-{$panel}' class='" . ($isActive ? "" : "hidden") . "' style='" . ($isActive ? "display:block" : "") . "'>";
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
    const sidebarEl = document.getElementById('sidebarNav');
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
      
      // Update URL without adding hash
      try {
        const url = new URL(window.location.href);
        url.hash = '';
        url.searchParams.set('panel', panelName);
        history.replaceState({}, '', url.toString());
      } catch(e) {}
    }

    // SPA-style sidebar navigation (prevent full reload and avoid hashes)
    if (sidebarEl) {
      sidebarEl.addEventListener('click', function(e){
        const link = e.target.closest('.sidebar-item');
        if (!link) return;
        const panel = link.dataset.panel;
        if (!panel) return;
        e.preventDefault();
        showPanel(panel);
      });
    }

    // Initialize all panels as hidden first (avoid :scope)
    const allPanels = panelHost.querySelectorAll('[id^="panel-"]');
    allPanels.forEach(p => { p.classList.add('hidden'); p.style.display = 'none'; });
    
    // Show initial panel based on URL
    const initialPanel = (new URL(window.location.href).searchParams.get('panel') || 'overview').replace('#','');
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

      // Also bind direct listeners (older browsers)
      const topBtn = document.getElementById('sidebarToggleTop');
      const contentBtn = document.getElementById('toggleSidebar');
      if (topBtn) topBtn.addEventListener('click', toggleSidebar);
      if (contentBtn) contentBtn.addEventListener('click', toggleSidebar);
    }
  })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>