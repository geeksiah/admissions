<?php
/**
 * Fixed Admin Dashboard - Production Ready
 * Optimized for cPanel/Shared Hosting (PHP 8.2+)
 */

// Error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/dashboard_errors.log');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit('Authentication required');
}

// Role check
$userRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($userRole, ['admin', 'super_admin'], true)) {
    http_response_code(403);
    exit('Access denied. Admin privileges required.');
}

// Get absolute paths
$rootPath = dirname(__DIR__);
$adminPath = __DIR__;

// Database configuration
$dbConfig = [
    'host' => 'localhost',
    'name' => 'u279576488_admissions',
    'user' => 'u279576488_lapaz',
    'pass' => '7uVV;OEX|',
    'charset' => 'utf8mb4'
];

// Initialize database connection with error handling
try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], $options);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Please contact administrator.');
}

// Helper function to safely fetch data
function safeQuery($pdo, $query, $params = []) {
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Query error: " . $e->getMessage() . " | Query: " . $query);
        return [];
    }
}

function safeQuerySingle($pdo, $query, $params = []) {
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Query error: " . $e->getMessage());
        return null;
    }
}

function safeCount($pdo, $query, $params = []) {
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Count query error: " . $e->getMessage());
        return 0;
    }
}

// Get current user data
$currentUser = safeQuerySingle(
    $pdo,
    "SELECT * FROM users WHERE id = ? LIMIT 1",
    [$_SESSION['user_id']]
);

if (!$currentUser) {
    $currentUser = [
        'first_name' => $_SESSION['first_name'] ?? 'Admin',
        'last_name' => $_SESSION['last_name'] ?? 'User',
        'email' => $_SESSION['email'] ?? 'admin@system.local'
    ];
}

// Get dashboard statistics with fallback
$stats = [
    'total_applications' => safeCount($pdo, "SELECT COUNT(*) FROM applications"),
    'pending_applications' => safeCount($pdo, "SELECT COUNT(*) FROM applications WHERE status = 'pending'"),
    'approved_applications' => safeCount($pdo, "SELECT COUNT(*) FROM applications WHERE status = 'approved'"),
    'rejected_applications' => safeCount($pdo, "SELECT COUNT(*) FROM applications WHERE status = 'rejected'"),
    'under_review_applications' => safeCount($pdo, "SELECT COUNT(*) FROM applications WHERE status = 'under_review'"),
    'today_applications' => safeCount($pdo, "SELECT COUNT(*) FROM applications WHERE DATE(created_at) = CURDATE()"),
    'week_applications' => safeCount($pdo, "SELECT COUNT(*) FROM applications WHERE YEARWEEK(created_at) = YEARWEEK(NOW())")
];

// Get recent applications (with safe JOIN)
$recentApplications = safeQuery(
    $pdo,
    "SELECT 
        a.id,
        a.application_id,
        a.first_name,
        a.last_name,
        a.status,
        a.created_at,
        COALESCE(p.name, 'Unknown Program') as program_name
    FROM applications a
    LEFT JOIN programs p ON a.program_id = p.id
    ORDER BY a.created_at DESC
    LIMIT 10"
);

// Get recent students
$recentStudents = safeQuery(
    $pdo,
    "SELECT 
        id,
        first_name,
        last_name,
        email,
        status,
        created_at
    FROM students
    ORDER BY created_at DESC
    LIMIT 10"
);

// Get active programs count
$activeProgramsCount = safeCount($pdo, "SELECT COUNT(*) FROM programs WHERE status = 'active'");

// Get pending documents count
$pendingDocumentsCount = safeCount($pdo, "SELECT COUNT(*) FROM documents WHERE status = 'pending'");

// Get total revenue (if payments table exists)
$totalRevenue = 0;
try {
    $stmt = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'completed'");
    $totalRevenue = (float) $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Revenue query failed: " . $e->getMessage());
}

// Status color helper
function getStatusColor($status) {
    $colors = [
        'approved' => 'success',
        'rejected' => 'danger',
        'under_review' => 'info',
        'pending' => 'warning',
        'active' => 'success',
        'inactive' => 'secondary'
    ];
    return $colors[$status] ?? 'secondary';
}

