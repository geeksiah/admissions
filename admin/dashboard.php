<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();
requireRole(['admin','super_admin','admissions_officer','reviewer']);

// Brand variables (read from settings if available)
$brandColor = '#2563eb';
$logoPath = '/uploads/logos/logo.png';
try {
  $db = new Database();
  $pdo = $db->getConnection();
  $pdo->exec("CREATE TABLE IF NOT EXISTS system_config (config_key VARCHAR(100) PRIMARY KEY, config_value TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
  $stmt = $pdo->prepare("SELECT config_key, config_value FROM system_config WHERE config_key IN ('brand_color','logo_path')");
  $stmt->execute();
  foreach ($stmt->fetchAll() as $row) {
    if ($row['config_key'] === 'brand_color' && !empty($row['config_value'])) { $brandColor = $row['config_value']; }
    if ($row['config_key'] === 'logo_path' && !empty($row['config_value'])) { $logoPath = $row['config_value']; }
  }
} catch (Throwable $e) { /* ignore, use defaults */ }
$hasLogo = file_exists($_SERVER['DOCUMENT_ROOT'] . $logoPath);

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<style>
  :root{--brand: <?php echo $brandColor; ?>;}
  .layout{display:grid;grid-template-columns:260px 1fr;gap:24px;padding-left:260px}
  .nav{padding-left:260px;padding-right:16px}
  .sidebar{background:var(--brand);border:none;border-radius:0;padding:26px 16px 16px 16px;position:fixed;top:0;left:0;bottom:0;width:260px;color:#fff;margin:0}
  .sidebar .logo{display:flex;align-items:center;gap:10px;margin:4px 8px 18px 8px}
  .sidebar .logo img{height:34px;width:auto;display:block}
  .sidebar .logo .placeholder{width:34px;height:34px;border-radius:8px;background:#fff;opacity:.95}
  .sidebar .title{font-weight:600;margin:8px 10px 12px 10px;color:rgba(255,255,255,.7);text-transform:uppercase;font-size:12px}
  .sidebar .item{display:flex;align-items:center;gap:10px;color:#fff;padding:14px 14px;border-radius:12px;cursor:pointer;transition:background .2s ease, transform .2s ease}
  .sidebar .item:hover{background:rgba(255,255,255,.10)}
  .sidebar .item.active{background:rgba(255,255,255,.16);outline:2px solid transparent}
  .content{min-height:60vh;padding:12px 24px 40px 0}
  .hidden{display:none}
  .stat-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
  .stat{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:16px;padding:16px;box-shadow:0 6px 18px rgba(0,0,0,.08);transition:transform .15s ease, box-shadow .15s ease
  }
  .stat h4{margin:0 0 6px 0;font-size:13px;color:var(--muted)}
  .stat .value{font-size:26px;font-weight:700}
  .stat:hover{transform:translateY(-2px);box-shadow:0 10px 22px rgba(0,0,0,.12)}
  .panel-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:18px;transition:box-shadow .15s ease}
  .panel-card:hover{box-shadow:0 8px 18px rgba(0,0,0,.10)}
  .right-rail{display:none}
  .profile{display:flex;gap:12px;align-items:center}
  .avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent-2))}
  .kpi{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:12px}
  .kpi .box{background:var(--surface-hover);border-radius:10px;padding:10px;text-align:center}
  .toolbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
  .hamburger{display:none}
  @media (max-width: 1280px){ .layout{grid-template-columns:1fr;padding-left:260px} }
  @media (max-width: 768px){
    .layout{grid-template-columns:1fr;padding-left:0}
    .nav{padding-left:16px}
    .sidebar{left:0;top:0;z-index:1000;width:240px;transform:translateX(-260px);transition:transform .2s ease}
    .sidebar.show{transform:translateX(0)}
    .hamburger{display:inline-flex}
  }
</style>

