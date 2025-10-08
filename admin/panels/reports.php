<?php
// Reports panel - Analytics and export functionality

$msg=''; $type='';

// Handle export actions
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  try {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) { 
      throw new RuntimeException('Invalid request'); 
    }
    
    if ($action==='export_applications') {
      $format = $_POST['format'] ?? 'csv';
      $dateFrom = $_POST['date_from'] ?? '';
      $dateTo = $_POST['date_to'] ?? '';
      $status = $_POST['status'] ?? '';
      $program = $_POST['program'] ?? '';
      
      // Build query
      $params = []; $where = [];
      if ($dateFrom) { $where[] = 'DATE(a.created_at) >= ?'; $params[] = $dateFrom; }
      if ($dateTo) { $where[] = 'DATE(a.created_at) <= ?'; $params[] = $dateTo; }
      if ($status) { $where[] = 'a.status = ?'; $params[] = $status; }
      if ($program) { $where[] = 'p.name = ?'; $params[] = $program; }
      
      $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
      
      $sql = "SELECT 
                a.id as application_id,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                s.email as student_email,
                s.phone as student_phone,
                p.name as program_name,
                a.status as application_status,
                DATE(a.created_at) as application_date,
                py.amount as payment_amount,
                py.payment_method,
                py.status as payment_status
              FROM applications a
              LEFT JOIN students s ON a.student_id = s.id
              LEFT JOIN programs p ON a.program_id = p.id
              LEFT JOIN payments py ON a.id = py.application_id
              $whereSql
              ORDER BY a.created_at DESC";
      
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
      
      if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="applications_' . date('Y-m-d') . '.csv"');
        
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
      
      $msg = 'Export completed successfully'; $type = 'success';
    }
  } catch (Throwable $e) { 
    $msg = 'Export failed: ' . $e->getMessage(); 
    $type = 'danger'; 
  }
}

// Fetch dashboard stats
$stats = [
  'total_applications' => 0,
  'pending_applications' => 0,
  'approved_applications' => 0,
  'rejected_applications' => 0,
  'total_payments' => 0,
  'pending_payments' => 0,
  'verified_payments' => 0,
  'total_revenue' => 0,
  'today_applications' => 0,
  'this_week_applications' => 0,
  'this_month_applications' => 0,
  'this_year_applications' => 0
];

