<?php
/**
 * Notifications Management Panel
 */

// Handle notification actions
if ($_POST && isset($_POST['action'])) {
    $notificationManager = new NotificationManager($database, $systemConfig);
    
    switch ($_POST['action']) {
        case 'send_test_notification':
            $userId = (int)$_POST['user_id'];
            $type = $_POST['notification_type'];
            $channels = $_POST['channels'] ?? ['email'];
            
            $result = $notificationManager->sendNotification($userId, $type, [
                'student_name' => 'Test User',
                'program_name' => 'Test Program',
                'application_id' => 'TEST001',
                'program_code' => 'TP001',
                'amount' => '$150.00',
                'transaction_id' => 'TXN_TEST123',
                'due_date' => date('Y-m-d', strtotime('+7 days')),
                'deadline' => date('Y-m-d', strtotime('+30 days')),
                'days_remaining' => '30',
                'payment_link' => '#',
                'application_link' => '#'
            ], $channels);
            
            if ($result['success']) {
                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>Test notification sent successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                      </div>';
            } else {
                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i>Failed to send notification: ' . htmlspecialchars($result['error']) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                      </div>';
            }
            break;
            
        case 'send_bulk_notification':
            $type = $_POST['notification_type'];
            $userIds = $_POST['user_ids'] ?? [];
            $channels = $_POST['channels'] ?? ['email'];
            $customMessage = $_POST['custom_message'] ?? '';
            
            $successCount = 0;
            $failCount = 0;
            
            foreach ($userIds as $userId) {
                $result = $notificationManager->sendNotification($userId, $type, [
                    'student_name' => 'Student',
                    'program_name' => 'Program',
                    'custom_message' => $customMessage
                ], $channels);
                
                if ($result['success']) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            }
            
            echo '<div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="bi bi-info-circle me-2"></i>Bulk notification completed: ' . $successCount . ' sent, ' . $failCount . ' failed.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>';
            break;
    }
}

// Get all users for dropdown
$userModel = new User($pdo);
$allUsers = $userModel->getAll();

// Get notification statistics
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            DATE(created_at) as date
        FROM notifications 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
        LIMIT 30
    ");
    $stmt->execute();
    $notificationStats = $stmt->fetchAll();
} catch (Exception $e) {
    $notificationStats = [];
}

