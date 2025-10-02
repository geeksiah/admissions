<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
require_once '../classes/BackupManager.php';

// Check admin access
requireRole(['admin']);

$pageTitle = 'Backup & Recovery Management';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'Backup Management', 'url' => '/admin/backup-management.php']
];

// Initialize backup manager
$backupManager = new BackupManager($database);

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_backup':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    try {
                        $description = $_POST['description'] ?? '';
                        $result = $backupManager->createFullBackup($description);
                        
                        $message = 'Backup created successfully! Backup ID: ' . $result['backup_id'];
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = 'Failed to create backup: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'restore_backup':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $backupId = $_POST['backup_id'];
                    try {
                        $result = $backupManager->restoreFromBackup($backupId);
                        
                        $message = 'Backup restored successfully! Restored at: ' . $result['restored_at'];
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = 'Failed to restore backup: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'delete_backup':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $backupId = $_POST['backup_id'];
                    try {
                        $backupManager->deleteBackup($backupId);
                        
                        $message = 'Backup deleted successfully!';
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = 'Failed to delete backup: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'schedule_backup':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    try {
                        $frequency = $_POST['frequency'];
                        $time = $_POST['time'];
                        $schedule = $backupManager->scheduleBackup($frequency, $time);
                        
                        $message = 'Backup schedule updated successfully! Next run: ' . $schedule['next_run'];
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = 'Failed to update backup schedule: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
        }
    }
}

// Get list of backups
$backups = $backupManager->listBackups();

// Get backup statistics
$totalBackups = count($backups);
$totalSize = array_sum(array_column($backups, 'size'));
$latestBackup = !empty($backups) ? $backups[0] : null;

include '../includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $totalBackups; ?></h4>
                        <p class="mb-0">Total Backups</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-archive display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo formatBytes($totalSize); ?></h4>
                        <p class="mb-0">Total Size</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-hdd display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $latestBackup ? formatDate($latestBackup['timestamp']) : 'Never'; ?></h4>
                        <p class="mb-0">Last Backup</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-clock display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $latestBackup ? $latestBackup['size_formatted'] : '0 B'; ?></h4>
                        <p class="mb-0">Latest Size</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-file-earmark-zip display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBackupModal">
            <i class="bi bi-plus-circle me-2"></i>Create New Backup
        </button>
        <button class="btn btn-outline-secondary ms-2" data-bs-toggle="modal" data-bs-target="#scheduleBackupModal">
            <i class="bi bi-clock me-2"></i>Schedule Backup
        </button>
    </div>
    <div class="col-md-6">
        <div class="d-flex gap-2 justify-content-end">
            <button class="btn btn-outline-info" onclick="refreshBackups()">
                <i class="bi bi-arrow-clockwise me-2"></i>Refresh
            </button>
        </div>
    </div>
</div>

<!-- Backup Schedule Card -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-clock me-2"></i>Backup Schedule
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Frequency:</strong> Daily at 2:00 AM</p>
                <p><strong>Next Run:</strong> Tomorrow at 2:00 AM</p>
            </div>
            <div class="col-md-6">
                <p><strong>Status:</strong> <span class="badge bg-success">Active</span></p>
                <p><strong>Retention:</strong> 30 days</p>
            </div>
        </div>
    </div>
</div>

<!-- Backups Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-archive me-2"></i>Available Backups
            <span class="badge bg-primary ms-2"><?php echo $totalBackups; ?></span>
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($backups)): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="backupsTable">
                    <thead>
                        <tr>
                            <th>Backup ID</th>
                            <th>Description</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Created</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup): ?>
                            <tr>
                                <td>
                                    <code><?php echo $backup['backup_id']; ?></code>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($backup['description'] ?: 'No description'); ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo ucfirst($backup['type']); ?></span>
                                </td>
                                <td>
                                    <?php echo $backup['size_formatted']; ?>
                                </td>
                                <td>
                                    <?php echo formatDate($backup['timestamp']); ?>
                                </td>
                                <td>
                                    <?php echo $backup['created_by'] ? 'User #' . $backup['created_by'] : 'System'; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-info" 
                                                onclick="viewBackupDetails('<?php echo $backup['backup_id']; ?>')"
                                                title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-success" 
                                                onclick="restoreBackup('<?php echo $backup['backup_id']; ?>')"
                                                title="Restore">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteBackup('<?php echo $backup['backup_id']; ?>')"
                                                title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-archive display-1 text-muted"></i>
                <h4 class="text-muted mt-3">No Backups Found</h4>
                <p class="text-muted">No backups have been created yet.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBackupModal">
                    <i class="bi bi-plus-circle me-2"></i>Create First Backup
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Backup Modal -->
<div class="modal fade" id="createBackupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="createBackupForm">
                <input type="hidden" name="action" value="create_backup">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        This will create a full backup including database and files. The process may take several minutes.
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Enter a description for this backup..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-archive me-2"></i>Create Backup
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Schedule Backup Modal -->
<div class="modal fade" id="scheduleBackupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule Automatic Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="scheduleBackupForm">
                <input type="hidden" name="action" value="schedule_backup">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="frequency" class="form-label">Frequency</label>
                                <select class="form-select" id="frequency" name="frequency" required>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="time" class="form-label">Time</label>
                                <input type="time" class="form-control" id="time" name="time" value="02:00" required>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Note: This requires a cron job or task scheduler to be configured on your server.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-clock me-2"></i>Update Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Backup Details Modal -->
<div class="modal fade" id="backupDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Backup Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="backupDetailsContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewBackupDetails(backupId) {
    // Load backup details via AJAX
    fetch(`/admin/backup-details.php?id=${backupId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('backupDetailsContent').innerHTML = data;
            new bootstrap.Modal(document.getElementById('backupDetailsModal')).show();
        })
        .catch(error => {
            alert('Failed to load backup details: ' + error);
        });
}

function restoreBackup(backupId) {
    if (confirm('Are you sure you want to restore this backup? This will overwrite the current system data.')) {
        const form = document.createElement('form');
        form.method = 'POST';
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
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_backup">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="backup_id" value="${backupId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function refreshBackups() {
    window.location.reload();
}

// Form validation
document.getElementById('createBackupForm').addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Creating Backup...';
});

document.getElementById('scheduleBackupForm').addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('input[required], select[required]');
    let hasErrors = false;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            hasErrors = true;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    if (hasErrors) {
        e.preventDefault();
        alert('Please fill in all required fields.');
    }
});
</script>

<?php include '../includes/footer.php'; ?>
