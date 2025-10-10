<?php
// Communications panel - Composer and delivery queue

$msg=''; $type='';

try {
  // Ensure queue table
  $pdo->exec("CREATE TABLE IF NOT EXISTS message_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_type ENUM('user','student','manual') DEFAULT 'user',
    recipient_id INT UNSIGNED NULL,
    recipient_email VARCHAR(150) NULL,
    recipient_phone VARCHAR(50) NULL,
    type ENUM('email','sms') NOT NULL,
    subject VARCHAR(200) NULL,
    message TEXT NOT NULL,
    status ENUM('pending','sent','failed','cancelled') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    scheduled_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status(status), INDEX idx_type(type), INDEX idx_sched(scheduled_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) { /* ignore */ }

// Handle actions
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  try {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) { throw new RuntimeException('Invalid request'); }

    if ($action==='queue_send') {
      $msgType = $_POST['msg_type'] ?? 'email';
      $subject = trim($_POST['subject'] ?? '');
      $body = trim($_POST['body'] ?? '');
      $recipientMode = $_POST['recipient_mode'] ?? 'user';
      $schedule = trim($_POST['schedule'] ?? '');
      if ($msgType==='email' && $subject==='') { throw new RuntimeException('Subject required for email'); }
      if ($body==='') { throw new RuntimeException('Message body required'); }

      $scheduledAt = $schedule ? date('Y-m-d H:i:s', strtotime($schedule)) : null;

      $queued = 0;
      if ($recipientMode==='user') {
        $recipients = $_POST['user_ids'] ?? [];
        if (empty($recipients)) throw new RuntimeException('Select at least one user');
        $stmt = $pdo->prepare("INSERT INTO message_queue (recipient_type, recipient_id, type, subject, message, status, scheduled_at) VALUES ('user', ?, ?, ?, ?, 'pending', ?)");
        foreach ($recipients as $rid) { $stmt->execute([(int)$rid, $msgType, $subject, $body, $scheduledAt]); $queued++; }
      } elseif ($recipientMode==='student') {
        $emails = array_filter(array_map('trim', explode(',', $_POST['student_emails'] ?? '')));
        if (empty($emails)) throw new RuntimeException('Provide at least one student email/phone');
        $stmt = $pdo->prepare("INSERT INTO message_queue (recipient_type, recipient_email, type, subject, message, status, scheduled_at) VALUES ('student', ?, ?, ?, ?, 'pending', ?)");
        foreach ($emails as $em) { $stmt->execute([$em, $msgType, $subject, $body, $scheduledAt]); $queued++; }
      } else { // manual
        $manual = array_filter(array_map('trim', preg_split('/[\n,;]+/', $_POST['manual_list'] ?? '')));
        if (empty($manual)) throw new RuntimeException('Provide recipients');
        $stmt = $pdo->prepare("INSERT INTO message_queue (recipient_type, recipient_email, recipient_phone, type, subject, message, status, scheduled_at) VALUES ('manual', ?, ?, ?, ?, ?, 'pending', ?)");
        foreach ($manual as $entry) {
          $email = strpos($entry, '@') !== false ? $entry : null;
          $phone = strpos($entry, '@') === false ? $entry : null;
          $stmt->execute([$email, $phone, $msgType, $subject, $body, $scheduledAt]); $queued++;
        }
      }
      $msg = "Queued {$queued} message(s)"; $type='success';

    } elseif ($action==='queue_cancel') {
      $id = (int)($_POST['id'] ?? 0);
      if (!$id) throw new RuntimeException('ID required');
      $pdo->prepare("UPDATE message_queue SET status='cancelled' WHERE id=? AND status='pending'")->execute([$id]);
      $msg='Message cancelled'; $type='success';

    } elseif ($action==='queue_retry') {
      $id = (int)($_POST['id'] ?? 0);
      if (!$id) throw new RuntimeException('ID required');
      $pdo->prepare("UPDATE message_queue SET status='pending', attempts=0, error_message=NULL WHERE id=? AND status IN ('failed','cancelled')")->execute([$id]);
      $msg='Message set to retry'; $type='success';
    }
  } catch (Throwable $e) { $msg='Failed: '.$e->getMessage(); $type='danger'; }
}

