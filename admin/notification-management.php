<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
require_once '../classes/EnhancedNotificationManager.php';

// Check admin access
requireRole(['admin', 'admissions_officer']);

$pageTitle = 'Notification Management';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'Notification Management', 'url' => '/admin/notification-management.php']
];

// Initialize notification manager
$notificationManager = new EnhancedNotificationManager($database);

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'send_test_notification':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    try {
                        $type = $_POST['notification_type'];
                        $applicationId = $_POST['application_id'];
                        
                        switch ($type) {
                            case 'deadline_reminder':
                                $result = $notificationManager->sendApplicationDeadlineReminder($applicationId);
                                break;
                            case 'payment_reminder':
                                $result = $notificationManager->sendPaymentDueReminder($applicationId);
                                break;
                            case 'document_reminder':
                                $result = $notificationManager->sendDocumentSubmissionReminder($applicationId);
                                break;
                            case 'countdown_timer':
                                $result = $notificationManager->sendCountdownNotifications($applicationId);
                                break;
                        }
                        
                        if ($result) {
                            $message = 'Test notification sent successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'No notification was sent (conditions not met).';
                            $messageType = 'info';
                        }
                    } catch (Exception $e) {
                        $message = 'Failed to send test notification: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'process_reminders':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    try {
                        $processed = $notificationManager->processPendingReminders();
                        $message = "Processed $processed pending reminders successfully!";
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = 'Failed to process reminders: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
        }
    }
}

// Get notification statistics
$statistics = $notificationManager->getNotificationStatistics(30);

// Get recent applications for testing
$stmt = $database->prepare("
    SELECT a.id, a.application_id, p.program_name, s.first_name, s.last_name, s.email
    FROM applications a
    JOIN programs p ON a.program_id = p.id
    JOIN students s ON a.student_id = s.id
    WHERE a.status = 'submitted'
    ORDER BY a.submitted_at DESC
    LIMIT 20
");
$stmt->execute();
$recentApplications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get notification log
$stmt = $database->prepare("
    SELECT nl.*, a.application_id, p.program_name, s.first_name, s.last_name
    FROM notification_log nl
    LEFT JOIN applications a ON nl.application_id = a.id
    LEFT JOIN programs p ON a.program_id = p.id
    LEFT JOIN students s ON a.student_id = s.id
    ORDER BY nl.created_at DESC
    LIMIT 50
");
$stmt->execute();
$notificationLog = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                        <h4 class="mb-0"><?php echo count($notificationLog); ?></h4>
                        <p class="mb-0">Recent Notifications</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-bell display-4"></i>
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
                        <h4 class="mb-0"><?php echo count($recentApplications); ?></h4>
                        <p class="mb-0">Active Applications</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-file-earmark-text display-4"></i>
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
                        <h4 class="mb-0"><?php echo count($statistics); ?></h4>
                        <p class="mb-0">Notification Types</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-graph-up display-4"></i>
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
                        <h4 class="mb-0">Auto</h4>
                        <p class="mb-0">Processing</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-gear display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#testNotificationModal">
            <i class="bi bi-send me-2"></i>Send Test Notification
        </button>
        <button class="btn btn-success ms-2" onclick="processReminders()">
            <i class="bi bi-arrow-clockwise me-2"></i>Process Reminders
        </button>
    </div>
    <div class="col-md-6">
        <div class="d-flex gap-2 justify-content-end">
            <button class="btn btn-outline-info" onclick="refreshNotifications()">
                <i class="bi bi-arrow-clockwise me-2"></i>Refresh
            </button>
        </div>
    </div>
</div>

<!-- Notification Statistics -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-graph-up me-2"></i>Notification Statistics (Last 30 Days)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($statistics)): ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Notification Type</th>
                            <th>Date</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($statistics as $stat): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-info"><?php echo ucwords(str_replace('_', ' ', $stat['notification_type'])); ?></span>
                                </td>
                                <td><?php echo formatDate($stat['date']); ?></td>
                                <td><?php echo $stat['count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">No notification statistics available.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Notification Log -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-list-ul me-2"></i>Recent Notification Log
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($notificationLog)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Application</th>
                            <th>Student</th>
                            <th>Data</th>
                            <th>Sent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notificationLog as $log): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-info"><?php echo ucwords(str_replace('_', ' ', $log['notification_type'])); ?></span>
                                </td>
                                <td>
                                    <?php if ($log['application_id']): ?>
                                        <code><?php echo htmlspecialchars($log['application_id']); ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log['first_name']): ?>
                                        <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $data = json_decode($log['data'], true);
                                    if ($data) {
                                        echo '<small class="text-muted">';
                                        foreach ($data as $key => $value) {
                                            echo $key . ': ' . $value . '<br>';
                                        }
                                        echo '</small>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php echo formatDate($log['created_at']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-bell display-1 text-muted"></i>
                <h4 class="text-muted mt-3">No Notifications Found</h4>
                <p class="text-muted">No notifications have been sent yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Test Notification Modal -->
<div class="modal fade" id="testNotificationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Test Notification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="testNotificationForm">
                <input type="hidden" name="action" value="send_test_notification">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="notification_type" class="form-label">Notification Type</label>
                        <select class="form-select" id="notification_type" name="notification_type" required>
                            <option value="">Select Type</option>
                            <option value="deadline_reminder">Application Deadline Reminder</option>
                            <option value="payment_reminder">Payment Due Reminder</option>
                            <option value="document_reminder">Document Submission Reminder</option>
                            <option value="countdown_timer">Countdown Timer</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="application_id" class="form-label">Application</label>
                        <select class="form-select" id="application_id" name="application_id" required>
                            <option value="">Select Application</option>
                            <?php foreach ($recentApplications as $app): ?>
                                <option value="<?php echo $app['id']; ?>">
                                    <?php echo htmlspecialchars($app['application_id'] . ' - ' . $app['first_name'] . ' ' . $app['last_name'] . ' (' . $app['program_name'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        This will send a test notification to the selected application. The notification will only be sent if the conditions are met.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-2"></i>Send Test
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function processReminders() {
    if (confirm('Are you sure you want to process all pending reminders? This may send multiple notifications.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="process_reminders">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function refreshNotifications() {
    window.location.reload();
}

// Form validation
document.getElementById('testNotificationForm').addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('select[required]');
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
