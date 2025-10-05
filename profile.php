<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database and models
$database = new Database();

// Check if user is logged in
requireLogin();

$pageTitle = 'Profile';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard'],
    ['name' => 'Profile', 'url' => '/profile']
];

// Get current user data
$userModel = new User($database);
$currentUser = $userModel->getById($_SESSION['user_id']);

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $validator = new Validator($_POST);
                    $validator->required(['first_name', 'last_name', 'email'])
                             ->email('email')
                             ->length('first_name', 2, 50)
                             ->length('last_name', 2, 50);
                    
                    if (!$validator->hasErrors()) {
                        $data = $validator->getValidatedData();
                        
                        // Check if email is already taken by another user
                        if ($userModel->emailExists($data['email'], $_SESSION['user_id'])) {
                            $message = 'Email address is already in use by another user.';
                            $messageType = 'danger';
                        } else {
                            if ($userModel->update($_SESSION['user_id'], $data)) {
                                // Update session data
                                $_SESSION['first_name'] = $data['first_name'];
                                $_SESSION['last_name'] = $data['last_name'];
                                $_SESSION['email'] = $data['email'];
                                
                                $message = 'Profile updated successfully!';
                                $messageType = 'success';
                                
                                // Refresh user data
                                $currentUser = $userModel->getById($_SESSION['user_id']);
                            } else {
                                $message = 'Failed to update profile. Please try again.';
                                $messageType = 'danger';
                            }
                        }
                    } else {
                        $message = 'Please correct the errors below.';
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'change_password':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $validator = new Validator($_POST);
                    $validator->required(['current_password', 'new_password', 'confirm_password'])
                             ->password('new_password')
                             ->confirmPassword('new_password', 'confirm_password');
                    
                    if (!$validator->hasErrors()) {
                        $data = $validator->getValidatedData();
                        
                        // Verify current password
                        if (password_verify($data['current_password'], $currentUser['password'])) {
                            if ($userModel->changePassword($_SESSION['user_id'], password_hash($data['new_password'], PASSWORD_DEFAULT))) {
                                $message = 'Password changed successfully!';
                                $messageType = 'success';
                            } else {
                                $message = 'Failed to change password. Please try again.';
                                $messageType = 'danger';
                            }
                        } else {
                            $message = 'Current password is incorrect.';
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'Please correct the errors below.';
                        $messageType = 'danger';
                    }
                }
                break;
        }
    }
}

include 'includes/header.php';
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
                    <i class="bi bi-person me-2"></i>Profile Information
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="profileForm">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($currentUser['first_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($currentUser['last_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo htmlspecialchars($currentUser['username'] ?? ''); ?>" readonly>
                        <div class="form-text">Username cannot be changed</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <input type="text" class="form-control" id="role" name="role" 
                               value="<?php echo ucwords(str_replace('_', ' ', $currentUser['role'] ?? '')); ?>" readonly>
                        <div class="form-text">Role is assigned by administrators</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>Update Profile
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-key me-2"></i>Change Password
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="passwordForm">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password *</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password *</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <div class="form-text">Password must be at least 8 characters long</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-key me-2"></i>Change Password
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-info-circle me-2"></i>Account Information
                </h5>
            </div>
            <div class="card-body">
                <p><strong>Member Since:</strong><br>
                   <?php echo formatDate($currentUser['created_at'] ?? ''); ?></p>
                
                <p><strong>Last Login:</strong><br>
                   <?php echo formatDate($currentUser['last_login'] ?? ''); ?></p>
                
                <p><strong>Status:</strong><br>
                   <span class="badge bg-success">Active</span></p>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.getElementById('profileForm').addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('input[required]');
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

document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New password and confirm password do not match.');
        return;
    }
    
    if (newPassword.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long.');
        return;
    }
});
</script>

<?php include 'includes/footer.php'; ?>
