<?php
// Fee Structures panel - Multiple fee management per program

$msg=''; $type='';
try { 
  $pdo->exec("CREATE TABLE IF NOT EXISTS fee_structures (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    program_id INT UNSIGNED,
    fee_type ENUM('application', 'acceptance', 'tuition', 'late', 'miscellaneous') NOT NULL,
    fee_name VARCHAR(150) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'GHS',
    description TEXT,
    is_required TINYINT(1) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    due_date DATE,
    late_fee_amount DECIMAL(10,2) DEFAULT 0,
    late_fee_grace_days INT DEFAULT 0,
    payment_methods JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_program(program_id),
    INDEX idx_type(fee_type),
    INDEX idx_active(is_active)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  
  $pdo->exec("CREATE TABLE IF NOT EXISTS waiver_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    max_uses INT,
    used_count INT DEFAULT 0,
    valid_from DATE,
    valid_until DATE,
    applicable_fees JSON,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code(code),
    INDEX idx_active(is_active)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  
  // Insert default fee structures
  $defaultFees = [
    [null, 'application', 'Application Fee', 100.00, 'GHS', 'Standard application processing fee', 1, 1, null, 0, 0, '["paystack", "flutterwave", "bank_deposit"]'],
    [null, 'acceptance', 'Acceptance Fee', 500.00, 'GHS', 'Fee to secure admission offer', 1, 1, null, 50.00, 7, '["paystack", "flutterwave", "bank_deposit"]']
  ];
  
  foreach ($defaultFees as $fee) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO fee_structures (program_id, fee_type, fee_name, amount, currency, description, is_required, is_active, due_date, late_fee_amount, late_fee_grace_days, payment_methods) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute($fee);
  }
} catch (Throwable $e) { /* ignore */ }

// Handle actions
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  try {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) { 
      throw new RuntimeException('Invalid request'); 
    }
    
    if ($action==='save_fee') {
      $programId = (int)($_POST['program_id'] ?? 0);
      $feeType = $_POST['fee_type'] ?? '';
      $feeName = trim($_POST['fee_name'] ?? '');
      $amount = (float)($_POST['amount'] ?? 0);
      $description = trim($_POST['description'] ?? '');
      $isRequired = isset($_POST['is_required']) ? 1 : 0;
      $dueDate = $_POST['due_date'] ?? null;
      $lateFeeAmount = (float)($_POST['late_fee_amount'] ?? 0);
      $lateFeeGraceDays = (int)($_POST['late_fee_grace_days'] ?? 0);
      $paymentMethods = json_encode($_POST['payment_methods'] ?? []);
      
      if (!$feeType || !$feeName || !$amount) {
        throw new RuntimeException('Fee type, name, and amount are required');
      }
      
      $stmt = $pdo->prepare("INSERT INTO fee_structures (program_id, fee_type, fee_name, amount, description, is_required, due_date, late_fee_amount, late_fee_grace_days, payment_methods) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->execute([$programId ?: null, $feeType, $feeName, $amount, $description, $isRequired, $dueDate ?: null, $lateFeeAmount, $lateFeeGraceDays, $paymentMethods]);
      
      $msg='Fee structure saved successfully'; $type='success';
      
    } elseif ($action==='update_fee') {
      $id = (int)($_POST['fee_id'] ?? 0);
      $feeName = trim($_POST['fee_name'] ?? '');
      $amount = (float)($_POST['amount'] ?? 0);
      $description = trim($_POST['description'] ?? '');
      $isRequired = isset($_POST['is_required']) ? 1 : 0;
      $isActive = isset($_POST['is_active']) ? 1 : 0;
      $dueDate = $_POST['due_date'] ?? null;
      $lateFeeAmount = (float)($_POST['late_fee_amount'] ?? 0);
      $lateFeeGraceDays = (int)($_POST['late_fee_grace_days'] ?? 0);
      $paymentMethods = json_encode($_POST['payment_methods'] ?? []);
      
      if (!$id || !$feeName || !$amount) {
        throw new RuntimeException('ID, fee name, and amount are required');
      }
      
      $stmt = $pdo->prepare("UPDATE fee_structures SET fee_name=?, amount=?, description=?, is_required=?, is_active=?, due_date=?, late_fee_amount=?, late_fee_grace_days=?, payment_methods=? WHERE id=?");
      $stmt->execute([$feeName, $amount, $description, $isRequired, $isActive, $dueDate ?: null, $lateFeeAmount, $lateFeeGraceDays, $paymentMethods, $id]);
      
      $msg='Fee structure updated successfully'; $type='success';
      
    } elseif ($action==='delete_fee') {
      $id = (int)($_POST['fee_id'] ?? 0);
      
      if (!$id) throw new RuntimeException('Fee ID required');
      
      $stmt = $pdo->prepare("DELETE FROM fee_structures WHERE id=?");
      $stmt->execute([$id]);
      
      $msg='Fee structure deleted successfully'; $type='success';
      
    } elseif ($action==='save_waiver') {
      $code = strtoupper(trim($_POST['code'] ?? ''));
      $description = trim($_POST['description'] ?? '');
      $discountType = $_POST['discount_type'] ?? '';
      $discountValue = (float)($_POST['discount_value'] ?? 0);
      $maxUses = (int)($_POST['max_uses'] ?? 0);
      $validFrom = $_POST['valid_from'] ?? null;
      $validUntil = $_POST['valid_until'] ?? null;
      $applicableFees = json_encode($_POST['applicable_fees'] ?? []);
      
      if (!$code || !$discountType || !$discountValue) {
        throw new RuntimeException('Code, discount type, and value are required');
      }
      
      $stmt = $pdo->prepare("INSERT INTO waiver_codes (code, description, discount_type, discount_value, max_uses, valid_from, valid_until, applicable_fees, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->execute([$code, $description, $discountType, $discountValue, $maxUses ?: null, $validFrom ?: null, $validUntil ?: null, $applicableFees, $_SESSION['user_id']]);
      
      $msg='Waiver code created successfully'; $type='success';
      
    } elseif ($action==='toggle_waiver') {
      $id = (int)($_POST['waiver_id'] ?? 0);
      $isActive = (int)($_POST['is_active'] ?? 0);
      
      if (!$id) throw new RuntimeException('Waiver ID required');
      
      $stmt = $pdo->prepare("UPDATE waiver_codes SET is_active=? WHERE id=?");
      $stmt->execute([$isActive, $id]);
      
      $msg='Waiver code status updated'; $type='success';
    }
  } catch (Throwable $e) { 
    $msg='Failed: '.$e->getMessage(); 
    $type='danger'; 
  }
}

// Fetch fee structures
$fees = [];
try {
  $stmt = $pdo->query("
    SELECT fs.*, p.name as program_name 
    FROM fee_structures fs 
    LEFT JOIN programs p ON fs.program_id = p.id 
    ORDER BY fs.program_id, fs.fee_type, fs.created_at
  ");
  $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* ignore */ }

// Fetch waiver codes
$waivers = [];
try {
  $stmt = $pdo->query("
    SELECT wc.*, u.username as created_by_name 
    FROM waiver_codes wc 
    LEFT JOIN users u ON wc.created_by = u.id 
    ORDER BY wc.created_at DESC
  ");
  $waivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* ignore */ }

// Fetch programs
$programs = [];
try {
  $stmt = $pdo->query("SELECT id, name FROM programs WHERE status = 'active' ORDER BY name");
  $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* ignore */ }

// Payment methods
$paymentMethods = ['paystack', 'flutterwave', 'stripe', 'bank_deposit', 'cash', 'mobile_money'];

// Fee types
$feeTypes = [
  'application' => 'Application Fee',
  'acceptance' => 'Acceptance Fee', 
  'tuition' => 'Tuition Fee',
  'late' => 'Late Fee',
  'miscellaneous' => 'Miscellaneous'
];
?>

<?php if($msg): ?>
<div class="card" style="border-left:4px solid <?php echo $type==='success'?'#10b981':'#ef4444'; ?>;margin-bottom:12px;"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<!-- Fee Management Tabs -->
<div class="panel-card">
  <div style="display:flex;gap:8px;margin-bottom:20px;border-bottom:1px solid var(--border)">
    <button class="btn secondary" onclick="showTab('fees')" id="feesTab">
      <i class="bi bi-credit-card"></i> Fee Structures
    </button>
    <button class="btn secondary" onclick="showTab('waivers')" id="waiversTab">
      <i class="bi bi-percent"></i> Waiver Codes
    </button>
  </div>

  <!-- Fee Structures Tab -->
  <div id="feesContent">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3>Fee Structures</h3>
      <button class="btn" onclick="showFeeModal()">
        <i class="bi bi-plus-lg"></i> Add Fee
      </button>
    </div>

    <?php if(empty($fees)): ?>
      <div class="card" style="text-align:center;padding:40px">
        <div style="font-size:48px;margin-bottom:16px;color:var(--muted)">💰</div>
        <h4>No Fee Structures</h4>
        <p class="muted">Create fee structures for different programs and fee types.</p>
        <button class="btn" onclick="showFeeModal()">
          <i class="bi bi-plus-lg"></i> Create First Fee
        </button>
      </div>
    <?php else: ?>
      <div class="card" style="overflow:auto">
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr style="text-align:left;border-bottom:1px solid var(--border)">
              <th style="padding:10px">Program</th>
              <th style="padding:10px">Fee Type</th>
              <th style="padding:10px">Name</th>
              <th style="padding:10px">Amount</th>
              <th style="padding:10px">Due Date</th>
              <th style="padding:10px">Status</th>
              <th style="padding:10px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($fees as $fee): ?>
            <tr style="border-bottom:1px solid var(--border)">
              <td style="padding:10px">
                <?php if($fee['program_name']): ?>
                  <?php echo htmlspecialchars($fee['program_name']); ?>
                <?php else: ?>
                  <span class="muted">All Programs</span>
                <?php endif; ?>
              </td>
              <td style="padding:10px">
                <span style="font-size:12px;padding:2px 6px;border-radius:4px;background:var(--surface-hover)">
                  <?php echo htmlspecialchars($feeTypes[$fee['fee_type']] ?? $fee['fee_type']); ?>
                </span>
              </td>
              <td style="padding:10px">
                <div style="font-weight:500"><?php echo htmlspecialchars($fee['fee_name']); ?></div>
                <?php if($fee['description']): ?>
                  <div class="muted" style="font-size:12px"><?php echo htmlspecialchars($fee['description']); ?></div>
                <?php endif; ?>
              </td>
              <td style="padding:10px">
                <div style="font-weight:500">GHS <?php echo number_format($fee['amount'], 2); ?></div>
                <?php if($fee['late_fee_amount'] > 0): ?>
                  <div class="muted" style="font-size:12px">Late: +GHS <?php echo number_format($fee['late_fee_amount'], 2); ?></div>
                <?php endif; ?>
              </td>
              <td style="padding:10px">
                <?php if($fee['due_date']): ?>
                  <span style="font-size:12px"><?php echo date('M j, Y', strtotime($fee['due_date'])); ?></span>
                <?php else: ?>
                  <span class="muted" style="font-size:12px">No due date</span>
                <?php endif; ?>
              </td>
              <td style="padding:10px">
                <?php if($fee['is_active']): ?>
                  <span style="color:#10b981;font-size:12px;font-weight:500">Active</span>
                <?php else: ?>
                  <span style="color:#6b7280;font-size:12px;font-weight:500">Inactive</span>
                <?php endif; ?>
                <?php if($fee['is_required']): ?>
                  <div style="color:#f59e0b;font-size:11px">Required</div>
                <?php endif; ?>
              </td>
              <td style="padding:10px;display:flex;gap:4px;flex-wrap:wrap">
                <button class="btn secondary" onclick="editFee(<?php echo htmlspecialchars(json_encode($fee)); ?>)">
                  <i class="bi bi-pencil"></i>
                </button>
                <form method="post" action="?panel=fee_structures" style="display:inline" onsubmit="return confirm('Delete this fee structure?')">
                  <input type="hidden" name="action" value="delete_fee">
                  <input type="hidden" name="fee_id" value="<?php echo (int)$fee['id']; ?>">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                  <button class="btn secondary" type="submit" style="color:#ef4444">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Waiver Codes Tab -->
  <div id="waiversContent" style="display:none">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3>Waiver Codes</h3>
      <button class="btn" onclick="showWaiverModal()">
        <i class="bi bi-plus-lg"></i> Create Waiver
      </button>
    </div>

    <?php if(empty($waivers)): ?>
      <div class="card" style="text-align:center;padding:40px">
        <div style="font-size:48px;margin-bottom:16px;color:var(--muted)">🎫</div>
        <h4>No Waiver Codes</h4>
        <p class="muted">Create waiver codes to offer discounts on fees.</p>
        <button class="btn" onclick="showWaiverModal()">
          <i class="bi bi-plus-lg"></i> Create First Waiver
        </button>
      </div>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px">
        <?php foreach($waivers as $waiver): 
          $isExpired = $waiver['valid_until'] && strtotime($waiver['valid_until']) < time();
          $isExhausted = $waiver['max_uses'] && $waiver['used_count'] >= $waiver['max_uses'];
          $isValid = !$isExpired && !$isExhausted && $waiver['is_active'];
        ?>
        <div style="border:1px solid var(--border);border-radius:12px;padding:20px;background:var(--card)">
          <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px">
            <div>
              <h4 style="margin:0;font-size:16px;font-family:monospace"><?php echo htmlspecialchars($waiver['code']); ?></h4>
              <div style="font-size:12px;margin-top:4px">
                <?php if($waiver['discount_type'] === 'percentage'): ?>
                  <?php echo $waiver['discount_value']; ?>% off
                <?php else: ?>
                  GHS <?php echo number_format($waiver['discount_value'], 2); ?> off
                <?php endif; ?>
              </div>
            </div>
            <div style="display:flex;gap:4px">
              <span style="font-size:11px;padding:2px 6px;border-radius:4px;background:<?php echo $isValid ? '#10b981' : '#6b7280'; ?>;color:white">
                <?php echo $isValid ? 'Active' : 'Inactive'; ?>
              </span>
            </div>
          </div>
          
          <?php if($waiver['description']): ?>
            <p class="muted" style="font-size:14px;margin-bottom:12px"><?php echo htmlspecialchars($waiver['description']); ?></p>
          <?php endif; ?>
          
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;font-size:12px">
            <div>
              <div class="muted">Uses:</div>
              <div><?php echo $waiver['used_count']; ?><?php echo $waiver['max_uses'] ? ' / ' . $waiver['max_uses'] : ' / ∞'; ?></div>
            </div>
            <div>
              <div class="muted">Valid Until:</div>
              <div><?php echo $waiver['valid_until'] ? date('M j, Y', strtotime($waiver['valid_until'])) : 'No expiry'; ?></div>
            </div>
          </div>
          
          <div style="display:flex;gap:8px;margin-top:16px">
            <button class="btn secondary" onclick="editWaiver(<?php echo htmlspecialchars(json_encode($waiver)); ?>)">
              <i class="bi bi-pencil"></i> Edit
            </button>
            <form method="post" action="?panel=fee_structures" style="display:inline" onsubmit="return confirm('Toggle waiver code status?')">
              <input type="hidden" name="action" value="toggle_waiver">
              <input type="hidden" name="waiver_id" value="<?php echo (int)$waiver['id']; ?>">
              <input type="hidden" name="is_active" value="<?php echo $waiver['is_active'] ? 0 : 1; ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
              <button class="btn secondary" type="submit">
                <i class="bi bi-toggle2-<?php echo $waiver['is_active']?'on':'off'; ?>"></i>
              </button>
            </form>
          </div>
          
          <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
            <div class="muted" style="font-size:12px">
              Created: <?php echo date('M j, Y', strtotime($waiver['created_at'])); ?>
              <?php if($waiver['created_by_name']): ?>
                by <?php echo htmlspecialchars($waiver['created_by_name']); ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Fee Modal -->
<div id="feeModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999">
  <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--card);border-radius:16px;padding:24px;min-width:600px;max-width:90vw">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3 id="feeModalTitle">Add Fee Structure</h3>
      <button onclick="closeFeeModal()" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
    </div>
    
    <form method="post" action="?panel=fee_structures" id="feeForm">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
      
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        <div>
          <label class="form-label">Program (Optional)</label>
          <select class="input" name="program_id" id="feeProgram">
            <option value="">All Programs</option>
            <?php foreach($programs as $program): ?>
              <option value="<?php echo (int)$program['id']; ?>"><?php echo htmlspecialchars($program['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Fee Type *</label>
          <select class="input" name="fee_type" id="feeType" required>
            <?php foreach($feeTypes as $value => $label): ?>
              <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Fee Name *</label>
          <input class="input" name="fee_name" id="feeName" required>
        </div>
        <div>
          <label class="form-label">Amount (GHS) *</label>
          <input class="input" name="amount" id="feeAmount" type="number" step="0.01" min="0" required>
        </div>
        <div>
          <label class="form-label">Due Date (Optional)</label>
          <input class="input" name="due_date" id="feeDueDate" type="date">
        </div>
        <div>
          <label class="form-label">Late Fee Amount</label>
          <input class="input" name="late_fee_amount" id="lateFeeAmount" type="number" step="0.01" min="0" value="0">
        </div>
        <div>
          <label class="form-label">Late Fee Grace Days</label>
          <input class="input" name="late_fee_grace_days" id="lateFeeGraceDays" type="number" min="0" value="0">
        </div>
      </div>
      
      <div style="margin-bottom:16px">
        <label class="form-label">Payment Methods</label>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <?php foreach($paymentMethods as $method): ?>
            <label style="display:flex;align-items:center;gap:6px">
              <input type="checkbox" name="payment_methods[]" value="<?php echo $method; ?>">
              <span style="text-transform:capitalize"><?php echo str_replace('_', ' ', $method); ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      
      <div style="margin-bottom:16px">
        <label class="form-label">Description</label>
        <textarea class="input" name="description" id="feeDescription" rows="3" placeholder="Optional description for this fee"></textarea>
      </div>
      
      <div style="display:flex;gap:12px;margin-bottom:16px">
        <label style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" name="is_required" id="feeRequired" value="1" checked>
          <span>Required Fee</span>
        </label>
        <label style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" name="is_active" id="feeActive" value="1" checked>
          <span>Active</span>
        </label>
      </div>
      
      <div style="display:flex;gap:12px;justify-content:flex-end">
        <button type="button" class="btn secondary" onclick="closeFeeModal()">Cancel</button>
        <button type="submit" class="btn">Save Fee</button>
      </div>
    </form>
  </div>
</div>

<!-- Waiver Modal -->
<div id="waiverModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999">
  <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--card);border-radius:16px;padding:24px;min-width:500px;max-width:90vw">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3 id="waiverModalTitle">Create Waiver Code</h3>
      <button onclick="closeWaiverModal()" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
    </div>
    
    <form method="post" action="?panel=fee_structures" id="waiverForm">
      <input type="hidden" name="action" value="save_waiver">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
      
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        <div>
          <label class="form-label">Waiver Code *</label>
          <input class="input" name="code" id="waiverCode" required placeholder="e.g., EARLY2025" style="text-transform:uppercase">
        </div>
        <div>
          <label class="form-label">Discount Type *</label>
          <select class="input" name="discount_type" id="waiverDiscountType" required>
            <option value="percentage">Percentage</option>
            <option value="fixed">Fixed Amount</option>
          </select>
        </div>
        <div>
          <label class="form-label">Discount Value *</label>
          <input class="input" name="discount_value" id="waiverDiscountValue" type="number" step="0.01" min="0" required>
        </div>
        <div>
          <label class="form-label">Max Uses (Optional)</label>
          <input class="input" name="max_uses" id="waiverMaxUses" type="number" min="0" placeholder="Leave empty for unlimited">
        </div>
        <div>
          <label class="form-label">Valid From (Optional)</label>
          <input class="input" name="valid_from" id="waiverValidFrom" type="date">
        </div>
        <div>
          <label class="form-label">Valid Until (Optional)</label>
          <input class="input" name="valid_until" id="waiverValidUntil" type="date">
        </div>
      </div>
      
      <div style="margin-bottom:16px">
        <label class="form-label">Applicable Fees</label>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <?php foreach($feeTypes as $value => $label): ?>
            <label style="display:flex;align-items:center;gap:6px">
              <input type="checkbox" name="applicable_fees[]" value="<?php echo $value; ?>">
              <span><?php echo $label; ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      
      <div style="margin-bottom:16px">
        <label class="form-label">Description</label>
        <textarea class="input" name="description" id="waiverDescription" rows="3" placeholder="Optional description for this waiver code"></textarea>
      </div>
      
      <div style="display:flex;gap:12px;justify-content:flex-end">
        <button type="button" class="btn secondary" onclick="closeWaiverModal()">Cancel</button>
        <button type="submit" class="btn">Create Waiver</button>
      </div>
    </form>
  </div>
</div>

<script>
function showTab(tab) {
  // Hide all content
  document.getElementById('feesContent').style.display = 'none';
  document.getElementById('waiversContent').style.display = 'none';
  
  // Remove active class from all tabs
  document.getElementById('feesTab').classList.remove('btn');
  document.getElementById('feesTab').classList.add('btn', 'secondary');
  document.getElementById('waiversTab').classList.remove('btn');
  document.getElementById('waiversTab').classList.add('btn', 'secondary');
  
  // Show selected content and activate tab
  if (tab === 'fees') {
    document.getElementById('feesContent').style.display = 'block';
    document.getElementById('feesTab').classList.remove('secondary');
  } else if (tab === 'waivers') {
    document.getElementById('waiversContent').style.display = 'block';
    document.getElementById('waiversTab').classList.remove('secondary');
  }
}

function showFeeModal(feeData = null) {
  const modal = document.getElementById('feeModal');
  const form = document.getElementById('feeForm');
  
  if (feeData) {
    // Edit mode
    form.action = '?panel=fee_structures&action=update_fee';
    form.innerHTML += '<input type="hidden" name="fee_id" value="' + feeData.id + '">';
    document.getElementById('feeModalTitle').textContent = 'Edit Fee Structure';
    
    document.getElementById('feeProgram').value = feeData.program_id || '';
    document.getElementById('feeType').value = feeData.fee_type || '';
    document.getElementById('feeName').value = feeData.fee_name || '';
    document.getElementById('feeAmount').value = feeData.amount || '';
    document.getElementById('feeDueDate').value = feeData.due_date || '';
    document.getElementById('lateFeeAmount').value = feeData.late_fee_amount || '0';
    document.getElementById('lateFeeGraceDays').value = feeData.late_fee_grace_days || '0';
    document.getElementById('feeDescription').value = feeData.description || '';
    document.getElementById('feeRequired').checked = feeData.is_required == '1';
    document.getElementById('feeActive').checked = feeData.is_active == '1';
    
    // Set payment methods
    try {
      const methods = JSON.parse(feeData.payment_methods || '[]');
      document.querySelectorAll('input[name="payment_methods[]"]').forEach(checkbox => {
        checkbox.checked = methods.includes(checkbox.value);
      });
    } catch(e) {}
  } else {
    // Create mode
    form.action = '?panel=fee_structures&action=save_fee';
    document.getElementById('feeModalTitle').textContent = 'Add Fee Structure';
    form.reset();
  }
  
  modal.style.display = 'block';
}

function closeFeeModal() {
  document.getElementById('feeModal').style.display = 'none';
}

function editFee(feeData) {
  showFeeModal(feeData);
}

function showWaiverModal(waiverData = null) {
  const modal = document.getElementById('waiverModal');
  const form = document.getElementById('waiverForm');
  
  if (waiverData) {
    // Edit mode - would need to implement update functionality
    document.getElementById('waiverModalTitle').textContent = 'Edit Waiver Code';
    // Set form values...
  } else {
    // Create mode
    document.getElementById('waiverModalTitle').textContent = 'Create Waiver Code';
    form.reset();
  }
  
  modal.style.display = 'block';
}

function closeWaiverModal() {
  document.getElementById('waiverModal').style.display = 'none';
}

function editWaiver(waiverData) {
  showWaiverModal(waiverData);
}

// Auto-uppercase waiver code
document.addEventListener('DOMContentLoaded', function() {
  const waiverCodeInput = document.getElementById('waiverCode');
  if (waiverCodeInput) {
    waiverCodeInput.addEventListener('input', function() {
      this.value = this.value.toUpperCase();
    });
  }
});

// Close modals when clicking outside
document.addEventListener('click', function(e) {
  if (e.target.id === 'feeModal') closeFeeModal();
  if (e.target.id === 'waiverModal') closeWaiverModal();
});
</script>
