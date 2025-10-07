<?php
/**
 * Login Test Page - Verify Login Process
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';
require_once 'config/database.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $message = 'Please enter both username and password.';
            $messageType = 'danger';
        } else {
            // Test authentication
            require_once 'models/User.php';
            $database = new Database();
            $userModel = new User($database);
            
            $user = $userModel->authenticate($username, $password);
            
            if ($user) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['first_name'] = $user['first_name'] ?? '';
                $_SESSION['last_name'] = $user['last_name'] ?? '';
                $_SESSION['email'] = $user['email'] ?? '';
                $_SESSION['role'] = $user['role'];
                
                $message = 'Login successful! Redirecting...';
                $messageType = 'success';
                
                // Redirect based on role
                if (in_array($user['role'], ['admin', 'super_admin', 'admissions_officer', 'reviewer'])) {
                    header('Location: /admin/dashboard');
                    exit;
                } else {
                    header('Location: /student/dashboard');
                    exit;
                }
            } else {
                $message = 'Invalid username or password.';
                $messageType = 'danger';
            }
        }
    } catch (Exception $e) {
        $message = 'Login error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Test - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card mt-5">
                    <div class="card-header text-center">
                        <h4><i class="bi bi-shield-lock me-2"></i>Login Test</h4>
                        <small class="text-muted"><?php echo APP_NAME; ?></small>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username or Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Login
                                </button>
                            </div>
                        </form>
                        
                        <div class="mt-4">
                            <h6>Test Accounts:</h6>
                            <small class="text-muted">
                                <strong>Admin:</strong> admin / admin123<br>
                                <strong>Student:</strong> student / student123
                            </small>
                        </div>
                    </div>
                    <div class="card-footer text-center">
                        <a href="/login" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left me-1"></i>Back to Main Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
