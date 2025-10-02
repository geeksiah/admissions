<?php
/**
 * Student Dashboard
 */

session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Security.php';
require_once '../models/User.php';
require_once '../models/Student.php';
require_once '../models/Application.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

$security = new Security($database);
$userModel = new User($pdo);
$studentModel = new Student($pdo);
$applicationModel = new Application($pdo);

// Get current user data
$currentUser = $userModel->getById($_SESSION['user_id']);
$student = $studentModel->getByEmail($currentUser['email'] ?? '') ?: [];

if (!$student) {
    header('Location: ../unauthorized.php');
    exit;
}

// Get student's applications
$applications = !empty($student['id']) ? $applicationModel->getByStudent($student['id']) : [];

// Get application statistics
$stats = [
    'total' => count($applications),
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'under_review' => 0
];

foreach ($applications as $app) {
    $stats[$app['status']]++;
}

// Get recent notifications (if notification system is implemented)
$recentNotifications = []; // Placeholder for now

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Welcome back, <?php echo htmlspecialchars(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')); ?>!</h1>
                    <p class="text-muted">Here's an overview of your applications</p>
                </div>
                <div>
                    <a href="apply.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>
                        New Application
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo $stats['total']; ?></h4>
                            <p class="mb-0">Total Applications</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-file-earmark-text" style="font-size: 2rem;"></i>
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
                            <h4 class="mb-0"><?php echo $stats['pending']; ?></h4>
                            <p class="mb-0">Pending</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-clock" style="font-size: 2rem;"></i>
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
                            <h4 class="mb-0"><?php echo $stats['approved']; ?></h4>
                            <p class="mb-0">Approved</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
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
                            <h4 class="mb-0"><?php echo $stats['under_review']; ?></h4>
                            <p class="mb-0">Under Review</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-eye" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Applications -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-file-earmark-text me-2"></i>
                        Recent Applications
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($applications)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-file-earmark-text text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">No Applications Yet</h5>
                            <p class="text-muted">Start your journey by submitting your first application.</p>
                            <a href="apply.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>
                                Create Application
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Application ID</th>
                                        <th>Program</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($applications, 0, 5) as $app): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($app['application_id']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($app['program_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo getStatusColor($app['status']); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($app['submitted_at'])); ?></td>
                                            <td>
                                                <a href="applications.php?id=<?php echo $app['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($applications) > 5): ?>
                            <div class="text-center mt-3">
                                <a href="applications.php" class="btn btn-outline-primary">
                                    View All Applications
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions & Notifications -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightning me-2"></i>
                        Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="apply.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>
                            New Application
                        </a>
                        <a href="applications.php" class="btn btn-outline-primary">
                            <i class="bi bi-list-ul me-2"></i>
                            View All Applications
                        </a>
                        <a href="programs.php" class="btn btn-outline-secondary">
                            <i class="bi bi-book me-2"></i>
                            Browse Programs
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Notifications -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-bell me-2"></i>
                        Recent Notifications
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentNotifications)): ?>
                        <div class="text-center py-3">
                            <i class="bi bi-bell-slash text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2 mb-0">No notifications yet</p>
                        </div>
                    <?php else: ?>
                        <!-- Notification items would go here -->
                        <p class="text-muted">Notifications will appear here when available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
function getStatusColor($status) {
    switch ($status) {
        case 'approved':
            return 'success';
        case 'rejected':
            return 'danger';
        case 'under_review':
            return 'info';
        case 'pending':
            return 'warning';
        default:
            return 'secondary';
    }
}

include '../includes/footer.php';
?>
