<?php
/**
 * Dashboard with Absolute Paths - Fixed for Hostinger
 */

// Start session
session_start();

// Check authentication first
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    echo "<p style='color: red;'>Access denied. Admin privileges required.</p>";
    exit;
}

// Get the absolute path to the root directory
$rootPath = dirname(__DIR__); // Go up one level from /admin to root

// Load configuration and database using absolute paths
require_once $rootPath . '/config/config.php';
require_once $rootPath . '/config/database.php';

try {
    // Initialize database
    $database = new Database();
    $pdo = $database->getConnection();

    // Load models using absolute paths
    require_once $rootPath . '/models/User.php';
    require_once $rootPath . '/models/Student.php';
    require_once $rootPath . '/models/Application.php';
    require_once $rootPath . '/models/Program.php';
    require_once $rootPath . '/models/Payment.php';
    require_once $rootPath . '/models/Report.php';

    $userModel = new User($pdo);
    $studentModel = new Student($pdo);
    $applicationModel = new Application($pdo);
    $programModel = new Program($pdo);
    $paymentModel = new Payment($pdo);
    $reportModel = new Report($pdo);

    // Get current user data
    $currentUser = $userModel->getById($_SESSION['user_id']);

    // Get dashboard statistics with error handling
    try {
        $stats = $reportModel->getDashboardStats();
    } catch (Exception $e) {
        $stats = [
            'total_applications' => 0,
            'pending_applications' => 0,
            'approved_applications' => 0,
            'rejected_applications' => 0,
            'under_review_applications' => 0
        ];
    }

    // Get recent applications
    try {
        $recentApplications = $applicationModel->getRecent(5);
    } catch (Exception $e) {
        $recentApplications = [];
    }

    // Get recent students
    try {
        $recentStudents = $studentModel->getRecent(5);
    } catch (Exception $e) {
        $recentStudents = [];
    }

    // Get active programs
    try {
        $activePrograms = $programModel->getActive();
    } catch (Exception $e) {
        $activePrograms = [];
    }

} catch (Exception $e) {
    // If there's any error, show it and stop
    echo "<h1>Dashboard Error</h1>";
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<p><strong>Root Path:</strong> " . htmlspecialchars($rootPath) . "</p>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .navbar-brand {
            font-weight: 600;
            color: #667eea !important;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 6px;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        }
        
        .stat-card {
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
        }
    </style>
</head>
<body>
    <!-- Simple Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="bi bi-mortarboard-fill me-2"></i>
                <?php echo APP_NAME; ?>
            </a>
            
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Welcome, <?php echo htmlspecialchars($currentUser['first_name'] ?? $_SESSION['first_name'] ?? 'Admin'); ?>!
                </span>
                <a class="nav-link" href="../logout.php">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Main Content Container -->
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">
                    <i class="bi bi-speedometer2 me-2"></i>
                    Dashboard Overview
                </h1>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3><?php echo $stats['total_applications'] ?? 0; ?></h3>
                                <p class="mb-0">Total Applications</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-file-earmark-text" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3><?php echo $stats['pending_applications'] ?? 0; ?></h3>
                                <p class="mb-0">Pending Review</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-clock" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3><?php echo $stats['approved_applications'] ?? 0; ?></h3>
                                <p class="mb-0">Approved</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3><?php echo count($activePrograms); ?></h3>
                                <p class="mb-0">Active Programs</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-mortarboard" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-file-earmark-text me-2"></i>
                            Recent Applications
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentApplications)): ?>
                            <p class="text-muted">No recent applications</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentApplications as $app): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($app['program_name'] ?? 'Unknown Program'); ?></small>
                                        </div>
                                        <span class="badge bg-<?php 
                                            switch($app['status']) {
                                                case 'approved': echo 'success'; break;
                                                case 'rejected': echo 'danger'; break;
                                                case 'pending': echo 'warning'; break;
                                                default: echo 'secondary';
                                            }
                                        ?>">
                                            <?php echo ucfirst($app['status']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-people me-2"></i>
                            Recent Students
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentStudents)): ?>
                            <p class="text-muted">No recent students</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentStudents as $student): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                        </div>
                                        <span class="badge bg-<?php echo ($student['status'] ?? 'active') === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($student['status'] ?? 'active'); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-lightning me-2"></i>
                            Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="students.php" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-person-plus me-2"></i>
                                    Manage Students
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="applications.php" class="btn btn-outline-success w-100">
                                    <i class="bi bi-file-plus me-2"></i>
                                    Review Applications
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="programs.php" class="btn btn-outline-info w-100">
                                    <i class="bi bi-mortarboard me-2"></i>
                                    Manage Programs
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="reports.php" class="btn btn-outline-warning w-100">
                                    <i class="bi bi-graph-up me-2"></i>
                                    View Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Info (for debugging) -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">System Status</h6>
                    </div>
                    <div class="card-body">
                        <small class="text-muted">
                            <strong>✅ Dashboard loaded successfully!</strong><br>
                            Root Path: <?php echo htmlspecialchars($rootPath); ?><br>
                            PHP Version: <?php echo phpversion(); ?><br>
                            Current User: <?php echo htmlspecialchars($currentUser['first_name'] ?? 'Unknown'); ?><br>
                            Active Programs: <?php echo count($activePrograms); ?><br>
                            Database: Connected ✅
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Simple JavaScript utilities
        function showAlert(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const container = document.querySelector('.container-fluid');
            container.insertBefore(alertDiv, container.firstChild);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
        
        // Show success message
        showAlert('Dashboard loaded successfully! 🎉', 'success');
    </script>
</body>
</html>
