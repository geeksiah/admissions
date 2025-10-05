<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
$programModel = new Program($database);

// Check admin access
requireRole(['admin', 'admissions_officer']);

$pageTitle = 'Application Requirements Management';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard'],
    ['name' => 'Application Requirements', 'url' => '/admin/application-requirements']
];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_requirement':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $validator = new Validator($_POST);
                    $validator->required(['requirement_name', 'requirement_type', 'description'])
                             ->length('requirement_name', 2, 100);
                    
                    if (!$validator->hasErrors()) {
                        $data = $validator->getValidatedData();
                        $data['created_by'] = $_SESSION['user_id'];
                        
                        try {
                            $stmt = $database->prepare("
                                INSERT INTO application_requirements 
                                (requirement_name, requirement_type, description, is_mandatory, program_id, created_by, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, NOW())
                            ");
                            $stmt->execute([
                                $data['requirement_name'],
                                $data['requirement_type'],
                                $data['description'],
                                isset($data['is_mandatory']) ? 1 : 0,
                                !empty($data['program_id']) ? $data['program_id'] : null,
                                $data['created_by']
                            ]);
                            
                            $message = 'Application requirement created successfully!';
                            $messageType = 'success';
                        } catch (PDOException $e) {
                            $message = 'Failed to create application requirement. Please try again.';
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'Please correct the errors below.';
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'update_requirement':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $requirementId = $_POST['requirement_id'];
                    $validator = new Validator($_POST);
                    $validator->required(['requirement_name', 'requirement_type', 'description'])
                             ->length('requirement_name', 2, 100);
                    
                    if (!$validator->hasErrors()) {
                        $data = $validator->getValidatedData();
                        
                        try {
                            $stmt = $database->prepare("
                                UPDATE application_requirements 
                                SET requirement_name = ?, requirement_type = ?, description = ?, 
                                    is_mandatory = ?, program_id = ?, updated_at = NOW()
                                WHERE id = ?
                            ");
                            $stmt->execute([
                                $data['requirement_name'],
                                $data['requirement_type'],
                                $data['description'],
                                isset($data['is_mandatory']) ? 1 : 0,
                                !empty($data['program_id']) ? $data['program_id'] : null,
                                $requirementId
                            ]);
                            
                            $message = 'Application requirement updated successfully!';
                            $messageType = 'success';
                        } catch (PDOException $e) {
                            $message = 'Failed to update application requirement. Please try again.';
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'Please correct the errors below.';
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'delete_requirement':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $requirementId = $_POST['requirement_id'];
                    
                    try {
                        $stmt = $database->prepare("DELETE FROM application_requirements WHERE id = ?");
                        $stmt->execute([$requirementId]);
                        
                        $message = 'Application requirement deleted successfully!';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = 'Failed to delete application requirement. Please try again.';
                        $messageType = 'danger';
                    }
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
    $whereConditions[] = "(ar.requirement_name LIKE ? OR ar.description LIKE ?)";
    $searchTerm = '%' . $_GET['search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($_GET['requirement_type'])) {
    $whereConditions[] = "ar.requirement_type = ?";
    $params[] = $_GET['requirement_type'];
}

if (!empty($_GET['program_id'])) {
    $whereConditions[] = "ar.program_id = ?";
    $params[] = $_GET['program_id'];
}

if (!empty($_GET['is_mandatory'])) {
    $whereConditions[] = "ar.is_mandatory = ?";
    $params[] = $_GET['is_mandatory'];
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countStmt = $database->prepare("
    SELECT COUNT(*) 
    FROM application_requirements ar
    LEFT JOIN programs p ON ar.program_id = p.id
    $whereClause
");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Get application requirements with pagination
$stmt = $database->prepare("
    SELECT ar.*, p.program_name, u.first_name, u.last_name 
    FROM application_requirements ar
    LEFT JOIN programs p ON ar.program_id = p.id
    LEFT JOIN users u ON ar.created_by = u.id
    $whereClause
    ORDER BY ar.requirement_name ASC
    LIMIT ? OFFSET ?
");
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get programs for filter
$programs = $programModel->getActive();

// Get requirement types
$requirementTypes = [
    'document' => 'Document',
    'qualification' => 'Qualification',
    'experience' => 'Experience',
    'test_score' => 'Test Score',
    'interview' => 'Interview',
    'portfolio' => 'Portfolio',
    'other' => 'Other'
];

// Get statistics
$statsStmt = $database->prepare("
    SELECT 
        COUNT(*) as total_requirements,
        SUM(CASE WHEN is_mandatory = 1 THEN 1 ELSE 0 END) as mandatory_requirements,
        SUM(CASE WHEN program_id IS NULL THEN 1 ELSE 0 END) as global_requirements
    FROM application_requirements
");
$statsStmt->execute();
$statistics = $statsStmt->fetch(PDO::FETCH_ASSOC);

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
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $statistics['total_requirements']; ?></h4>
                        <p class="mb-0">Total Requirements</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-list-check display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $statistics['mandatory_requirements']; ?></h4>
                        <p class="mb-0">Mandatory</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-exclamation-triangle display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $statistics['global_requirements']; ?></h4>
                        <p class="mb-0">Global</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-globe display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createRequirementModal">
            <i class="bi bi-plus-circle me-2"></i>Add New Requirement
        </button>
    </div>
    <div class="col-md-6">
        <div class="d-flex gap-2 justify-content-end">
            <button class="btn btn-outline-secondary" onclick="exportTableToCSV('requirementsTable', 'application_requirements.csv')">
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
                       placeholder="Requirement name or description">
            </div>
            <div class="col-md-2">
                <label for="requirement_type" class="form-label">Type</label>
                <select class="form-select" id="requirement_type" name="requirement_type">
                    <option value="">All Types</option>
                    <?php foreach ($requirementTypes as $value => $label): ?>
                        <option value="<?php echo $value; ?>" 
                                <?php echo ($_GET['requirement_type'] ?? '') === $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="program_id" class="form-label">Program</label>
                <select class="form-select" id="program_id" name="program_id">
                    <option value="">All Programs</option>
                    <option value="0" <?php echo ($_GET['program_id'] ?? '') === '0' ? 'selected' : ''; ?>>Global Requirements</option>
                    <?php foreach ($programs as $program): ?>
                        <option value="<?php echo $program['id']; ?>" 
                                <?php echo ($_GET['program_id'] ?? '') == $program['id'] ? 'selected' : ''; ?>>
                            <?php echo $program['program_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="is_mandatory" class="form-label">Mandatory</label>
                <select class="form-select" id="is_mandatory" name="is_mandatory">
                    <option value="">All</option>
                    <option value="1" <?php echo ($_GET['is_mandatory'] ?? '') === '1' ? 'selected' : ''; ?>>Yes</option>
                    <option value="0" <?php echo ($_GET['is_mandatory'] ?? '') === '0' ? 'selected' : ''; ?>>No</option>
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

<!-- Application Requirements Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-list-check me-2"></i>Application Requirements
            <span class="badge bg-primary ms-2"><?php echo $totalRecords; ?></span>
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($requirements)): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="requirementsTable">
                    <thead>
                        <tr>
                            <th>Requirement Name</th>
                            <th>Type</th>
                            <th>Program</th>
                            <th>Mandatory</th>
                            <th>Description</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requirements as $requirement): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($requirement['requirement_name']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $requirementTypes[$requirement['requirement_type']] ?? ucfirst($requirement['requirement_type']); ?></span>
                                </td>
                                <td>
                                    <?php if ($requirement['program_name']): ?>
                                        <?php echo htmlspecialchars($requirement['program_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Global Requirement</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $requirement['is_mandatory'] ? 'danger' : 'secondary'; ?>">
                                        <?php echo $requirement['is_mandatory'] ? 'Yes' : 'No'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(substr($requirement['description'], 0, 100)); ?>
                                    <?php if (strlen($requirement['description']) > 100): ?>
                                        <span class="text-muted">...</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($requirement['first_name'] . ' ' . $requirement['last_name']); ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="editRequirement(<?php echo $requirement['id']; ?>)"
                                                title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteRequirement(<?php echo $requirement['id']; ?>)"
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
                <nav aria-label="Application requirements pagination">
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
                <i class="bi bi-list-check display-1 text-muted"></i>
                <h4 class="text-muted mt-3">No Application Requirements Found</h4>
                <p class="text-muted">No application requirements match your current filters.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createRequirementModal">
                    <i class="bi bi-plus-circle me-2"></i>Add First Requirement
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Requirement Modal -->
<div class="modal fade" id="createRequirementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Application Requirement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="createRequirementForm">
                <input type="hidden" name="action" value="create_requirement">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="requirement_name" class="form-label">Requirement Name *</label>
                                <input type="text" class="form-control" id="requirement_name" name="requirement_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="requirement_type" class="form-label">Requirement Type *</label>
                                <select class="form-select" id="requirement_type" name="requirement_type" required>
                                    <option value="">Select Type</option>
                                    <?php foreach ($requirementTypes as $value => $label): ?>
                                        <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="program_id" class="form-label">Program</label>
                        <select class="form-select" id="program_id" name="program_id">
                            <option value="">Global Requirement (All Programs)</option>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?php echo $program['id']; ?>">
                                    <?php echo $program['program_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description *</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_mandatory" name="is_mandatory">
                        <label class="form-check-label" for="is_mandatory">
                            Mandatory Requirement
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Requirement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editRequirement(requirementId) {
    // Implement edit functionality
    window.location.href = '/admin/edit-application-requirement.php?id=' + requirementId;
}

function deleteRequirement(requirementId) {
    if (confirm('Are you sure you want to delete this application requirement? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_requirement">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="requirement_id" value="${requirementId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Form validation
document.getElementById('createRequirementForm').addEventListener('submit', function(e) {
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
</script>

<?php include '../includes/footer.php'; ?>
