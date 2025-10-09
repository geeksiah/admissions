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
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_status(status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
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
      $stmt = $pdo->prepare('INSERT INTO applications(student_id, program_id, status) VALUES(?,?,"pending")');
      $stmt->execute([$studentId, $programId]);
      $message = 'Application created.';
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
    }
  } catch (Throwable $e) { $error = $e->getMessage(); }
}

// Load data
$programs = [];$apps=[];$documents=[];$payments=[];$notes=[];
if ($error==='') {
  try {
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
include __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-content" style="margin-left:0; padding:24px; height:calc(100vh - 60px); overflow:auto;">
  <div class="panel-card">
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
      <a class="btn secondary" href="?panel=applications" style="<?php echo $currentPanel==='applications'?'background:var(--surface-hover)':''; ?>">Applications</a>
      <a class="btn secondary" href="?panel=documents" style="<?php echo $currentPanel==='documents'?'background:var(--surface-hover)':''; ?>">Documents</a>
      <a class="btn secondary" href="?panel=payments" style="<?php echo $currentPanel==='payments'?'background:var(--surface-hover)':''; ?>">Payments</a>
      <a class="btn secondary" href="?panel=notifications" style="<?php echo $currentPanel==='notifications'?'background:var(--surface-hover)':''; ?>">Notifications</a>
      <a class="btn secondary" href="?panel=profile" style="<?php echo $currentPanel==='profile'?'background:var(--surface-hover)':''; ?>">Profile</a>
    </div>
  </div>

  <?php if ($currentPanel==='applications'): ?>
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
  </div>
  <?php endif; ?>

  <?php if ($currentPanel==='documents'): ?>
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
  <?php endif; ?>

  <?php if ($currentPanel==='payments'): ?>
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
  <?php endif; ?>

  <?php if ($currentPanel==='notifications'): ?>
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
  <?php endif; ?>

  <?php if ($currentPanel==='profile'): ?>
  <div class="panel-card">
    <h3>Profile</h3>
    <p class="muted">Coming soon.</p>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>


