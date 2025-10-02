<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
require_once '../classes/BulkOperationsManager.php';

// Check admin access
requireRole(['admin', 'admissions_officer']);

$pageTitle = 'Bulk Operations';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'Bulk Operations', 'url' => '/admin/bulk-operations.php']
];

// Initialize bulk operations manager
$bulkManager = new BulkOperationsManager($database);

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'bulk_update_status':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $applicationIds = $_POST['application_ids'] ?? [];
                    $newStatus = $_POST['new_status'];
                    
                    if (!empty($applicationIds)) {
                        $result = $bulkManager->bulkUpdateApplicationStatus($applicationIds, $newStatus, $_SESSION['user_id']);
                        
                        if ($result['success']) {
                            $message = $result['message'];
                            $messageType = 'success';
                        } else {
                            $message = 'Error: ' . $result['error'];
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'No applications selected';
                        $messageType = 'warning';
                    }
                }
                break;
                
            case 'bulk_assign_reviewers':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $applicationIds = $_POST['application_ids'] ?? [];
                    $reviewerIds = $_POST['reviewer_ids'] ?? [];
                    
                    if (!empty($applicationIds) && !empty($reviewerIds)) {
                        $result = $bulkManager->bulkAssignReviewers($applicationIds, $reviewerIds, $_SESSION['user_id']);
                        
                        if ($result['success']) {
                            $message = $result['message'];
                            $messageType = 'success';
                        } else {
                            $message = 'Error: ' . $result['error'];
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'Please select applications and reviewers';
                        $messageType = 'warning';
                    }
                }
                break;
                
            case 'bulk_send_notifications':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $applicationIds = $_POST['application_ids'] ?? [];
                    $notificationType = $_POST['notification_type'];
                    $messageText = $_POST['message'];
                    
                    if (!empty($applicationIds) && !empty($messageText)) {
                        $result = $bulkManager->bulkSendNotifications($applicationIds, $notificationType, $messageText, $_SESSION['user_id']);
                        
                        if ($result['success']) {
                            $message = $result['message'];
                            $messageType = 'success';
                        } else {
                            $message = 'Error: ' . $result['error'];
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'Please select applications and enter a message';
                        $messageType = 'warning';
                    }
                }
                break;
                
            case 'bulk_export':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $applicationIds = $_POST['application_ids'] ?? [];
                    $format = $_POST['export_format'];
                    $includeFiles = isset($_POST['include_files']);
                    
                    if (!empty($applicationIds)) {
                        $result = $bulkManager->bulkExportApplications($applicationIds, $format, $includeFiles);
                        
                        if ($result['success']) {
                            // Download the file
                            $mimeType = $format === 'zip' ? 'application/zip' : 'application/octet-stream';
                            header('Content-Type: ' . $mimeType);
                            header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
                            header('Content-Length: ' . filesize($result['filepath']));
                            readfile($result['filepath']);
                            unlink($result['filepath']); // Clean up temp file
                            exit;
                        } else {
                            $message = 'Error: ' . $result['error'];
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'No applications selected';
                        $messageType = 'warning';
                    }
                }
                break;
                
            case 'bulk_delete':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $applicationIds = $_POST['application_ids'] ?? [];
                    
                    if (!empty($applicationIds)) {
                        $result = $bulkManager->bulkDeleteApplications($applicationIds, $_SESSION['user_id']);
                        
                        if ($result['success']) {
                            $message = $result['message'];
                            $messageType = 'success';
                        } else {
                            $message = 'Error: ' . $result['error'];
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'No applications selected';
                        $messageType = 'warning';
                    }
                }
                break;
        }
    }
}

