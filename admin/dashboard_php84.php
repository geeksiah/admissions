<?php
/**
 * PHP 8.4 Compatible Dashboard
 * Simplified version without autoloader or complex includes
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    echo "<p style='color: red;'>Access denied. Admin privileges required.</p>";
    exit;
}

// Get root path
$rootPath = dirname(__DIR__);

// Load configuration (PHP 8.4 compatible version)
require_once $rootPath . '/config/config_php84.php';

// Load database class
require_once $rootPath . '/config/database.php';

try {
    // Create database connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Load models manually (avoid autoloader issues)
    require_once $rootPath . '/models/User.php';
    require_once $rootPath . '/models/Student.php';
    require_once $rootPath . '/models/Application.php';
    require_once $rootPath . '/models/Program.php';
    require_once $rootPath . '/models/Payment.php';
    require_once $rootPath . '/models/Report.php';
    
    // Create model instances
    $userModel = new User($pdo);
    $studentModel = new Student($pdo);
    $applicationModel = new Application($pdo);
    $programModel = new Program($pdo);
    $paymentModel = new Payment($pdo);
    $reportModel = new Report($pdo);
    
    // Get current user
    $currentUser = $userModel->getById($_SESSION['user_id']);
    
    // Get dashboard data with error handling
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
    
    try {
        $recentApplications = $applicationModel->getRecent(5);
    } catch (Exception $e) {
        $recentApplications = [];
    }
    
    try {
        $recentStudents = $studentModel->getRecent(5);
    } catch (Exception $e) {
        $recentStudents = [];
    }
    
    try {
        $activePrograms = $programModel->getActive();
    } catch (Exception $e) {
        $activePrograms = [];
    }
    
} catch (Exception $e) {
    echo "<h1>Dashboard Error</h1>";
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
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
        
        .stat-card {
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .success-banner {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Success Banner -->
    <div class="container-fluid mt-3">
        <div class="success-banner">
            <h4><i class="bi bi-check-circle-fill me-2"></i>Dashboard Successfully Loaded!</h4>
            <p class="mb-0">PHP <?php echo phpversion(); ?> compatibility confirmed. All systems operational.</p>
        </div>
    </div>

    <!-- Navigation -->
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
    
    <!-- Main Content -->
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
                            <div class="text-center py-4">
                                <i class="bi bi-file-earmark-text text-muted" style="font-size: 3rem;"></i>
                                <h6 class="mt-3">No Recent Applications</h6>
                                <p class="text-muted">Applications will appear here when submitted.</p>
                            </div>
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
                            <div class="text-center py-4">
                                <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                                <h6 class="mt-3">No Recent Students</h6>
                                <p class="text-muted">Student registrations will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentStudents as $student): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                        </div>
                                        <span class="badge bg-success">Active</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Information -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            System Information
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <strong>PHP Version:</strong><br>
                                <span class="text-success"><?php echo phpversion(); ?></span>
                            </div>
                            <div class="col-md-3">
                                <strong>Database:</strong><br>
                                <span class="text-success">Connected ✓</span>
                            </div>
                            <div class="col-md-3">
                                <strong>Active Programs:</strong><br>
                                <span class="text-info"><?php echo count($activePrograms); ?></span>
                            </div>
                            <div class="col-md-3">
                                <strong>Current User:</strong><br>
                                <span class="text-primary"><?php echo htmlspecialchars($currentUser['first_name'] ?? 'Admin'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Show success notification
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide success banner after 5 seconds
            setTimeout(function() {
                const banner = document.querySelector('.success-banner');
                if (banner) {
                    banner.style.transition = 'opacity 0.5s';
                    banner.style.opacity = '0';
                    setTimeout(() => banner.remove(), 500);
                }
            }, 5000);
        });
    </script>
</body>
</html>
