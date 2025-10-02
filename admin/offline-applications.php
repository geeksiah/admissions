<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
$offlineAppModel = new OfflineApplication($database);
$programModel = new Program($database);
$userModel = new User($database);

// Check admin access
requireRole(['admin', 'admissions_officer']);

$pageTitle = 'Offline Applications';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'Offline Applications', 'url' => '/admin/offline-applications.php']
];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_offline_application':
                // Create new offline application
                $validator = new Validator($_POST);
                $validator->required(['student_first_name', 'student_last_name', 'program_id', 'application_date', 'entry_method'])
                         ->date('application_date');
                
                if (!$validator->hasErrors()) {
                    $data = $validator->getValidatedData();
                    $data['received_by'] = $_SESSION['user_id'];
                    
                    if ($offlineAppModel->create($data)) {
                        $message = 'Offline application created successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to create offline application. Please try again.';
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Please correct the errors below.';
                    $messageType = 'danger';
                }
                break;
                
            case 'update_status':
                // Update application status
                $applicationId = $_POST['application_id'];
                $status = $_POST['status'];
                $notes = $_POST['notes'] ?? '';
                
                if ($offlineAppModel->update($applicationId, ['status' => $status, 'conversion_notes' => $notes])) {
                    $message = 'Application status updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update application status. Please try again.';
                    $messageType = 'danger';
                }
                break;
                
            case 'convert_to_online':
                // Convert to online application
                $applicationId = $_POST['application_id'];
                $studentData = [
                    'first_name' => $_POST['student_first_name'],
                    'last_name' => $_POST['student_last_name'],
                    'email' => $_POST['student_email'],
                    'phone' => $_POST['student_phone'],
                    'date_of_birth' => $_POST['date_of_birth'],
                    'gender' => $_POST['gender'],
                    'nationality' => $_POST['nationality'],
                    'address' => $_POST['address'],
                    'city' => $_POST['city'],
                    'state' => $_POST['state'],
                    'postal_code' => $_POST['postal_code'],
                    'country' => $_POST['country']
                ];
                
                $applicationData = [
                    'application_date' => $_POST['application_date'],
                    'status' => 'submitted',
                    'notes' => 'Converted from offline application'
                ];
                
                $onlineAppId = $offlineAppModel->convertToOnline($applicationId, $studentData, $applicationData, $_SESSION['user_id']);
                if ($onlineAppId) {
                    $message = 'Application converted to online successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to convert application. Please try again.';
                    $messageType = 'danger';
                }
                break;
                
            case 'delete_application':
                // Delete application
                $applicationId = $_POST['application_id'];
                if ($offlineAppModel->delete($applicationId)) {
                    $message = 'Application deleted successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete application. Please try again.';
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
if (!empty($_GET['entry_method'])) {
    $filters['entry_method'] = $_GET['entry_method'];
}
if (!empty($_GET['program_id'])) {
    $filters['program_id'] = $_GET['program_id'];
}
if (!empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}

// Get applications with pagination
$applicationsData = $offlineAppModel->getAll($page, $limit, $filters);
$applications = $applicationsData['applications'] ?? [];
$totalPages = $applicationsData['pages'] ?? 1;

// Get programs for filter
$programs = $programModel->getActive();

// Get entry methods
$entryMethods = $offlineAppModel->getEntryMethods();
$statusOptions = $offlineAppModel->getStatusOptions();

// Get statistics
$statistics = $offlineAppModel->getStatistics();

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
                        <h4 class="mb-0"><?php echo $statistics['total_offline_applications'] ?? 0; ?></h4>
                        <p class="mb-0">Total Applications</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-file-text display-4"></i>
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
                        <h4 class="mb-0"><?php echo $statistics['received_applications'] ?? 0; ?></h4>
                        <p class="mb-0">Received</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-inbox display-4"></i>
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
                        <h4 class="mb-0"><?php echo $statistics['processing_applications'] ?? 0; ?></h4>
                        <p class="mb-0">Processing</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-gear display-4"></i>
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
                        <h4 class="mb-0"><?php echo $statistics['converted_applications'] ?? 0; ?></h4>
                        <p class="mb-0">Converted</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-check-circle display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createOfflineApplicationModal">
            <i class="bi bi-plus-circle me-2"></i>Add Offline Application
        </button>
    </div>
    <div class="col-md-6">
        <div class="d-flex gap-2 justify-content-end">
            <button class="btn btn-outline-secondary" onclick="exportTableToCSV('offlineApplicationsTable', 'offline_applications.csv')">
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
                       placeholder="Name, email, or application number">
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?php echo $value; ?>" 
                                <?php echo ($_GET['status'] ?? '') === $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="entry_method" class="form-label">Entry Method</label>
                <select class="form-select" id="entry_method" name="entry_method">
                    <option value="">All Methods</option>
                    <?php foreach ($entryMethods as $value => $label): ?>
                        <option value="<?php echo $value; ?>" 
                                <?php echo ($_GET['entry_method'] ?? '') === $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
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
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-file-text me-2"></i>Offline Applications
            <span class="badge bg-primary ms-2"><?php echo $applicationsData['total'] ?? 0; ?></span>
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($applications)): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="offlineApplicationsTable">
                    <thead>
                        <tr>
                            <th>Application #</th>
                            <th>Student Name</th>
                            <th>Program</th>
                            <th>Entry Method</th>
                            <th>Status</th>
                            <th>Application Date</th>
                            <th>Received By</th>
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
                                        <?php if ($application['student_email']): ?>
                                            <br><small class="text-muted"><?php echo $application['student_email']; ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo $application['program_name']; ?></strong>
                                        <br><small class="text-muted"><?php echo $application['program_code']; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $entryMethods[$application['entry_method']] ?? $application['entry_method']; ?></span>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = 'secondary';
                                    switch ($application['status']) {
                                        case 'received':
                                            $statusClass = 'warning';
                                            break;
                                        case 'processing':
                                            $statusClass = 'info';
                                            break;
                                        case 'converted':
                                            $statusClass = 'success';
                                            break;
                                        case 'rejected':
                                            $statusClass = 'danger';
                                            break;
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                        <?php echo $statusOptions[$application['status']] ?? ucfirst($application['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo formatDate($application['application_date']); ?>
                                </td>
                                <td>
                                    <?php echo $application['received_by_first_name'] . ' ' . $application['received_by_last_name']; ?>
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
                                                <li><a class="dropdown-item" href="#" onclick="convertToOnline(<?php echo $application['id']; ?>)">
                                                    <i class="bi bi-arrow-right-circle me-2"></i>Convert to Online
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" onclick="viewDocuments(<?php echo $application['id']; ?>)">
                                                    <i class="bi bi-file-earmark me-2"></i>View Documents
                                                </a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="deleteApplication(<?php echo $application['id']; ?>)">
                                                    <i class="bi bi-trash me-2"></i>Delete
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
                <nav aria-label="Offline applications pagination">
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
                <h4 class="text-muted mt-3">No Offline Applications Found</h4>
                <p class="text-muted">No applications match your current filters.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createOfflineApplicationModal">
                    <i class="bi bi-plus-circle me-2"></i>Add First Application
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Offline Application Modal -->
<div class="modal fade" id="createOfflineApplicationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Offline Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="createOfflineApplicationForm">
                <input type="hidden" name="action" value="create_offline_application">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="student_first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="student_first_name" name="student_first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="student_last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="student_last_name" name="student_last_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="student_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="student_email" name="student_email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="student_phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="student_phone" name="student_phone">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="program_id" class="form-label">Program *</label>
                                <select class="form-select" id="program_id" name="program_id" required>
                                    <option value="">Select Program</option>
                                    <?php foreach ($programs as $program): ?>
                                        <option value="<?php echo $program['id']; ?>">
                                            <?php echo $program['program_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="entry_method" class="form-label">Entry Method *</label>
                                <select class="form-select" id="entry_method" name="entry_method" required>
                                    <option value="">Select Method</option>
                                    <?php foreach ($entryMethods as $value => $label): ?>
                                        <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="application_date" class="form-label">Application Date *</label>
                                <input type="date" class="form-control" id="application_date" name="application_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="conversion_notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="conversion_notes" name="conversion_notes" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewApplication(applicationId) {
    window.location.href = '/admin/offline-application-details.php?id=' + applicationId;
}

function editApplication(applicationId) {
    window.location.href = '/admin/edit-offline-application.php?id=' + applicationId;
}

function updateStatus(applicationId, currentStatus) {
    // Implement status update modal
    const newStatus = prompt('Enter new status (received, processing, converted, rejected):', currentStatus);
    if (newStatus && newStatus !== currentStatus) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="application_id" value="${applicationId}">
            <input type="hidden" name="status" value="${newStatus}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function convertToOnline(applicationId) {
    window.location.href = '/admin/convert-offline-application.php?id=' + applicationId;
}

function viewDocuments(applicationId) {
    window.location.href = '/admin/offline-application-documents.php?id=' + applicationId;
}

function deleteApplication(applicationId) {
    if (confirm('Are you sure you want to delete this offline application? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_application">
            <input type="hidden" name="application_id" value="${applicationId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Set default application date
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('application_date').value = today;
});
</script>

<?php include '../includes/footer.php'; ?>
