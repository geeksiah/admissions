<?php
// Notifications panel - System notifications and communications

$msg=''; $type='';
try { 
  $pdo->exec("CREATE TABLE IF NOT EXISTS notification_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('email', 'sms', 'push') DEFAULT 'email',
    subject VARCHAR(200),
    body TEXT NOT NULL,
    variables JSON,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  
  $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED,
    student_id INT UNSIGNED,
    type ENUM('email', 'sms', 'push', 'system') DEFAULT 'system',
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    sent_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user(user_id),
    INDEX idx_student(student_id),
    INDEX idx_read(is_read)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  
  $pdo->exec("CREATE TABLE IF NOT EXISTS message_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_type ENUM('user', 'student', 'bulk') DEFAULT 'user',
    recipient_id INT UNSIGNED,
    recipient_email VARCHAR(150),
    recipient_phone VARCHAR(50),
    type ENUM('email', 'sms') NOT NULL,
    subject VARCHAR(200),
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    scheduled_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status(status),
    INDEX idx_scheduled(scheduled_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  
  // Insert default templates
  $templates = [
    ['Application Received', 'email', 'Application Received - {{institution_name}}', 'Dear {{student_name}},\n\nThank you for submitting your application to {{institution_name}}. Your application has been received and is currently under review.\n\nApplication Number: {{application_number}}\nProgram: {{program_name}}\n\nWe will notify you of any updates via email.\n\nBest regards,\nAdmissions Team', '["student_name", "institution_name", "application_number", "program_name"]'],
    ['Payment Confirmed', 'email', 'Payment Confirmed - {{institution_name}}', 'Dear {{student_name}},\n\nYour payment has been successfully confirmed.\n\nReceipt Number: {{receipt_number}}\nAmount: {{amount}}\nPayment Method: {{payment_method}}\n\nThank you for your payment.\n\nBest regards,\nFinance Team', '["student_name", "receipt_number", "amount", "payment_method"]'],
    ['Admission Decision', 'email', 'Admission Decision - {{institution_name}}', 'Dear {{student_name}},\n\nWe are pleased to inform you that your application has been {{decision}}.\n\nProgram: {{program_name}}\nApplication Number: {{application_number}}\n\n{{#if approved}}Please log in to your portal to accept your admission offer.{{else}}Thank you for your interest in our institution.{{/if}}\n\nBest regards,\nAdmissions Team', '["student_name", "decision", "program_name", "application_number", "approved"]'],
    ['Document Reminder', 'sms', null, 'Hi {{student_name}}, please upload missing documents for your application {{application_number}}. Visit your portal to complete. - {{institution_name}}', '["student_name", "application_number"]'],
    ['Payment Reminder', 'sms', null, 'Hi {{student_name}}, payment for application {{application_number}} is due. Amount: {{amount}}. Pay now to avoid delays. - {{institution_name}}', '["student_name", "application_number", "amount"]']
  ];
  
  foreach ($templates as [$name, $type, $subject, $body, $variables]) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO notification_templates (name, type, subject, body, variables) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $type, $subject, $body, $variables]);
  }
} catch (Throwable $e) { /* ignore */ }