// Get recent notifications
try {
    $stmt = $pdo->prepare("
        SELECT n.*, u.first_name, u.last_name, u.email 
        FROM notifications n 
        LEFT JOIN users u ON n.user_id = u.id 
        ORDER BY n.created_at DESC 
        LIMIT 20
    ");
    $stmt->execute();
    $recentNotifications = $stmt->fetchAll();
} catch (Exception $e) {
    $recentNotifications = [];
}
?>

<div class="row mb-3">
    <div class="col-12">
        <h5 class="mb-0">
            <i class="bi bi-bell me-2"></i>
            Notifications Management
        </h5>
        <p class="text-muted mt-1 mb-0">Manage email and SMS notifications across the system.</p>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Total Sent</h6>
                        <h3 class="mb-0"><?php echo count($recentNotifications); ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-envelope" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Delivered</h6>
                        <h3 class="mb-0">
                            <?php 
                            $delivered = array_sum(array_column($notificationStats, 'delivered'));
                            echo $delivered;
                            ?>
                        </h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Failed</h6>
                        <h3 class="mb-0">
                            <?php 
                            $failed = array_sum(array_column($notificationStats, 'failed'));
                            echo $failed;
                            ?>
                        </h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-x-circle" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Pending</h6>
                        <h3 class="mb-0">
                            <?php 
                            $pending = array_sum(array_column($notificationStats, 'pending'));
                            echo $pending;
                            ?>
                        </h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-clock" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Send Notifications -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-send me-2"></i>Send Notifications
                </h6>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs" id="notificationTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="test-tab" data-bs-toggle="tab" data-bs-target="#test" type="button" role="tab">
                            Test Notification
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="bulk-tab" data-bs-toggle="tab" data-bs-target="#bulk" type="button" role="tab">
                            Bulk Notification
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content mt-3" id="notificationTabContent">
                    <!-- Test Notification Tab -->
                    <div class="tab-pane fade show active" id="test" role="tabpanel">
                        <form method="POST">
                            <input type="hidden" name="action" value="send_test_notification">
                            
                            <div class="mb-3">
                                <label class="form-label">Select User</label>
                                <select class="form-select" name="user_id" required>
                                    <option value="">Choose a user...</option>
                                    <?php foreach ($allUsers as $user): ?>
                                        <option value="<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Notification Type</label>
                                <select class="form-select" name="notification_type" required>
                                    <option value="">Select type...</option>
                                    <option value="application_submitted">Application Submitted</option>
                                    <option value="application_approved">Application Approved</option>
                                    <option value="application_rejected">Application Rejected</option>
                                    <option value="payment_required">Payment Required</option>
                                    <option value="payment_received">Payment Received</option>
                                    <option value="deadline_reminder">Deadline Reminder</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Channels</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="channels[]" value="email" id="test_email" checked>
                                    <label class="form-check-label" for="test_email">Email</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="channels[]" value="sms" id="test_sms">
                                    <label class="form-check-label" for="test_sms">SMS</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="channels[]" value="push" id="test_push">
                                    <label class="form-check-label" for="test_push">Push Notification</label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-2"></i>Send Test Notification
                            </button>
                        </form>
                    </div>
                    
                    <!-- Bulk Notification Tab -->
                    <div class="tab-pane fade" id="bulk" role="tabpanel">
                        <form method="POST">
                            <input type="hidden" name="action" value="send_bulk_notification">
                            
                            <div class="mb-3">
                                <label class="form-label">Select Users</label>
                                <select class="form-select" name="user_ids[]" multiple size="5">
                                    <?php foreach ($allUsers as $user): ?>
                                        <option value="<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple users</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Notification Type</label>
                                <select class="form-select" name="notification_type" required>
                                    <option value="">Select type...</option>
                                    <option value="application_submitted">Application Submitted</option>
                                    <option value="application_approved">Application Approved</option>
                                    <option value="application_rejected">Application Rejected</option>
                                    <option value="payment_required">Payment Required</option>
                                    <option value="payment_received">Payment Received</option>
                                    <option value="deadline_reminder">Deadline Reminder</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Custom Message (Optional)</label>
                                <textarea class="form-control" name="custom_message" rows="3" 
                                          placeholder="Add any custom message to include in the notification..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Channels</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="channels[]" value="email" id="bulk_email" checked>
                                    <label class="form-check-label" for="bulk_email">Email</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="channels[]" value="sms" id="bulk_sms">
                                    <label class="form-check-label" for="bulk_sms">SMS</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="channels[]" value="push" id="bulk_push">
                                    <label class="form-check-label" for="bulk_push">Push Notification</label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-send me-2"></i>Send Bulk Notification
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Notifications -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-clock-history me-2"></i>Recent Notifications
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($recentNotifications)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-bell-slash text-muted" style="font-size: 2rem;"></i>
                        <p class="text-muted mt-2">No notifications sent yet.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentNotifications as $notification): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold">
                                        <?php echo htmlspecialchars($notification['first_name'] . ' ' . $notification['last_name']); ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                    </small>
                                </div>
                                <span class="badge bg-<?php 
                                    switch($notification['status']) {
                                        case 'delivered': echo 'success'; break;
                                        case 'failed': echo 'danger'; break;
                                        case 'pending': echo 'warning'; break;
                                        default: echo 'secondary';
                                    }
                                ?> rounded-pill">
                                    <?php echo ucfirst($notification['status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh notifications every 30 seconds
setInterval(function() {
    // You could implement AJAX refresh here
}, 30000);
</script>
