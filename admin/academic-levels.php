<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();

// Check admin access
requireRole(['admin', 'admissions_officer']);

$pageTitle = 'Academic Levels Management';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'Academic Levels', 'url' => '/admin/academic-levels.php']
];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_level':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $validator = new Validator($_POST);
                    $validator->required(['level_name', 'level_code', 'description'])
                             ->length('level_name', 2, 100)
                             ->length('level_code', 2, 20);
                    
                    if (!$validator->hasErrors()) {
                        $data = $validator->getValidatedData();
                        $data['created_by'] = $_SESSION['user_id'];
                        
                        try {
                            $stmt = $database->prepare("
                                INSERT INTO academic_levels (level_name, level_code, description, is_active, created_by, created_at) 
                                VALUES (?, ?, ?, ?, ?, NOW())
                            ");
                            $stmt->execute([
                                $data['level_name'],
                                $data['level_code'],
                                $data['description'],
                                isset($data['is_active']) ? 1 : 0,
                                $data['created_by']
                            ]);
                            
                            $message = 'Academic level created successfully!';
                            $messageType = 'success';
                        } catch (PDOException $e) {
                            if ($e->getCode() == 23000) {
                                $message = 'A level with this code already exists.';
                            } else {
                                $message = 'Failed to create academic level. Please try again.';
                            }
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'Please correct the errors below.';
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'update_level':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $levelId = $_POST['level_id'];
                    $validator = new Validator($_POST);
                    $validator->required(['level_name', 'level_code', 'description'])
                             ->length('level_name', 2, 100)
                             ->length('level_code', 2, 20);
                    
                    if (!$validator->hasErrors()) {
                        $data = $validator->getValidatedData();
                        
                        try {
                            $stmt = $database->prepare("
                                UPDATE academic_levels 
                                SET level_name = ?, level_code = ?, description = ?, is_active = ?, updated_at = NOW()
                                WHERE id = ?
                            ");
                            $stmt->execute([
                                $data['level_name'],
                                $data['level_code'],
                                $data['description'],
                                isset($data['is_active']) ? 1 : 0,
                                $levelId
                            ]);
                            
                            $message = 'Academic level updated successfully!';
                            $messageType = 'success';
                        } catch (PDOException $e) {
                            if ($e->getCode() == 23000) {
                                $message = 'A level with this code already exists.';
                            } else {
                                $message = 'Failed to update academic level. Please try again.';
                            }
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'Please correct the errors below.';
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'delete_level':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $levelId = $_POST['level_id'];
                    
                    try {
                        // Check if level is used by any programs
                        $stmt = $database->prepare("SELECT COUNT(*) FROM programs WHERE level_id = ?");
                        $stmt->execute([$levelId]);
                        $programCount = $stmt->fetchColumn();
                        
                        if ($programCount > 0) {
                            $message = 'Cannot delete this level as it is used by ' . $programCount . ' program(s).';
                            $messageType = 'danger';
                        } else {
                            $stmt = $database->prepare("DELETE FROM academic_levels WHERE id = ?");
                            $stmt->execute([$levelId]);
                            
                            $message = 'Academic level deleted successfully!';
                            $messageType = 'success';
                        }
                    } catch (PDOException $e) {
                        $message = 'Failed to delete academic level. Please try again.';
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
    $whereConditions[] = "(level_name LIKE ? OR level_code LIKE ? OR description LIKE ?)";
    $searchTerm = '%' . $_GET['search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($_GET['is_active'])) {
    $whereConditions[] = "is_active = ?";
    $params[] = $_GET['is_active'];
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countStmt = $database->prepare("SELECT COUNT(*) FROM academic_levels $whereClause");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Get academic levels with pagination
$stmt = $database->prepare("
    SELECT al.*, u.first_name, u.last_name 
    FROM academic_levels al
    LEFT JOIN users u ON al.created_by = u.id
    $whereClause
    ORDER BY al.level_name ASC
    LIMIT ? OFFSET ?
");
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$statsStmt = $database->prepare("
    SELECT 
        COUNT(*) as total_levels,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_levels,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_levels
    FROM academic_levels
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
                        <h4 class="mb-0"><?php echo $statistics['total_levels']; ?></h4>
                        <p class="mb-0">Total Levels</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-layers display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $statistics['active_levels']; ?></h4>
                        <p class="mb-0">Active Levels</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-check-circle display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-secondary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $statistics['inactive_levels']; ?></h4>
                        <p class="mb-0">Inactive Levels</p>
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
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createLevelModal">
            <i class="bi bi-plus-circle me-2"></i>Add New Academic Level
        </button>
    </div>
    <div class="col-md-6">
        <div class="d-flex gap-2 justify-content-end">
            <button class="btn btn-outline-secondary" onclick="exportTableToCSV('levelsTable', 'academic_levels.csv')">
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
                       placeholder="Level name, code, or description">
            </div>
            <div class="col-md-4">
                <label for="is_active" class="form-label">Status</label>
                <select class="form-select" id="is_active" name="is_active">
                    <option value="">All Status</option>
                    <option value="1" <?php echo ($_GET['is_active'] ?? '') === '1' ? 'selected' : ''; ?>>Active</option>
                    <option value="0" <?php echo ($_GET['is_active'] ?? '') === '0' ? 'selected' : ''; ?>>Inactive</option>
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

<!-- Academic Levels Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-layers me-2"></i>Academic Levels
            <span class="badge bg-primary ms-2"><?php echo $totalRecords; ?></span>
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($levels)): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="levelsTable">
                    <thead>
                        <tr>
                            <th>Level Name</th>
                            <th>Code</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($levels as $level): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($level['level_name']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($level['level_code']); ?></span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($level['description']); ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $level['is_active'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $level['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($level['first_name'] . ' ' . $level['last_name']); ?>
                                </td>
                                <td>
                                    <?php echo formatDate($level['created_at']); ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="editLevel(<?php echo $level['id']; ?>)"
                                                title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteLevel(<?php echo $level['id']; ?>)"
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
                <nav aria-label="Academic levels pagination">
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
                <i class="bi bi-layers display-1 text-muted"></i>
                <h4 class="text-muted mt-3">No Academic Levels Found</h4>
                <p class="text-muted">No academic levels match your current filters.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createLevelModal">
                    <i class="bi bi-plus-circle me-2"></i>Add First Academic Level
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Level Modal -->
<div class="modal fade" id="createLevelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Academic Level</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="createLevelForm">
                <input type="hidden" name="action" value="create_level">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="level_name" class="form-label">Level Name *</label>
                        <input type="text" class="form-control" id="level_name" name="level_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="level_code" class="form-label">Level Code *</label>
                        <input type="text" class="form-control" id="level_code" name="level_code" required>
                        <div class="form-text">Short code for this level (e.g., UG, PG, PHD)</div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description *</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                        <label class="form-check-label" for="is_active">
                            Active
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Level</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editLevel(levelId) {
    window.location.href = '/admin/edit-academic-level.php?id=' + levelId;
}

function deleteLevel(levelId) {
    if (confirm('Are you sure you want to delete this academic level? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_level">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="level_id" value="${levelId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Form validation
document.getElementById('createLevelForm').addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('input[required], textarea[required]');
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