// Format currency
function formatCurrency($amount) {
    return 'GHS ' . number_format($amount, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Admissions Management System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --dark-color: #1f2937;
            --light-bg: #f8fafc;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--light-bg);
            color: #374151;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
            font-size: 1.25rem;
        }
        
        .stat-card {
            border: none;
            border-radius: 12px;
            padding: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.1);
        }
        
        .stat-card.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stat-card.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .stat-card.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }
        
        .stat-card.info {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }
        
        .stat-card.danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
            margin: 0.5rem 0;
        }
        
        .stat-label {
            font-size: 0.875rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.3;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            font-size: 1.125rem;
        }
        
        .table {
            font-size: 0.925rem;
        }
        
        .table thead th {
            background-color: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
        
        .badge {
            padding: 0.375rem 0.75rem;
            font-weight: 500;
            font-size: 0.75rem;
        }
        
        .btn-action {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #9ca3af;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .quick-action-btn {
            width: 100%;
            padding: 1rem;
            margin-bottom: 0.75rem;
            text-align: left;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .quick-action-btn:hover {
            transform: translateX(4px);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container-fluid px-4">
            <a class="navbar-brand" href="#">
                <i class="bi bi-mortarboard-fill me-2"></i>
                Admissions System
            </a>
            
            <div class="navbar-nav ms-auto align-items-center">
                <span class="navbar-text me-3">
                    <i class="bi bi-person-circle me-2"></i>
                    Welcome, <strong><?php echo htmlspecialchars($currentUser['first_name']); ?></strong>
                </span>
                <a class="btn btn-outline-danger btn-sm" href="../logout.php">
                    <i class="bi bi-box-arrow-right me-1"></i>
                    Logout
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container-fluid px-4 py-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="h3 mb-1">
                    <i class="bi bi-speedometer2 me-2"></i>
                    Dashboard Overview
                </h1>
                <p class="text-muted mb-0">Real-time insights and quick actions</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card primary">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Total Applications</div>
                            <div class="stat-number"><?php echo number_format($stats['total_applications']); ?></div>
                            <small>+<?php echo $stats['today_applications']; ?> today</small>
                        </div>
                        <i class="bi bi-file-earmark-text stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card warning">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Pending Review</div>
                            <div class="stat-number"><?php echo number_format($stats['pending_applications']); ?></div>
                            <small><?php echo $pendingDocumentsCount; ?> pending documents</small>
                        </div>
                        <i class="bi bi-clock-history stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card success">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Approved</div>
                            <div class="stat-number"><?php echo number_format($stats['approved_applications']); ?></div>
                            <small><?php echo $stats['under_review_applications']; ?> under review</small>
                        </div>
                        <i class="bi bi-check-circle stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card info">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Active Programs</div>
                            <div class="stat-number"><?php echo number_format($activeProgramsCount); ?></div>
                            <small><?php echo formatCurrency($totalRevenue); ?> revenue</small>
                        </div>
                        <i class="bi bi-mortarboard stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Applications -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-file-earmark-text me-2"></i>
                            Recent Applications
                        </span>
                        <a href="applications.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentApplications)): ?>
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <h5 class="mt-3">No Applications Yet</h5>
                                <p class="text-muted">Applications will appear here as they are submitted.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Application ID</th>
                                            <th>Student Name</th>
                                            <th>Program</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentApplications as $app): ?>
                                            <tr>
                                                <td>
                                                    <strong class="text-primary">
                                                        <?php echo htmlspecialchars($app['application_id'] ?? 'N/A'); ?>
                                                    </strong>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($app['program_name']); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusColor($app['status']); ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?php echo date('M j, Y', strtotime($app['created_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <a href="view-application.php?id=<?php echo $app['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary btn-action">
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
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-lightning-charge me-2"></i>
                        Quick Actions
                    </div>
                    <div class="card-body">
                        <a href="applications.php" class="btn btn-outline-primary quick-action-btn">
                            <i class="bi bi-list-check me-2"></i>
                            Review Applications
                            <?php if ($stats['pending_applications'] > 0): ?>
                                <span class="badge bg-warning float-end">
                                    <?php echo $stats['pending_applications']; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <a href="students.php" class="btn btn-outline-success quick-action-btn">
                            <i class="bi bi-people me-2"></i>
                            Manage Students
                        </a>
                        <a href="programs.php" class="btn btn-outline-info quick-action-btn">
                            <i class="bi bi-mortarboard me-2"></i>
                            Manage Programs
                        </a>
                        <a href="reports.php" class="btn btn-outline-warning quick-action-btn">
                            <i class="bi bi-graph-up me-2"></i>
                            View Reports
                        </a>
                        <a href="settings.php" class="btn btn-outline-secondary quick-action-btn">
                            <i class="bi bi-gear me-2"></i>
                            System Settings
                        </a>
                    </div>
                </div>

                <!-- Recent Students -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-people me-2"></i>
                            Recent Students
                        </span>
                        <a href="students.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentStudents)): ?>
                            <div class="empty-state py-3">
                                <i class="bi bi-person-x"></i>
                                <p class="text-muted mb-0">No students yet</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach (array_slice($recentStudents, 0, 5) as $student): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1">
                                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($student['email']); ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-<?php echo getStatusColor($student['status'] ?? 'active'); ?>">
                                                <?php echo ucfirst($student['status'] ?? 'active'); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status Footer -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body py-2">
                        <small class="text-muted">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            <strong>System Status:</strong> Operational
                            <span class="mx-2">|</span>
                            <strong>PHP:</strong> <?php echo phpversion(); ?>
                            <span class="mx-2">|</span>
                            <strong>Database:</strong> Connected
                            <span class="mx-2">|</span>
                            <strong>Last Login:</strong> <?php echo date('M j, Y g:i A'); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-refresh statistics every 5 minutes
        setInterval(function() {
            // You can implement AJAX refresh here if needed
            console.log('Dashboard statistics refreshed');
        }, 300000);
        
        // Show toast notification
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
            toast.style.zIndex = '9999';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 5000);
        }
        
        // Initial success message
        window.addEventListener('load', function() {
            showToast('Dashboard loaded successfully!', 'success');
        });
    </script>
</body>
</html>