// Handle actions
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? ($_GET['action'] ?? '');
  try {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) { 
      throw new RuntimeException('Invalid request'); 
    }
    
    if ($action==='send_notification') {
      $type = $_POST['notification_type'] ?? 'system';
      $title = trim($_POST['title'] ?? '');
      $message = trim($_POST['message'] ?? '');
      $recipients = $_POST['recipients'] ?? [];
      
      if (!$title || !$message) {
        throw new RuntimeException('Title and message are required');
      }
      
      if (empty($recipients)) {
        throw new RuntimeException('At least one recipient is required');
      }
      
      // Create notifications for each recipient
      foreach ($recipients as $recipientId) {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$recipientId, $type, $title, $message]);
      }
      
      $msg='Notifications sent successfully'; $type='success';
      
    } elseif ($action==='send_email') {
      $templateId = (int)($_POST['template_id'] ?? 0);
      $recipientEmail = trim($_POST['recipient_email'] ?? '');
      $varsRaw = $_POST['variables'] ?? '[]';
      $variables = is_array($varsRaw) ? $varsRaw : (json_decode($varsRaw, true) ?: []);
      
      if (!$templateId || !$recipientEmail) {
        throw new RuntimeException('Template and recipient email are required');
      }
      
      // Get template
      $stmt = $pdo->prepare("SELECT * FROM notification_templates WHERE id = ?");
      $stmt->execute([$templateId]);
      $template = $stmt->fetch(PDO::FETCH_ASSOC);
      
      if (!$template) {
        throw new RuntimeException('Template not found');
      }
      
      // Process template variables
      $subject = $template['subject'];
      $body = $template['body'];
      
      foreach ($variables as $key => $value) {
        $subject = str_replace("{{$key}}", $value, $subject);
        $body = str_replace("{{$key}}", $value, $body);
      }
      
      // Queue email
      $stmt = $pdo->prepare("INSERT INTO message_queue (recipient_type, recipient_email, type, subject, message, status) VALUES ('user', ?, 'email', ?, ?, 'pending')");
      $stmt->execute([$recipientEmail, $subject, $body]);
      
      $msg='Email queued for sending'; $type='success';
      
    } elseif ($action==='send_sms') {
      $templateId = (int)($_POST['template_id'] ?? 0);
      $recipientPhone = trim($_POST['recipient_phone'] ?? '');
      $varsRaw = $_POST['variables'] ?? '[]';
      $variables = is_array($varsRaw) ? $varsRaw : (json_decode($varsRaw, true) ?: []);
      
      if (!$templateId || !$recipientPhone) {
        throw new RuntimeException('Template and recipient phone are required');
      }
      
      // Get template
      $stmt = $pdo->prepare("SELECT * FROM notification_templates WHERE id = ?");
      $stmt->execute([$templateId]);
      $template = $stmt->fetch(PDO::FETCH_ASSOC);
      
      if (!$template) {
        throw new RuntimeException('Template not found');
      }
      
      // Process template variables
      $body = $template['body'];
      foreach ($variables as $key => $value) {
        $body = str_replace("{{$key}}", $value, $body);
      }
      
      // Queue SMS
      $stmt = $pdo->prepare("INSERT INTO message_queue (recipient_type, recipient_phone, type, message, status) VALUES ('user', ?, 'sms', ?, 'pending')");
      $stmt->execute([$recipientPhone, $body]);
      
      $msg='SMS queued for sending'; $type='success';
      
    } elseif ($action==='save_template') {
      $name = trim($_POST['template_name'] ?? '');
      $type = $_POST['template_type'] ?? 'email';
      $subject = trim($_POST['template_subject'] ?? '');
      $body = trim($_POST['template_body'] ?? '');
      
      if (!$name || !$body) {
        throw new RuntimeException('Template name and body are required');
      }
      
      $stmt = $pdo->prepare("INSERT INTO notification_templates (name, type, subject, body) VALUES (?, ?, ?, ?)");
      $stmt->execute([$name, $type, $subject, $body]);
      
      $msg='Template saved successfully'; $type='success';
    } elseif ($action==='update_template') {
      $id = (int)($_POST['id'] ?? 0);
      $name = trim($_POST['template_name'] ?? '');
      $templateType = $_POST['template_type'] ?? 'email';
      $subject = trim($_POST['template_subject'] ?? '');
      $body = trim($_POST['template_body'] ?? '');
      $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : null;
      if (!$id || !$name || !$body) { throw new RuntimeException('Template ID, name and body are required'); }
      $sql = "UPDATE notification_templates SET name=?, type=?, subject=?, body=?" . ($isActive!==null?", is_active=?":"") . " WHERE id=?";
      $params = [$name, $templateType, $subject, $body];
      if ($isActive!==null) { $params[] = $isActive; }
      $params[] = $id;
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      $msg='Template updated successfully'; $type='success';
    } elseif ($action==='toggle_template') {
      $id = (int)($_POST['id'] ?? 0);
      $isActive = (int)($_POST['is_active'] ?? 0);
      if (!$id) { throw new RuntimeException('Template ID required'); }
      $stmt = $pdo->prepare("UPDATE notification_templates SET is_active=? WHERE id=?");
      $stmt->execute([$isActive, $id]);
      $msg='Template status updated'; $type='success';
    } elseif ($action==='delete_template') {
      $id = (int)($_POST['id'] ?? 0);
      if (!$id) { throw new RuntimeException('Template ID required'); }
      $stmt = $pdo->prepare("DELETE FROM notification_templates WHERE id=?");
      $stmt->execute([$id]);
      $msg='Template deleted'; $type='success';
    } elseif ($action==='dedupe_templates') {
      // Keep the most recent (highest id) per (name,type), delete others
      $dups = $pdo->query("SELECT name, type, MAX(id) AS keep_id, COUNT(*) AS cnt FROM notification_templates GROUP BY name, type HAVING cnt>1")->fetchAll(PDO::FETCH_ASSOC);
      $removed = 0;
      foreach ($dups as $d) {
        $del = $pdo->prepare("DELETE FROM notification_templates WHERE name=? AND type=? AND id<>?");
        $del->execute([$d['name'], $d['type'], $d['keep_id']]);
        $removed += $del->rowCount();
      }
      $msg = $removed ? ("Deduplicated templates. Removed ".$removed." duplicates.") : 'No duplicates found';
      $type='success';
    }
  } catch (Throwable $e) { 
    $msg='Failed: '.$e->getMessage(); 
    $type='danger'; 
  }
}

