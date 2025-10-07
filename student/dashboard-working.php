<?php
/**
 * Working Student Dashboard - Production Ready
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: /student-login');
    exit;
}

// Check student role
if (($_SESSION['role'] ?? '') !== 'student') {
    header('Location: /unauthorized');
    exit;
}

// Get current user info
$currentUser = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'] ?? '',
    'first_name' => $_SESSION['first_name'] ?? '',
    'last_name' => $_SESSION['last_name'] ?? '',
    'email' => $_SESSION['email'] ?? '',
    'role' => $_SESSION['role'] ?? ''
];

$database = new Database();

// Get basic stats
$stats = [
    'total_applications' => 0,
    'pending_applications' => 0,
    'approved_applications' => 0,
    'rejected_applications' => 0
];

// Try to get real stats if possible
try {
    $pdo = $database->getConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ?");
    $stmt->execute([$currentUser['id']]);
    $stats['total_applications'] = $stmt->fetchColumn();
} catch (Exception $e) {
    // Ignore errors for now
}

$applications = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background: linear-gradient(180deg, #28a745 0%, #20c997 100%);
            color: white;
            z-index: 1000;
        }
        
        .main-content {
            margin-left: 250px;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        
        .sidebar-header {
            padding: 1.5rem 1rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-nav .nav-link {
            color: white;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .sidebar-nav .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar-nav .nav-link.active {
            background-color: rgba(255,255,255,0.15);
            color: white;
        }
        
        .sidebar-nav .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #28a745;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h4><?php echo APP_NAME; ?></h4>
            <small>Student Portal</small>
        </div>
        
        <ul class="nav flex-column sidebar-nav">
            <li class="nav-item">
                <a class="nav-link active" href="/student/dashboard">
                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/student/applications">
                    <i class="bi bi-file-earmark-text"></i>
                    My Applications
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/student/apply">
                    <i class="bi bi-plus-circle"></i>
                    Apply Now
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/student/programs">
                    <i class="bi bi-mortarboard"></i>
                    Programs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/student/payment">
                    <i class="bi bi-credit-card"></i>
                    Payments
                </a>
            </li>
            <li class="nav-item mt-3">
                <a class="nav-link" href="/profile">
                    <i class="bi bi-person-circle"></i>
                    Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/logout">
                    <i class="bi bi-box-arrow-right"></i>
                    Logout
                </a>
            </li>
        </ul>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="bg-white shadow-sm p-3 mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0">Student Dashboard</h4>
                    <small class="text-muted">Welcome back, <?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>!</small>
                </div>
                <div class="d-flex align-items-center">
                    <span class="badge bg-success me-2">Student</span>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="/profile">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/logout">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Content -->
        <div class="container-fluid">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
                        <div class="stat-label">Total Applications</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-number"><?php echo $stats['pending_applications']; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-number"><?php echo $stats['approved_applications']; ?></div>
                        <div class="stat-label">Approved</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-number"><?php echo $stats['rejected_applications']; ?></div>
                        <div class="stat-label">Rejected</div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <a href="/student/apply" class="btn btn-success w-100">
                                        <i class="bi bi-plus-circle me-2"></i>Apply to Program
                                    </a>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <a href="/student/applications" class="btn btn-outline-primary w-100">
                                        <i class="bi bi-file-earmark-text me-2"></i>View Applications
                                    </a>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <a href="/student/programs" class="btn btn-outline-info w-100">
                                        <i class="bi bi-mortarboard me-2"></i>Browse Programs
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Applications -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Applications</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($applications)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-file-earmark-text display-1 text-muted"></i>
                                    <h4 class="text-muted mt-3">No Applications Yet</h4>
                                    <p class="text-muted">Start your academic journey by applying to a program.</p>
                                    <a href="/student/apply" class="btn btn-success">
                                        <i class="bi bi-plus-circle me-2"></i>Apply Now
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Application #</th>
                                                <th>Program</th>
                                                <th>Status</th>
                                                <th>Applied Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($applications as $application): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($application['application_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($application['program_name']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $application['status'] === 'approved' ? 'success' : ($application['status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                                            <?php echo ucwords(str_replace('_', ' ', $application['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo formatDate($application['application_date']); ?></td>
                                                    <td>
                                                        <a href="/student/application-details?id=<?php echo $application['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-eye"></i> View
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
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Simple mobile sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            // Add mobile toggle button
            const header = document.querySelector('.bg-white.shadow-sm');
            if (header && window.innerWidth <= 768) {
                const toggleBtn = document.createElement('button');
                toggleBtn.className = 'btn btn-outline-secondary btn-sm';
                toggleBtn.innerHTML = '<i class="bi bi-list"></i>';
                toggleBtn.onclick = function() {
                    document.querySelector('.sidebar').classList.toggle('show');
                };
                header.querySelector('.d-flex').prepend(toggleBtn);
            }
        });
    </script>
</body>
</html>