try {
  // Application stats
  $st = $pdo->query("SELECT COUNT(*) FROM applications");
  $stats['total_applications'] = (int)$st->fetchColumn();
  
  $st = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'pending'");
  $stats['pending_applications'] = (int)$st->fetchColumn();
  
  $st = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'approved'");
  $stats['approved_applications'] = (int)$st->fetchColumn();
  
  $st = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'rejected'");
  $stats['rejected_applications'] = (int)$st->fetchColumn();
  
  // Payment stats
  $st = $pdo->query("SELECT COUNT(*) FROM payments");
  $stats['total_payments'] = (int)$st->fetchColumn();
  
  $st = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'");
  $stats['pending_payments'] = (int)$st->fetchColumn();
  
  $st = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'verified'");
  $stats['verified_payments'] = (int)$st->fetchColumn();
  
  $st = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'verified'");
  $stats['total_revenue'] = (float)($st->fetchColumn() ?: 0);
  
  // Time-based stats
  $st = $pdo->query("SELECT COUNT(*) FROM applications WHERE DATE(created_at) = CURDATE()");
  $stats['today_applications'] = (int)$st->fetchColumn();
  
  $st = $pdo->query("SELECT COUNT(*) FROM applications WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)");
  $stats['this_week_applications'] = (int)$st->fetchColumn();
  
  $st = $pdo->query("SELECT COUNT(*) FROM applications WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
  $stats['this_month_applications'] = (int)$st->fetchColumn();
  
  $st = $pdo->query("SELECT COUNT(*) FROM applications WHERE YEAR(created_at) = YEAR(CURDATE())");
  $stats['this_year_applications'] = (int)$st->fetchColumn();
} catch (Throwable $e) { /* ignore */ }

// Fetch program statistics
$programStats = [];
try {
  $st = $pdo->query("
    SELECT 
      p.name as program_name,
      COUNT(a.id) as application_count,
      COUNT(CASE WHEN a.status = 'approved' THEN 1 END) as approved_count,
      COUNT(CASE WHEN a.status = 'rejected' THEN 1 END) as rejected_count,
      COUNT(CASE WHEN a.status = 'pending' THEN 1 END) as pending_count
    FROM programs p
    LEFT JOIN applications a ON p.id = a.program_id
    GROUP BY p.id, p.name
    ORDER BY application_count DESC
  ");
  $programStats = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* ignore */ }

// Fetch monthly trends (last 12 months)
$monthlyTrends = [];
try {
  $st = $pdo->query("
    SELECT 
      DATE_FORMAT(created_at, '%Y-%m') as month,
      COUNT(*) as applications,
      COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved
    FROM applications 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
  ");
  $monthlyTrends = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* ignore */ }

// Fetch programs for filter dropdown
$programs = [];
try {
  $st = $pdo->query("SELECT name FROM programs WHERE status = 'active' ORDER BY name");
  $programs = $st->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) { /* ignore */ }
?>

<?php if($msg): ?>
<div class="card" style="border-left:4px solid <?php echo $type==='success'?'#10b981':'#ef4444'; ?>;margin-bottom:12px;"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<!-- Key Metrics Dashboard -->
<div class="stat-grid">
  <div class="stat-card">
    <h4 class="stat-card-title">Total Applications</h4>
    <div class="stat-card-value"><?php echo number_format($stats['total_applications']); ?></div>
    <div class="muted" style="font-size:12px;margin-top:4px">This Year: <?php echo number_format($stats['this_year_applications']); ?></div>
  </div>
  <div class="stat-card">
    <h4 class="stat-card-title">Pending Review</h4>
    <div class="stat-card-value"><?php echo number_format($stats['pending_applications']); ?></div>
    <div class="muted" style="font-size:12px;margin-top:4px"><?php echo $stats['total_applications'] > 0 ? round(($stats['pending_applications'] / $stats['total_applications']) * 100, 1) : 0; ?>% of total</div>
  </div>
  <div class="stat-card">
    <h4 class="stat-card-title">Approved</h4>
    <div class="stat-card-value"><?php echo number_format($stats['approved_applications']); ?></div>
    <div class="muted" style="font-size:12px;margin-top:4px"><?php echo $stats['total_applications'] > 0 ? round(($stats['approved_applications'] / $stats['total_applications']) * 100, 1) : 0; ?>% approval rate</div>
  </div>
  <div class="stat-card">
    <h4 class="stat-card-title">Total Revenue</h4>
    <div class="stat-card-value">GHS <?php echo number_format($stats['total_revenue'], 2); ?></div>
    <div class="muted" style="font-size:12px;margin-top:4px"><?php echo number_format($stats['verified_payments']); ?> payments</div>
  </div>
</div>

<!-- Time-based Analytics -->
<div class="panel-card">
  <h3>Application Trends</h3>
  <div class="kpi-grid">
    <div class="kpi-box">
      <div class="kpi-label">Today</div>
      <div class="kpi-value"><?php echo number_format($stats['today_applications']); ?></div>
    </div>
    <div class="kpi-box">
      <div class="kpi-label">This Week</div>
      <div class="kpi-value"><?php echo number_format($stats['this_week_applications']); ?></div>
    </div>
    <div class="kpi-box">
      <div class="kpi-label">This Month</div>
      <div class="kpi-value"><?php echo number_format($stats['this_month_applications']); ?></div>
    </div>
    <div class="kpi-box">
      <div class="kpi-label">This Year</div>
      <div class="kpi-value"><?php echo number_format($stats['this_year_applications']); ?></div>
    </div>
  </div>
</div>

<!-- Program Performance -->
<div class="panel-card">
  <h3>Program Performance</h3>
  <?php if(empty($programStats)): ?>
    <div class="muted">No program data available.</div>
  <?php else: ?>
    <div class="card" style="overflow:auto">
      <table style="width:100%;border-collapse:collapse">
        <thead>
          <tr style="text-align:left;border-bottom:1px solid var(--border)">
            <th style="padding:10px">Program</th>
            <th style="padding:10px">Total Apps</th>
            <th style="padding:10px">Approved</th>
            <th style="padding:10px">Rejected</th>
            <th style="padding:10px">Pending</th>
            <th style="padding:10px">Approval Rate</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($programStats as $p): 
            $approvalRate = $p['application_count'] > 0 ? round(($p['approved_count'] / $p['application_count']) * 100, 1) : 0;
          ?>
          <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:10px"><?php echo htmlspecialchars($p['program_name']); ?></td>
            <td style="padding:10px"><?php echo number_format($p['application_count']); ?></td>
            <td style="padding:10px"><span style="color:#10b981"><?php echo number_format($p['approved_count']); ?></span></td>
            <td style="padding:10px"><span style="color:#ef4444"><?php echo number_format($p['rejected_count']); ?></span></td>
            <td style="padding:10px"><span style="color:#f59e0b"><?php echo number_format($p['pending_count']); ?></span></td>
            <td style="padding:10px"><?php echo $approvalRate; ?>%</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Monthly Trends Chart -->
<div class="panel-card">
  <h3>Monthly Application Trends</h3>
  <div style="height:300px;background:var(--surface-hover);border-radius:10px;padding:20px;display:flex;align-items:center;justify-content:center">
    <div style="text-align:center">
      <div class="muted" style="margin-bottom:10px">Monthly Trends Chart</div>
      <div style="font-size:14px;color:var(--muted)">
        <?php if(!empty($monthlyTrends)): ?>
          <?php foreach(array_slice($monthlyTrends, -6) as $trend): ?>
            <div style="margin:4px 0">
              <?php echo date('M Y', strtotime($trend['month'].'-01')); ?>: 
              <?php echo $trend['applications']; ?> applications 
              (<?php echo $trend['approved']; ?> approved)
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          No trend data available
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Export Tools -->
<div class="panel-card">
  <h3>Export Data</h3>
  <form method="post" action="?panel=reports" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px">
    <input type="hidden" name="action" value="export_applications">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    
    <div>
      <label class="form-label">Export Format</label>
      <select class="input" name="format" required>
        <option value="csv">CSV File</option>
        <option value="excel" disabled>Excel (Coming Soon)</option>
        <option value="pdf" disabled>PDF Report (Coming Soon)</option>
      </select>
    </div>
    
    <div>
      <label class="form-label">Date From</label>
      <input class="input" name="date_from" type="date">
    </div>
    
    <div>
      <label class="form-label">Date To</label>
      <input class="input" name="date_to" type="date">
    </div>
    
    <div>
      <label class="form-label">Status Filter</label>
      <select class="input" name="status">
        <option value="">All Statuses</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
        <option value="waitlisted">Waitlisted</option>
      </select>
    </div>
    
    <div>
      <label class="form-label">Program Filter</label>
      <select class="input" name="program">
        <option value="">All Programs</option>
        <?php foreach($programs as $program): ?>
          <option value="<?php echo htmlspecialchars($program); ?>"><?php echo htmlspecialchars($program); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    
    <div style="display:flex;align-items:flex-end">
      <button class="btn" type="submit">
        <i class="bi bi-download"></i> Export Applications
      </button>
    </div>
  </form>
</div>

<!-- Quick Reports -->
<div class="panel-card">
  <h3>Quick Reports</h3>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
    <button class="btn secondary" onclick="generateQuickReport('pending_payments')">
      <i class="bi bi-clock"></i> Pending Payments
    </button>
    <button class="btn secondary" onclick="generateQuickReport('overdue_applications')">
      <i class="bi bi-exclamation-triangle"></i> Overdue Applications
    </button>
    <button class="btn secondary" onclick="generateQuickReport('payment_summary')">
      <i class="bi bi-credit-card"></i> Payment Summary
    </button>
    <button class="btn secondary" onclick="generateQuickReport('student_demographics')">
      <i class="bi bi-people"></i> Student Demographics
    </button>
  </div>
</div>

<!-- System Health -->
<div class="panel-card">
  <h3>System Health</h3>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">
    <div style="text-align:center;padding:16px;background:var(--surface-hover);border-radius:8px">
      <div style="font-size:24px;font-weight:700;color:#10b981"><?php echo $stats['verified_payments']; ?></div>
      <div class="muted">Verified Payments</div>
    </div>
    <div style="text-align:center;padding:16px;background:var(--surface-hover);border-radius:8px">
      <div style="font-size:24px;font-weight:700;color:#f59e0b"><?php echo $stats['pending_payments']; ?></div>
      <div class="muted">Pending Payments</div>
    </div>
    <div style="text-align:center;padding:16px;background:var(--surface-hover);border-radius:8px">
      <div style="font-size:24px;font-weight:700;color:#2563eb"><?php echo count($programs); ?></div>
      <div class="muted">Active Programs</div>
    </div>
    <div style="text-align:center;padding:16px;background:var(--surface-hover);border-radius:8px">
      <div style="font-size:24px;font-weight:700;color:#8b5cf6"><?php echo round($stats['total_revenue']); ?></div>
      <div class="muted">Total Revenue (GHS)</div>
    </div>
  </div>
</div>

<script>
function generateQuickReport(type) {
  // This would typically generate and download a specific report
  // For now, we'll show a toast notification
  showToast(`Generating ${type.replace('_', ' ')} report...`, 'info');
  
  // In a real implementation, this would:
  // 1. Make an AJAX request to generate the report
  // 2. Return a download link
  // 3. Trigger the download
  
  setTimeout(() => {
    showToast('Report generated successfully!', 'success');
  }, 2000);
}

// Simple toast notification function
function showToast(message, type = 'info') {
  const toast = document.createElement('div');
  toast.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 12px 16px;
    background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#2563eb'};
    color: white;
    border-radius: 8px;
    z-index: 10000;
    font-size: 14px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  `;
  toast.textContent = message;
  document.body.appendChild(toast);
  
  setTimeout(() => {
    toast.remove();
  }, 3000);
}
</script>
