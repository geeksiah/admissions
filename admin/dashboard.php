<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();
requireRole(['admin','super_admin','admissions_officer','reviewer']);

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<style>
  .layout{display:grid;grid-template-columns:240px 1fr;gap:16px}
  .sidebar{background:var(--card);border:1px solid #1f2937;border-radius:12px;padding:16px;height:calc(100vh - 140px);position:sticky;top:80px}
  .sidebar .item{display:flex;align-items:center;gap:10px;color:var(--text);padding:10px 12px;border-radius:8px;cursor:pointer}
  .sidebar .item:hover{background:#0b1220}
  .sidebar .item.active{background:#0b1220;outline:2px solid #1f2937}
  .content{min-height:60vh}
  .hidden{display:none}
</style>

<div class="layout">
  <div class="sidebar" id="sidebarNav">
    <div class="item active" data-panel="overview"><i class="bi bi-speedometer2"></i> Overview</div>
    <div class="item" data-panel="applications"><i class="bi bi-list-check"></i> Applications</div>
    <div class="item" data-panel="students"><i class="bi bi-people"></i> Students</div>
    <div class="item" data-panel="programs"><i class="bi bi-mortarboard"></i> Programs</div>
    <div class="item" data-panel="settings"><i class="bi bi-gear"></i> Settings</div>
  </div>

  <div class="content" id="panelHost">
    <div id="panel-overview">
      <div class="grid cols-3">
        <div class="card"><div class="muted">Total Applications</div><div style="font-size:28px;font-weight:700">0</div></div>
        <div class="card"><div class="muted">Pending Review</div><div style="font-size:28px;font-weight:700">0</div></div>
        <div class="card"><div class="muted">Active Programs</div><div style="font-size:28px;font-weight:700">0</div></div>
      </div>
      <div class="card">
        <h3>Quick Actions</h3>
        <button class="btn"><i class="bi bi-list-check"></i> Review Applications</button>
        <button class="btn secondary"><i class="bi bi-people"></i> Manage Students</button>
      </div>
    </div>

    <div id="panel-applications" class="hidden">
      <?php if (file_exists(__DIR__.'/panels/applications.php')) include __DIR__.'/panels/applications.php'; else: ?>
        <div class="card"><h3>Applications</h3><p class="muted">Module scaffold. Implement list, filters, and workflows.</p></div>
      <?php endif; ?>
    </div>

    <div id="panel-students" class="hidden">
      <?php if (file_exists(__DIR__.'/panels/students.php')) include __DIR__.'/panels/students.php'; else: ?>
        <div class="card"><h3>Students</h3><p class="muted">Module scaffold. Implement directory and profiles.</p></div>
      <?php endif; ?>
    </div>

    <div id="panel-programs" class="hidden">
      <?php if (file_exists(__DIR__.'/panels/programs.php')) include __DIR__.'/panels/programs.php'; else: ?>
        <div class="card"><h3>Programs</h3><p class="muted">Module scaffold. Implement CRUD for programs.</p></div>
      <?php endif; ?>
    </div>

    <div id="panel-settings" class="hidden">
      <?php if (file_exists(__DIR__.'/panels/settings.php')) include __DIR__.'/panels/settings.php'; else: ?>
        <div class="card"><h3>Settings</h3><p class="muted">Module scaffold. System settings and branding.</p></div>
      <?php endif; ?>
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
  })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>


