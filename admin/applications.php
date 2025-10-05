<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
$applicationModel = new Application($database);
$programModel = new Program($database);

// Check admin access
requireRole(['admin', 'admissions_officer']);

$pageTitle = 'Application Management';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard'],
    ['name' => 'Applications', 'url' => '/admin/applications']
];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                // Update application status
                $applicationId = $_POST['application_id'];
                $status = $_POST['status'];
                $decisionNotes = $_POST['decision_notes'] ?? '';
                
                if ($applicationModel->updateStatus($applicationId, $status, $_SESSION['user_id'], $decisionNotes)) {
                    $message = 'Application status updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update application status. Please try again.';
                    $messageType = 'danger';
                }
                break;
                
            case 'assign_reviewer':
                // Assign reviewer to application
                $applicationId = $_POST['application_id'];
                $reviewerId = $_POST['reviewer_id'];
                
                if ($applicationModel->update($applicationId, ['reviewer_id' => $reviewerId])) {
                    $message = 'Reviewer assigned successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to assign reviewer. Please try again.';
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = RECORDS_PER_PAGE;

// Get filter parameters
$filters = [];
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (!empty($_GET['program_id'])) {
    $filters['program_id'] = $_GET['program_id'];
}
if (!empty($_GET['priority'])) {
    $filters['priority'] = $_GET['priority'];
}
if (!empty($_GET['reviewer_id'])) {
    $filters['reviewer_id'] = $_GET['reviewer_id'];
}
if (!empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}

// Get applications with pagination
$applicationsData = $applicationModel->getAll($page, $limit, $filters);
$applications = $applicationsData['applications'] ?? [];
$totalPages = $applicationsData['pages'] ?? 1;

// Get programs for filter
$programs = $programModel->getActive();

// Get reviewers for filter
$userModel = new User($database);
$reviewers = $userModel->getByRole('reviewer');

include '../includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <h5 class="mb-0">
            <i class="bi bi-file-text me-2"></i>Applications
            <span class="badge bg-primary ms-2"><?php echo $applicationsData['total'] ?? 0; ?></span>
        </h5>
    </div>
    <div class="col-md-6">
        <div class="d-flex gap-2 justify-content-end">
            <button class="btn btn-outline-secondary" onclick="exportTableToCSV('applicationsTable', 'applications.csv')">
                <i class="bi bi-download me-2"></i>Export CSV
            </button>
            <button class="btn btn-outline-secondary" onclick="printPage()">
                <i class="bi bi-printer me-2"></i>Print
            </button>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                       placeholder="Student name, email, or application number">
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="submitted" <?php echo ($_GET['status'] ?? '') === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                    <option value="under_review" <?php echo ($_GET['status'] ?? '') === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                    <option value="approved" <?php echo ($_GET['status'] ?? '') === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo ($_GET['status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="waitlisted" <?php echo ($_GET['status'] ?? '') === 'waitlisted' ? 'selected' : ''; ?>>Waitlisted</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="program_id" class="form-label">Program</label>
                <select class="form-select" id="program_id" name="program_id">
                    <option value="">All Programs</option>
                    <?php foreach ($programs as $program): ?>
                        <option value="<?php echo $program['id']; ?>" 
                                <?php echo ($_GET['program_id'] ?? '') == $program['id'] ? 'selected' : ''; ?>>
                            <?php echo $program['program_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="priority" class="form-label">Priority</label>
                <select class="form-select" id="priority" name="priority">
                    <option value="">All Priorities</option>
                    <option value="high" <?php echo ($_GET['priority'] ?? '') === 'high' ? 'selected' : ''; ?>>High</option>
                    <option value="medium" <?php echo ($_GET['priority'] ?? '') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="low" <?php echo ($_GET['priority'] ?? '') === 'low' ? 'selected' : ''; ?>>Low</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="reviewer_id" class="form-label">Reviewer</label>
                <select class="form-select" id="reviewer_id" name="reviewer_id">
                    <option value="">All Reviewers</option>
                    <?php foreach ($reviewers as $reviewer): ?>
                        <option value="<?php echo $reviewer['id']; ?>" 
                                <?php echo ($_GET['reviewer_id'] ?? '') == $reviewer['id'] ? 'selected' : ''; ?>>
                            <?php echo $reviewer['first_name'] . ' ' . $reviewer['last_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
        </form>
        
        <!-- Date Range Filter -->
        <div class="row mt-3">
            <div class="col-md-3">
                <label for="date_from" class="form-label">From Date</label>
                <input type="date" class="form-control" id="date_from" name="date_from" 
                       value="<?php echo $_GET['date_from'] ?? ''; ?>">
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label">To Date</label>
                <input type="date" class="form-control" id="date_to" name="date_to" 
                       value="<?php echo $_GET['date_to'] ?? ''; ?>">
            </div>
        </div>
    </div>
</div>

<!-- Applications Table -->
<div class="card">
    <div class="card-body">
        <?php if (!empty($applications)): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="applicationsTable">
                    <thead>
                        <tr>
                            <th>Application #</th>
                            <th>Student</th>
                            <th>Program</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Reviewer</th>
                            <th>Applied Date</th>
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
                                        <strong><?php echo $application['student_first_name'] . ' ' . $application['student_last_name']; ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo $application['student_email']; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo $application['program_name']; ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo $application['program_code']; ?></small>
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
                                    <?php if ($application['reviewer_first_name']): ?>
                                        <?php echo $application['reviewer_first_name'] . ' ' . $application['reviewer_last_name']; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo formatDate($application['application_date']); ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="viewApplication(<?php echo $application['id']; ?>)"
                                                title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning" 
                                                onclick="editApplication(<?php echo $application['id']; ?>)"
                                                title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                    data-bs-toggle="dropdown" title="More Actions">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $application['id']; ?>, '<?php echo $application['status']; ?>')">
                                                    <i class="bi bi-arrow-repeat me-2"></i>Update Status
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" onclick="assignReviewer(<?php echo $application['id']; ?>)">
                                                    <i class="bi bi-person-plus me-2"></i>Assign Reviewer
                                                </a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item" href="#" onclick="viewDocuments(<?php echo $application['id']; ?>)">
                                                    <i class="bi bi-file-earmark me-2"></i>View Documents
                                                </a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Applications pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($filters); ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($filters); ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-file-text display-1 text-muted"></i>
                <h4 class="text-muted mt-3">No Applications Found</h4>
                <p class="text-muted">No applications match your current filters.</p>
            </div>
        <?php endif; ?>
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
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="application_id" id="statusApplicationId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="submitted">Submitted</option>
                            <option value="under_review">Under Review</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="waitlisted">Waitlisted</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="decision_notes" class="form-label">Decision Notes</label>
                        <textarea class="form-control" id="decision_notes" name="decision_notes" rows="3"></textarea>
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

<!-- Assign Reviewer Modal -->
<div class="modal fade" id="assignReviewerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Reviewer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="assignReviewerForm">
                <input type="hidden" name="action" value="assign_reviewer">
                <input type="hidden" name="application_id" id="reviewerApplicationId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="reviewer_id" class="form-label">Select Reviewer *</label>
                        <select class="form-select" id="reviewer_id" name="reviewer_id" required>
                            <option value="">Select a reviewer</option>
                            <?php foreach ($reviewers as $reviewer): ?>
                                <option value="<?php echo $reviewer['id']; ?>">
                                    <?php echo $reviewer['first_name'] . ' ' . $reviewer['last_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Reviewer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewApplication(applicationId) {
    window.location.href = '/admin/application-details.php?id=' + applicationId;
}

function editApplication(applicationId) {
    window.location.href = '/admin/edit-application.php?id=' + applicationId;
}

function updateStatus(applicationId, currentStatus) {
    document.getElementById('statusApplicationId').value = applicationId;
    document.getElementById('status').value = currentStatus;
    new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
}

function assignReviewer(applicationId) {
    document.getElementById('reviewerApplicationId').value = applicationId;
    new bootstrap.Modal(document.getElementById('assignReviewerModal')).show();
}

function viewDocuments(applicationId) {
    window.location.href = '/admin/application-documents.php?id=' + applicationId;
}
</script>

<?php include '../includes/footer.php'; ?>
