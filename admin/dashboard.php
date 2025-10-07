<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();
requireRole(['admin','super_admin','admissions_officer','reviewer']);

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<style>
  .layout{display:grid;grid-template-columns:240px 1fr 320px;gap:16px}
  .sidebar{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px;height:calc(100vh - 140px);position:sticky;top:80px}
  .sidebar .title{font-weight:600;margin:4px 10px 10px 10px;color:var(--muted);text-transform:uppercase;font-size:12px}
  .sidebar .item{display:flex;align-items:center;gap:10px;color:var(--text);padding:10px 12px;border-radius:10px;cursor:pointer}
  .sidebar .item:hover{background:var(--surface-hover)}
  .sidebar .item.active{background:var(--surface-hover);outline:2px solid transparent}
  .content{min-height:60vh}
  .hidden{display:none}
  .stat-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
  .stat{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:16px;padding:16px;box-shadow:0 6px 18px rgba(0,0,0,.08)
  }
  .stat h4{margin:0 0 6px 0;font-size:13px;color:var(--muted)}
  .stat .value{font-size:26px;font-weight:700}
  .panel-card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}
  .right-rail{position:sticky;top:80px;height:calc(100vh - 140px);display:flex;flex-direction:column;gap:16px}
  .profile{display:flex;gap:12px;align-items:center}
  .avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent-2))}
  .kpi{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:12px}
  .kpi .box{background:var(--surface-hover);border-radius:10px;padding:10px;text-align:center}
  .toolbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
  .hamburger{display:none}
  @media (max-width: 1280px){ .layout{grid-template-columns:240px 1fr} .right-rail{display:none} }
  @media (max-width: 768px){
    .layout{grid-template-columns:1fr}
    .sidebar{position:fixed;left:12px;top:70px;z-index:1000;width:240px;transform:translateX(-260px);transition:transform .2s ease}
    .sidebar.show{transform:translateX(0)}
    .hamburger{display:inline-flex}
  }
</style>

<div class="layout">
  <div class="sidebar" id="sidebarNav">
    <div class="title">Menu</div>
    <div class="item active" data-panel="overview"><i class="bi bi-speedometer2"></i> Overview</div>
    <div class="item" data-panel="applications"><i class="bi bi-list-check"></i> Applications</div>
    <div class="item" data-panel="students"><i class="bi bi-people"></i> Students</div>
    <div class="item" data-panel="programs"><i class="bi bi-mortarboard"></i> Programs</div>
    <div class="item" data-panel="settings"><i class="bi bi-gear"></i> Settings</div>
  </div>

  <div class="content" id="panelHost">
    <div class="toolbar">
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

    <div id="panel-settings" class="hidden">
      <?php if (file_exists(__DIR__.'/panels/settings.php')) { include __DIR__.'/panels/settings.php'; } else { ?>
        <div class="card"><h3>Settings</h3><p class="muted">Module scaffold. System settings and branding.</p></div>
      <?php } ?>
    </div>
  </div>

  <div class="right-rail">
    <div class="panel-card">
      <div class="profile">
        <div class="avatar"></div>
        <div>
          <div style="font-weight:600">Administrator</div>
          <div class="muted" style="font-size:12px">Welcome back</div>
        </div>
      </div>
      <div class="kpi">
        <div class="box"><div class="muted" style="font-size:12px">Today</div><div style="font-weight:700">0</div></div>
        <div class="box"><div class="muted" style="font-size:12px">Week</div><div style="font-weight:700">0</div></div>
        <div class="box"><div class="muted" style="font-size:12px">Month</div><div style="font-weight:700">0</div></div>
      </div>
    </div>
    <div class="panel-card">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <h4 style="margin:0">Campaign</h4>
        <span class="btn secondary" style="padding:6px 10px;font-size:12px">Active</span>
      </div>
      <div class="muted" style="margin-top:8px;font-size:13px">Performance preview</div>
      <div style="height:120px;background:var(--surface-hover);border-radius:10px;margin-top:10px"></div>
    </div>
  </div>
</div>

<script>
  (function(){
    const items = document.querySelectorAll('#sidebarNav .item');
    const panels = {
      overview: document.getElementById('panel-overview'),
      applications: document.getElementById('panel-applications'),
      students: document.getElementById('panel-students'),
      programs: document.getElementById('panel-programs'),
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


