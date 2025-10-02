<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
require_once '../classes/EmailVerificationManager.php';

// Check admin access
requireRole(['admin', 'admissions_officer']);

$pageTitle = 'Email Verification Management';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'Email Verification', 'url' => '/admin/email-verification.php']
];

// Initialize email verification manager
$verificationManager = new EmailVerificationManager($database);

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'send_verification':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $studentId = $_POST['student_id'];
                    
                    $result = $verificationManager->sendVerificationEmail($studentId);
                    
                    if ($result['success']) {
                        $message = $result['message'];
                        $messageType = 'success';
                    } else {
                        $message = 'Error: ' . $result['error'];
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'resend_verification':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $studentId = $_POST['student_id'];
                    
                    $result = $verificationManager->resendVerificationEmail($studentId);
                    
                    if ($result['success']) {
                        $message = $result['message'];
                        $messageType = 'success';
                    } else {
                        $message = 'Error: ' . $result['error'];
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'bulk_send_verification':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $studentIds = $_POST['student_ids'] ?? [];
                    
                    if (!empty($studentIds)) {
                        $result = $verificationManager->bulkSendVerificationEmails($studentIds);
                        
                        if ($result['success']) {
                            $message = "Sent verification emails to {$result['success_count']} students. {$result['error_count']} failed.";
                            $messageType = $result['error_count'] > 0 ? 'warning' : 'success';
                        } else {
                            $message = 'Error: Failed to send verification emails';
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'No students selected';
                        $messageType = 'warning';
                    }
                }
                break;
                
            case 'cleanup_expired':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $cleaned = $verificationManager->cleanupExpiredTokens();
                    $message = "Cleaned up $cleaned expired verification tokens";
                    $messageType = 'success';
                }
                break;
        }
    }
}

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = RECORDS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Get filter parameters
$filters = [];
$whereConditions = [];
$params = [];