// Fetch recent notifications
$notifications = [];
try {
  $stmt = $pdo->query("
    SELECT n.*, u.username 
    FROM notifications n 
    LEFT JOIN users u ON n.user_id = u.id 
    ORDER BY n.created_at DESC 
    LIMIT 50
  ");
  $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* ignore */ }

// Fetch templates
$templates = [];
try {
  $stmt = $pdo->query("SELECT * FROM notification_templates ORDER BY name");
  $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* ignore */ }

// Fetch users for recipient selection
$users = [];
try {
  $stmt = $pdo->query("SELECT id, username, first_name, last_name FROM users WHERE is_active = 1 ORDER BY first_name, last_name");
  $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* ignore */ }

// Fetch queue status
$queueStats = ['pending' => 0, 'sent' => 0, 'failed' => 0];
try {
  $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM message_queue GROUP BY status");
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $queueStats[$row['status']] = (int)$row['count'];
  }
} catch (Throwable $e) { /* ignore */ }

// Stats
$stats = [
  'total_notifications' => count($notifications),
  'unread_notifications' => count(array_filter($notifications, fn($n) => !$n['is_read'])),
  'pending_messages' => $queueStats['pending'],
  'sent_messages' => $queueStats['sent']
];
?>

<?php if($msg): ?>
<script>document.addEventListener('DOMContentLoaded',function(){ clearToasts(); toast({ message: <?php echo json_encode($msg); ?>, variant: '<?php echo $type==='success'?'success':'error'; ?>' }); });</script>
<?php endif; ?>

<!-- Notification Stats -->
<div class="stat-grid">
  <div class="stat-card">
    <h4 class="stat-card-title">Total Notifications</h4>
    <div class="stat-card-value"><?php echo number_format($stats['total_notifications']); ?></div>
  </div>
  <div class="stat-card">
    <h4 class="stat-card-title">Unread</h4>
    <div class="stat-card-value"><?php echo number_format($stats['unread_notifications']); ?></div>
  </div>
  <div class="stat-card">
    <h4 class="stat-card-title">Pending Messages</h4>
    <div class="stat-card-value"><?php echo number_format($stats['pending_messages']); ?></div>
  </div>
  <div class="stat-card">
    <h4 class="stat-card-title">Sent Today</h4>
    <div class="stat-card-value"><?php echo number_format($stats['sent_messages']); ?></div>
  </div>