<div class="layout">
  <div class="sidebar" id="sidebarNav">
    <a href="/admin/dashboard" class="logo" aria-label="Home">
      <?php if ($hasLogo): ?>
        <img src="<?php echo $logoPath; ?>" alt="Logo">
      <?php else: ?>
        <div class="placeholder"></div>
      <?php endif; ?>
    </a>
    <div class="title">Navigation</div>
    <div class="item active" data-panel="overview"><i class="bi bi-speedometer2"></i> Overview</div>
    <div class="item" data-panel="applications"><i class="bi bi-list-check"></i> Applications</div>
    <div class="item" data-panel="students"><i class="bi bi-people"></i> Students</div>
    <div class="item" data-panel="programs"><i class="bi bi-mortarboard"></i> Programs</div>
    <div class="item" data-panel="application_forms"><i class="bi bi-ui-checks"></i> Application Forms</div>
    <div class="item" data-panel="users"><i class="bi bi-person-gear"></i> Users</div>
    <div class="item" data-panel="payments"><i class="bi bi-credit-card"></i> Payments</div>
    <div class="item" data-panel="reports"><i class="bi bi-graph-up"></i> Reports</div>
    <div class="item" data-panel="notifications"><i class="bi bi-bell"></i> Notifications</div>
    <div class="item" data-panel="communications"><i class="bi bi-chat-dots"></i> Communications</div>
    <div class="item" data-panel="settings"><i class="bi bi-gear"></i> Settings</div>
  </div>

  <div class="content" id="panelHost">
    <div class="toolbar" style="margin-top:8px">
      <button class="btn secondary hamburger" id="toggleSidebar"><i class="bi bi-list"></i></button>
      <div class="muted">Dashboard</div>
      <div></div>
    </div>
    <div id="panel-overview">
      <div class="stat-grid">
        <div class="stat"><h4>Total Applications</h4><div class="value">0</div></div>
        <div class="stat"><h4>Pending Review</h4><div class="value">0</div></div>
        <div class="stat"><h4>Active Programs</h4><div class="value">0</div></div>
      </div>
      <div class="panel-card" style="margin-top:16px;">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
          <div class="box"><div class="muted" style="font-size:12px">Today</div><div style="font-weight:700;font-size:18px">0</div></div>
          <div class="box"><div class="muted" style="font-size:12px">Week</div><div style="font-weight:700;font-size:18px">0</div></div>
          <div class="box"><div class="muted" style="font-size:12px">Month</div><div style="font-weight:700;font-size:18px">0</div></div>
        </div>
      </div>
      <div class="panel-card" style="margin-top:16px;">
        <h3>Performance Overview</h3>
        <div style="height:200px;background:var(--surface-hover);border-radius:10px"></div>
      </div>
      <div class="panel-card" style="margin-top:16px;">
        <h3>Quick Actions</h3>
        <button class="btn"><i class="bi bi-list-check"></i> Review Applications</button>
        <button class="btn secondary"><i class="bi bi-people"></i> Manage Students</button>
      </div>
    </div>

    <div id="panel-applications" class="hidden">
      <?php if (file_exists(__DIR__.'/panels/applications.php')) { include __DIR__.'/panels/applications.php'; } else { ?>
        <div class="card"><h3>Applications</h3><p class="muted">Module scaffold. Implement list, filters, and workflows.</p></div>
      <?php } ?>
    </div>

    <div id="panel-students" class="hidden">
      <?php if (file_exists(__DIR__.'/panels/students.php')) { include __DIR__.'/panels/students.php'; } else { ?>
        <div class="card"><h3>Students</h3><p class="muted">Module scaffold. Implement directory and profiles.</p></div>
      <?php } ?>
    </div>

    <div id="panel-programs" class="hidden">
      <?php if (file_exists(__DIR__.'/panels/programs.php')) { include __DIR__.'/panels/programs.php'; } else { ?>
        <div class="card"><h3>Programs</h3><p class="muted">Module scaffold. Implement CRUD for programs.</p></div>
      <?php } ?>
    </div>

    <div id="panel-application_forms" class="hidden">
      <?php if (file_exists(__DIR__.'/panels/application_forms.php')) { include __DIR__.'/panels/application_forms.php'; } else { ?>
        <div class="card"><h3>Application Forms</h3><p class="muted">Module scaffold. Build form builder and templates.</p></div>
      <?php } ?>
    </div>

    <div id="panel-users" class="hidden">
      <?php if (file_exists(__DIR__.'/panels/users.php')) { include __DIR__.'/panels/users.php'; } else { ?>
        <div class="card"><h3>Users</h3><p class="muted">Module scaffold. Admin user management, roles, permissions.</p></div>
      <?php } ?>
    </div>

    <div id="panel-payments" class="hidden">
      <?php if (file_exists(__DIR__.'/panels/payments.php')) { include __DIR__.'/panels/payments.php'; } else { ?>
        <div class="card"><h3>Payments</h3><p class="muted">Module scaffold. Transactions and gateways config.</p></div>
      <?php } ?>
    </div>

    <div id="panel-reports" class="hidden">
      <?php if (file_exists(__DIR__.'/panels/reports.php')) { include __DIR__.'/panels/reports.php'; } else { ?>
        <div class="card"><h3>Reports</h3><p class="muted">Module scaffold. Analytics and export tools.</p></div>
      <?php } ?>
    </div>

    <div id="panel-notifications" class="hidden">
      <?php if (file_exists(__DIR__.'/panels/notifications.php')) { include __DIR__.'/panels/notifications.php'; } else { ?>
        <div class="card"><h3>Notifications</h3><p class="muted">Module scaffold. Email/SMS/in-app notifications center.</p></div>
      <?php } ?>
    </div>

    <div id="panel-communications" class="hidden">
      <?php if (file_exists(__DIR__.'/panels/communications.php')) { include __DIR__.'/panels/communications.php'; } else { ?>
        <div class="card"><h3>Communications</h3><p class="muted">Module scaffold. Messaging and templates.</p></div>
      <?php } ?>
    </div>

    <div id="panel-settings" class="hidden">
      <?php if (file_exists(__DIR__.'/panels/settings.php')) { include __DIR__.'/panels/settings.php'; } else { ?>
        <div class="card"><h3>Settings</h3><p class="muted">Module scaffold. System settings and branding.</p></div>
      <?php } ?>
    </div>
  </div>

  <div class="right-rail"></div>
