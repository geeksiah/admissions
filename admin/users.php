<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
$userModel = new User($database);

// Check admin access
requireRole(['admin', 'admissions_officer']);

$pageTitle = 'User Management';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard'],
    ['name' => 'Users', 'url' => '/admin/users']
];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_user':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $validator = new Validator($_POST);
                    $validator->required(['username', 'email', 'password', 'role'])
                             ->email('email')
                             ->password('password')
                             ->in('role', ['admin', 'admissions_officer', 'reviewer']);
                    
                    if (!$validator->hasErrors()) {
                        $data = $validator->getValidatedData();
                        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
                        $data['created_by'] = $_SESSION['user_id'];
                        
                        if ($userModel->create($data)) {
                            $message = 'User created successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to create user. Username or email may already exist.';
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'Please correct the errors below.';
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'update_user':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $userId = $_POST['user_id'];
                    $validator = new Validator($_POST);
                    $validator->required(['username', 'email', 'role'])
                             ->email('email')
                             ->in('role', ['admin', 'admissions_officer', 'reviewer']);
                    
                    if (!$validator->hasErrors()) {
                        $data = $validator->getValidatedData();
                        unset($data['password']); // Don't update password here
                        
                        if ($userModel->update($userId, $data)) {
                            $message = 'User updated successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to update user.';
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'Please correct the errors below.';
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'delete_user':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $userId = $_POST['user_id'];
                    
                    if ($userId == $_SESSION['user_id']) {
                        $message = 'You cannot delete your own account.';
                        $messageType = 'danger';
                    } else {
                        if ($userModel->delete($userId)) {
                            $message = 'User deleted successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to delete user.';
                            $messageType = 'danger';
                        }
                    }
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
if (!empty($_GET['role'])) {
    $filters['role'] = $_GET['role'];
}
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}

// Get users with pagination
$usersData = $userModel->getAll($page, $limit, $filters);
$users = $usersData['users'] ?? [];
$totalPages = $usersData['pages'] ?? 1;

// Get user statistics
$stats = $userModel->getStatistics();

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
                        <h4 class="mb-0"><?php echo $stats['total_users']; ?></h4>
                        <p class="mb-0">Total Users</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-people display-4"></i>
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
                        <h4 class="mb-0"><?php echo $stats['active_users']; ?></h4>
                        <p class="mb-0">Active Users</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-person-check display-4"></i>
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
                        <h4 class="mb-0"><?php echo $stats['admin_users']; ?></h4>
                        <p class="mb-0">Admins</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-shield-check display-4"></i>
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
                        <h4 class="mb-0"><?php echo $stats['reviewer_users']; ?></h4>
                        <p class="mb-0">Reviewers</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-person-badge display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
            <i class="bi bi-plus-circle me-2"></i>Add New User
        </button>
    </div>
    <div class="col-md-6">
        <div class="d-flex gap-2 justify-content-end">
            <button class="btn btn-outline-secondary" onclick="exportTableToCSV('usersTable', 'users.csv')">
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
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                       placeholder="Username, email, or name">
            </div>
            <div class="col-md-3">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo ($_GET['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="admissions_officer" <?php echo ($_GET['role'] ?? '') === 'admissions_officer' ? 'selected' : ''; ?>>Admissions Officer</option>
                    <option value="reviewer" <?php echo ($_GET['role'] ?? '') === 'reviewer' ? 'selected' : ''; ?>>Reviewer</option>
                </select>
            </div>
            <div class="col-md-3">
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
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-people me-2"></i>Users
            <span class="badge bg-primary ms-2"><?php echo $usersData['total'] ?? 0; ?></span>
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($users)): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="usersTable">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'admissions_officer' ? 'warning' : 'info'); ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $user['role'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $user['last_login'] ? formatDate($user['last_login']) : 'Never'; ?>
                                </td>
                                <td>
                                    <?php echo formatDate($user['created_at']); ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="editUser(<?php echo $user['id']; ?>)"
                                                title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteUser(<?php echo $user['id']; ?>)"
                                                    title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
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
                <nav aria-label="Users pagination">
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
                <h4 class="text-muted mt-3">No Users Found</h4>
                <p class="text-muted">No users match your current filters.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                    <i class="bi bi-plus-circle me-2"></i>Add First User
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="createUserForm">
                <input type="hidden" name="action" value="create_user">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Password *</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">Role *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="admin">Admin</option>
                                    <option value="admissions_officer">Admissions Officer</option>
                                    <option value="reviewer">Reviewer</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editUser(userId) {
    // Implement edit functionality
    window.location.href = '/admin/edit-user.php?id=' + userId;
}

function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Form validation
document.getElementById('createUserForm').addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('input[required], select[required]');
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