if (!empty($_GET['search'])) {
    $whereConditions[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ?)";
    $searchTerm = '%' . $_GET['search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($_GET['verification_status'])) {
    switch ($_GET['verification_status']) {
        case 'verified':
            $whereConditions[] = "s.email_verified_at IS NOT NULL";
            break;
        case 'pending':
            $whereConditions[] = "s.email_verified_at IS NULL AND ev.verification_token IS NOT NULL AND ev.expires_at > NOW()";
            break;
        case 'expired':
            $whereConditions[] = "s.email_verified_at IS NULL AND ev.verification_token IS NOT NULL AND ev.expires_at <= NOW()";
            break;
        case 'unverified':
            $whereConditions[] = "s.email_verified_at IS NULL AND ev.verification_token IS NULL";
            break;
    }
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countStmt = $database->prepare("
    SELECT COUNT(DISTINCT s.id)
    FROM students s
    LEFT JOIN email_verifications ev ON s.id = ev.student_id
    $whereClause
");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Get students with verification status
$stmt = $database->prepare("
    SELECT 
        s.id, s.first_name, s.last_name, s.email, s.email_verified_at,
        ev.verification_token, ev.expires_at, ev.created_at as last_verification_sent
    FROM students s
    LEFT JOIN email_verifications ev ON s.id = ev.student_id
    $whereClause
    ORDER BY s.created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$statistics = $verificationManager->getVerificationStatistics(30);

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
                        <h4 class="mb-0"><?php echo $statistics['total_verifications']; ?></h4>
                        <p class="mb-0">Total Verifications</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-envelope display-4"></i>
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
                        <h4 class="mb-0"><?php echo $statistics['verified_count']; ?></h4>
                        <p class="mb-0">Verified</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-check-circle display-4"></i>
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
                        <h4 class="mb-0"><?php echo $statistics['pending_count']; ?></h4>
                        <p class="mb-0">Pending</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-clock display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $statistics['expired_count']; ?></h4>
                        <p class="mb-0">Expired</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-x-circle display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sendVerificationModal">
            <i class="bi bi-envelope me-2"></i>Send Verification
        </button>
        <button class="btn btn-warning ms-2" onclick="cleanupExpired()">
            <i class="bi bi-trash me-2"></i>Cleanup Expired
        </button>
    </div>
    <div class="col-md-6">
        <div class="d-flex gap-2 justify-content-end">
            <button class="btn btn-outline-secondary" onclick="exportTableToCSV('studentsTable', 'email_verifications.csv')">
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
            <div class="col-md-6">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                       placeholder="Student name or email">
            </div>
            <div class="col-md-4">
                <label for="verification_status" class="form-label">Verification Status</label>
                <select class="form-select" id="verification_status" name="verification_status">
                    <option value="">All Status</option>
                    <option value="verified" <?php echo ($_GET['verification_status'] ?? '') === 'verified' ? 'selected' : ''; ?>>Verified</option>
                    <option value="pending" <?php echo ($_GET['verification_status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="expired" <?php echo ($_GET['verification_status'] ?? '') === 'expired' ? 'selected' : ''; ?>>Expired</option>
                    <option value="unverified" <?php echo ($_GET['verification_status'] ?? '') === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Students Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-people me-2"></i>Email Verification Status
            <span class="badge bg-primary ms-2"><?php echo $totalRecords; ?></span>
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($students)): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="studentsTable">
                    <thead>
                        <tr>
                            <th width="50">
                                <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()">
                            </th>
                            <th>Student</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Last Verification Sent</th>
                            <th>Expires At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="student-checkbox" value="<?php echo $student['id']; ?>" onchange="updateSelectedCount()">
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($student['email']); ?>
                                </td>
                                <td>
                                    <?php if ($student['email_verified_at']): ?>
                                        <span class="badge bg-success">Verified</span>
                                        <br><small class="text-muted"><?php echo formatDate($student['email_verified_at']); ?></small>
                                    <?php elseif ($student['verification_token'] && $student['expires_at'] > date('Y-m-d H:i:s')): ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php elseif ($student['verification_token'] && $student['expires_at'] <= date('Y-m-d H:i:s')): ?>
                                        <span class="badge bg-danger">Expired</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Unverified</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $student['last_verification_sent'] ? formatDate($student['last_verification_sent']) : 'Never'; ?>
                                </td>
                                <td>
                                    <?php echo $student['expires_at'] ? formatDate($student['expires_at']) : 'N/A'; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <?php if (!$student['email_verified_at']): ?>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="sendVerification(<?php echo $student['id']; ?>)"
                                                    title="Send Verification">
                                                <i class="bi bi-envelope"></i>
                                            </button>
                                            <?php if ($student['verification_token']): ?>
                                                <button class="btn btn-sm btn-outline-warning" 
                                                        onclick="resendVerification(<?php echo $student['id']; ?>)"
                                                        title="Resend Verification">
                                                    <i class="bi bi-arrow-clockwise"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Students pagination">
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
                <i class="bi bi-people display-1 text-muted"></i>
                <h4 class="text-muted mt-3">No Students Found</h4>
                <p class="text-muted">No students match your current filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Send Verification Modal -->
<div class="modal fade" id="sendVerificationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Verification Email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="sendVerificationForm">
                <input type="hidden" name="action" value="send_verification">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="student_id" id="sendVerificationStudentId">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        This will send a verification email to the selected student.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-envelope me-2"></i>Send Verification
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let selectedStudents = [];

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.student-checkbox:checked');
    selectedStudents = Array.from(checkboxes).map(cb => cb.value);
}

function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.student-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    
    updateSelectedCount();
}

function sendVerification(studentId) {
    document.getElementById('sendVerificationStudentId').value = studentId;
    new bootstrap.Modal(document.getElementById('sendVerificationModal')).show();
}

function resendVerification(studentId) {
    if (confirm('Are you sure you want to resend the verification email?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="resend_verification">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="student_id" value="${studentId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function cleanupExpired() {
    if (confirm('Are you sure you want to cleanup expired verification tokens?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="cleanup_expired">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Initialize
updateSelectedCount();
</script>

<?php include '../includes/footer.php'; ?>
