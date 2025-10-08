<?php
// Payments panel - Payment records and verification management

$msg=''; $type='';
try { 
  $pdo->exec("CREATE TABLE IF NOT EXISTS payment_methods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    gateway VARCHAR(50),
    is_online TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    config JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  
  $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED,
    student_id INT UNSIGNED,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    currency VARCHAR(3) DEFAULT 'GHS',
    payment_method VARCHAR(50),
    gateway VARCHAR(50),
    transaction_id VARCHAR(100),
    reference VARCHAR(100),
    receipt_number VARCHAR(50),
    status VARCHAR(30) DEFAULT 'pending',
    payment_date TIMESTAMP NULL,
    verified_at TIMESTAMP NULL,
    verified_by INT UNSIGNED,
    notes TEXT,
    proof_of_payment VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status(status),
    INDEX idx_application(application_id),
    INDEX idx_student(student_id),
    INDEX idx_receipt(receipt_number)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  
  // Insert default payment methods
  $methods = [
    ['Paystack', 'paystack', 1, 1, '{"test_mode": true}'],
    ['Flutterwave', 'flutterwave', 1, 1, '{"test_mode": true}'],
    ['Stripe', 'stripe', 1, 1, '{"test_mode": true}'],
    ['Bank Deposit', 'bank_deposit', 0, 1, '{}'],
    ['Cash Payment', 'cash', 0, 1, '{}'],
    ['Mobile Money', 'mobile_money', 0, 1, '{}'],
    ['Cheque', 'cheque', 0, 1, '{}']
  ];
  
  foreach ($methods as [$name, $gateway, $isOnline, $isActive, $config]) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO payment_methods (name, gateway, is_online, is_active, config) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $gateway, $isOnline, $isActive, $config]);
  }
} catch (Throwable $e) { /* ignore */ }

// Handle payment actions
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  try {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) { 
      throw new RuntimeException('Invalid request'); 
    }
    
    if ($action==='verify') {
      $id = (int)($_POST['id'] ?? 0);
      $status = $_POST['status'] ?? 'verified';
      $notes = trim($_POST['notes'] ?? '');
      
      if (!$id) throw new RuntimeException('Payment ID required');
      
      $stmt = $pdo->prepare("UPDATE payments SET status=?, verified_at=NOW(), verified_by=?, notes=? WHERE id=?");
      $stmt->execute([$status, $_SESSION['user_id'], $notes, $id]);
      $msg='Payment verification updated'; $type='success';
      
    } elseif ($action==='add_manual') {
      $studentId = (int)($_POST['student_id'] ?? 0);
      $applicationId = (int)($_POST['application_id'] ?? 0);
      $amount = (float)($_POST['amount'] ?? 0);
      $method = $_POST['payment_method'] ?? '';
      $reference = trim($_POST['reference'] ?? '');
      $notes = trim($_POST['notes'] ?? '');
      
      if (!$studentId || !$amount || !$method) {
        throw new RuntimeException('Student, amount, and method are required');
      }
      
      // Generate receipt number
      $year = date('Y');
      $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE YEAR(created_at) = ?");
      $stmt->execute([$year]);
      $count = (int)$stmt->fetchColumn() + 1;
      $receiptNumber = "RCP-{$year}-" . str_pad($count, 5, '0', STR_PAD_LEFT);
      
      $stmt = $pdo->prepare("INSERT INTO payments (student_id, application_id, amount, payment_method, reference, notes, receipt_number, status, payment_date) VALUES (?, ?, ?, ?, ?, ?, ?, 'verified', NOW())");
      $stmt->execute([$studentId, $applicationId, $amount, $method, $reference, $notes, $receiptNumber]);
      $msg="Payment recorded successfully. Receipt: {$receiptNumber}"; $type='success';
      
    } elseif ($action==='update') {
      $id = (int)($_POST['id'] ?? 0);
      $amount = (float)($_POST['amount'] ?? 0);
      $method = $_POST['payment_method'] ?? '';
      $reference = trim($_POST['reference'] ?? '');
      $notes = trim($_POST['notes'] ?? '');
      
      if (!$id || !$amount || !$method) {
        throw new RuntimeException('ID, amount, and method are required');
      }
      
      $stmt = $pdo->prepare("UPDATE payments SET amount=?, payment_method=?, reference=?, notes=? WHERE id=?");
      $stmt->execute([$amount, $method, $reference, $notes, $id]);
      $msg='Payment updated successfully'; $type='success';
    }
  } catch (Throwable $e) { 
    $msg='Failed: '.$e->getMessage(); 
    $type='danger'; 
  }
}

