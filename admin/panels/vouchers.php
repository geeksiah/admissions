<?php
// Vouchers panel - Voucher generation and management

$msg=''; $type='';
try { 
  $pdo->exec("CREATE TABLE IF NOT EXISTS voucher_batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_name VARCHAR(150) NOT NULL,
    batch_description TEXT,
    voucher_value DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'GHS',
    quantity INT NOT NULL,
    generated_count INT DEFAULT 0,
    used_count INT DEFAULT 0,
    expiry_date DATE,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active(is_active),
    INDEX idx_expiry(expiry_date)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  
  $pdo->exec("CREATE TABLE IF NOT EXISTS vouchers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED,
    serial_number VARCHAR(50) UNIQUE NOT NULL,
    pin_code VARCHAR(20) NOT NULL,
    value DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'GHS',
    status ENUM('active', 'used', 'expired', 'cancelled') DEFAULT 'active',
    used_by INT UNSIGNED,
    used_at TIMESTAMP NULL,
    application_id INT UNSIGNED,
    expiry_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_serial(serial_number),
    INDEX idx_status(status),
    INDEX idx_batch(batch_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) { /* ignore */ }

// Handle actions
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  try {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) { 
      throw new RuntimeException('Invalid request'); 
    }
    
    if ($action==='generate_batch') {
      $batchName = trim($_POST['batch_name'] ?? '');
      $batchDescription = trim($_POST['batch_description'] ?? '');
      $voucherValue = (float)($_POST['voucher_value'] ?? 0);
      $quantity = (int)($_POST['quantity'] ?? 0);
      $expiryDate = $_POST['expiry_date'] ?? null;
      
      if (!$batchName || !$voucherValue || !$quantity) {
        throw new RuntimeException('Batch name, value, and quantity are required');
      }
      
      if ($quantity > 1000) {
        throw new RuntimeException('Maximum 1000 vouchers per batch');
      }
      
      // Create batch
      $stmt = $pdo->prepare("INSERT INTO voucher_batches (batch_name, batch_description, voucher_value, quantity, expiry_date, created_by) VALUES (?, ?, ?, ?, ?, ?)");
      $stmt->execute([$batchName, $batchDescription, $voucherValue, $quantity, $expiryDate ?: null, $_SESSION['user_id']]);
      $batchId = $pdo->lastInsertId();
      
      // Generate vouchers
      $generated = 0;
      for ($i = 0; $i < $quantity; $i++) {
        $serial = 'VCH-' . strtoupper(substr(md5(uniqid() . $i), 0, 8));
        $pin = str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        
        try {
          $stmt = $pdo->prepare("INSERT INTO vouchers (batch_id, serial_number, pin_code, value, expiry_date) VALUES (?, ?, ?, ?, ?)");
          $stmt->execute([$batchId, $serial, $pin, $voucherValue, $expiryDate ?: null]);
          $generated++;
        } catch (PDOException $e) {
          // Skip duplicate serials
          continue;
        }
      }
      
      // Update batch count
      $stmt = $pdo->prepare("UPDATE voucher_batches SET generated_count = ? WHERE id = ?");
      $stmt->execute([$generated, $batchId]);
      
      $msg="Voucher batch created successfully. Generated {$generated} vouchers."; 
      $type='success';
      
    } elseif ($action==='toggle_batch') {
      $id = (int)($_POST['batch_id'] ?? 0);
      $isActive = (int)($_POST['is_active'] ?? 0);
      
      if (!$id) throw new RuntimeException('Batch ID required');
      
      $stmt = $pdo->prepare("UPDATE voucher_batches SET is_active=? WHERE id=?");
      $stmt->execute([$isActive, $id]);
      
      $msg='Batch status updated'; $type='success';
      
    } elseif ($action==='export_vouchers') {
      $batchId = (int)($_POST['batch_id'] ?? 0);
      
      if (!$batchId) throw new RuntimeException('Batch ID required');
      
      // Get batch info
      $stmt = $pdo->prepare("SELECT * FROM voucher_batches WHERE id = ?");
      $stmt->execute([$batchId]);
      $batch = $stmt->fetch(PDO::FETCH_ASSOC);
      
      if (!$batch) throw new RuntimeException('Batch not found');
      
      // Get vouchers
      $stmt = $pdo->prepare("SELECT serial_number, pin_code, value, status FROM vouchers WHERE batch_id = ? ORDER BY id");
      $stmt->execute([$batchId]);
      $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
      
      // Export as CSV
      header('Content-Type: text/csv');
      header('Content-Disposition: attachment; filename="vouchers_' . $batch['batch_name'] . '_' . date('Y-m-d') . '.csv"');
      
      $output = fopen('php://output', 'w');
      fputcsv($output, ['Serial Number', 'PIN Code', 'Value', 'Status']);
      foreach ($vouchers as $voucher) {
        fputcsv($output, [$voucher['serial_number'], $voucher['pin_code'], $voucher['value'], $voucher['status']]);
      }
      fclose($output);
      exit;
    }
  } catch (Throwable $e) { 
    $msg='Failed: '.$e->getMessage(); 
    $type='danger'; 
  }
}

// Fetch batches with stats
$batches = [];
try {
  $stmt = $pdo->query("
    SELECT 
      vb.*,
      u.username as created_by_name,
      COUNT(v.id) as total_vouchers,
      COUNT(CASE WHEN v.status = 'used' THEN 1 END) as used_vouchers,
      COUNT(CASE WHEN v.status = 'active' THEN 1 END) as active_vouchers
    FROM voucher_batches vb
    LEFT JOIN users u ON vb.created_by = u.id
    LEFT JOIN vouchers v ON vb.id = v.batch_id
    GROUP BY vb.id
    ORDER BY vb.created_at DESC
  ");
  $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* ignore */ }

// Fetch recent vouchers
$recentVouchers = [];
try {
  $stmt = $pdo->query("
    SELECT v.*, vb.batch_name, s.first_name, s.last_name
    FROM vouchers v
    LEFT JOIN voucher_batches vb ON v.batch_id = vb.id
    LEFT JOIN students s ON v.used_by = s.id
    ORDER BY v.created_at DESC
    LIMIT 20
  ");
  $recentVouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* ignore */ }

// Stats
$stats = [
  'total_batches' => count($batches),
  'total_vouchers' => 0,
  'used_vouchers' => 0,
  'active_vouchers' => 0,
  'total_value' => 0
];

foreach ($batches as $batch) {
  $stats['total_vouchers'] += $batch['total_vouchers'];
  $stats['used_vouchers'] += $batch['used_vouchers'];
  $stats['active_vouchers'] += $batch['active_vouchers'];
  $stats['total_value'] += $batch['total_vouchers'] * $batch['voucher_value'];
}
?>

<?php if($msg): ?>
<div class="card" style="border-left:4px solid <?php echo $type==='success'?'#10b981':'#ef4444'; ?>;margin-bottom:12px;"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<!-- Voucher Stats -->
<div class="stat-grid">
  <div class="stat-card">
    <h4 class="stat-card-title">Total Batches</h4>
    <div class="stat-card-value"><?php echo number_format($stats['total_batches']); ?></div>
  </div>
  <div class="stat-card">
    <h4 class="stat-card-title">Total Vouchers</h4>
    <div class="stat-card-value"><?php echo number_format($stats['total_vouchers']); ?></div>
  </div>
  <div class="stat-card">
    <h4 class="stat-card-title">Used Vouchers</h4>
    <div class="stat-card-value"><?php echo number_format($stats['used_vouchers']); ?></div>
  </div>
  <div class="stat-card">
    <h4 class="stat-card-title">Active Vouchers</h4>
    <div class="stat-card-value"><?php echo number_format($stats['active_vouchers']); ?></div>
  </div>
  <div class="stat-card">
    <h4 class="stat-card-title">Total Value</h4>
    <div class="stat-card-value">GHS <?php echo number_format($stats['total_value'], 2); ?></div>
  </div>
</div>

<!-- Generate Voucher Batch -->
<div class="panel-card">
  <h3>Generate Voucher Batch</h3>
  <form method="post" action="?panel=vouchers" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px">
    <input type="hidden" name="action" value="generate_batch">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    
    <div>
      <label class="form-label">Batch Name *</label>
      <input class="input" name="batch_name" required placeholder="e.g., Q1 2025 Vouchers">
    </div>
    
    <div>
      <label class="form-label">Voucher Value (GHS) *</label>
      <input class="input" name="voucher_value" type="number" step="0.01" min="0" required placeholder="500.00">
    </div>
    
    <div>
      <label class="form-label">Quantity *</label>
      <input class="input" name="quantity" type="number" min="1" max="1000" required placeholder="100">
    </div>
    
    <div>
      <label class="form-label">Expiry Date (Optional)</label>
      <input class="input" name="expiry_date" type="date">
    </div>
    
    <div style="grid-column:1/-1">
      <label class="form-label">Description</label>
      <textarea class="input" name="batch_description" rows="2" placeholder="Optional description for this voucher batch"></textarea>
    </div>
    
    <div style="display:flex;align-items:flex-end">
      <button class="btn" type="submit">
        <i class="bi bi-plus-lg"></i> Generate Vouchers
      </button>
    </div>
  </form>
</div>

<!-- Voucher Batches -->
<div class="panel-card">
  <h3>Voucher Batches</h3>
  
  <?php if(empty($batches)): ?>
    <div class="card" style="text-align:center;padding:40px">
      <div style="font-size:48px;margin-bottom:16px;color:var(--muted)">🎫</div>
      <h4>No Voucher Batches</h4>
      <p class="muted">Create your first voucher batch to start generating vouchers.</p>
    </div>
  <?php else: ?>
    <div class="card" style="overflow:auto">
      <table style="width:100%;border-collapse:collapse">
        <thead>
          <tr style="text-align:left;border-bottom:1px solid var(--border)">
            <th style="padding:10px">Batch Name</th>
            <th style="padding:10px">Value</th>
            <th style="padding:10px">Total</th>
            <th style="padding:10px">Used</th>
            <th style="padding:10px">Active</th>
            <th style="padding:10px">Status</th>
            <th style="padding:10px">Created</th>
            <th style="padding:10px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($batches as $batch): 
            $usageRate = $batch['total_vouchers'] > 0 ? round(($batch['used_vouchers'] / $batch['total_vouchers']) * 100, 1) : 0;
          ?>
          <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:10px">
              <div>
                <div style="font-weight:500"><?php echo htmlspecialchars($batch['batch_name']); ?></div>
                <?php if($batch['batch_description']): ?>
                  <div class="muted" style="font-size:12px"><?php echo htmlspecialchars($batch['batch_description']); ?></div>
                <?php endif; ?>
              </div>
            </td>
            <td style="padding:10px">
              <div style="font-weight:500">GHS <?php echo number_format($batch['voucher_value'], 2); ?></div>
            </td>
            <td style="padding:10px"><?php echo number_format($batch['total_vouchers']); ?></td>
            <td style="padding:10px">
              <div style="color:#10b981"><?php echo number_format($batch['used_vouchers']); ?></div>
              <div class="muted" style="font-size:12px"><?php echo $usageRate; ?>%</div>
            </td>
            <td style="padding:10px">
              <div style="color:#2563eb"><?php echo number_format($batch['active_vouchers']); ?></div>
            </td>
            <td style="padding:10px">
              <?php if($batch['is_active']): ?>
                <span style="color:#10b981;font-size:12px;font-weight:500">Active</span>
              <?php else: ?>
                <span style="color:#6b7280;font-size:12px;font-weight:500">Inactive</span>
              <?php endif; ?>
            </td>
            <td style="padding:10px">
              <div style="font-size:12px">
                <?php echo date('M j, Y', strtotime($batch['created_at'])); ?>
              </div>
              <div class="muted" style="font-size:11px">
                by <?php echo htmlspecialchars($batch['created_by_name'] ?? 'Unknown'); ?>
              </div>
            </td>
            <td style="padding:10px;display:flex;gap:4px;flex-wrap:wrap">
              <form method="post" action="?panel=vouchers" style="display:inline">
                <input type="hidden" name="action" value="export_vouchers">
                <input type="hidden" name="batch_id" value="<?php echo (int)$batch['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <button class="btn secondary" type="submit" title="Export to CSV">
                  <i class="bi bi-download"></i>
                </button>
              </form>
              <button class="btn secondary" onclick="viewBatch(<?php echo (int)$batch['id']; ?>, '<?php echo htmlspecialchars($batch['batch_name']); ?>')" title="View Vouchers">
                <i class="bi bi-eye"></i>
              </button>
              <form method="post" action="?panel=vouchers" style="display:inline" onsubmit="return confirm('Toggle batch status?')">
                <input type="hidden" name="action" value="toggle_batch">
                <input type="hidden" name="batch_id" value="<?php echo (int)$batch['id']; ?>">
                <input type="hidden" name="is_active" value="<?php echo $batch['is_active'] ? 0 : 1; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <button class="btn secondary" type="submit" title="Toggle Status">
                  <i class="bi bi-toggle2-<?php echo $batch['is_active']?'on':'off'; ?>"></i>
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

<!-- Recent Voucher Activity -->
<div class="panel-card">
  <h3>Recent Voucher Activity</h3>
  <?php if(empty($recentVouchers)): ?>
    <div class="muted">No voucher activity yet.</div>
  <?php else: ?>
    <div class="card" style="overflow:auto;max-height:300px">
      <?php foreach($recentVouchers as $voucher): ?>
        <div style="padding:12px;border-bottom:1px solid var(--border)">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
            <div style="flex:1">
              <div style="display:flex;gap:12px;align-items:center;margin-bottom:4px">
                <div style="font-weight:500;font-family:monospace"><?php echo htmlspecialchars($voucher['serial_number']); ?></div>
                <span style="font-size:12px;padding:2px 6px;border-radius:4px;background:var(--surface-hover)">
                  <?php echo strtoupper($voucher['status']); ?>
                </span>
              </div>
              <div class="muted" style="font-size:12px">
                <?php if($voucher['used_by'] && $voucher['first_name']): ?>
                  Used by: <?php echo htmlspecialchars($voucher['first_name'].' '.$voucher['last_name']); ?> • 
                <?php endif; ?>
                Batch: <?php echo htmlspecialchars($voucher['batch_name']); ?> • 
                Value: GHS <?php echo number_format($voucher['value'], 2); ?>
              </div>
            </div>
            <div style="font-size:12px;color:var(--muted)">
              <?php echo date('M j, Y g:i A', strtotime($voucher['created_at'])); ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- View Batch Modal -->
<div id="viewBatchModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999">
  <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--card);border-radius:16px;padding:24px;min-width:600px;max-width:90vw;max-height:90vh;overflow:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3 id="batchModalTitle">Voucher Details</h3>
      <button onclick="closeBatchModal()" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
    </div>
    <div id="batchVouchers"></div>
  </div>
</div>

<script>
function viewBatch(batchId, batchName) {
  document.getElementById('batchModalTitle').textContent = batchName + ' - Vouchers';
  
  // In a real implementation, this would fetch vouchers via AJAX
  document.getElementById('batchVouchers').innerHTML = `
    <div style="text-align:center;padding:40px;color:var(--muted)">
      <div style="font-size:24px;margin-bottom:8px">🎫</div>
      <div>Loading vouchers for batch ID: ${batchId}</div>
      <div style="font-size:12px;margin-top:8px">This would display all vouchers in the selected batch</div>
    </div>
  `;
  
  document.getElementById('viewBatchModal').style.display = 'block';
}

function closeBatchModal() {
  document.getElementById('viewBatchModal').style.display = 'none';
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
  if (e.target.id === 'viewBatchModal') closeBatchModal();
});
</script>
