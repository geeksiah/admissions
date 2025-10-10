<?php
// Applications panel - list with filters, pagination, and status management
// Requires $pdo available from dashboard

// Ensure status history table exists
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS application_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    old_status VARCHAR(30),
    new_status VARCHAR(30) NOT NULL,
    changed_by INT UNSIGNED NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_app(application_id),
    INDEX idx_changed(changed_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) { /* ignore */ }

// Handle actions (approve/reject/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) { throw new RuntimeException('Invalid request'); }
    $action = $_POST['action'] ?? '';
    if ($action === 'update_status') {
      $id = (int)($_POST['id'] ?? 0);
      $status = $_POST['status'] ?? 'pending';
      if (!$id || !in_array($status, ['pending','under_review','approved','rejected','waitlisted'], true)) {
        throw new RuntimeException('Invalid status update');
      }
      // Read old status
      $old = null;
      try {
        $st = $pdo->prepare('SELECT status FROM applications WHERE id=?');
        $st->execute([$id]);
        $old = $st->fetchColumn();
      } catch (Throwable $e) {}

      $stmt = $pdo->prepare("UPDATE applications SET status=? WHERE id=?");
      $stmt->execute([$status, $id]);

      // Write history
      try {
        $st = $pdo->prepare('INSERT INTO application_status_history(application_id, old_status, new_status, changed_by) VALUES(?,?,?,?)');
        $st->execute([$id, $old, $status, ($_SESSION['user_id'] ?? null)]);
      } catch (Throwable $e) {}
    } elseif ($action === 'delete_application') {
      $id = (int)($_POST['id'] ?? 0);
      if (!$id) { throw new RuntimeException('Invalid application'); }
      // Log deletion as terminal history entry
      try {
        $st = $pdo->prepare('INSERT INTO application_status_history(application_id, old_status, new_status, changed_by) VALUES(?,?,?,?)');
        // Fetch last known status
        $last = null; $st2 = $pdo->prepare('SELECT status FROM applications WHERE id=?'); $st2->execute([$id]); $last = $st2->fetchColumn();
        $st->execute([$id, $last, 'deleted', ($_SESSION['user_id'] ?? null)]);
      } catch (Throwable $e) {}
      $stmt = $pdo->prepare("DELETE FROM applications WHERE id=?");
      $stmt->execute([$id]);
    }
  } catch (Throwable $e) {
    echo '<div class="card" style="border-left:4px solid #ef4444;margin-bottom:12px">'.htmlspecialchars($e->getMessage()).'</div>';
  }
}

$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$status = trim($_GET['status'] ?? '');
$q = trim($_GET['q'] ?? '');

$where = [];
$params = [];
if ($status !== '') { $where[] = 'a.status = ?'; $params[] = $status; }
if ($q !== '') { $where[] = '(s.first_name LIKE ? OR s.last_name LIKE ? OR p.name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications a LEFT JOIN students s ON s.id=a.student_id LEFT JOIN programs p ON p.id=a.program_id $whereSql");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
} catch (Throwable $e) { $total = 0; }

