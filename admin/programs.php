<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
$programModel = new Program($database);

// Check admin access
requireRole(['admin', 'admissions_officer']);

$pageTitle = 'Program Management';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'Programs', 'url' => '/admin/programs.php']
];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_program':
                // Create new program
                $validator = new Validator($_POST);
                $validator->required(['program_code', 'program_name', 'department', 'degree_level', 'duration_months'])
                         ->numeric('duration_months')
                         ->numeric('tuition_fee')
                         ->numeric('application_fee')
                         ->numeric('max_capacity');
                
                if (!$validator->hasErrors()) {
                    $data = $validator->getValidatedData();
                    
                    // Check if program code already exists
                    if (!$programModel->codeExists($data['program_code'])) {
                        if ($programModel->create($data)) {
                            $message = 'Program created successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to create program. Please try again.';
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'Program code already exists.';
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Please correct the errors below.';
                    $messageType = 'danger';
                }
                break;
                
            case 'update_program':
                // Update program
                $programId = $_POST['program_id'];
                $validator = new Validator($_POST);
                $validator->required(['program_code', 'program_name', 'department', 'degree_level', 'duration_months'])
                         ->numeric('duration_months')
                         ->numeric('tuition_fee')
                         ->numeric('application_fee')
                         ->numeric('max_capacity');
                
                if (!$validator->hasErrors()) {
                    $data = $validator->getValidatedData();
                    
                    // Check if program code exists for other programs
                    if (!$programModel->codeExists($data['program_code'], $programId)) {
                        if ($programModel->update($programId, $data)) {
                            $message = 'Program updated successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to update program. Please try again.';
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'Program code already exists for another program.';
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Please correct the errors below.';
                    $messageType = 'danger';
                }
                break;
                
            case 'delete_program':
                // Delete program
                $programId = $_POST['program_id'];
                if ($programModel->delete($programId)) {
                    $message = 'Program deleted successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete program. Please try again.';
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
if (!empty($_GET['department'])) {
    $filters['department'] = $_GET['department'];
}
if (!empty($_GET['degree_level'])) {
    $filters['degree_level'] = $_GET['degree_level'];
}
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}

// Get programs with pagination
$programsData = $programModel->getAll($page, $limit, $filters);
$programs = $programsData['programs'] ?? [];
$totalPages = $programsData['pages'] ?? 1;

// Get unique departments for filter
$departments = $programModel->getDepartments();
$degreeLevels = $programModel->getDegreeLevels();

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
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProgramModal">
            <i class="bi bi-plus-circle me-2"></i>Add New Program
        </button>
    </div>
    <div class="col-md-6">
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" onclick="exportTableToCSV('programsTable', 'programs.csv')">
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
                       placeholder="Program name or code">
            </div>
            <div class="col-md-3">
                <label for="department" class="form-label">Department</label>
                <select class="form-select" id="department" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?php echo $department; ?>" 
                                <?php echo ($_GET['department'] ?? '') === $department ? 'selected' : ''; ?>>
                            <?php echo $department; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="degree_level" class="form-label">Degree Level</label>
                <select class="form-select" id="degree_level" name="degree_level">
                    <option value="">All Levels</option>
                    <?php foreach ($degreeLevels as $level): ?>
                        <option value="<?php echo $level; ?>" 
                                <?php echo ($_GET['degree_level'] ?? '') === $level ? 'selected' : ''; ?>>
                            <?php echo ucfirst($level); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo ($_GET['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($_GET['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-2"></i>Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Programs Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-mortarboard me-2"></i>Programs
            <span class="badge bg-primary ms-2"><?php echo $programsData['total'] ?? 0; ?></span>
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($programs)): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="programsTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Program Name</th>
                            <th>Department</th>
                            <th>Degree Level</th>
                            <th>Duration</th>
                            <th>Tuition Fee</th>
                            <th>Capacity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($programs as $program): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $program['program_code']; ?></strong>
                                </td>
                                <td>
                                    <?php echo $program['program_name']; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $program['department']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo ucfirst($program['degree_level']); ?></span>
                                </td>
                                <td>
                                    <?php echo $program['duration_months']; ?> months
                                </td>
                                <td>
                                    <?php echo $program['tuition_fee'] ? formatCurrency($program['tuition_fee']) : 'N/A'; ?>
                                </td>
                                <td>
                                    <?php echo $program['current_enrolled'] . '/' . ($program['max_capacity'] ?? '∞'); ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $program['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($program['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="viewProgram(<?php echo $program['id']; ?>)"
                                                title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning" 
                                                onclick="editProgram(<?php echo $program['id']; ?>)"
                                                title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteProgram(<?php echo $program['id']; ?>)"
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
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Programs pagination">
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
                <i class="bi bi-mortarboard display-1 text-muted"></i>
                <h4 class="text-muted mt-3">No Programs Found</h4>
                <p class="text-muted">No programs match your current filters.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProgramModal">
                    <i class="bi bi-plus-circle me-2"></i>Add First Program
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Program Modal -->
<div class="modal fade" id="createProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Program</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="createProgramForm">
                <input type="hidden" name="action" value="create_program">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="program_code" class="form-label">Program Code *</label>
                                <input type="text" class="form-control" id="program_code" name="program_code" required>
                                <div class="form-text">Unique identifier for the program</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="program_name" class="form-label">Program Name *</label>
                                <input type="text" class="form-control" id="program_name" name="program_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="department" class="form-label">Department *</label>
                                <input type="text" class="form-control" id="department" name="department" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="degree_level" class="form-label">Degree Level *</label>
                                <select class="form-select" id="degree_level" name="degree_level" required>
                                    <option value="">Select Degree Level</option>
                                    <?php foreach ($degreeLevels as $level): ?>
                                        <option value="<?php echo $level; ?>"><?php echo ucfirst($level); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="duration_months" class="form-label">Duration (Months) *</label>
                                <input type="number" class="form-control" id="duration_months" name="duration_months" required min="1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="max_capacity" class="form-label">Max Capacity</label>
                                <input type="number" class="form-control" id="max_capacity" name="max_capacity" min="1">
                                <div class="form-text">Leave empty for unlimited capacity</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="tuition_fee" class="form-label">Tuition Fee</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="tuition_fee" name="tuition_fee" step="0.01" min="0">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="application_fee" class="form-label">Application Fee</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="application_fee" name="application_fee" step="0.01" min="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="application_deadline" class="form-label">Application Deadline</label>
                                <input type="date" class="form-control" id="application_deadline" name="application_deadline">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="requirements" class="form-label">Requirements</label>
                        <textarea class="form-control" id="requirements" name="requirements" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Program</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewProgram(programId) {
    // Implement view program functionality
    window.location.href = '/admin/program-details.php?id=' + programId;
}

function editProgram(programId) {
    // Implement edit program functionality
    window.location.href = '/admin/edit-program.php?id=' + programId;
}

function deleteProgram(programId) {
    if (confirm('Are you sure you want to delete this program? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_program">
            <input type="hidden" name="program_id" value="${programId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>