// Fetch payments with filters
$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$method = trim($_GET['method'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = max(1,(int)($_GET['page'] ?? 1));
$per=20; $offset=($page-1)*$per;

$params=[]; $where=[];
if ($q!==''){ 
  $where[]='(p.receipt_number LIKE ? OR p.reference LIKE ? OR p.transaction_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)'; 
  $params = array_merge($params, ["%$q%", "%$q%", "%$q%", "%$q%", "%$q%"]);
}
if ($status!==''){ $where[]='p.status=?'; $params[]=$status; }
if ($method!==''){ $where[]='p.payment_method=?'; $params[]=$method; }
if ($dateFrom!==''){ $where[]='DATE(p.created_at) >= ?'; $params[]=$dateFrom; }
if ($dateTo!==''){ $where[]='DATE(p.created_at) <= ?'; $params[]=$dateTo; }

$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

$total=0; 
try{ 
  $st=$pdo->prepare("SELECT COUNT(*) FROM payments p LEFT JOIN students s ON p.student_id = s.id $whereSql"); 
  $st->execute($params); 
  $total=(int)$st->fetchColumn(); 
} catch(Throwable $e){}

$pages=max(1,(int)ceil($total/$per));

$rows=[]; 
try{ 
  $sql = "SELECT p.*, s.first_name, s.last_name, s.email as student_email 
          FROM payments p 
          LEFT JOIN students s ON p.student_id = s.id 
          $whereSql 
          ORDER BY p.created_at DESC 
          LIMIT $offset,$per";
  $st=$pdo->prepare($sql); 
  $st->execute($params); 
  $rows=$st->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e){}

// Fetch payment methods
$methods=[]; 
try{ 
  $st=$pdo->query("SELECT name, gateway FROM payment_methods WHERE is_active=1 ORDER BY name"); 
  $methods=$st->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e){}

// Fetch students for manual payment
$students=[]; 
try{ 
  $st=$pdo->query("SELECT id, first_name, last_name, email FROM students ORDER BY first_name, last_name"); 
  $students=$st->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e){}

// Stats
$stats = ['total' => 0, 'pending' => 0, 'verified' => 0, 'rejected' => 0, 'today' => 0];
try {
  $st = $pdo->query("SELECT status, COUNT(*) as count FROM payments GROUP BY status");
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $stats[$row['status']] = (int)$row['count'];
    $stats['total'] += (int)$row['count'];
  }
  $st = $pdo->query("SELECT COUNT(*) FROM payments WHERE DATE(created_at) = CURDATE()");
  $stats['today'] = (int)$st->fetchColumn();
} catch (Throwable $e) {}
?>

<?php if($msg): ?>
<div class="card" style="border-left:4px solid <?php echo $type==='success'?'#10b981':'#ef4444'; ?>;margin-bottom:12px;"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<!-- Payment Stats -->
<div class="stat-grid">
  <div class="stat-card">
    <h4 class="stat-card-title">Total Payments</h4>
    <div class="stat-card-value"><?php echo number_format($stats['total']); ?></div>
  </div>
  <div class="stat-card">
    <h4 class="stat-card-title">Pending Verification</h4>
    <div class="stat-card-value"><?php echo number_format($stats['pending']); ?></div>
  </div>
  <div class="stat-card">
    <h4 class="stat-card-title">Verified Today</h4>
    <div class="stat-card-value"><?php echo number_format($stats['today']); ?></div>
  </div>
</div>

<div class="panel-card">
  <h3>Payment Records</h3>
  
  <form method="get" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
    <input type="hidden" name="panel" value="payments">
    <input class="input" name="q" placeholder="Search payments..." value="<?php echo htmlspecialchars($q); ?>" style="min-width:240px">
    <select class="input" name="status" style="max-width:140px">
      <option value="">All Status</option>
      <option value="pending" <?php echo $status==='pending'?'selected':''; ?>>Pending</option>
      <option value="verified" <?php echo $status==='verified'?'selected':''; ?>>Verified</option>
      <option value="rejected" <?php echo $status==='rejected'?'selected':''; ?>>Rejected</option>
    </select>
    <select class="input" name="method" style="max-width:160px">
      <option value="">All Methods</option>
      <?php foreach($methods as $m): ?>
        <option value="<?php echo htmlspecialchars($m['name']); ?>" <?php echo $method===$m['name']?'selected':''; ?>><?php echo htmlspecialchars($m['name']); ?></option>
      <?php endforeach; ?>
    </select>
    <input class="input" name="date_from" type="date" value="<?php echo htmlspecialchars($dateFrom); ?>" style="max-width:140px">
    <input class="input" name="date_to" type="date" value="<?php echo htmlspecialchars($dateTo); ?>" style="max-width:140px">
    <button class="btn" type="submit"><i class="bi bi-search"></i> Filter</button>
  </form>

  <div class="card" style="overflow:auto">
    <table style="width:100%;border-collapse:collapse">
      <thead>
        <tr style="text-align:left;border-bottom:1px solid var(--border)">
          <th style="padding:10px">Receipt</th>
          <th style="padding:10px">Student</th>
          <th style="padding:10px">Amount</th>
          <th style="padding:10px">Method</th>
          <th style="padding:10px">Status</th>
          <th style="padding:10px">Date</th>
          <th style="padding:10px">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if(!$rows): ?>
        <tr><td colspan="7" style="padding:14px" class="muted">No payments found.</td></tr>
        <?php else: foreach($rows as $r): ?>
        <tr style="border-bottom:1px solid var(--border)">
          <td style="padding:10px">
            <div style="font-weight:500"><?php echo htmlspecialchars($r['receipt_number']); ?></div>
            <?php if($r['reference']): ?>
              <div class="muted" style="font-size:12px">Ref: <?php echo htmlspecialchars($r['reference']); ?></div>
            <?php endif; ?>
          </td>
          <td style="padding:10px">
            <div><?php echo htmlspecialchars(($r['first_name'] ?? '').' '.($r['last_name'] ?? '')); ?></div>
            <?php if($r['student_email']): ?>
              <div class="muted" style="font-size:12px"><?php echo htmlspecialchars($r['student_email']); ?></div>
            <?php endif; ?>
          </td>
          <td style="padding:10px">
            <div style="font-weight:500"><?php echo 'GHS ' . number_format($r['amount'], 2); ?></div>
          </td>
          <td style="padding:10px">
            <span class="muted"><?php echo htmlspecialchars($r['payment_method']); ?></span>
          </td>
          <td style="padding:10px">
            <?php
            $statusColors = [
              'pending' => '#f59e0b',
              'verified' => '#10b981', 
              'rejected' => '#ef4444'
            ];
            $color = $statusColors[$r['status']] ?? '#6b7280';
            ?>
            <span style="color:<?php echo $color; ?>;font-size:12px;font-weight:500">
              <?php echo ucfirst($r['status']); ?>
            </span>
          </td>
          <td style="padding:10px">
            <span class="muted" style="font-size:12px">
              <?php echo date('M j, Y', strtotime($r['created_at'])); ?>
            </span>
          </td>
          <td style="padding:10px;display:flex;gap:4px;flex-wrap:wrap">
            <?php if($r['status'] === 'pending'): ?>
              <button class="btn secondary" onclick="verifyPayment(<?php echo (int)$r['id']; ?>, '<?php echo htmlspecialchars($r['receipt_number']); ?>')">
                <i class="bi bi-check-circle"></i> Verify
              </button>
            <?php endif; ?>
            <button class="btn secondary" onclick="viewPayment(<?php echo htmlspecialchars(json_encode($r)); ?>)">
              <i class="bi bi-eye"></i> View
            </button>
            <button class="btn secondary" onclick="editPayment(<?php echo htmlspecialchars(json_encode($r)); ?>)">
              <i class="bi bi-pencil"></i> Edit
            </button>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if($pages>1): ?>
  <div style="display:flex;gap:6px;justify-content:flex-end;margin-top:10px">
    <?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++): ?>
      <a class="btn secondary" href="?panel=payments&page=<?php echo $i; ?>&q=<?php echo urlencode($q); ?>&status=<?php echo urlencode($status); ?>&method=<?php echo urlencode($method); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" style="padding:6px 10px;border-radius:6px;<?php echo $i===$page?'background:var(--surface-hover)':''; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<div class="panel-card">
  <h3>Record Manual Payment</h3>
  <form method="post" action="?panel=payments" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px">
    <input type="hidden" name="action" value="add_manual">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    
    <div>
      <label class="form-label">Student *</label>
      <select class="input" name="student_id" required>
        <option value="">Select Student</option>
        <?php foreach($students as $s): ?>
          <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['first_name'].' '.$s['last_name'].' ('.$s['email'].')'); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="form-label">Application ID (Optional)</label>
      <input class="input" name="application_id" type="number" placeholder="Application ID">
    </div>
    <div>
      <label class="form-label">Amount *</label>
      <input class="input" name="amount" type="number" step="0.01" min="0" required placeholder="0.00">
    </div>
    <div>
      <label class="form-label">Payment Method *</label>
      <select class="input" name="payment_method" required>
        <?php foreach($methods as $m): ?>
          <option value="<?php echo htmlspecialchars($m['name']); ?>"><?php echo htmlspecialchars($m['name']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="form-label">Reference/Transaction ID</label>
      <input class="input" name="reference" placeholder="Reference number">
    </div>
    <div>
      <label class="form-label">Notes</label>
      <textarea class="input" name="notes" rows="2" placeholder="Additional notes"></textarea>
    </div>
    <div style="display:flex;align-items:flex-end">
      <button class="btn" type="submit"><i class="bi bi-plus-lg"></i> Record Payment</button>
    </div>
  </form>
</div>

<!-- Verify Payment Modal -->
<div id="verifyModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999">
  <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--card);border-radius:16px;padding:24px;min-width:400px;max-width:90vw">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3>Verify Payment</h3>
      <button onclick="closeVerifyModal()" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
    </div>
    <form method="post" action="?panel=payments" id="verifyForm">
      <input type="hidden" name="action" value="verify">
      <input type="hidden" name="id" id="verifyId">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
      
      <div style="margin-bottom:16px">
        <label class="form-label">Receipt Number</label>
        <div id="verifyReceipt" style="font-weight:500;padding:8px;background:var(--surface-hover);border-radius:6px"></div>
      </div>
      
      <div style="margin-bottom:16px">
        <label class="form-label">Verification Status</label>
        <select class="input" name="status" required>
          <option value="verified">Verified</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>
      
      <div style="margin-bottom:16px">
        <label class="form-label">Notes</label>
        <textarea class="input" name="notes" rows="3" placeholder="Verification notes"></textarea>
      </div>
      
      <div style="display:flex;gap:12px;justify-content:flex-end">
        <button type="button" class="btn secondary" onclick="closeVerifyModal()">Cancel</button>
        <button type="submit" class="btn">Update Status</button>
      </div>
    </form>
  </div>
