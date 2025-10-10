<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Guard: student session
if (!isset($_SESSION['student_id'])) { header('Location: /student/login'); exit; }

$pageTitle = 'Student Dashboard';
$studentId = (int)$_SESSION['student_id'];
$error = '';$message='';
$allPanels = ['applications','documents','payments','notifications','profile'];
$currentPanel = isset($_GET['panel']) && in_array($_GET['panel'],$allPanels,true) ? $_GET['panel'] : 'applications';

try { $db = new Database(); $pdo = $db->getConnection(); } catch (Throwable $e) { $error = $e->getMessage(); }

if ($error==='') {
  try {
    // Ensure minimal schemas
    $pdo->exec("CREATE TABLE IF NOT EXISTS programs (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(150) NOT NULL,
      status VARCHAR(30) DEFAULT 'active',
      prospectus_path VARCHAR(255) NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_status(status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Add prospectus_path to legacy programs table if missing
    try {
      $col = $pdo->query("SHOW COLUMNS FROM programs LIKE 'prospectus_path'")->fetch();
      if (!$col) { $pdo->exec("ALTER TABLE programs ADD COLUMN prospectus_path VARCHAR(255) NULL"); }
    } catch (Throwable $e) { /* ignore */ }
    $pdo->exec("CREATE TABLE IF NOT EXISTS applications (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      student_id INT UNSIGNED NOT NULL,
      program_id INT UNSIGNED NOT NULL,
      status VARCHAR(30) DEFAULT 'pending',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_student(student_id), INDEX idx_status(status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_documents (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      student_id INT UNSIGNED NOT NULL,
      application_id INT UNSIGNED NULL,
      doc_type VARCHAR(80) NOT NULL,
      file_path VARCHAR(255) NOT NULL,
      status VARCHAR(30) DEFAULT 'submitted',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_student(student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Ensure notifications has student_id for student portal
    try {
      $ncol = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'student_id'")->fetch();
      if (!$ncol) { $pdo->exec("ALTER TABLE notifications ADD COLUMN student_id INT UNSIGNED NULL"); }
    } catch (Throwable $e) { /* ignore */ }
  } catch (Throwable $e) { $error = $e->getMessage(); }
}

// Actions
if ($error==='' && $_SERVER['REQUEST_METHOD']==='POST') {
  try {
    if (!validateCSRFToken($_POST['csrf'] ?? '')) { throw new RuntimeException('Invalid request'); }
    $action = $_POST['action'] ?? '';
    if ($action==='create_application') {
      $programId = (int)($_POST['program_id'] ?? 0);
      if (!$programId) throw new RuntimeException('Select a program');
      // Enforce mode: if voucher required, block direct creation and prompt
      $settings = [];
      try {
        $st = $pdo->query("SELECT config_key, config_value FROM system_config");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) { $settings[$row['config_key']] = $row['config_value']; }
      } catch (Throwable $e) {}
      $mode = $settings['mode_toggle'] ?? 'pay_after';
      if ($mode === 'voucher' || ($settings['voucher_required'] ?? '0') === '1') {
        throw new RuntimeException('A voucher is required before starting an application.');
      }
      $stmt = $pdo->prepare('INSERT INTO applications(student_id, program_id, status) VALUES(?,?,"pending")');
      $stmt->execute([$studentId, $programId]);
      $message = 'Application created.';
      $currentPanel = 'applications';
    } elseif ($action==='redeem_voucher') {
      // Validate voucher, mark used, create application
      $serial = trim($_POST['voucher_serial'] ?? '');
      $pin = trim($_POST['voucher_pin'] ?? '');
      $programId = (int)($_POST['program_id'] ?? 0);
      if ($serial==='' || $pin==='' || !$programId) { throw new RuntimeException('Provide voucher serial, PIN and program'); }
      // Ensure vouchers table exists
      try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS vouchers (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          batch_id INT UNSIGNED,
          serial_number VARCHAR(50) UNIQUE NOT NULL,
          pin_code VARCHAR(20) NOT NULL,
          value DECIMAL(10,2) DEFAULT 0,
          currency VARCHAR(3) DEFAULT 'GHS',
          status ENUM('active', 'used', 'expired', 'cancelled') DEFAULT 'active',
          used_by INT UNSIGNED,
          used_at TIMESTAMP NULL,
          application_id INT UNSIGNED,
          expiry_date DATE,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
      } catch (Throwable $e) {}
      // Look up active voucher
      $st=$pdo->prepare('SELECT * FROM vouchers WHERE serial_number=? AND pin_code=? LIMIT 1');
      $st->execute([$serial, $pin]);
      $voucher = $st->fetch(PDO::FETCH_ASSOC);
      if (!$voucher) { throw new RuntimeException('Invalid voucher details'); }
      if ($voucher['status']!=='active') { throw new RuntimeException('Voucher not active'); }
      if (!empty($voucher['expiry_date']) && strtotime($voucher['expiry_date']) < strtotime(date('Y-m-d'))) {
        throw new RuntimeException('Voucher expired');
      }
      // Create application
      $stmt = $pdo->prepare('INSERT INTO applications(student_id, program_id, status) VALUES(?,?,"pending")');
      $stmt->execute([$studentId, $programId]);
      $appId = (int)$pdo->lastInsertId();
      // Mark voucher used
      $st=$pdo->prepare('UPDATE vouchers SET status="used", used_by=?, used_at=NOW(), application_id=? WHERE id=?');
      $st->execute([$studentId, $appId, $voucher['id']]);
      $message = 'Voucher redeemed. Application created.';
      $currentPanel = 'applications';
    } elseif ($action==='upload_document') {
      $docType = sanitizeInput($_POST['doc_type'] ?? 'document');
      $appId = (int)($_POST['application_id'] ?? 0);
      if (empty($_FILES['file']['name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        throw new RuntimeException('Select a file to upload');
      }
      $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['pdf','jpg','jpeg','png','webp'], true)) { throw new RuntimeException('Unsupported file type'); }
      $dir = __DIR__ . '/../uploads/documents/' . $studentId;
      if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
      $fname = uniqid('doc_', true) . '.' . $ext;
      $dest = $dir . '/' . $fname;
      if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) { throw new RuntimeException('Upload failed'); }
      $rel = '/uploads/documents/' . $studentId . '/' . $fname;
      $stmt = $pdo->prepare('INSERT INTO student_documents(student_id, application_id, doc_type, file_path) VALUES(?,?,?,?)');
      $stmt->execute([$studentId, $appId ?: null, $docType, $rel]);
      $message = 'Document uploaded.';
      $currentPanel = 'documents';
    } elseif ($action==='delete_document') {
      $docId = (int)($_POST['id'] ?? 0);
      if (!$docId) throw new RuntimeException('Invalid document');
      $st = $pdo->prepare('SELECT file_path FROM student_documents WHERE id=? AND student_id=?');
      $st->execute([$docId, $studentId]);
      $row = $st->fetch(PDO::FETCH_ASSOC);
      if (!$row) throw new RuntimeException('Document not found');
      $abs = $_SERVER['DOCUMENT_ROOT'] . ($row['file_path'] ?? '');
      $pdo->prepare('DELETE FROM student_documents WHERE id=? AND student_id=?')->execute([$docId, $studentId]);
      if ($abs && is_file($abs) && strpos($abs, $_SERVER['DOCUMENT_ROOT'].'/uploads/documents/'.$studentId) === 0) { @unlink($abs); }
      $message = 'Document deleted.';
      $currentPanel = 'documents';
    } elseif ($action==='change_password') {
      $new = $_POST['new_password'] ?? '';
      $confirm = $_POST['confirm_password'] ?? '';
      if (strlen($new) < 8 || $new !== $confirm) { throw new RuntimeException('Password mismatch or too short'); }
      $hash = password_hash($new, PASSWORD_DEFAULT);
      $pdo->prepare('UPDATE users SET password=? WHERE id=? AND role="student"')->execute([$hash, $studentId]);
      $message = 'Password updated.';
      $currentPanel = 'profile';
    }
  } catch (Throwable $e) { $error = $e->getMessage(); }
}

// Load data
$programs = [];$apps=[];$documents=[];$payments=[];$notes=[];
if ($error==='') {
  try {
    // Load system settings for gating (voucher mode, etc.)
    $systemSettings = [];
    try {
      $st = $pdo->query("SELECT config_key, config_value FROM system_config");
      foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) { $systemSettings[$row['config_key']] = $row['config_value']; }
    } catch (Throwable $e) { /* ignore */ }

    $programs = $pdo->query("SELECT id,name FROM programs WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare("SELECT a.id,a.status,a.created_at,p.name AS program,p.id AS program_id,p.prospectus_path FROM applications a JOIN programs p ON p.id=a.program_id WHERE a.student_id=? ORDER BY a.created_at DESC");
    $st->execute([$studentId]);
    $apps = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare('SELECT * FROM student_documents WHERE student_id=? ORDER BY created_at DESC');
    $st->execute([$studentId]);
    $documents = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare('SELECT * FROM payments WHERE student_id=? ORDER BY created_at DESC');
    $st->execute([$studentId]);
    $payments = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare('SELECT * FROM notifications WHERE student_id=? ORDER BY created_at DESC');
    $st->execute([$studentId]);
    $notes = $st->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { $error = $e->getMessage(); }
}

$hideTopActions = true;
// Branding and layout similar to admin
$brandColor = $systemSettings['brand_color'] ?? '#2563eb';
$logoPath = $systemSettings['logo_path'] ?? '/uploads/logos/logo.png';
$hasLogo = file_exists($_SERVER['DOCUMENT_ROOT'] . $logoPath);
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/dashboard.css">
<style>
  :root { --brand: <?php echo htmlspecialchars($brandColor); ?>; }
  body { overflow:hidden; }
  .dashboard-content { height: calc(100vh - 60px); overflow:auto; }
  .dashboard-sidebar { top:0; height:100vh; }
</style>

<div class="dashboard-layout">
  <aside class="dashboard-sidebar" id="sidebarNav">
    <a href="/student/dashboard" class="sidebar-logo" aria-label="Home">
      <?php if ($hasLogo): ?>
        <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Logo" class="sidebar-logo-img">
      <?php else: ?>
        <div class="sidebar-logo-placeholder"></div>
      <?php endif; ?>
    </a>
    <div class="sidebar-title">Student</div>
    <a href="?panel=applications" class="sidebar-item <?php echo $currentPanel==='applications'?'active':''; ?>" data-panel="applications"><i class="bi bi-list-check"></i> Applications</a>
    <a href="?panel=documents" class="sidebar-item <?php echo $currentPanel==='documents'?'active':''; ?>" data-panel="documents"><i class="bi bi-folder2"></i> Documents</a>
    <a href="?panel=payments" class="sidebar-item <?php echo $currentPanel==='payments'?'active':''; ?>" data-panel="payments"><i class="bi bi-credit-card"></i> Payments</a>
    <a href="?panel=notifications" class="sidebar-item <?php echo $currentPanel==='notifications'?'active':''; ?>" data-panel="notifications"><i class="bi bi-bell"></i> Notifications</a>
    <a href="?panel=profile" class="sidebar-item <?php echo $currentPanel==='profile'?'active':''; ?>" data-panel="profile"><i class="bi bi-person"></i> Profile</a>
  </aside>

  <main class="dashboard-content" id="panelHost">
    <div class="toolbar">
      <button class="btn secondary mobile-only" id="toggleSidebar"><i class="bi bi-list"></i></button>
      <div class="muted">Student Dashboard</div>
      <div></div>
    </div>

    <div id="panel-applications" class="<?php echo $currentPanel==='applications'?'':'hidden'; ?>" style="<?php echo $currentPanel==='applications'?'display:block':''; ?>">
  <div class="panel-card">
    <h3>My Applications</h3>
    <?php if ($error): ?><div class="card" style="border-left:4px solid #ef4444;margin-bottom:12px"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($message): ?><div class="card" style="border-left:4px solid #10b981;margin-bottom:12px"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

    <div class="card" style="overflow:auto">
      <table style="width:100%;border-collapse:collapse">
        <thead>
          <tr style="text-align:left;border-bottom:1px solid var(--border)">
            <th style="padding:10px">#</th>
            <th style="padding:10px">Program</th>
            <th style="padding:10px">Status</th>
            <th style="padding:10px">Date</th>
            <th style="padding:10px">Prospectus</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$apps): ?>
            <tr><td colspan="4" style="padding:14px" class="muted">No applications yet.</td></tr>
          <?php else: foreach ($apps as $r): ?>
            <tr style="border-bottom:1px solid var(--border)">
              <td style="padding:10px">#<?php echo (int)$r['id']; ?></td>
              <td style="padding:10px"><?php echo htmlspecialchars($r['program']); ?></td>
              <td style="padding:10px"><span class="muted"><?php echo ucwords(str_replace('_',' ', $r['status'])); ?></span></td>
              <td style="padding:10px"><?php echo htmlspecialchars(date('Y-m-d', strtotime($r['created_at']))); ?></td>
              <td style="padding:10px">
                <?php if(($r['status'] ?? '')==='approved' && !empty($r['prospectus_path'])): ?>
                  <a class="btn secondary" href="/api/prospectus.php?program_id=<?php echo (int)$r['program_id']; ?>&application_id=<?php echo (int)$r['id']; ?>">
                    <i class="bi bi-file-earmark-pdf"></i> Download
                  </a>
                <?php else: ?>
                  <span class="muted">N/A</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel-card">
    <h3>Start a New Application</h3>
    <?php
      $modeEffective = ($systemSettings['mode_toggle'] ?? 'pay_after');
      $voucherMode = ($modeEffective === 'voucher') || (($systemSettings['voucher_required'] ?? '0') === '1');
    ?>
    <?php if (!$voucherMode): ?>
    <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="action" value="create_application">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
      <div style="min-width:260px">
        <label class="form-label">Program</label>
        <select class="input" name="program_id" required>
          <option value="">Select program</option>
          <?php foreach ($programs as $p): ?>
            <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn" type="submit"><i class="bi bi-plus-lg"></i> Create</button>
    </form>
    <?php else: ?>
    <div class="muted" style="margin-bottom:8px">A valid voucher is required to start an application.</div>
    <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="action" value="redeem_voucher">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
      <div style="min-width:220px">
        <label class="form-label">Voucher Serial</label>
        <input class="input" name="voucher_serial" placeholder="e.g., VCH-AB12CD34" required>
      </div>
      <div style="min-width:140px">
        <label class="form-label">PIN</label>
        <input class="input" name="voucher_pin" placeholder="1234" minlength="4" maxlength="10" required>
      </div>
      <div style="min-width:220px">
        <label class="form-label">Program</label>
        <select class="input" name="program_id" required>
          <option value="">Select program</option>
          <?php foreach ($programs as $p): ?>
            <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn" type="submit"><i class="bi bi-ticket"></i> Redeem & Create</button>
    </form>
    <?php endif; ?>
  </div>
    </div>

    <div id="panel-documents" class="<?php echo $currentPanel==='documents'?'':'hidden'; ?>" style="<?php echo $currentPanel==='documents'?'display:block':''; ?>">
  <div class="panel-card">
    <h3>My Documents</h3>
    <div class="card" style="overflow:auto">
      <table style="width:100%;border-collapse:collapse">
        <thead>
          <tr style="text-align:left;border-bottom:1px solid var(--border)"><th style="padding:10px">Type</th><th style="padding:10px">File</th><th style="padding:10px">Status</th><th style="padding:10px">Date</th></tr>
        </thead>
        <tbody>
          <?php if(!$documents): ?>
            <tr><td colspan="4" style="padding:14px" class="muted">No documents uploaded.</td></tr>
          <?php else: foreach($documents as $d): ?>
            <tr style="border-bottom:1px solid var(--border)">
              <td style="padding:10px"><?php echo htmlspecialchars($d['doc_type']); ?></td>
              <td style="padding:10px"><a href="<?php echo htmlspecialchars($d['file_path']); ?>" target="_blank">View</a></td>
              <td style="padding:10px"><span class="muted"><?php echo htmlspecialchars($d['status']); ?></span></td>
              <td style="padding:10px"><?php echo htmlspecialchars(date('Y-m-d', strtotime($d['created_at']))); ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="panel-card">
    <h3>Upload Document</h3>
    <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="action" value="upload_document">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
      <div><label class="form-label">Type</label><input class="input" name="doc_type" placeholder="e.g., Transcript" required></div>
      <div><label class="form-label">Application (optional)</label>
        <select class="input" name="application_id">
          <option value="">None</option>
          <?php foreach($apps as $r): ?><option value="<?php echo (int)$r['id']; ?>">#<?php echo (int)$r['id']; ?> - <?php echo htmlspecialchars($r['program']); ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label class="form-label">File (PDF/JPG/PNG)</label><input class="input" type="file" name="file" required></div>
      <button class="btn" type="submit"><i class="bi bi-upload"></i> Upload</button>
    </form>
  </div>
    </div>

    <div id="panel-payments" class="<?php echo $currentPanel==='payments'?'':'hidden'; ?>" style="<?php echo $currentPanel==='payments'?'display:block':''; ?>">
  <div class="panel-card">
    <h3>Payments</h3>
    <div class="card" style="overflow:auto">
      <table style="width:100%;border-collapse:collapse">
        <thead>
          <tr style="text-align:left;border-bottom:1px solid var(--border)"><th style="padding:10px">Receipt</th><th style="padding:10px">Amount</th><th style="padding:10px">Method</th><th style="padding:10px">Status</th><th style="padding:10px">Date</th></tr>
        </thead>
        <tbody>
          <?php if(!$payments): ?>
            <tr><td colspan="5" style="padding:14px" class="muted">No payments found.</td></tr>
          <?php else: foreach($payments as $p): ?>
            <tr style="border-bottom:1px solid var(--border)">
              <td style="padding:10px"><?php echo htmlspecialchars($p['receipt_number'] ?? ''); ?></td>
              <td style="padding:10px"><?php echo 'GHS '.number_format((float)($p['amount'] ?? 0),2); ?></td>
              <td style="padding:10px"><?php echo htmlspecialchars($p['payment_method'] ?? ''); ?></td>
              <td style="padding:10px"><span class="muted"><?php echo htmlspecialchars($p['status'] ?? ''); ?></span></td>
              <td style="padding:10px"><?php echo htmlspecialchars(date('Y-m-d', strtotime($p['created_at']))); ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
    </div>

    <div id="panel-notifications" class="<?php echo $currentPanel==='notifications'?'':'hidden'; ?>" style="<?php echo $currentPanel==='notifications'?'display:block':''; ?>">
  <div class="panel-card">
    <h3>Notifications</h3>
    <div class="card" style="overflow:auto">
      <?php if(!$notes): ?>
        <div class="muted">No notifications.</div>
      <?php else: foreach($notes as $n): ?>
        <div style="padding:12px;border-bottom:1px solid var(--border)">
          <div style="font-weight:500;margin-bottom:4px"><?php echo htmlspecialchars($n['title'] ?? 'Notification'); ?></div>
          <div class="muted" style="font-size:14px"><?php echo htmlspecialchars($n['message'] ?? ''); ?></div>
          <div class="muted" style="font-size:12px"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($n['created_at']))); ?></div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
    </div>

    <div id="panel-profile" class="<?php echo $currentPanel==='profile'?'':'hidden'; ?>" style="<?php echo $currentPanel==='profile'?'display:block':''; ?>">
  <div class="panel-card">
    <h3>Profile</h3>
    <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px">
      <input type="hidden" name="action" value="change_password">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
      <div>
        <label class="form-label">New Password</label>
        <input class="input" type="password" name="new_password" minlength="8" required>
      </div>
      <div>
        <label class="form-label">Confirm Password</label>
        <input class="input" type="password" name="confirm_password" minlength="8" required>
      </div>
      <div style="display:flex;align-items:flex-end"><button class="btn" type="submit"><i class="bi bi-key"></i> Update Password</button></div>
    </form>
  </div>
    </div>
  </main>
</div>

<script>
(function(){
  const sidebar = document.getElementById('sidebarNav');
  const panelHost = document.getElementById('panelHost');
  const navItems = document.querySelectorAll('#sidebarNav .sidebar-item');
  function showPanel(panel){
    const panels = panelHost.querySelectorAll('[id^="panel-"]');
    panels.forEach(p=>{ p.classList.add('hidden'); p.style.display='none'; });
    const target = document.getElementById('panel-'+panel);
    if (target){ target.classList.remove('hidden'); target.style.display='block'; }
    navItems.forEach(a=>a.classList.toggle('active', a.dataset.panel===panel));
    try { const url=new URL(window.location.href); url.searchParams.set('panel', panel); url.hash=''; history.replaceState({},'',url.toString()); } catch(e){}
  }
  if (sidebar){
    sidebar.addEventListener('click', function(e){
      const link = e.target.closest('.sidebar-item');
      if (!link) return;
      e.preventDefault();
      showPanel(link.dataset.panel);
      if (window.innerWidth <= 1024 && sidebar.classList.contains('show')) sidebar.classList.remove('show');
    });
  }
  const toggleButtons = document.querySelectorAll('#toggleSidebar, #sidebarToggleTop');
  toggleButtons.forEach(b=> b && b.addEventListener('click', function(e){ e.stopPropagation(); sidebar.classList.toggle('show'); }));
  document.addEventListener('click', function(e){ if (window.innerWidth<=1024 && sidebar.classList.contains('show') && !sidebar.contains(e.target)) sidebar.classList.remove('show'); });
  document.addEventListener('keydown', function(e){ if (e.key==='Escape' && sidebar.classList.contains('show')) sidebar.classList.remove('show'); });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>


