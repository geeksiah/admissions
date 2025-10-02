<?php
/**
 * Admin Dashboard
 */

session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Security.php';
require_once '../models/User.php';
require_once '../models/Application.php';
require_once '../models/Student.php';
require_once '../models/Program.php';
require_once '../models/Payment.php';
require_once '../models/Report.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: ../login.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

$security = new Security($database);
$userModel = new User($pdo);
$applicationModel = new Application($pdo);
$studentModel = new Student($pdo);
$programModel = new Program($pdo);
$paymentModel = new Payment($pdo);
$reportModel = new Report($pdo);

// Get current user data
$currentUser = $userModel->getById($_SESSION['user_id']);

// Get dashboard statistics
$stats = $reportModel->getDashboardStats();

// Get recent applications
$recentApplications = $applicationModel->getRecent(10);

// Get pending documents
$pendingDocuments = $applicationModel->getPendingDocuments(5);

// Get payment statistics
$paymentStats = $paymentModel->getStatistics();

// Get popular programs
$popularPrograms = $programModel->getPopular(5);

// Get application trends (last 30 days)
$applicationTrends = $reportModel->getApplicationTrends(30);

include '../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">Dashboard</h1>
                    <p class="text-muted">Welcome back, <?php echo htmlspecialchars($currentUser['full_name']); ?>!</p>
                </div>
                <div>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary" onclick="refreshDashboard()">
                            <i class="bi bi-arrow-clockwise me-2"></i>
                            Refresh
                        </button>
                        <button type="button" class="btn btn-primary" onclick="exportDashboard()">
                            <i class="bi bi-download me-2"></i>
                            Export Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Applications
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_applications']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-file-earmark-text fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Approved Applications
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['approved_applications']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending Review
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['pending_applications']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Total Revenue
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo APP_CURRENCY . ' ' . number_format($paymentStats['total_revenue'], 2); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-currency-dollar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Applications -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Applications</h6>
                    <a href="applications.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentApplications)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-file-earmark-text text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">No Applications Yet</h5>
                            <p class="text-muted">Applications will appear here as they are submitted.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Application ID</th>
                                        <th>Student Name</th>
                                        <th>Program</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentApplications as $app): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($app['application_id']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></td>
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
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions & Stats -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="applications.php" class="btn btn-primary">
                            <i class="bi bi-list-ul me-2"></i>
                            Review Applications
                        </a>
                        <a href="students.php" class="btn btn-outline-primary">
                            <i class="bi bi-people me-2"></i>
                            Manage Students
                        </a>
                        <a href="programs.php" class="btn btn-outline-primary">
                            <i class="bi bi-book me-2"></i>
                            Manage Programs
                        </a>
                        <a href="reports.php" class="btn btn-outline-primary">
                            <i class="bi bi-graph-up me-2"></i>
                            View Reports
                        </a>
                    </div>
                </div>
            </div>

            <!-- Pending Documents -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-warning">Pending Documents</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($pendingDocuments)): ?>
                        <div class="text-center py-3">
                            <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2 mb-0">No pending documents</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pendingDocuments as $doc): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <small class="font-weight-bold"><?php echo htmlspecialchars($doc['requirement_name']); ?></small>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></small>
                                </div>
                                <a href="documents.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center mt-3">
                            <a href="documents.php" class="btn btn-sm btn-outline-warning">View All</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Popular Programs -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-info">Popular Programs</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($popularPrograms)): ?>
                        <div class="text-center py-3">
                            <i class="bi bi-book text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2 mb-0">No programs yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($popularPrograms as $program): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <small class="font-weight-bold"><?php echo htmlspecialchars($program['program_name']); ?></small>
                                    <br>
                                    <small class="text-muted"><?php echo $program['application_count']; ?> applications</small>
                                </div>
                                <span class="badge bg-info"><?php echo $program['level_name']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function refreshDashboard() {
    location.reload();
}

function exportDashboard() {
    // Implementation for exporting dashboard data
    window.open('reports.php?export=dashboard', '_blank');
}

// Auto-refresh every 5 minutes
setInterval(function() {
    // Refresh only the statistics cards
    fetch('api/dashboard-stats.php')
        .then(response => response.json())
        .then(data => {
            // Update statistics
            document.querySelector('.card.border-left-primary .h5').textContent = data.total_applications.toLocaleString();
            document.querySelector('.card.border-left-success .h5').textContent = data.approved_applications.toLocaleString();
            document.querySelector('.card.border-left-warning .h5').textContent = data.pending_applications.toLocaleString();
            document.querySelector('.card.border-left-info .h5').textContent = APP_CURRENCY + ' ' + data.total_revenue.toLocaleString();
        })
        .catch(error => console.error('Error refreshing dashboard:', error));
}, 300000); // 5 minutes
</script>

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
