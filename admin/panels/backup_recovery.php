<?php
// Backup & Recovery panel - Database and file backup management

$msg=''; $type='';

// Handle backup actions
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  try {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) { 
      throw new RuntimeException('Invalid request'); 
    }
    
    if ($action==='create_backup') {
      $backupType = $_POST['backup_type'] ?? 'full';
      $includeFiles = isset($_POST['include_files']);
      
      // Create backup directory if it doesn't exist
      $backupDir = $_SERVER['DOCUMENT_ROOT'] . '/../backups';
      if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
      }
      
      $timestamp = date('Y-m-d_H-i-s');
      $backupName = "backup_{$timestamp}";
      
      if ($backupType === 'database') {
        // Database backup only
        $sqlFile = "{$backupDir}/{$backupName}.sql";
        $command = "mysqldump --host=" . DB_HOST . " --user=" . DB_USER . " --password=" . DB_PASS . " " . DB_NAME . " > " . $sqlFile;
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($sqlFile)) {
          $msg = 'Database backup created successfully'; $type = 'success';
        } else {
          throw new RuntimeException('Database backup failed');
        }
      } else {
        // Full backup (database + files)
        $zipFile = "{$backupDir}/{$backupName}.zip";
        
        // Create database dump
        $sqlFile = "{$backupDir}/{$backupName}.sql";
        $command = "mysqldump --host=" . DB_HOST . " --user=" . DB_USER . " --password=" . DB_PASS . " " . DB_NAME . " > " . $sqlFile;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
          throw new RuntimeException('Database backup failed');
        }
        
        // Create ZIP file
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
          // Add database file
          $zip->addFile($sqlFile, 'database.sql');
          
          // Add uploads directory if requested
          if ($includeFiles) {
            $uploadsDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads';
            if (is_dir($uploadsDir)) {
              $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadsDir));
              foreach ($iterator as $file) {
                if ($file->isFile()) {
                  $relativePath = 'uploads/' . substr($file->getPathname(), strlen($uploadsDir) + 1);
                  $zip->addFile($file->getPathname(), $relativePath);
                }
              }
            }
          }
          
          $zip->close();
          
          // Remove temporary SQL file
          unlink($sqlFile);
          
          if (file_exists($zipFile)) {
            $fileSize = filesize($zipFile);
            $msg = "Full backup created successfully (" . formatBytes($fileSize) . ")"; 
            $type = 'success';
          } else {
            throw new RuntimeException('Backup file creation failed');
          }
        } else {
          throw new RuntimeException('Cannot create backup archive');
        }
      }
      
    } elseif ($action==='restore_backup') {
      $backupFile = $_FILES['backup_file']['tmp_name'] ?? '';
      
      if (!$backupFile || !is_uploaded_file($backupFile)) {
        throw new RuntimeException('Please select a valid backup file');
      }
      
      // Create safety backup before restore
      $safetyBackup = "safety_backup_" . date('Y-m-d_H-i-s') . ".sql";
      $safetyPath = $_SERVER['DOCUMENT_ROOT'] . '/../backups/' . $safetyBackup;
      
      $command = "mysqldump --host=" . DB_HOST . " --user=" . DB_USER . " --password=" . DB_PASS . " " . DB_NAME . " > " . $safetyPath;
      exec($command, $output, $returnCode);
      
      if ($returnCode !== 0) {
        throw new RuntimeException('Failed to create safety backup');
      }
      
      // Check if it's a ZIP file or SQL file
      $fileExtension = pathinfo($_FILES['backup_file']['name'], PATHINFO_EXTENSION);
      
      if (strtolower($fileExtension) === 'zip') {
        // Extract and restore from ZIP
        $zip = new ZipArchive();
        if ($zip->open($backupFile) === TRUE) {
          $sqlContent = $zip->getFromName('database.sql');
          if ($sqlContent === false) {
            throw new RuntimeException('No database.sql found in backup file');
          }
          
          // Write SQL content to temporary file
          $tempSql = tempnam(sys_get_temp_dir(), 'restore_');
          file_put_contents($tempSql, $sqlContent);
          
          // Restore database
          $command = "mysql --host=" . DB_HOST . " --user=" . DB_USER . " --password=" . DB_PASS . " " . DB_NAME . " < " . $tempSql;
          exec($command, $output, $returnCode);
          
          unlink($tempSql);
          $zip->close();
          
          if ($returnCode === 0) {
            $msg = 'Backup restored successfully. Safety backup created at: ' . $safetyBackup; 
            $type = 'success';
          } else {
            throw new RuntimeException('Database restore failed');
          }
        } else {
          throw new RuntimeException('Cannot open backup file');
        }
      } else {
        // Direct SQL file restore
        $command = "mysql --host=" . DB_HOST . " --user=" . DB_USER . " --password=" . DB_PASS . " " . DB_NAME . " < " . $backupFile;
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
          $msg = 'Database restored successfully. Safety backup created at: ' . $safetyBackup; 
          $type = 'success';
        } else {
          throw new RuntimeException('Database restore failed');
        }
      }
      
    } elseif ($action==='download_backup') {
      $filename = $_POST['filename'] ?? '';
      $backupPath = $_SERVER['DOCUMENT_ROOT'] . '/../backups/' . $filename;
      
      if (!file_exists($backupPath)) {
        throw new RuntimeException('Backup file not found');
      }
      
      header('Content-Type: application/octet-stream');
      header('Content-Disposition: attachment; filename="' . $filename . '"');
      header('Content-Length: ' . filesize($backupPath));
      readfile($backupPath);
      exit;
      
    } elseif ($action==='delete_backup') {
      $filename = $_POST['filename'] ?? '';
      $backupPath = $_SERVER['DOCUMENT_ROOT'] . '/../backups/' . $filename;
      
      if (!file_exists($backupPath)) {
        throw new RuntimeException('Backup file not found');
      }
      
      if (unlink($backupPath)) {
        $msg = 'Backup file deleted successfully'; $type = 'success';
      } else {
        throw new RuntimeException('Failed to delete backup file');
      }
    }
  } catch (Throwable $e) { 
    $msg = 'Failed: ' . $e->getMessage(); 
    $type = 'danger'; 
  }
}