// Filters
$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$mtype = trim($_GET['type'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 20; $offset = ($page-1)*$per;

$where=[]; $params=[];
if ($q!=='') { $where[] = '(recipient_email LIKE ? OR recipient_phone LIKE ? OR subject LIKE ? OR message LIKE ?)'; $params = array_merge($params, ["%$q%","%$q%","%$q%","%$q%"]); }
if ($status!=='') { $where[] = 'status=?'; $params[]=$status; }
if ($mtype!=='') { $where[] = 'type=?'; $params[]=$mtype; }
$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

$total=0; try { $st=$pdo->prepare("SELECT COUNT(*) FROM message_queue $whereSql"); $st->execute($params); $total=(int)$st->fetchColumn(); } catch(Throwable $e){}
$pages = max(1, (int)ceil($total/$per));

$queue=[]; try {
  $st=$pdo->prepare("SELECT * FROM message_queue $whereSql ORDER BY COALESCE(scheduled_at, created_at) DESC, id DESC LIMIT $offset,$per");
  $st->execute($params); $queue=$st->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e){}

// Users list for composer
$users=[]; try { $st=$pdo->query("SELECT id, username, first_name, last_name FROM users WHERE is_active=1 ORDER BY first_name, last_name"); $users=$st->fetchAll(PDO::FETCH_ASSOC);} catch(Throwable $e){}
?>

<?php if($msg): ?>
<script>document.addEventListener('DOMContentLoaded',function(){ clearToasts(); toast({ message: <?php echo json_encode($msg); ?>, variant: '<?php echo $type==='success'?'success':'error'; ?>' }); });</script>
<?php endif; ?>

<!-- Composer -->
<div class="panel-card">
  <h3>Compose Message</h3>
  <form method="post" action="?panel=communications" id="composer" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px">
    <input type="hidden" name="action" value="queue_send">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">

    <div>
      <label class="form-label">Type</label>
      <select class="input" name="msg_type" id="msgType">
        <option value="email">Email</option>
        <option value="sms">SMS</option>
      </select>
    </div>
    <div>
      <label class="form-label">Recipients</label>
      <select class="input" name="recipient_mode" id="recipientMode">
        <option value="user">Users (select below)</option>
        <option value="student">Student emails/phones (comma-separated)</option>
        <option value="manual">Manual list (newline/comma separated)</option>
      </select>
    </div>
    <div>
      <label class="form-label">Schedule (optional)</label>
      <input class="input" type="datetime-local" name="schedule">
    </div>
    <div style="grid-column:1/-1" id="recipientBlock">
      <div class="muted" style="font-size:12px;margin-bottom:4px">Select Users</div>
      <div style="max-height:220px;overflow:auto;border:1px solid var(--border);border-radius:8px;padding:8px;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:4px">
        <?php foreach($users as $u): ?>
        <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="user_ids[]" value="<?php echo (int)$u['id']; ?>"> <span><?php echo htmlspecialchars(($u['first_name']??'').' '.($u['last_name']??'').' ('.($u['username']??'').')'); ?></span></label>
        <?php endforeach; ?>
      </div>
    </div>

    <div id="studentBlock" style="display:none;grid-column:1/-1">
      <label class="form-label">Student emails/phones (comma-separated)</label>
      <textarea class="input" name="student_emails" rows="3" placeholder="email1@example.com, +233123456789"></textarea>
    </div>

    <div id="manualBlock" style="display:none;grid-column:1/-1">
      <label class="form-label">Manual list</label>
      <textarea class="input" name="manual_list" rows="3" placeholder="One item per line or comma-separated"></textarea>
    </div>

    <div style="grid-column:1/-1" id="subjectBlock">
      <label class="form-label">Subject</label>
      <input class="input" name="subject" placeholder="Subject for email messages">
    </div>

    <div style="grid-column:1/-1">
      <label class="form-label">Message *</label>
      <textarea class="input" name="body" rows="6" placeholder="Your message text" required></textarea>
    </div>

    <div style="grid-column:1/-1;display:flex;gap:10px;justify-content:flex-end">
      <button class="btn" type="submit"><i class="bi bi-send"></i> Queue Message</button>
    </div>
  </form>
</div>

<!-- Queue List -->
<div class="panel-card">
  <h3>Delivery Queue</h3>
  <form method="get" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
    <input type="hidden" name="panel" value="communications">
    <input class="input" name="q" placeholder="Search queue..." value="<?php echo htmlspecialchars($q); ?>" style="min-width:240px">
    <select class="input" name="status"><option value="">All Status</option><option value="pending" <?php echo $status==='pending'?'selected':''; ?>>Pending</option><option value="sent" <?php echo $status==='sent'?'selected':''; ?>>Sent</option><option value="failed" <?php echo $status==='failed'?'selected':''; ?>>Failed</option><option value="cancelled" <?php echo $status==='cancelled'?'selected':''; ?>>Cancelled</option></select>
    <select class="input" name="type"><option value="">All Types</option><option value="email" <?php echo $mtype==='email'?'selected':''; ?>>Email</option><option value="sms" <?php echo $mtype==='sms'?'selected':''; ?>>SMS</option></select>
    <button class="btn" type="submit"><i class="bi bi-search"></i> Filter</button>
  </form>

  <div class="card" style="overflow:auto">
    <table style="width:100%;border-collapse:collapse">
      <thead>
        <tr style="text-align:left;border-bottom:1px solid var(--border)"><th style="padding:10px">Type</th><th style="padding:10px">Recipient</th><th style="padding:10px">Subject/Preview</th><th style="padding:10px">Status</th><th style="padding:10px">Scheduled</th><th style="padding:10px">Actions</th></tr>
      </thead>
      <tbody>
        <?php if(!$queue): ?>
          <tr><td colspan="6" class="muted" style="padding:14px">No queued messages.</td></tr>
        <?php else: foreach($queue as $row): ?>
          <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:10px"><?php echo strtoupper($row['type']); ?></td>
            <td style="padding:10px"><?php echo htmlspecialchars($row['recipient_email'] ?: $row['recipient_phone'] ?: ('#'.$row['recipient_id'])); ?></td>
            <td style="padding:10px"><div style="font-weight:500"><?php echo htmlspecialchars($row['subject'] ?? ''); ?></div><div class="muted" style="font-size:12px;max-width:520px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo htmlspecialchars(substr($row['message'],0,120)); ?></div></td>
            <td style="padding:10px"><span class="muted"><?php echo ucfirst($row['status']); ?></span></td>
            <td style="padding:10px"><span class="muted" style="font-size:12px"><?php echo $row['scheduled_at'] ? date('M j, Y g:i A', strtotime($row['scheduled_at'])) : '—'; ?></span></td>
            <td style="padding:10px;display:flex;gap:6px;flex-wrap:wrap">
              <?php if($row['status']!=='pending'): ?>
                <form method="post" action="?panel=communications" style="display:inline">
                  <input type="hidden" name="action" value="queue_retry"><input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                  <button class="btn secondary" type="submit"><i class="bi bi-arrow-repeat"></i> Retry</button>
                </form>
              <?php endif; ?>
              <?php if($row['status']==='pending'): ?>
                <form method="post" action="?panel=communications" style="display:inline" onsubmit="return confirm('Cancel this message?')">
                  <input type="hidden" name="action" value="queue_cancel"><input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                  <button class="btn secondary" type="submit" style="color:#ef4444"><i class="bi bi-x-circle"></i> Cancel</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if($pages>1): ?>
    <div style="display:flex;gap:6px;justify-content:flex-end;margin-top:10px">
      <?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++): ?>
        <a class="btn secondary" href="?panel=communications&page=<?php echo $i; ?>&q=<?php echo urlencode($q); ?>&status=<?php echo urlencode($status); ?>&type=<?php echo urlencode($mtype); ?>" style="padding:6px 10px;border-radius:6px;<?php echo $i===$page?'background:var(--surface-hover)':''; ?>"><?php echo $i; ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>

<script>
// Recipient mode toggle
document.addEventListener('DOMContentLoaded', function(){
  const mode = document.getElementById('recipientMode');
  if (!mode) return;
  const userB = document.getElementById('recipientBlock');
  const stuB = document.getElementById('studentBlock');
  const manB = document.getElementById('manualBlock');
  mode.addEventListener('change', function(){
    const v = this.value;
    userB.style.display = v==='user' ? 'block' : 'none';
    stuB.style.display = v==='student' ? 'block' : 'none';
    manB.style.display = v==='manual' ? 'block' : 'none';
  });
  const typeSel = document.getElementById('msgType');
  const subj = document.getElementById('subjectBlock');
  typeSel.addEventListener('change', function(){ subj.style.display = this.value==='email' ? 'block' : 'none'; });
});
</script>