</div>

<!-- Send Notification -->
<div class="panel-card">
  <h3>Send System Notification</h3>
  <form method="post" action="?panel=notifications" style="display:grid;grid-template-columns:1fr;gap:16px">
    <input type="hidden" name="action" value="send_notification">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    
    <div>
      <label class="form-label">Notification Type</label>
      <select class="input" name="notification_type">
        <option value="system">System Notification</option>
        <option value="email">Email</option>
        <option value="sms">SMS</option>
      </select>
    </div>
    
    <div>
      <label class="form-label">Title *</label>
      <input class="input" name="title" required placeholder="Notification title">
    </div>
    
    <div>
      <label class="form-label">Message *</label>
      <textarea class="input" name="message" rows="4" required placeholder="Notification message"></textarea>
    </div>
    
    <div>
      <label class="form-label">Recipients *</label>
      <div style="max-height:200px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:8px">
        <?php foreach($users as $user): ?>
          <label style="display:flex;align-items:center;gap:8px;padding:4px 0">
            <input type="checkbox" name="recipients[]" value="<?php echo (int)$user['id']; ?>">
            <span><?php echo htmlspecialchars($user['first_name'].' '.$user['last_name'].' ('.$user['username'].')'); ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    
    <button class="btn" type="submit">
      <i class="bi bi-send"></i> Send Notification
    </button>
  </form>
</div>