</div>

<script>
  (function(){
    const items = document.querySelectorAll('#sidebarNav .item');
    const panels = {
      overview: document.getElementById('panel-overview'),
      applications: document.getElementById('panel-applications'),
      students: document.getElementById('panel-students'),
      programs: document.getElementById('panel-programs'),
      application_forms: document.getElementById('panel-application_forms'),
      users: document.getElementById('panel-users'),
      payments: document.getElementById('panel-payments'),
      reports: document.getElementById('panel-reports'),
      notifications: document.getElementById('panel-notifications'),
      communications: document.getElementById('panel-communications'),
      settings: document.getElementById('panel-settings')
    };
    function show(panel){
      Object.values(panels).forEach(p => p.classList.add('hidden'));
      if (panels[panel]) panels[panel].classList.remove('hidden');
      items.forEach(i => i.classList.toggle('active', i.getAttribute('data-panel')===panel));
      try{ history.replaceState({}, '', '?panel='+panel); }catch(e){}
    }
    items.forEach(i=>{
      i.addEventListener('click', function(){ show(this.getAttribute('data-panel')); });
    });
    const url = new URL(window.location.href);
    show(url.searchParams.get('panel') || 'overview');

    const toggle = document.getElementById('toggleSidebar');
    const sb = document.getElementById('sidebarNav');
    if (toggle && sb) toggle.addEventListener('click', function(){ sb.classList.toggle('show'); });
  })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>