// Get backup files
$backupFiles = [];
$backupDir = $_SERVER['DOCUMENT_ROOT'] . '/../backups';
if (is_dir($backupDir)) {
  $files = scandir($backupDir);
  foreach ($files as $file) {
    if ($file !== '.' && $file !== '..' && (strpos($file, 'backup_') === 0 || strpos($file, 'safety_backup_') === 0)) {
      $filePath = $backupDir . '/' . $file;
      $backupFiles[] = [
        'name' => $file,
        'size' => filesize($filePath),
        'date' => filemtime($filePath),
        'type' => pathinfo($file, PATHINFO_EXTENSION)
      ];
    }
  }
  
  // Sort by date (newest first)
  usort($backupFiles, function($a, $b) {
    return $b['date'] - $a['date'];
  });
}

// System info
$systemInfo = [
  'php_version' => PHP_VERSION,
  'mysql_version' => '',
  'disk_space' => disk_free_space($_SERVER['DOCUMENT_ROOT']),
  'memory_limit' => ini_get('memory_limit'),
  'max_execution_time' => ini_get('max_execution_time')
];

try {
  $stmt = $pdo->query("SELECT VERSION() as version");
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  $systemInfo['mysql_version'] = $result['version'];
} catch (Throwable $e) {
  $systemInfo['mysql_version'] = 'Unknown';
}

function formatBytes($bytes, $precision = 2) {
  $units = array('B', 'KB', 'MB', 'GB', 'TB');
  
  for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
    $bytes /= 1024;
  }
  
  return round($bytes, $precision) . ' ' . $units[$i];
}
?>