// Get applications for selection
$stmt = $database->prepare("
    SELECT a.id, a.application_id, a.status, a.submitted_at, s.first_name, s.last_name, p.program_name
    FROM applications a
    JOIN students s ON a.student_id = s.id
    JOIN programs p ON a.program_id = p.id
    WHERE a.deleted_at IS NULL
    ORDER BY a.submitted_at DESC
    LIMIT 100
");
$stmt->execute();
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get reviewers
$stmt = $database->prepare("
    SELECT id, first_name, last_name, email
    FROM users
    WHERE role IN ('admin', 'admissions_officer', 'reviewer')
    AND is_active = 1
    ORDER BY first_name, last_name
");
$stmt->execute();
$reviewers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get bulk operation history
$operationHistory = $bulkManager->getBulkOperationHistory(20);

include '../includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <!-- Application Selection -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-list-check me-2"></i>Select Applications
                    <span class="badge bg-primary ms-2" id="selectedCount">0</span>
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <button class="btn btn-sm btn-outline-primary" onclick="selectAll()">Select All</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="selectNone()">Select None</button>
                    <button class="btn btn-sm btn-outline-info" onclick="selectByStatus()">Select by Status</button>
                </div>
                
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm table-hover">
                        <thead class="sticky-top bg-light">
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()">
                                </th>
                                <th>Application ID</th>
                                <th>Student</th>
                                <th>Program</th>
                                <th>Status</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="application-checkbox" value="<?php echo $app['id']; ?>" onchange="updateSelectedCount()">
                                    </td>
                                    <td><code><?php echo htmlspecialchars($app['application_id']); ?></code></td>
                                    <td><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($app['program_name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo getStatusColor($app['status']); ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $app['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($app['submitted_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Bulk Operations -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-gear me-2"></i>Bulk Operations
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                                <i class="bi bi-arrow-repeat me-2"></i>Update Status
                            </button>
                            <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#assignReviewersModal">
                                <i class="bi bi-people me-2"></i>Assign Reviewers
                            </button>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#sendNotificationsModal">
                                <i class="bi bi-send me-2"></i>Send Notifications
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-grid gap-2">
                            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#exportModal">
                                <i class="bi bi-download me-2"></i>Export Data
                            </button>
                            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <i class="bi bi-trash me-2"></i>Delete Applications
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Operation History -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clock-history me-2"></i>Recent Operations
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($operationHistory)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($operationHistory as $operation): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between">
                                    <h6 class="mb-1"><?php echo ucwords(str_replace('_', ' ', $operation['operation_type'])); ?></h6>
                                    <small><?php echo formatDate($operation['created_at']); ?></small>
                                </div>
                                <p class="mb-1"><?php echo $operation['record_count']; ?> records processed</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No recent operations found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Application Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="updateStatusForm">
                <input type="hidden" name="action" value="bulk_update_status">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="application_ids" id="updateStatusApplicationIds">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_status" class="form-label">New Status</label>
                        <select class="form-select" id="new_status" name="new_status" required>
                            <option value="">Select Status</option>
                            <option value="submitted">Submitted</option>
                            <option value="under_review">Under Review</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="waitlisted">Waitlisted</option>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        This will update the status of all selected applications.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Reviewers Modal -->
<div class="modal fade" id="assignReviewersModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Reviewers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="assignReviewersForm">
                <input type="hidden" name="action" value="bulk_assign_reviewers">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="application_ids" id="assignReviewersApplicationIds">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="reviewer_ids" class="form-label">Select Reviewers</label>
                        <select class="form-select" id="reviewer_ids" name="reviewer_ids[]" multiple required>
                            <?php foreach ($reviewers as $reviewer): ?>
                                <option value="<?php echo $reviewer['id']; ?>">
                                    <?php echo htmlspecialchars($reviewer['first_name'] . ' ' . $reviewer['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        This will assign the selected reviewers to all selected applications.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Reviewers</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Send Notifications Modal -->
<div class="modal fade" id="sendNotificationsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Notifications</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="sendNotificationsForm">
                <input type="hidden" name="action" value="bulk_send_notifications">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="application_ids" id="sendNotificationsApplicationIds">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="notification_type" class="form-label">Notification Type</label>
                        <select class="form-select" id="notification_type" name="notification_type" required>
                            <option value="">Select Type</option>
                            <option value="general">General Notification</option>
                            <option value="reminder">Reminder</option>
                            <option value="update">Status Update</option>
                            <option value="deadline">Deadline Reminder</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="message" class="form-label">Message</label>
                        <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        This will send the message to all selected applications via email and SMS.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Notifications</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Export Applications</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="exportForm">
                <input type="hidden" name="action" value="bulk_export">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="application_ids" id="exportApplicationIds">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="export_format" class="form-label">Export Format</label>
                        <select class="form-select" id="export_format" name="export_format" required>
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="zip">ZIP (with files)</option>
                        </select>
                    </div>
                    <div class="mb-3" id="includeFilesDiv" style="display: none;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="include_files" name="include_files">
                            <label class="form-check-label" for="include_files">
                                Include uploaded files in export
                            </label>
                        </div>
                        <div class="form-text">This will create a ZIP file containing the data and all uploaded documents.</div>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        This will export all selected applications to a downloadable file.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Export Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Applications</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="bulk_delete">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="application_ids" id="deleteApplicationIds">
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This will permanently delete all selected applications. This action cannot be undone.
                    </div>
                    <p>Are you sure you want to delete the selected applications?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Applications</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let selectedApplications = [];

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.application-checkbox:checked');
    selectedApplications = Array.from(checkboxes).map(cb => cb.value);
    document.getElementById('selectedCount').textContent = selectedApplications.length;
    
    // Update hidden inputs in modals
    document.getElementById('updateStatusApplicationIds').value = selectedApplications.join(',');
    document.getElementById('assignReviewersApplicationIds').value = selectedApplications.join(',');
    document.getElementById('sendNotificationsApplicationIds').value = selectedApplications.join(',');
    document.getElementById('exportApplicationIds').value = selectedApplications.join(',');
    document.getElementById('deleteApplicationIds').value = selectedApplications.join(',');
}

function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.application-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    
    updateSelectedCount();
}

function selectAll() {
    const checkboxes = document.querySelectorAll('.application-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    document.getElementById('selectAllCheckbox').checked = true;
    updateSelectedCount();
}

function selectNone() {
    const checkboxes = document.querySelectorAll('.application-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    document.getElementById('selectAllCheckbox').checked = false;
    updateSelectedCount();
}

function selectByStatus() {
    const status = prompt('Enter status to select (submitted, under_review, approved, rejected, waitlisted):');
    if (status) {
        const rows = document.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const statusBadge = row.querySelector('.badge');
            if (statusBadge && statusBadge.textContent.toLowerCase().includes(status.toLowerCase())) {
                const checkbox = row.querySelector('.application-checkbox');
                if (checkbox) {
                    checkbox.checked = true;
                }
            }
        });
        updateSelectedCount();
    }
}

// Form validation
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (selectedApplications.length === 0) {
            e.preventDefault();
            alert('Please select at least one application.');
            return;
        }
        
        const requiredFields = this.querySelectorAll('input[required], select[required], textarea[required]');
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
});

// Handle export format change
document.getElementById('export_format').addEventListener('change', function() {
    const includeFilesDiv = document.getElementById('includeFilesDiv');
    if (this.value === 'zip') {
        includeFilesDiv.style.display = 'block';
        document.getElementById('include_files').checked = true;
    } else {
        includeFilesDiv.style.display = 'none';
        document.getElementById('include_files').checked = false;
    }
});

// Initialize
updateSelectedCount();
</script>

<?php include '../includes/footer.php'; ?>
