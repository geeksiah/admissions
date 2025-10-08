<?php
// Audit Trail panel - System activity logging and monitoring

$msg=''; $type='';
try { 
  $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50),
    resource_id INT UNSIGNED,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    session_id VARCHAR(128),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user(user_id),
    INDEX idx_action(action),
    INDEX idx_resource(resource_type, resource_id),
    INDEX idx_created(created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  
  $pdo->exec("CREATE TABLE IF NOT EXISTS login_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED,
    username VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent TEXT,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_time TIMESTAMP NULL,
    session_duration INT,
    status ENUM('success', 'failed', 'locked') DEFAULT 'success',
    failure_reason VARCHAR(200),
    INDEX idx_user(user_id),
    INDEX idx_ip(ip_address),
    INDEX idx_time(login_time)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  
  $pdo->exec("CREATE TABLE IF NOT EXISTS system_errors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    error_type VARCHAR(50),
    error_message TEXT,
    error_file VARCHAR(255),
    error_line INT,
    stack_trace TEXT,
    user_id INT UNSIGNED,
    ip_address VARCHAR(45),
    request_uri VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type(error_type),
    INDEX idx_user(user_id),
    INDEX idx_created(created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) { /* ignore */ }

// Handle actions
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  try {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) { 
      throw new RuntimeException('Invalid request'); 
    }
    
    if ($action==='clear_logs') {
      $logType = $_POST['log_type'] ?? '';
      $days = (int)($_POST['days'] ?? 30);
      
      if (!$logType) {
        throw new RuntimeException('Log type is required');
      }
      
      if ($logType === 'audit') {
        $stmt = $pdo->prepare("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$days]);
        $msg = "Cleared audit logs older than {$days} days"; $type = 'success';
      } elseif ($logType === 'login') {
        $stmt = $pdo->prepare("DELETE FROM login_history WHERE login_time < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$days]);
        $msg = "Cleared login history older than {$days} days"; $type = 'success';
      } elseif ($logType === 'errors') {
        $stmt = $pdo->prepare("DELETE FROM system_errors WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$days]);
        $msg = "Cleared error logs older than {$days} days"; $type = 'success';
      }
      
    } elseif ($action==='export_logs') {
      $logType = $_POST['log_type'] ?? '';
      $format = $_POST['format'] ?? 'csv';
      $dateFrom = $_POST['date_from'] ?? '';
      $dateTo = $_POST['date_to'] ?? '';
      
      if (!$logType) {
        throw new RuntimeException('Log type is required');
      }
      
      $whereClause = '';
      $params = [];
      
      if ($dateFrom) {
        $whereClause .= ' AND created_at >= ?';
        $params[] = $dateFrom . ' 00:00:00';
      }
      if ($dateTo) {
        $whereClause .= ' AND created_at <= ?';
        $params[] = $dateTo . ' 23:59:59';
      }
      
      if ($logType === 'audit') {
        $stmt = $pdo->prepare("SELECT al.*, u.username FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id WHERE 1=1 " . $whereClause . " ORDER BY al.created_at DESC");
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
      } elseif ($logType === 'login') {
        $stmt = $pdo->prepare("SELECT * FROM login_history WHERE 1=1 " . $whereClause . " ORDER BY login_time DESC");
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
      } elseif ($logType === 'errors') {
        $stmt = $pdo->prepare("SELECT se.*, u.username FROM system_errors se LEFT JOIN users u ON se.user_id = u.id WHERE 1=1 " . $whereClause . " ORDER BY se.created_at DESC");
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
      }
      
      if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $logType . '_logs_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        if (!empty($data)) {
          fputcsv($output, array_keys($data[0]));
          foreach ($data as $row) {
            fputcsv($output, $row);
          }
        }
        fclose($output);
        exit;
      }
    }
  } catch (Throwable $e) { 
    $msg = 'Failed: ' . $e->getMessage(); 
    $type = 'danger'; 
  }
}

// Fetch audit logs with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 20;
$offset = ($page - 1) * $per;

$auditLogs = [];
$totalAuditLogs = 0;
try {
  // Get total count
  $stmt = $pdo->query("SELECT COUNT(*) FROM audit_logs");
  $totalAuditLogs = (int)$stmt->fetchColumn();
  
  // Get logs
  $stmt = $pdo->prepare("
    SELECT al.*, u.username 
    FROM audit_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC 
    LIMIT $offset, $per
  ");
  $stmt->execute();
  $auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* ignore */ }

// Fetch recent login history
$loginHistory = [];
try {
  $stmt = $pdo->query("
    SELECT lh.*, u.username 
    FROM login_history lh 
    LEFT JOIN users u ON lh.user_id = u.id 
    ORDER BY lh.login_time DESC 
    LIMIT 50
  ");
  $loginHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* ignore */ }

// Fetch recent system errors
$systemErrors = [];
try {
  $stmt = $pdo->query("
    SELECT se.*, u.username 
    FROM system_errors se 
    LEFT JOIN users u ON se.user_id = u.id 
    ORDER BY se.created_at DESC 
    LIMIT 50
  ");
  $systemErrors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* ignore */ }

// Stats
$stats = [
  'total_audit_logs' => $totalAuditLogs,
  'total_logins' => 0,
  'failed_logins' => 0,
  'total_errors' => 0,
  'recent_errors' => 0
];

try {
  $stmt = $pdo->query("SELECT COUNT(*) FROM login_history");
  $stats['total_logins'] = (int)$stmt->fetchColumn();
  
  $stmt = $pdo->query("SELECT COUNT(*) FROM login_history WHERE status = 'failed'");
  $stats['failed_logins'] = (int)$stmt->fetchColumn();
  
  $stmt = $pdo->query("SELECT COUNT(*) FROM system_errors");
  $stats['total_errors'] = (int)$stmt->fetchColumn();
  
  $stmt = $pdo->query("SELECT COUNT(*) FROM system_errors WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
  $stats['recent_errors'] = (int)$stmt->fetchColumn();
} catch (Throwable $e) { /* ignore */ }

$auditPages = max(1, (int)ceil($totalAuditLogs / $per));
?>

<?php if($msg): ?>
<div class="card" style="border-left:4px solid <?php echo $type==='success'?'#10b981':'#ef4444'; ?>;margin-bottom:12px;"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<!-- Audit Trail Stats -->
<div class="stat-grid">
  <div class="stat-card">
    <h4 class="stat-card-title">Total Audit Logs</h4>
    <div class="stat-card-value"><?php echo number_format($stats['total_audit_logs']); ?></div>
  </div>
  <div class="stat-card">
    <h4 class="stat-card-title">Total Logins</h4>
    <div class="stat-card-value"><?php echo number_format($stats['total_logins']); ?></div>
  </div>
  <div class="stat-card">
    <h4 class="stat-card-title">Failed Logins</h4>
    <div class="stat-card-value"><?php echo number_format($stats['failed_logins']); ?></div>
  </div>
  <div class="stat-card">
    <h4 class="stat-card-title">System Errors</h4>
    <div class="stat-card-value"><?php echo number_format($stats['total_errors']); ?></div>
  </div>
  <div class="stat-card">
    <h4 class="stat-card-title">Recent Errors (24h)</h4>
    <div class="stat-card-value"><?php echo number_format($stats['recent_errors']); ?></div>
  </div>
</div>

<!-- Audit Logs -->
<div class="panel-card">
  <h3>System Audit Logs</h3>
  
  <?php if(empty($auditLogs)): ?>
    <div class="card" style="text-align:center;padding:40px">
      <div style="font-size:48px;margin-bottom:16px;color:var(--muted)">📋</div>
      <h4>No Audit Logs</h4>
      <p class="muted">System activity will be logged here as users interact with the system.</p>
    </div>
  <?php else: ?>
    <div class="card" style="overflow:auto">
      <table style="width:100%;border-collapse:collapse">
        <thead>
          <tr style="text-align:left;border-bottom:1px solid var(--border)">
            <th style="padding:10px">User</th>
            <th style="padding:10px">Action</th>
            <th style="padding:10px">Resource</th>
            <th style="padding:10px">IP Address</th>
            <th style="padding:10px">Timestamp</th>
            <th style="padding:10px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($auditLogs as $log): ?>
          <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:10px">
              <div><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></div>
              <?php if($log['user_id']): ?>
                <div class="muted" style="font-size:12px">ID: <?php echo (int)$log['user_id']; ?></div>
              <?php endif; ?>
            </td>
            <td style="padding:10px">
              <span style="font-size:12px;padding:2px 6px;border-radius:4px;background:var(--surface-hover)">
                <?php echo htmlspecialchars($log['action']); ?>
              </span>
            </td>
            <td style="padding:10px">
              <?php if($log['resource_type']): ?>
                <div><?php echo htmlspecialchars($log['resource_type']); ?></div>
                <?php if($log['resource_id']): ?>
                  <div class="muted" style="font-size:12px">ID: <?php echo (int)$log['resource_id']; ?></div>
                <?php endif; ?>
              <?php else: ?>
                <span class="muted">-</span>
              <?php endif; ?>
            </td>
            <td style="padding:10px">
              <span style="font-family:monospace;font-size:12px"><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></span>
            </td>
            <td style="padding:10px">
              <div style="font-size:12px">
                <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
              </div>
            </td>
            <td style="padding:10px">
              <button class="btn secondary" onclick="viewLogDetails(<?php echo htmlspecialchars(json_encode($log)); ?>)" title="View Details">
                <i class="bi bi-eye"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    
    <?php if($auditPages > 1): ?>
    <div style="display:flex;gap:6px;justify-content:center;margin-top:16px">
      <?php for($i = max(1, $page - 2); $i <= min($auditPages, $page + 2); $i++): ?>
        <a class="btn secondary" href="?panel=audit_trail&page=<?php echo $i; ?>" style="padding:6px 10px;border-radius:6px;<?php echo $i === $page ? 'background:var(--surface-hover)' : ''; ?>"><?php echo $i; ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- Login History -->
<div class="panel-card">
  <h3>Login History</h3>
  <?php if(empty($loginHistory)): ?>
    <div class="muted">No login history available.</div>
  <?php else: ?>
    <div class="card" style="overflow:auto;max-height:300px">
      <?php foreach($loginHistory as $login): ?>
        <div style="padding:12px;border-bottom:1px solid var(--border)">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
            <div style="flex:1">
              <div style="display:flex;gap:12px;align-items:center;margin-bottom:4px">
                <div style="font-weight:500"><?php echo htmlspecialchars($login['username'] ?? 'Unknown'); ?></div>
                <span style="font-size:12px;padding:2px 6px;border-radius:4px;background:<?php echo $login['status'] === 'success' ? '#10b981' : '#ef4444'; ?>;color:white">
                  <?php echo strtoupper($login['status']); ?>
                </span>
              </div>
              <div class="muted" style="font-size:12px">
                IP: <?php echo htmlspecialchars($login['ip_address'] ?? '-'); ?> • 
                <?php if($login['session_duration']): ?>
                  Duration: <?php echo gmdate('H:i:s', $login['session_duration']); ?>
                <?php endif; ?>
                <?php if($login['failure_reason']): ?>
                  • Reason: <?php echo htmlspecialchars($login['failure_reason']); ?>
                <?php endif; ?>
              </div>
            </div>
            <div style="font-size:12px;color:var(--muted)">
              <?php echo date('M j, Y g:i A', strtotime($login['login_time'])); ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- System Errors -->
<div class="panel-card">
  <h3>System Errors</h3>
  <?php if(empty($systemErrors)): ?>
    <div class="muted">No system errors logged.</div>
  <?php else: ?>
    <div class="card" style="overflow:auto;max-height:300px">
      <?php foreach($systemErrors as $error): ?>
        <div style="padding:12px;border-bottom:1px solid var(--border)">
          <div style="display:flex;justify-content:space-between;align-items:start;gap:12px">
            <div style="flex:1">
              <div style="display:flex;gap:12px;align-items:center;margin-bottom:4px">
                <div style="font-weight:500"><?php echo htmlspecialchars($error['error_type'] ?? 'Unknown Error'); ?></div>
                <span style="font-size:12px;padding:2px 6px;border-radius:4px;background:#ef4444;color:white">
                  ERROR
                </span>
              </div>
              <div style="font-size:14px;margin-bottom:4px">
                <?php echo htmlspecialchars(substr($error['error_message'] ?? '', 0, 200)); ?>
                <?php if(strlen($error['error_message'] ?? '') > 200): ?>...<?php endif; ?>
              </div>
              <div class="muted" style="font-size:12px">
                File: <?php echo htmlspecialchars($error['error_file'] ?? '-'); ?>
                <?php if($error['error_line']): ?>
                  (Line <?php echo (int)$error['error_line']; ?>)
                <?php endif; ?>
                <?php if($error['ip_address']): ?>
                  • IP: <?php echo htmlspecialchars($error['ip_address']); ?>
                <?php endif; ?>
              </div>
            </div>
            <div style="font-size:12px;color:var(--muted)">
              <?php echo date('M j, Y g:i A', strtotime($error['created_at'])); ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Log Management -->
<div class="panel-card">
  <h3>Log Management</h3>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px">
    
    <!-- Export Logs -->
    <div style="border:1px solid var(--border);border-radius:8px;padding:16px">
      <h4 style="margin:0 0 12px 0">Export Logs</h4>
      <form method="post" action="?panel=audit_trail" style="display:grid;gap:12px">
        <input type="hidden" name="action" value="export_logs">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
        
        <div>
          <label class="form-label">Log Type</label>
          <select class="input" name="log_type" required>
            <option value="audit">Audit Logs</option>
            <option value="login">Login History</option>
            <option value="errors">System Errors</option>
          </select>
        </div>
        
        <div>
          <label class="form-label">Date Range</label>
          <div style="display:flex;gap:8px">
            <input class="input" name="date_from" type="date" placeholder="From">
            <input class="input" name="date_to" type="date" placeholder="To">
          </div>
        </div>
        
        <div>
          <label class="form-label">Format</label>
          <select class="input" name="format">
            <option value="csv">CSV</option>
          </select>
        </div>
        
        <button class="btn" type="submit">
          <i class="bi bi-download"></i> Export Logs
        </button>
      </form>
    </div>
    
    <!-- Clear Logs -->
    <div style="border:1px solid var(--border);border-radius:8px;padding:16px">
      <h4 style="margin:0 0 12px 0">Clear Old Logs</h4>
      <div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:6px;padding:8px;margin-bottom:12px">
        <div style="color:#92400e;font-size:12px">
          <i class="bi bi-exclamation-triangle"></i>
          This action cannot be undone
        </div>
      </div>
      
      <form method="post" action="?panel=audit_trail" style="display:grid;gap:12px">
        <input type="hidden" name="action" value="clear_logs">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
        
        <div>
          <label class="form-label">Log Type</label>
          <select class="input" name="log_type" required>
            <option value="audit">Audit Logs</option>
            <option value="login">Login History</option>
            <option value="errors">System Errors</option>
          </select>
        </div>
        
        <div>
          <label class="form-label">Older Than (Days)</label>
          <input class="input" name="days" type="number" min="1" value="30" required>
        </div>
        
        <button class="btn" type="submit" style="background:#ef4444;color:white" onclick="return confirm('Are you sure you want to delete these logs? This action cannot be undone.')">
          <i class="bi bi-trash"></i> Clear Logs
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Log Details Modal -->
<div id="logDetailsModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999">
  <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--card);border-radius:16px;padding:24px;min-width:600px;max-width:90vw;max-height:90vh;overflow:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3>Log Details</h3>
      <button onclick="closeLogDetails()" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
    </div>
    <div id="logDetailsContent"></div>
  </div>
</div>

<script>
function viewLogDetails(log) {
  const content = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
      <div>
        <strong>User:</strong><br>
        ${log.username || 'System'} ${log.user_id ? '(ID: ' + log.user_id + ')' : ''}
      </div>
      <div>
        <strong>Action:</strong><br>
        ${log.action}
      </div>
      <div>
        <strong>Resource:</strong><br>
        ${log.resource_type || '-'} ${log.resource_id ? '(ID: ' + log.resource_id + ')' : ''}
      </div>
      <div>
        <strong>IP Address:</strong><br>
        ${log.ip_address || '-'}
      </div>
      <div>
        <strong>Session ID:</strong><br>
        ${log.session_id || '-'}
      </div>
      <div>
        <strong>Timestamp:</strong><br>
        ${new Date(log.created_at).toLocaleString()}
      </div>
    </div>
    
    ${log.old_values ? `
      <div style="margin-bottom:16px">
        <strong>Old Values:</strong>
        <pre style="background:var(--surface-hover);padding:8px;border-radius:4px;font-size:12px;overflow:auto">${JSON.stringify(JSON.parse(log.old_values), null, 2)}</pre>
      </div>
    ` : ''}
    
    ${log.new_values ? `
      <div style="margin-bottom:16px">
        <strong>New Values:</strong>
        <pre style="background:var(--surface-hover);padding:8px;border-radius:4px;font-size:12px;overflow:auto">${JSON.stringify(JSON.parse(log.new_values), null, 2)}</pre>
      </div>
    ` : ''}
    
    ${log.user_agent ? `
      <div>
        <strong>User Agent:</strong><br>
        <div style="font-size:12px;color:var(--muted);word-break:break-all">${log.user_agent}</div>
      </div>
    ` : ''}
  `;
  
  document.getElementById('logDetailsContent').innerHTML = content;
  document.getElementById('logDetailsModal').style.display = 'block';
}

function closeLogDetails() {
  document.getElementById('logDetailsModal').style.display = 'none';
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
  if (e.target.id === 'logDetailsModal') closeLogDetails();
});
</script>