<?php if($msg): ?>
<div class="card" style="border-left:4px solid <?php echo $type==='success'?'#10b981':'#ef4444'; ?>;margin-bottom:12px;"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<!-- System Information -->
<div class="panel-card">
  <h3>System Information</h3>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px">
    <div style="text-align:center;padding:16px;background:var(--surface-hover);border-radius:8px">
      <div style="font-size:24px;font-weight:700;color:#2563eb"><?php echo $systemInfo['php_version']; ?></div>
      <div class="muted">PHP Version</div>
    </div>
    <div style="text-align:center;padding:16px;background:var(--surface-hover);border-radius:8px">
      <div style="font-size:24px;font-weight:700;color:#10b981"><?php echo $systemInfo['mysql_version']; ?></div>
      <div class="muted">MySQL Version</div>
    </div>
    <div style="text-align:center;padding:16px;background:var(--surface-hover);border-radius:8px">
      <div style="font-size:24px;font-weight:700;color:#f59e0b"><?php echo formatBytes($systemInfo['disk_space']); ?></div>
      <div class="muted">Free Disk Space</div>
    </div>
    <div style="text-align:center;padding:16px;background:var(--surface-hover);border-radius:8px">
      <div style="font-size:24px;font-weight:700;color:#8b5cf6"><?php echo $systemInfo['memory_limit']; ?></div>
      <div class="muted">Memory Limit</div>
    </div>
  </div>
</div>

<!-- Create Backup -->
<div class="panel-card">
  <h3>Create Backup</h3>
  <form method="post" action="?panel=backup_recovery" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px">
    <input type="hidden" name="action" value="create_backup">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    
    <div>
      <label class="form-label">Backup Type</label>
      <select class="input" name="backup_type" required>
        <option value="full">Full Backup (Database + Files)</option>
        <option value="database">Database Only</option>
      </select>
    </div>
    
    <div>
      <label class="form-label">Include Upload Files</label>
      <div style="display:flex;align-items:center;gap:8px;margin-top:8px">
        <input type="checkbox" name="include_files" value="1" checked>
        <span>Include uploads directory</span>
      </div>
    </div>
    
    <div style="display:flex;align-items:flex-end">
      <button class="btn" type="submit">
        <i class="bi bi-download"></i> Create Backup
      </button>
    </div>
  </form>
</div>

<!-- Restore Backup -->
<div class="panel-card">
  <h3>Restore Backup</h3>
  <div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:12px;margin-bottom:16px">
    <div style="display:flex;align-items:center;gap:8px;color:#92400e">
      <i class="bi bi-exclamation-triangle"></i>
      <span style="font-weight:500">Warning:</span>
      <span>This will overwrite your current database. A safety backup will be created automatically.</span>
    </div>
  </div>
  
  <form method="post" action="?panel=backup_recovery" enctype="multipart/form-data" style="display:grid;grid-template-columns:1fr auto;gap:16px;align-items:end">
    <input type="hidden" name="action" value="restore_backup">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    
    <div>
      <label class="form-label">Select Backup File</label>
      <input class="input" type="file" name="backup_file" accept=".sql,.zip" required>
      <div class="muted" style="font-size:12px;margin-top:4px">Supported formats: .sql, .zip</div>
    </div>
    
    <button class="btn" type="submit" style="background:#ef4444;color:white">
      <i class="bi bi-upload"></i> Restore Backup
    </button>
  </form>
</div>

<!-- Backup Files -->
<div class="panel-card">
  <h3>Backup Files</h3>
  
  <?php if(empty($backupFiles)): ?>
    <div class="card" style="text-align:center;padding:40px">
      <div style="font-size:48px;margin-bottom:16px;color:var(--muted)">💾</div>
      <h4>No Backup Files</h4>
      <p class="muted">Create your first backup to get started.</p>
    </div>
  <?php else: ?>
    <div class="card" style="overflow:auto">
      <table style="width:100%;border-collapse:collapse">
        <thead>
          <tr style="text-align:left;border-bottom:1px solid var(--border)">
            <th style="padding:10px">Filename</th>
            <th style="padding:10px">Type</th>
            <th style="padding:10px">Size</th>
            <th style="padding:10px">Created</th>
            <th style="padding:10px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($backupFiles as $file): ?>
          <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:10px">
              <div style="font-weight:500;font-family:monospace"><?php echo htmlspecialchars($file['name']); ?></div>
            </td>
            <td style="padding:10px">
              <span style="font-size:12px;padding:2px 6px;border-radius:4px;background:var(--surface-hover)">
                <?php echo strtoupper($file['type']); ?>
              </span>
            </td>
            <td style="padding:10px">
              <div style="font-weight:500"><?php echo formatBytes($file['size']); ?></div>
            </td>
            <td style="padding:10px">
              <div style="font-size:12px">
                <?php echo date('M j, Y g:i A', $file['date']); ?>
              </div>
            </td>
            <td style="padding:10px;display:flex;gap:4px;flex-wrap:wrap">
              <form method="post" action="?panel=backup_recovery" style="display:inline">
                <input type="hidden" name="action" value="download_backup">
                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($file['name']); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <button class="btn secondary" type="submit" title="Download">
                  <i class="bi bi-download"></i>
                </button>
              </form>
              <form method="post" action="?panel=backup_recovery" style="display:inline" onsubmit="return confirm('Delete this backup file? This cannot be undone.')">
                <input type="hidden" name="action" value="delete_backup">
                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($file['name']); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <button class="btn secondary" type="submit" title="Delete" style="color:#ef4444">
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