$pages = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$rows = [];
try {
    $stmt = $pdo->prepare("SELECT a.id, a.status, a.created_at, s.first_name, s.last_name, p.name AS program
        FROM applications a
        LEFT JOIN students s ON s.id=a.student_id
        LEFT JOIN programs p ON p.id=a.program_id
        $whereSql
        ORDER BY a.created_at DESC
        LIMIT $offset, $perPage");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $rows = []; }
?>

<div class="panel-card">
  <h3>Applications</h3>
  <?php if(!empty($msg)): ?>
    <script>document.addEventListener('DOMContentLoaded', function(){ clearToasts(); toast({ message: <?php echo json_encode($msg); ?>, variant: '<?php echo $type==='success'?'success':'error'; ?>' }); });</script>
  <?php endif; ?>
  <form method="get" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
    <input type="hidden" name="panel" value="applications">
    <select name="status" class="input" style="max-width:160px">
      <option value="">All Status</option>
      <?php foreach (["pending","under_review","approved","rejected"] as $opt): ?>
        <option value="<?php echo $opt; ?>" <?php echo $status===$opt?'selected':''; ?>><?php echo ucwords(str_replace('_',' ',$opt)); ?></option>
      <?php endforeach; ?>
    </select>
    <input class="input" name="q" placeholder="Search name/program" value="<?php echo htmlspecialchars($q); ?>" style="min-width:240px">
    <button class="btn" type="submit"><i class="bi bi-search"></i> Filter</button>
  </form>

  <div class="card" style="overflow:auto">
    <table style="width:100%;border-collapse:collapse">
      <thead>
        <tr style="text-align:left;border-bottom:1px solid var(--border)">
          <th style="padding:10px">ID</th>
          <th style="padding:10px">Applicant</th>
          <th style="padding:10px">Program</th>
          <th style="padding:10px">Status</th>
          <th style="padding:10px">Date</th>
          <th style="padding:10px">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="5" style="padding:14px" class="muted">No applications found.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:10px">#<?php echo (int)$r['id']; ?></td>
            <td style="padding:10px"><?php echo htmlspecialchars(trim(($r['first_name']??'').' '.($r['last_name']??''))); ?></td>
            <td style="padding:10px"><?php echo htmlspecialchars($r['program'] ?? ''); ?></td>
            <td style="padding:10px">
              <form method="post" action="?panel=applications" style="display:flex;gap:6px;align-items:center">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <select class="input" name="status" style="max-width:160px">
                  <?php foreach (["pending","under_review","approved","rejected","waitlisted"] as $opt): ?>
                    <option value="<?php echo $opt; ?>" <?php echo $r['status']===$opt?'selected':''; ?>><?php echo ucwords(str_replace('_',' ',$opt)); ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn secondary" type="submit"><i class="bi bi-save"></i></button>
              </form>
            </td>
            <td style="padding:10px"><?php echo htmlspecialchars(date('Y-m-d', strtotime($r['created_at']))); ?></td>
            <td style="padding:10px;display:flex;gap:6px;flex-wrap:wrap">
              <button class="btn secondary" type="button" onclick="viewHistory(<?php echo (int)$r['id']; ?>)"><i class="bi bi-clock-history"></i> History</button>
              <form method="post" action="?panel=applications" onsubmit="return confirm('Delete this application?')">
                <input type="hidden" name="action" value="delete_application">
                <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <button class="btn secondary" type="submit" style="color:#ef4444"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <div style="display:flex;gap:6px;justify-content:flex-end;margin-top:10px">
      <?php for ($i=max(1,$page-2); $i<=min($pages,$page+2); $i++): ?>
        <a class="btn secondary" href="?panel=applications&page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&q=<?php echo urlencode($q); ?>" style="padding:6px 10px;border-radius:6px;<?php echo $i===$page?'background:var(--surface-hover)':''; ?>"><?php echo $i; ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>



<div id="historyModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999">
  <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--card);border-radius:16px;padding:24px;min-width:500px;max-width:90vw;max-height:90vh;overflow:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3>Application History</h3>
      <button onclick="closeHistoryModal()" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
    </div>
    <div id="historyBody" class="muted">Loading...</div>
  </div>
  <script>
    async function viewHistory(appId) {
      const modal = document.getElementById('historyModal');
      const body = document.getElementById('historyBody');
      body.innerHTML = 'Loading...';
      modal.style.display = 'block';
      try {
        const res = await fetch(`/api/application_history.php?application_id=${appId}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.ok) throw new Error(json.error || 'Failed');
        if (!json.data || json.data.length === 0) {
          body.innerHTML = '<div class="muted">No history yet.</div>';
          return;
        }
        const rows = json.data.map(h => {
          const by = (h.username || h.email || 'System');
          return `<div style="padding:10px;border-bottom:1px solid var(--border)">
            <div><strong>${(h.old_status||'—')} → ${h.new_status}</strong></div>
            <div class="muted" style="font-size:12px">${new Date(h.changed_at).toLocaleString()} • by ${by}</div>
          </div>`;
        }).join('');
        body.innerHTML = rows;
      } catch (e) {
        body.innerHTML = '<div style="color:#ef4444">Failed to load history.</div>';
      }
    }
    function closeHistoryModal(){ document.getElementById('historyModal').style.display='none'; }
    document.addEventListener('click', function(e){ if(e.target.id==='historyModal'){ closeHistoryModal(); } });
  </script>
</div>
