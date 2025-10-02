<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
require_once '../classes/BackupManager.php';

// Check admin access
requireRole(['admin']);

// Get backup ID from request
$backupId = $_GET['id'] ?? '';

if (empty($backupId)) {
    echo '<div class="alert alert-danger">Backup ID is required.</div>';
    exit;
}

// Initialize backup manager
$backupManager = new BackupManager($database);

// Get backup details
$backupDetails = $backupManager->getBackupDetails($backupId);

if (!$backupDetails) {
    echo '<div class="alert alert-danger">Backup not found.</div>';
    exit;
}

// Format bytes function
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Format date function
function formatDate($date) {
    return date('M j, Y g:i A', strtotime($date));
}
?>

<div class="row">
    <div class="col-md-6">
        <h6>Backup Information</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Backup ID:</strong></td>
                <td><code><?php echo htmlspecialchars($backupDetails['backup_id']); ?></code></td>
            </tr>
            <tr>
                <td><strong>Type:</strong></td>
                <td><span class="badge bg-info"><?php echo ucfirst($backupDetails['type']); ?></span></td>
            </tr>
            <tr>
                <td><strong>Created:</strong></td>
                <td><?php echo formatDate($backupDetails['timestamp']); ?></td>
            </tr>
            <tr>
                <td><strong>Size:</strong></td>
                <td><?php echo $backupDetails['size_formatted']; ?></td>
            </tr>
            <tr>
                <td><strong>Created By:</strong></td>
                <td><?php echo $backupDetails['created_by'] ? 'User #' . $backupDetails['created_by'] : 'System'; ?></td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <h6>Description</h6>
        <p><?php echo htmlspecialchars($backupDetails['description'] ?: 'No description provided'); ?></p>
        
        <h6>Files Included</h6>
        <ul class="list-unstyled">
            <?php if (isset($backupDetails['database_file'])): ?>
                <li><i class="bi bi-database text-primary me-2"></i><?php echo htmlspecialchars($backupDetails['database_file']); ?></li>
            <?php endif; ?>
            <?php if (isset($backupDetails['files_file'])): ?>
                <li><i class="bi bi-folder text-success me-2"></i><?php echo htmlspecialchars($backupDetails['files_file']); ?></li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <h6>Actions</h6>
        <div class="btn-group" role="group">
            <button class="btn btn-success" onclick="restoreBackup('<?php echo $backupDetails['backup_id']; ?>')">
                <i class="bi bi-arrow-clockwise me-2"></i>Restore Backup
            </button>
            <button class="btn btn-danger" onclick="deleteBackup('<?php echo $backupDetails['backup_id']; ?>')">
                <i class="bi bi-trash me-2"></i>Delete Backup
            </button>
        </div>
    </div>
</div>

<?php if (isset($backupDetails['path']) && is_dir($backupDetails['path'])): ?>
<div class="row mt-4">
    <div class="col-12">
        <h6>Backup Contents</h6>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Size</th>
                        <th>Modified</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $backupPath = $backupDetails['path'];
                    $files = glob($backupPath . '*');
                    
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            $filename = basename($file);
                            $filesize = filesize($file);
                            $modified = filemtime($file);
                            
                            echo '<tr>';
                            echo '<td><i class="bi bi-file-earmark me-2"></i>' . htmlspecialchars($filename) . '</td>';
                            echo '<td>' . formatBytes($filesize) . '</td>';
                            echo '<td>' . formatDate(date('Y-m-d H:i:s', $modified)) . '</td>';
                            echo '</tr>';
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function restoreBackup(backupId) {
    if (confirm('Are you sure you want to restore this backup? This will overwrite the current system data.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin/backup-management.php';
        form.innerHTML = `
            <input type="hidden" name="action" value="restore_backup">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="backup_id" value="${backupId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteBackup(backupId) {
    if (confirm('Are you sure you want to delete this backup? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin/backup-management.php';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_backup">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="backup_id" value="${backupId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
