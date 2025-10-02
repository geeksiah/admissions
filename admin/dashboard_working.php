<?php
/**
 * Working Admin Dashboard
 * This is a fully functional dashboard with all features working
 */

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

// Load database
require_once '../config/database.php';
$database = new Database();
$pdo = $database->getConnection();

// Load models
require_once '../models/User.php';
require_once '../models/Student.php';
require_once '../models/Application.php';
require_once '../models/Program.php';
require_once '../models/Payment.php';
require_once '../models/Report.php';

$userModel = new User($pdo);
$studentModel = new Student($pdo);
$applicationModel = new Application($pdo);
$programModel = new Program($pdo);
$paymentModel = new Payment($pdo);
$reportModel = new Report($pdo);

// Get current user data
$currentUser = $userModel->getById($_SESSION['user_id']);

// Get dashboard statistics
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Admissions Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            border-radius: 10px;
            margin: 5px 0;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0">
                <div class="p-3">
                    <h4 class="text-white mb-4">
                        <i class="fas fa-graduation-cap me-2"></i>
                        Admissions
                    </h4>
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="dashboard_working.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a class="nav-link" href="students.php">
                            <i class="fas fa-users me-2"></i>Students
                        </a>
                        <a class="nav-link" href="applications.php">
                            <i class="fas fa-file-alt me-2"></i>Applications
                        </a>
                        <a class="nav-link" href="programs.php">
                            <i class="fas fa-book me-2"></i>Programs
                        </a>
                        <a class="nav-link" href="payments.php">
                            <i class="fas fa-credit-card me-2"></i>Payments
                        </a>
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10">
                <!-- Top Navigation -->
                <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
                    <div class="container-fluid">
                        <span class="navbar-text">
                            Welcome back, <strong><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></strong>
                        </span>
                        <div class="navbar-nav ms-auto">
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-1"></i>Logout
                            </a>
                        </div>
                    </div>
                </nav>
                
                <!-- Dashboard Content -->
                <div class="p-4">
                    <h1 class="mb-4">
                        <i class="fas fa-tachometer-alt me-2"></i>
                        Dashboard Overview
                    </h1>
                    
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
                                            <i class="fas fa-file-alt fa-2x"></i>
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
                                            <i class="fas fa-clock fa-2x"></i>
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
                                            <i class="fas fa-check-circle fa-2x"></i>
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
                                            <i class="fas fa-book fa-2x"></i>
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
                                        <i class="fas fa-file-alt me-2"></i>
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
                                        <i class="fas fa-users me-2"></i>
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
                                                    <span class="badge bg-<?php echo $student['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($student['status']); ?>
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
                                        <i class="fas fa-bolt me-2"></i>
                                        Quick Actions
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <a href="students.php" class="btn btn-outline-primary w-100">
                                                <i class="fas fa-user-plus me-2"></i>
                                                Add Student
                                            </a>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <a href="applications.php" class="btn btn-outline-success w-100">
                                                <i class="fas fa-file-plus me-2"></i>
                                                Review Applications
                                            </a>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <a href="programs.php" class="btn btn-outline-info w-100">
                                                <i class="fas fa-book-plus me-2"></i>
                                                Manage Programs
                                            </a>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <a href="reports.php" class="btn btn-outline-warning w-100">
                                                <i class="fas fa-chart-line me-2"></i>
                                                View Reports
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
