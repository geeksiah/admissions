<?php
/**
 * Admin Dashboard - Commit 1,29 Stable Version
 * Working navigation with test buttons
 */

// Include configuration files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: /login');
    exit('Authentication required');
}

// Role check - allow more admin roles
$userRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
$allowedRoles = ['admin', 'super_admin', 'admissions_officer', 'reviewer'];
if (!in_array($userRole, $allowedRoles)) {
    header('Location: /unauthorized');
    exit('Access denied. Admin privileges required.');
}

// Initialize database connection using proper config (fail-safe)
try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    error_log("Database connection failed (dashboard fallback): " . $e->getMessage());
    $pdo = null; // continue with safe fallbacks below
}

// Get current user data
try {
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $currentUser = $stmt->fetch();
    } else {
        $currentUser = null;
    }
} catch (Exception $e) {
    error_log("User query failed: " . $e->getMessage());
    $currentUser = null;
}

if (!$currentUser) {
    $currentUser = [
        'first_name' => $_SESSION['first_name'] ?? 'Admin',
        'last_name' => $_SESSION['last_name'] ?? 'User',
        'email' => $_SESSION['email'] ?? 'admin@system.local'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Admissions Management System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
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
        
        .sidebar {
            background: white;
            box-shadow: 2px 0 4px rgba(0,0,0,0.1);
            min-height: calc(100vh - 76px);
        }
        
        .nav-link {
            color: #6b7280;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin: 0.25rem 0;
            transition: all 0.2s;
        }
        
        .nav-link:hover {
            background-color: #f3f4f6;
            color: var(--primary-color);
        }
        
        .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
        }
        
        .panel-content {
            display: none;
            animation: fadeIn 0.3s ease-in;
        }
        
        .panel-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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
                <a class="btn btn-outline-danger btn-sm" href="/logout">
                    <i class="bi bi-box-arrow-right me-1"></i>
                    Logout
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 p-0">
                <div class="sidebar p-3">
                    <h6 class="text-muted mb-3">ADMIN PANEL</h6>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#" data-panel="overview">
                                <i class="bi bi-speedometer2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-panel="applications">
                                <i class="bi bi-file-earmark-text"></i>
                                Applications
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-panel="students">
                                <i class="bi bi-people"></i>
                                Students
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-panel="programs">
                                <i class="bi bi-mortarboard"></i>
                                Programs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-panel="application_forms">
                                <i class="bi bi-file-earmark-plus"></i>
                                Application Forms
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-panel="users">
                                <i class="bi bi-person-gear"></i>
                                Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-panel="payments">
                                <i class="bi bi-credit-card"></i>
                                Payments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-panel="reports">
                                <i class="bi bi-graph-up"></i>
                                Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-panel="notifications">
                                <i class="bi bi-bell"></i>
                                Notifications
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-panel="communications">
                                <i class="bi bi-chat-dots"></i>
                                Communications
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-panel="settings">
                                <i class="bi bi-gear"></i>
                                Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-panel="system">
                                <i class="bi bi-cpu"></i>
                                System
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0" id="pageTitle">Dashboard Overview</h1>
                </div>
                
                <!-- Overview Panel -->
                <div class="panel-content active" id="overview-panel">
                    <div class="alert alert-success">
                        <h4>Dashboard Overview</h4>
                        <p>Welcome to the admin dashboard. Navigation should be working now.</p>
                        <p>Current time: <?php echo date('Y-m-d H:i:s'); ?></p>
                        <button class="btn btn-primary mt-2" onclick="alert('Basic JavaScript works!'); console.log('Button clicked');">
                            Test Basic JavaScript
                        </button>
                        <button class="btn btn-secondary mt-2" onclick="if(window.showPanel) { alert('showPanel function exists'); window.showPanel('applications'); } else { alert('showPanel function does NOT exist'); }">
                            Test Navigation Function
                        </button>
                    </div>

                    <!-- Quick Stats -->
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="stat-card primary">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Total Applications</div>
                                        <div class="stat-number">0</div>
                                    </div>
                                    <i class="bi bi-file-earmark-text stat-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card warning">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Pending Review</div>
                                        <div class="stat-number">0</div>
                                    </div>
                                    <i class="bi bi-clock-history stat-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card success">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Approved</div>
                                        <div class="stat-number">0</div>
                                    </div>
                                    <i class="bi bi-check-circle stat-icon"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-card info">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Active Programs</div>
                                        <div class="stat-number">0</div>
                                    </div>
                                    <i class="bi bi-mortarboard stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Applications Panel -->
                <div class="panel-content" id="applications-panel">
                    <div class="alert alert-info">Applications Panel - Navigation Test</div>
                </div>
                
                <!-- Students Panel -->
                <div class="panel-content" id="students-panel">
                    <div class="alert alert-info">Students Panel - Navigation Test</div>
                </div>
                
                <!-- Programs Panel -->
                <div class="panel-content" id="programs-panel">
                    <div class="alert alert-info">Programs Panel - Navigation Test</div>
                </div>
                
                <!-- Application Forms Panel -->
                <div class="panel-content" id="application_forms-panel">
                    <div class="alert alert-info">Application Forms Panel - Navigation Test</div>
                </div>
                
                <!-- Users Panel -->
                <div class="panel-content" id="users-panel">
                    <div class="alert alert-info">Users Panel - Navigation Test</div>
                </div>
                
                <!-- Payments Panel -->
                <div class="panel-content" id="payments-panel">
                    <div class="alert alert-info">Payments Panel - Navigation Test</div>
                </div>
                
                <!-- Reports Panel -->
                <div class="panel-content" id="reports-panel">
                    <div class="alert alert-info">Reports Panel - Navigation Test</div>
                </div>
                
                <!-- Notifications Panel -->
                <div class="panel-content" id="notifications-panel">
                    <div class="alert alert-info">Notifications Panel - Navigation Test</div>
                </div>
                
                <!-- Communications Panel -->
                <div class="panel-content" id="communications-panel">
                    <div class="alert alert-info">Communications Panel - Navigation Test</div>
                </div>
                
                <!-- Settings Panel -->
                <div class="panel-content" id="settings-panel">
                    <div class="alert alert-info">Settings Panel - Navigation Test</div>
                </div>
                
                <!-- System Panel -->
                <div class="panel-content" id="system-panel">
                    <div class="alert alert-info">System Panel - Navigation Test</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Dashboard initialization
        console.log('Admin dashboard script loading...');
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded - Admin Dashboard');
            const navLinks = document.querySelectorAll('.nav-link[data-panel]');
            const panelContents = document.querySelectorAll('.panel-content');
            const pageTitle = document.getElementById('pageTitle');
            
            console.log('Found nav links:', navLinks.length);
            console.log('Found panels:', panelContents.length);
            
            // Panel titles mapping
            const panelTitles = {
                'overview': 'Dashboard Overview',
                'applications': 'Applications Management',
                'students': 'Students Management',
                'programs': 'Programs Management',
                'application_forms': 'Application Forms',
                'users': 'Users Management',
                'payments': 'Payments Management',
                'reports': 'Reports & Analytics',
                'notifications': 'Notifications',
                'communications': 'Communications',
                'settings': 'System Settings',
                'system': 'System Information'
            };
            
            // Global function for external calls
            window.showPanel = function(panelName) {
                console.log('showPanel called with:', panelName);
                
                // Remove active class from all nav links
                navLinks.forEach(link => link.classList.remove('active'));
                
                // Hide all panels
                panelContents.forEach(panel => panel.classList.remove('active'));
                
                // Show selected panel
                const targetPanel = document.getElementById(panelName + '-panel');
                if (targetPanel) {
                    targetPanel.classList.add('active');
                    console.log('Panel activated:', panelName);
                } else {
                    console.error('Panel not found:', panelName);
                }
                
                // Update page title
                if (pageTitle && panelTitles[panelName]) {
                    pageTitle.textContent = panelTitles[panelName];
                }
                
                // Update nav link
                const targetLink = document.querySelector(`[data-panel="${panelName}"]`);
                if (targetLink) {
                    targetLink.classList.add('active');
                }
            };
            
            // Add click event listeners
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const panelName = this.getAttribute('data-panel');
                    console.log('Nav link clicked:', panelName);
                    window.showPanel(panelName);
                });
            });
            
            console.log('Navigation system initialized');
        });
    </script>
</body>
</html>