<!-- Send Template-Based Message -->
<div class="panel-card">
  <h3>Send Template Message</h3>
  <form method="post" action="?panel=notifications" id="templateForm" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    
    <div>
      <label class="form-label">Template</label>
      <select class="input" name="template_id" id="templateSelect" required onchange="loadTemplate()">
        <option value="">Select Template</option>
        <?php foreach($templates as $template): ?>
          <option value="<?php echo (int)$template['id']; ?>" data-type="<?php echo htmlspecialchars($template['type']); ?>">
            <?php echo htmlspecialchars($template['name']); ?> (<?php echo strtoupper($template['type']); ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    
    <div id="recipientFields">
      <div>
        <label class="form-label">Recipient Email</label>
        <input class="input" name="recipient_email" type="email" placeholder="email@example.com">
      </div>
      <div>
        <label class="form-label">Recipient Phone</label>
        <input class="input" name="recipient_phone" placeholder="+233123456789">
      </div>
    </div>
    
    <div>
      <label class="form-label">Variables (JSON)</label>
      <textarea class="input" name="variables" id="templateVars" rows="3" placeholder='{"student_name": "John Doe", "amount": "500.00"}'></textarea>
      <div class="muted" id="varsHint" style="font-size:12px;margin-top:4px"></div>
    </div>
    
    <div style="display:flex;align-items:flex-end">
      <button class="btn" type="submit" id="sendButton" disabled>
        <i class="bi bi-send"></i> Send Message
      </button>
    </div>
  </form>
</div>

<!-- Recent Notifications -->
<div class="panel-card">
  <h3>Recent Notifications</h3>
  <?php if(empty($notifications)): ?>
    <div class="muted">No notifications found.</div>
  <?php else: ?>
    <div class="card" style="overflow:auto;max-height:400px">
      <?php foreach($notifications as $notification): ?>
        <div style="padding:12px;border-bottom:1px solid var(--border);<?php echo !$notification['is_read'] ? 'background:var(--surface-hover)' : ''; ?>">
          <div style="display:flex;justify-content:space-between;align-items:start;gap:12px">
            <div style="flex:1">
              <div style="font-weight:500;margin-bottom:4px"><?php echo htmlspecialchars($notification['title']); ?></div>
              <div class="muted" style="font-size:14px;margin-bottom:4px"><?php echo htmlspecialchars($notification['message']); ?></div>
              <div class="muted" style="font-size:12px">
                <?php if($notification['username']): ?>
                  To: <?php echo htmlspecialchars($notification['username']); ?> • 
                <?php endif; ?>
                <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
              </div>
            </div>
            <div style="display:flex;gap:8px;align-items:center">
              <span style="font-size:12px;padding:2px 6px;border-radius:4px;background:var(--surface-hover)">
                <?php echo strtoupper($notification['type']); ?>
              </span>
              <?php if(!$notification['is_read']): ?>
                <span style="width:8px;height:8px;border-radius:50%;background:#ef4444"></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Message Templates -->
<div class="panel-card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
    <h3>Message Templates</h3>
    <form method="post" action="?panel=notifications">
      <input type="hidden" name="action" value="dedupe_templates">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
      <button class="btn secondary" type="submit"><i class="bi bi-magic"></i> Deduplicate</button>
    </form>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px">
    <?php foreach($templates as $template): ?>
      <div style="border:1px solid var(--border);border-radius:8px;padding:16px">
        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px">
          <div>
            <div style="font-weight:500"><?php echo htmlspecialchars($template['name']); ?></div>
            <div class="muted" style="font-size:12px"><?php echo strtoupper($template['type']); ?></div>
          </div>
          <span style="font-size:12px;padding:2px 6px;border-radius:4px;background:var(--surface-hover)">
            <?php echo $template['is_active'] ? 'Active' : 'Inactive'; ?>
          </span>
        </div>
        
        <?php if($template['subject']): ?>
          <div style="margin-bottom:8px">
            <div style="font-size:12px;color:var(--muted)">Subject:</div>
            <div style="font-size:14px"><?php echo htmlspecialchars($template['subject']); ?></div>
          </div>
        <?php endif; ?>
        
        <div style="margin-bottom:8px">
          <div style="font-size:12px;color:var(--muted)">Message:</div>
          <div style="font-size:14px;max-height:100px;overflow:hidden"><?php echo htmlspecialchars(substr($template['body'], 0, 200)); ?><?php echo strlen($template['body']) > 200 ? '...' : ''; ?></div>
        </div>
        
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
          <form method="post" action="?panel=notifications" style="display:inline">
            <input type="hidden" name="action" value="toggle_template">
            <input type="hidden" name="id" value="<?php echo (int)$template['id']; ?>">
            <input type="hidden" name="is_active" value="<?php echo $template['is_active']?0:1; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <button class="btn secondary" type="submit"><i class="bi bi-toggle2-<?php echo $template['is_active']?'on':'off'; ?>"></i> <?php echo $template['is_active']?'Deactivate':'Activate'; ?></button>
          </form>
          <button class="btn secondary" type="button" onclick="editTemplate(<?php echo (int)$template['id']; ?>)"><i class="bi bi-pencil"></i> Edit</button>
          <form method="post" action="?panel=notifications" style="display:inline" data-confirm="Delete this template?">
            <input type="hidden" name="action" value="delete_template">
            <input type="hidden" name="id" value="<?php echo (int)$template['id']; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <button class="btn secondary" type="submit" style="color:#ef4444"><i class="bi bi-trash"></i> Delete</button>
          </form>
        </div>

        <form id="templateEditForm-<?php echo (int)$template['id']; ?>" method="post" action="?panel=notifications" style="display:none;margin-top:12px;gap:12px">
          <input type="hidden" name="action" value="update_template">
          <input type="hidden" name="id" value="<?php echo (int)$template['id']; ?>">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
            <div><label class="form-label">Name</label><input class="input" name="template_name" value="<?php echo htmlspecialchars($template['name']); ?>" required></div>
            <div><label class="form-label">Type</label><select class="input" name="template_type"><option value="email" <?php echo $template['type']==='email'?'selected':''; ?>>Email</option><option value="sms" <?php echo $template['type']==='sms'?'selected':''; ?>>SMS</option><option value="push" <?php echo $template['type']==='push'?'selected':''; ?>>Push</option></select></div>
            <div style="grid-column:1/-1"><label class="form-label">Subject</label><input class="input" name="template_subject" value="<?php echo htmlspecialchars($template['subject']); ?>"></div>
            <div style="grid-column:1/-1"><label class="form-label">Body</label><textarea class="input" name="template_body" rows="4" required><?php echo htmlspecialchars($template['body']); ?></textarea></div>
            <div><label class="form-label">Active</label><select class="input" name="is_active"><option value="1" <?php echo $template['is_active']?'selected':''; ?>>Yes</option><option value="0" <?php echo !$template['is_active']?'selected':''; ?>>No</option></select></div>
          </div>
          <div style="margin-top:8px"><button class="btn" type="submit"><i class="bi bi-save"></i> Save</button></div>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Create New Template -->
<div class="panel-card">
  <h3>Create New Template</h3>
  <form method="post" action="?panel=notifications" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px">
    <input type="hidden" name="action" value="save_template">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    
    <div>
      <label class="form-label">Template Name *</label>
      <input class="input" name="template_name" required placeholder="e.g., Welcome Email">
    </div>
    
    <div>
      <label class="form-label">Type</label>
      <select class="input" name="template_type">
        <option value="email">Email</option>
        <option value="sms">SMS</option>
        <option value="push">Push Notification</option>
      </select>
    </div>
    
    <div style="grid-column:1/-1">
      <label class="form-label">Subject (for email)</label>
      <input class="input" name="template_subject" placeholder="Use {{variable}} for dynamic content">
    </div>
    
    <div style="grid-column:1/-1">
      <label class="form-label">Message Body *</label>
      <textarea class="input" name="template_body" rows="6" required placeholder="Use {{variable}} for dynamic content. Available variables will be shown based on template type."></textarea>
    </div>
    
    <div style="grid-column:1/-1">
      <button class="btn" type="submit">
        <i class="bi bi-plus-lg"></i> Create Template
      </button>
    </div>
  </form>
</div>

<script>
function loadTemplate() {
  const select = document.getElementById('templateSelect');
  const templateId = select.value;
  const templateType = select.options[select.selectedIndex]?.dataset.type;
  
  if (!templateId) {
    document.getElementById('sendButton').disabled = true;
    return;
  }
  
  // Update form action based on template type
  const form = document.getElementById('templateForm');
  const recipientFields = document.getElementById('recipientFields');
  
  if (templateType === 'email') {
    form.action = '?panel=notifications&action=send_email';
    recipientFields.innerHTML = `
      <div>
        <label class="form-label">Recipient Email *</label>
        <input class="input" name="recipient_email" type="email" required placeholder="email@example.com">
      </div>
    `;
  } else if (templateType === 'sms') {
    form.action = '?panel=notifications&action=send_sms';
    recipientFields.innerHTML = `
      <div>
        <label class="form-label">Recipient Phone *</label>
        <input class="input" name="recipient_phone" required placeholder="+233123456789">
      </div>
    `;
  }
  
  document.getElementById('sendButton').disabled = false;
}

// Auto-refresh notifications every 30 seconds
setInterval(() => {
  // In a real implementation, this would fetch new notifications via AJAX
  console.log('Checking for new notifications...');
}, 30000);

// Validate variable JSON with live hint
document.addEventListener('input', function(e){
  if (e.target && e.target.id === 'templateVars') {
    var hint = document.getElementById('varsHint');
    try { JSON.parse(e.target.value || '{}'); hint.textContent = 'Variables OK'; hint.style.color = '#10b981'; }
    catch(err){ hint.textContent = 'Invalid JSON'; hint.style.color = '#ef4444'; }
  }
});
</script>
