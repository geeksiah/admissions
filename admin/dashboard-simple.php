<?php
/**
 * Simple Admin Dashboard
 * Basic dashboard without complex dependencies
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Check if user has admin role
$allowedRoles = ['admin', 'super_admin', 'admissions_officer', 'reviewer'];
if (!in_array($_SESSION['role'] ?? '', $allowedRoles)) {
    header('Location: /unauthorized-simple');
    exit;
}

$pageTitle = 'Admin Dashboard';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard']
];

// Initialize database
$database = new Database();

// Get basic stats
$stats = [
    'total_applications' => 0,
    'pending_applications' => 0,
    'approved_applications' => 0,
    'rejected_applications' => 0,
    'total_students' => 0,
    'total_programs' => 0
];

// Try to get real stats if models are available
try {
    require_once '../models/Application.php';
    require_once '../models/Student.php';
    require_once '../models/Program.php';
    
    $applicationModel = new Application($database);
    $studentModel = new Student($database);
    $programModel = new Program($database);
    
    // Get application stats
    $appStats = $applicationModel->getStatistics();
    if ($appStats) {
        $stats['total_applications'] = $appStats['total'] ?? 0;
        $stats['pending_applications'] = $appStats['pending'] ?? 0;
        $stats['approved_applications'] = $appStats['approved'] ?? 0;
        $stats['rejected_applications'] = $appStats['rejected'] ?? 0;
    }
    
    // Get student stats
    $studentStats = $studentModel->getStatistics();
    if ($studentStats) {
        $stats['total_students'] = $studentStats['total'] ?? 0;
    }
    
    // Get program stats
    $programStats = $programModel->getStatistics();
    if ($programStats) {
        $stats['total_programs'] = $programStats['total'] ?? 0;
    }
    
} catch (Exception $e) {
    // Use default stats if models fail
    error_log("Dashboard stats error: " . $e->getMessage());
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $stats['total_applications']; ?></h4>
                        <p class="mb-0">Total Applications</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-file-text display-4"></i>
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
                        <h4 class="mb-0"><?php echo $stats['pending_applications']; ?></h4>
                        <p class="mb-0">Pending Applications</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-clock display-4"></i>
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
                        <h4 class="mb-0"><?php echo $stats['approved_applications']; ?></h4>
                        <p class="mb-0">Approved Applications</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-check-circle display-4"></i>
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
                        <h4 class="mb-0"><?php echo $stats['total_students']; ?></h4>
                        <p class="mb-0">Total Students</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-people display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-graph-up me-2"></i>Recent Activity
                </h5>
            </div>
            <div class="card-body">
                <div class="text-center py-5">
                    <i class="bi bi-activity display-1 text-muted"></i>
                    <h4 class="text-muted mt-3">Welcome to Admin Dashboard</h4>
                    <p class="text-muted">System is running successfully. Navigation is working!</p>
                    
                    <div class="mt-4">
                        <h5>Quick Actions:</h5>
                        <div class="btn-group" role="group">
                            <a href="/admin/applications" class="btn btn-primary">
                                <i class="bi bi-file-text me-2"></i>Applications
                            </a>
                            <a href="/admin/students" class="btn btn-success">
                                <i class="bi bi-people me-2"></i>Students
                            </a>
                            <a href="/admin/programs" class="btn btn-info">
                                <i class="bi bi-mortarboard me-2"></i>Programs
                            </a>
                            <a href="/admin/settings" class="btn btn-secondary">
                                <i class="bi bi-gear me-2"></i>Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-info-circle me-2"></i>System Information
                </h5>
            </div>
            <div class="card-body">
                <p><strong>User:</strong><br>
                   <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></p>
                
                <p><strong>Role:</strong><br>
                   <span class="badge bg-primary"><?php echo ucwords(str_replace('_', ' ', $_SESSION['role'])); ?></span></p>
                
                <p><strong>Session ID:</strong><br>
                   <code><?php echo substr(session_id(), 0, 8); ?>...</code></p>
                
                <p><strong>Server Time:</strong><br>
                   <?php echo date('Y-m-d H:i:s'); ?></p>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-tools me-2"></i>Navigation Test
                </h5>
            </div>
            <div class="card-body">
                <p>Test these navigation links:</p>
                <div class="d-grid gap-2">
                    <a href="/admin/applications" class="btn btn-outline-primary btn-sm">Applications</a>
                    <a href="/admin/students" class="btn btn-outline-success btn-sm">Students</a>
                    <a href="/admin/programs" class="btn btn-outline-info btn-sm">Programs</a>
                    <a href="/admin/settings" class="btn btn-outline-secondary btn-sm">Settings</a>
                    <a href="/profile" class="btn btn-outline-warning btn-sm">Profile</a>
                    <a href="/logout" class="btn btn-outline-danger btn-sm">Logout</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