</div>

<!-- View Payment Modal -->
<div id="viewModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999">
  <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--card);border-radius:16px;padding:24px;min-width:500px;max-width:90vw;max-height:90vh;overflow:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3>Payment Details</h3>
      <button onclick="closeViewModal()" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
    </div>
    <div id="paymentDetails"></div>
  </div>
</div>

<!-- Edit Payment Modal -->
<div id="editModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999">
  <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--card);border-radius:16px;padding:24px;min-width:500px;max-width:90vw">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3>Edit Payment</h3>
      <button onclick="closeEditModal()" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
    </div>
    <form method="post" action="?panel=payments" id="editForm">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="editId">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
      
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        <div>
          <label class="form-label">Amount *</label>
          <input class="input" name="amount" id="editAmount" type="number" step="0.01" min="0" required>
        </div>
        <div>
          <label class="form-label">Payment Method *</label>
          <select class="input" name="payment_method" id="editMethod" required>
            <?php foreach($methods as $m): ?>
              <option value="<?php echo htmlspecialchars($m['name']); ?>"><?php echo htmlspecialchars($m['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Reference</label>
          <input class="input" name="reference" id="editReference">
        </div>
      </div>
      
      <div style="margin-bottom:16px">
        <label class="form-label">Notes</label>
        <textarea class="input" name="notes" id="editNotes" rows="3"></textarea>
      </div>
      
      <div style="display:flex;gap:12px;justify-content:flex-end">
        <button type="button" class="btn secondary" onclick="closeEditModal()">Cancel</button>
        <button type="submit" class="btn">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function verifyPayment(id, receipt) {
  document.getElementById('verifyId').value = id;
  document.getElementById('verifyReceipt').textContent = receipt;
  document.getElementById('verifyForm').reset();
  document.getElementById('verifyModal').style.display = 'block';
}

function closeVerifyModal() {
  document.getElementById('verifyModal').style.display = 'none';
}

function viewPayment(payment) {
  const details = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div><strong>Receipt Number:</strong><br>${payment.receipt_number}</div>
      <div><strong>Status:</strong><br><span style="color:${payment.status==='verified'?'#10b981':payment.status==='rejected'?'#ef4444':'#f59e0b'}">${payment.status}</span></div>
      <div><strong>Amount:</strong><br>GHS ${parseFloat(payment.amount).toFixed(2)}</div>
      <div><strong>Method:</strong><br>${payment.payment_method}</div>
      <div><strong>Reference:</strong><br>${payment.reference || 'N/A'}</div>
      <div><strong>Date:</strong><br>${new Date(payment.created_at).toLocaleDateString()}</div>
      <div><strong>Student:</strong><br>${payment.first_name} ${payment.last_name}</div>
      <div><strong>Student Email:</strong><br>${payment.student_email || 'N/A'}</div>
    </div>
    ${payment.notes ? `<div style="margin-top:16px"><strong>Notes:</strong><br>${payment.notes}</div>` : ''}
  `;
  document.getElementById('paymentDetails').innerHTML = details;
  document.getElementById('viewModal').style.display = 'block';
}

function closeViewModal() {
  document.getElementById('viewModal').style.display = 'none';
}

function editPayment(payment) {
  document.getElementById('editId').value = payment.id;
  document.getElementById('editAmount').value = payment.amount;
  document.getElementById('editMethod').value = payment.payment_method;
  document.getElementById('editReference').value = payment.reference || '';
  document.getElementById('editNotes').value = payment.notes || '';
  document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
  document.getElementById('editModal').style.display = 'none';
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
  if (e.target.id === 'verifyModal') closeVerifyModal();
  if (e.target.id === 'viewModal') closeViewModal();
  if (e.target.id === 'editModal') closeEditModal();
});
</script>
