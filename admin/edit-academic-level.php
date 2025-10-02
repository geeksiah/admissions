<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database
$database = new Database();

// Check admin access
requireRole(['admin', 'admissions_officer']);

$pageTitle = 'Edit Academic Level';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'Academic Levels', 'url' => '/admin/academic-levels.php'],
    ['name' => 'Edit Level', 'url' => '/admin/edit-academic-level.php']
];

// Get level ID from URL
$levelId = $_GET['id'] ?? '';

if (empty($levelId)) {
    header('Location: /admin/academic-levels.php');
    exit;
}

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (validateCSRFToken($_POST['csrf_token'])) {
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
                
                if ($stmt->rowCount() > 0) {
                    $message = 'Academic level updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'No changes were made.';
                    $messageType = 'info';
                }
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
}

// Get level details
$stmt = $database->prepare("
    SELECT * FROM academic_levels WHERE id = ?
");
$stmt->execute([$levelId]);
$level = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$level) {
    header('Location: /admin/academic-levels.php');
    exit;
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

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-pencil me-2"></i>Edit Academic Level
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="editLevelForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="level_name" class="form-label">Level Name *</label>
                                <input type="text" class="form-control" id="level_name" name="level_name" 
                                       value="<?php echo htmlspecialchars($level['level_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="level_code" class="form-label">Level Code *</label>
                                <input type="text" class="form-control" id="level_code" name="level_code" 
                                       value="<?php echo htmlspecialchars($level['level_code']); ?>" required>
                                <div class="form-text">Short code for this level (e.g., UG, PG, PHD)</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description *</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($level['description']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                   <?php echo $level['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">
                                Active
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="/admin/academic-levels.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Back to Levels
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Update Level
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">Level Information</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <td><strong>ID:</strong></td>
                        <td><?php echo $level['id']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Created:</strong></td>
                        <td><?php echo formatDate($level['created_at']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Last Updated:</strong></td>
                        <td><?php echo formatDate($level['updated_at']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td>
                            <span class="badge bg-<?php echo $level['is_active'] ? 'success' : 'secondary'; ?>">
                                <?php echo $level['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="card-title mb-0">Danger Zone</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small">Once you delete an academic level, there is no going back. Please be certain.</p>
                <button class="btn btn-danger btn-sm" onclick="deleteLevel(<?php echo $level['id']; ?>)">
                    <i class="bi bi-trash me-2"></i>Delete Level
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function deleteLevel(levelId) {
    if (confirm('Are you sure you want to delete this academic level? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin/academic-levels.php';
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
document.getElementById('editLevelForm').addEventListener('submit', function(e) {
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