<!-- Database Export -->
<div class="panel-card">
  <h3>Database Export</h3>
  <p class="muted" style="margin-bottom:16px">Export specific data from your database in various formats.</p>
  
  <form method="post" action="?panel=backup_recovery" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px">
    <input type="hidden" name="action" value="export_data">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    
    <div>
      <label class="form-label">Export Type</label>
      <select class="input" name="export_type">
        <option value="applications">Applications</option>
        <option value="students">Students</option>
        <option value="payments">Payments</option>
        <option value="users">Users</option>
        <option value="full">Full Database</option>
      </select>
    </div>
    
    <div>
      <label class="form-label">Date Range (Optional)</label>
      <div style="display:flex;gap:8px">
        <input class="input" name="date_from" type="date" placeholder="From">
        <input class="input" name="date_to" type="date" placeholder="To">
      </div>
    </div>
    
    <div>
      <label class="form-label">Format</label>
      <select class="input" name="export_format">
        <option value="csv">CSV</option>
        <option value="sql">SQL</option>
      </select>
    </div>
    
    <div style="display:flex;align-items:flex-end">
      <button class="btn" type="submit">
        <i class="bi bi-download"></i> Export Data
      </button>
    </div>
  </form>
</div>

<!-- Automated Backup Settings -->
<div class="panel-card">
  <h3>Automated Backup Settings</h3>
  <div style="background:#e0f2fe;border:1px solid #0891b2;border-radius:8px;padding:16px;margin-bottom:16px">
    <div style="display:flex;align-items:center;gap:8px;color:#0c4a6e;margin-bottom:8px">
      <i class="bi bi-info-circle"></i>
      <span style="font-weight:500">Cron Job Setup Required</span>
    </div>
    <p style="color:#0c4a6e;margin:0;font-size:14px">
      To enable automated backups, add this cron job to run daily at 2 AM:
    </p>
    <div style="background:#f0f9ff;border:1px solid #0ea5e9;border-radius:6px;padding:12px;margin-top:8px;font-family:monospace;font-size:14px">
      0 2 * * * php <?php echo $_SERVER['DOCUMENT_ROOT']; ?>/cron/backup-cron.php
    </div>
  </div>
  
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px">
    <div style="text-align:center;padding:16px;background:var(--surface-hover);border-radius:8px">
      <div style="font-size:24px;font-weight:700;color:#10b981">Daily</div>
      <div class="muted">Database Backups</div>
      <div style="font-size:12px;margin-top:4px">Retention: 7 days</div>
    </div>
    <div style="text-align:center;padding:16px;background:var(--surface-hover);border-radius:8px">
      <div style="font-size:24px;font-weight:700;color:#2563eb">Weekly</div>
      <div class="muted">Full Backups</div>
      <div style="font-size:12px;margin-top:4px">Retention: 4 weeks</div>
    </div>
    <div style="text-align:center;padding:16px;background:var(--surface-hover);border-radius:8px">
      <div style="font-size:24px;font-weight:700;color:#8b5cf6">Monthly</div>
      <div class="muted">Archive Backups</div>
      <div style="font-size:12px;margin-top:4px">Retention: 3 months</div>
    </div>
  </div>
</div>

<script>
// Auto-refresh backup list every 30 seconds when creating backup
document.addEventListener('DOMContentLoaded', function() {
  const backupForm = document.querySelector('form[action*="create_backup"]');
  if (backupForm) {
    backupForm.addEventListener('submit', function() {
      setTimeout(() => {
        location.reload();
      }, 3000);
    });
  }
});
</script>
