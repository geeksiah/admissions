<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
$studentModel = new Student($database);
$applicationModel = new Application($database);
$programModel = new Program($database);

// Check student access
requireRole(['student']);

$pageTitle = 'My Applications';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'My Applications', 'url' => '/student/applications.php']
];

// Get current user's student record
$currentUser = $userModel->getById($_SESSION['user_id']);
$student = $studentModel->getByEmail($currentUser['email']);

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'withdraw_application':
                $applicationId = $_POST['application_id'];
                if ($applicationModel->updateStatus($applicationId, 'withdrawn', $_SESSION['user_id'], 'Application withdrawn by student')) {
                    $message = 'Application withdrawn successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to withdraw application. Please try again.';
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Get student's applications
$applications = [];
if ($student) {
    $applications = $applicationModel->getByStudent($student['id']);
}

include '../includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!$student): ?>
    <div class="alert alert-warning" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Student Profile Required:</strong> You need to complete your student profile before viewing applications. 
        <a href="/student/profile.php" class="alert-link">Complete your profile now</a>.
    </div>
<?php else: ?>
    <div class="row mb-4">
        <div class="col-md-6">
            <h5 class="mb-0">
                <i class="bi bi-file-text me-2"></i>My Applications
                <span class="badge bg-primary ms-2"><?php echo count($applications); ?></span>
            </h5>
        </div>
        <div class="col-md-6">
            <div class="d-flex gap-2 justify-content-end">
                <a href="/student/apply.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>New Application
                </a>
                <button class="btn btn-outline-secondary" onclick="exportTableToCSV('applicationsTable', 'my-applications.csv')">
                    <i class="bi bi-download me-2"></i>Export CSV
                </button>
            </div>
        </div>
    </div>

    <?php if (!empty($applications)): ?>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="applicationsTable">
                        <thead>
                            <tr>
                                <th>Application #</th>
                                <th>Program</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Applied Date</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $application): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $application['application_number']; ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo $application['program_name']; ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo $application['program_code']; ?> - <?php echo ucfirst($application['degree_level']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge status-<?php echo str_replace('_', '-', $application['status']); ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $application['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $application['priority'] === 'high' ? 'danger' : ($application['priority'] === 'medium' ? 'warning' : 'secondary'); ?>">
                                            <?php echo ucfirst($application['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo formatDate($application['application_date']); ?>
                                    </td>
                                    <td>
                                        <?php echo $application['updated_at'] ? formatDate($application['updated_at']) : '-'; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="viewApplication(<?php echo $application['id']; ?>)"
                                                    title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" 
                                                    onclick="viewDocuments(<?php echo $application['id']; ?>)"
                                                    title="View Documents">
                                                <i class="bi bi-file-earmark"></i>
                                            </button>
                                            <?php if (in_array($application['status'], ['submitted', 'under_review'])): ?>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="withdrawApplication(<?php echo $application['id']; ?>)"
                                                        title="Withdraw Application">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Application Status Legend -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-info-circle me-2"></i>Application Status Guide
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <span class="badge status-submitted me-2">Submitted</span>
                        <small class="text-muted">Application received and under review</small>
                    </div>
                    <div class="col-md-3">
                        <span class="badge status-under-review me-2">Under Review</span>
                        <small class="text-muted">Application being evaluated by reviewers</small>
                    </div>
                    <div class="col-md-3">
                        <span class="badge status-approved me-2">Approved</span>
                        <small class="text-muted">Application accepted for admission</small>
                    </div>
                    <div class="col-md-3">
                        <span class="badge status-rejected me-2">Rejected</span>
                        <small class="text-muted">Application not accepted</small>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-3">
                        <span class="badge status-waitlisted me-2">Waitlisted</span>
                        <small class="text-muted">Application on waiting list</small>
                    </div>
                    <div class="col-md-3">
                        <span class="badge bg-secondary me-2">Withdrawn</span>
                        <small class="text-muted">Application withdrawn by student</small>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-file-text display-1 text-muted"></i>
            <h4 class="text-muted mt-3">No Applications Yet</h4>
            <p class="text-muted">You haven't submitted any applications yet. Start by exploring available programs.</p>
            <div class="d-flex gap-2 justify-content-center">
                <a href="/student/programs.php" class="btn btn-outline-primary">
                    <i class="bi bi-mortarboard me-2"></i>Browse Programs
                </a>
                <a href="/student/apply.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Apply Now
                </a>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Withdraw Application Modal -->
<div class="modal fade" id="withdrawModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Withdraw Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to withdraw this application? This action cannot be undone.</p>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> Withdrawing your application will remove it from consideration. 
                    You may need to submit a new application if you change your mind.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="withdraw_application">
                    <input type="hidden" name="application_id" id="withdrawApplicationId">
                    <button type="submit" class="btn btn-danger">Withdraw Application</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function viewApplication(applicationId) {
    window.location.href = '/student/application-details.php?id=' + applicationId;
}

function viewDocuments(applicationId) {
    window.location.href = '/student/application-documents.php?id=' + applicationId;
}

function withdrawApplication(applicationId) {
    document.getElementById('withdrawApplicationId').value = applicationId;
    new bootstrap.Modal(document.getElementById('withdrawModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
