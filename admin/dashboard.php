<?php
/**
 * Unified Admin Dashboard - Single Page Application
 * Professional UI with panel-based navigation
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/SecurityManager.php';

$database = new Database();
$security = new SecurityManager($database);

// Require admin authentication
$security->requireAdmin();

// Get current user info
$currentUser = $security->getCurrentUser();

// Initialize models
require_once '../models/User.php';
require_once '../models/Student.php';
require_once '../models/Application.php';
require_once '../models/Program.php';
require_once '../models/Payment.php';
require_once '../models/Report.php';

$pdo = $database->getConnection();
$userModel = new User($pdo);
$studentModel = new Student($pdo);
$applicationModel = new Application($pdo);
$programModel = new Program($pdo);
$paymentModel = new Payment($pdo);
$reportModel = new Report($pdo);

// Get dashboard data
try {
    $stats = $reportModel->getDashboardStats();
    $recentApplications = $applicationModel->getRecent(5);
    $recentStudents = $studentModel->getRecent(5);
    $activePrograms = $programModel->getActive();
    $popularPrograms = $programModel->getPopular(5);
} catch (Exception $e) {
    $stats = [
        'total_applications' => 0,
        'pending_applications' => 0,
        'approved_applications' => 0,
        'rejected_applications' => 0,
        'under_review_applications' => 0
    ];
    $recentApplications = [];
    $recentStudents = [];
    $activePrograms = [];
    $popularPrograms = [];
}

// Get branding settings
$brandingSettings = [];
try {
    require_once '../models/SystemConfig.php';
    $configModel = new SystemConfig($pdo);
    $brandingSettings = $configModel->getByCategory('branding');
} catch (Exception $e) {
    // Use defaults
    $brandingSettings = [
        'logo_url' => null,
        'primary_color' => '#667eea',
        'secondary_color' => '#764ba2'
    ];
}

// Determine current panel
$panel = $_GET['panel'] ?? 'overview';
$validPanels = [
    'overview', 'applications', 'students', 'programs', 'users', 
    'reports', 'settings', 'payments', 'communications', 'system'
];

if (!in_array($panel, $validPanels)) {
    $panel = 'overview';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Admin Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-color: <?php echo $brandingSettings['primary_color'] ?? '#667eea'; ?>;
            --secondary-color: <?php echo $brandingSettings['secondary_color'] ?? '#764ba2'; ?>;
            --sidebar-width: 280px;
            --header-height: 70px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8fafc;
            color: #334155;
            overflow-x: hidden;
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            z-index: 1000;
            transition: all 0.3s ease;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar-logo {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            margin: 0 auto 1rem;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .sidebar-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 12px;
        }
        
        .sidebar-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .nav-item {
            margin: 0.25rem 0;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.5rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 0;
            position: relative;
        }
        
        .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-link.active {
            background-color: rgba(255,255,255,0.15);
            color: white;
            border-right: 3px solid white;
        }
        
        .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
            text-align: center;
            font-size: 1.1rem;
        }
        
        .nav-link span {
            font-weight: 500;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        /* Header */
        .top-header {
            background: white;
            height: var(--header-height);
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 999;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .header-left {
            display: flex;
            align-items: center;
        }
        
        .sidebar-toggle {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #64748b;
            cursor: pointer;
            margin-right: 1rem;
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .sidebar-toggle:hover {
            background-color: #f1f5f9;
            color: var(--primary-color);
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-dropdown {
            position: relative;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        /* Content Area */
        .content-wrapper {
            padding: 2rem;
        }
        
        /* Cards */
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #f1f5f9;
            border-radius: 16px 16px 0 0 !important;
            padding: 1.5rem;
        }
        
        .card-title {
            margin: 0;
            font-weight: 600;
            color: #1e293b;
            font-size: 1.1rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Statistics Cards */
        .stat-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .stat-card.bg-success {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        
        .stat-card.bg-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        
        .stat-card.bg-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        
        .stat-card.bg-info {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            font-weight: 500;
        }
        
        .stat-icon {
            font-size: 2rem;
            opacity: 0.8;
        }
        
        /* Tables */
        .table {
            border-radius: 12px;
            overflow: hidden;
        }
        
        .table thead th {
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            color: #475569;
            padding: 1rem;
        }
        
        .table tbody td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
        }
        
        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        /* Responsive */
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
            
            .content-wrapper {
                padding: 1rem;
            }
        }
        
        /* Loading States */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        /* Panel Content */
        .panel-content {
            display: none;
        }
        
        .panel-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <?php if (!empty($brandingSettings['logo_url'])): ?>
                    <img src="<?php echo htmlspecialchars($brandingSettings['logo_url']); ?>" alt="Logo">
                <?php else: ?>
                    <i class="bi bi-mortarboard-fill"></i>
                <?php endif; ?>
            </div>
            <h4 class="sidebar-title"><?php echo APP_NAME; ?></h4>
        </div>
        
        <ul class="nav flex-column sidebar-nav">
            <li class="nav-item">
                <a class="nav-link <?php echo $panel === 'overview' ? 'active' : ''; ?>" href="#" data-panel="overview">
                    <i class="bi bi-speedometer2"></i>
                    <span>Overview</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $panel === 'applications' ? 'active' : ''; ?>" href="#" data-panel="applications">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Applications</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $panel === 'students' ? 'active' : ''; ?>" href="#" data-panel="students">
                    <i class="bi bi-people"></i>
                    <span>Students</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $panel === 'programs' ? 'active' : ''; ?>" href="#" data-panel="programs">
                    <i class="bi bi-mortarboard"></i>
                    <span>Programs</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $panel === 'users' ? 'active' : ''; ?>" href="#" data-panel="users">
                    <i class="bi bi-person-gear"></i>
                    <span>Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $panel === 'payments' ? 'active' : ''; ?>" href="#" data-panel="payments">
                    <i class="bi bi-credit-card"></i>
                    <span>Payments</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $panel === 'reports' ? 'active' : ''; ?>" href="#" data-panel="reports">
                    <i class="bi bi-graph-up"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $panel === 'communications' ? 'active' : ''; ?>" href="#" data-panel="communications">
                    <i class="bi bi-chat-dots"></i>
                    <span>Communications</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $panel === 'settings' ? 'active' : ''; ?>" href="#" data-panel="settings">
                    <i class="bi bi-gear"></i>
                    <span>Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $panel === 'system' ? 'active' : ''; ?>" href="#" data-panel="system">
                    <i class="bi bi-shield-check"></i>
                    <span>System</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
                <h5 class="page-title" id="pageTitle">Dashboard Overview</h5>
            </div>
            
            <div class="header-right">
                <div class="user-dropdown">
                    <div class="user-avatar" data-bs-toggle="dropdown">
                        <?php echo strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)); ?>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header"><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" data-panel="settings"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="#" data-panel="settings"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Overview Panel -->
            <div class="panel-content <?php echo $panel === 'overview' ? 'active' : ''; ?>" id="overview-panel">
                <?php include 'panels/overview.php'; ?>
            </div>
            
            <!-- Applications Panel -->
            <div class="panel-content <?php echo $panel === 'applications' ? 'active' : ''; ?>" id="applications-panel">
                <?php include 'panels/applications.php'; ?>
            </div>
            
            <!-- Students Panel -->
            <div class="panel-content <?php echo $panel === 'students' ? 'active' : ''; ?>" id="students-panel">
                <?php include 'panels/students.php'; ?>
            </div>
            
            <!-- Programs Panel -->
            <div class="panel-content <?php echo $panel === 'programs' ? 'active' : ''; ?>" id="programs-panel">
                <?php include 'panels/programs.php'; ?>
            </div>
            
            <!-- Users Panel -->
            <div class="panel-content <?php echo $panel === 'users' ? 'active' : ''; ?>" id="users-panel">
                <?php include 'panels/users.php'; ?>
            </div>
            
            <!-- Payments Panel -->
            <div class="panel-content <?php echo $panel === 'payments' ? 'active' : ''; ?>" id="payments-panel">
                <?php include 'panels/payments.php'; ?>
            </div>
            
            <!-- Reports Panel -->
            <div class="panel-content <?php echo $panel === 'reports' ? 'active' : ''; ?>" id="reports-panel">
                <?php include 'panels/reports.php'; ?>
            </div>
            
            <!-- Communications Panel -->
            <div class="panel-content <?php echo $panel === 'communications' ? 'active' : ''; ?>" id="communications-panel">
                <?php include 'panels/communications.php'; ?>
            </div>
            
            <!-- Settings Panel -->
            <div class="panel-content <?php echo $panel === 'settings' ? 'active' : ''; ?>" id="settings-panel">
                <?php include 'panels/settings.php'; ?>
            </div>
            
            <!-- System Panel -->
            <div class="panel-content <?php echo $panel === 'system' ? 'active' : ''; ?>" id="system-panel">
                <?php include 'panels/system.php'; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Panel Navigation
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav-link[data-panel]');
            const panelContents = document.querySelectorAll('.panel-content');
            const pageTitle = document.getElementById('pageTitle');
            
            // Panel titles mapping
            const panelTitles = {
                'overview': 'Dashboard Overview',
                'applications': 'Application Management',
                'students': 'Student Management',
                'programs': 'Program Management',
                'users': 'User Management',
                'payments': 'Payment Management',
                'reports': 'Reports & Analytics',
                'communications': 'Communications',
                'settings': 'Settings',
                'system': 'System Administration'
            };
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const targetPanel = this.getAttribute('data-panel');
                    
                    // Update URL without page reload
                    const url = new URL(window.location);
                    url.searchParams.set('panel', targetPanel);
                    window.history.pushState({}, '', url);
                    
                    // Remove active class from all nav links
                    navLinks.forEach(nl => nl.classList.remove('active'));
                    
                    // Add active class to clicked link
                    this.classList.add('active');
                    
                    // Hide all panel contents
                    panelContents.forEach(panel => panel.classList.remove('active'));
                    
                    // Show target panel
                    const targetPanelElement = document.getElementById(targetPanel + '-panel');
                    if (targetPanelElement) {
                        targetPanelElement.classList.add('active');
                    }
                    
                    // Update page title
                    if (panelTitles[targetPanel]) {
                        pageTitle.textContent = panelTitles[targetPanel];
                    }
                });
            });
            
            // Sidebar toggle for mobile
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
            });
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                        sidebar.classList.remove('show');
                    }
                }
            });
        });
        
        // Handle browser back/forward buttons
        window.addEventListener('popstate', function(e) {
            const urlParams = new URLSearchParams(window.location.search);
            const panel = urlParams.get('panel') || 'overview';
            
            // Update navigation
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('data-panel') === panel) {
                    link.classList.add('active');
                }
            });
            
            // Update content
            document.querySelectorAll('.panel-content').forEach(content => {
                content.classList.remove('active');
            });
            
            const targetPanel = document.getElementById(panel + '-panel');
            if (targetPanel) {
                targetPanel.classList.add('active');
            }
            
            // Update title
            const panelTitles = {
                'overview': 'Dashboard Overview',
                'applications': 'Application Management',
                'students': 'Student Management',
                'programs': 'Program Management',
                'users': 'User Management',
                'payments': 'Payment Management',
                'reports': 'Reports & Analytics',
                'communications': 'Communications',
                'settings': 'Settings',
                'system': 'System Administration'
            };
            
            if (panelTitles[panel]) {
                document.getElementById('pageTitle').textContent = panelTitles[panel];
            }
        });
    </script>
</body>
</html>