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
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --border-color: #e2e8f0;
            --border-light: #f1f5f9;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            overflow-x: hidden;
            line-height: 1.6;
            font-size: 14px;
        }
        
        /* Custom Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--bg-tertiary);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--text-muted);
            border-radius: 4px;
            transition: background 0.2s ease;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }
        
        /* Firefox Scrollbar */
        * {
            scrollbar-width: thin;
            scrollbar-color: var(--text-muted) var(--bg-tertiary);
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: var(--bg-primary);
            color: var(--text-primary);
            z-index: 1000;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            border-right: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
        }
        
        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid var(--border-light);
            text-align: center;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .sidebar-logo {
            width: 50px;
            height: 50px;
            border-radius: var(--radius-lg);
            margin: 0 auto 1rem;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .sidebar-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: var(--radius-lg);
        }
        
        .sidebar-title {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            opacity: 0.95;
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .nav-item {
            margin: 0.125rem 0.75rem;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: var(--radius-md);
            position: relative;
            font-weight: 500;
            font-size: 0.875rem;
        }
        
        .nav-link:hover {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
            transform: translateX(2px);
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: var(--shadow-md);
        }
        
        .nav-link.active:hover {
            transform: translateX(2px);
            box-shadow: var(--shadow-lg);
        }
        
        .nav-link i {
            width: 18px;
            margin-right: 0.75rem;
            text-align: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .nav-link span {
            font-weight: 500;
            letter-spacing: -0.01em;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background-color: var(--bg-secondary);
        }
        
        /* Header */
        .top-header {
            background: var(--bg-primary);
            height: var(--header-height);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 999;
            border-bottom: 1px solid var(--border-color);
            backdrop-filter: blur(10px);
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .sidebar-toggle {
            background: none;
            border: none;
            font-size: 1.25rem;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--radius-md);
            transition: all 0.2s ease;
            display: none;
        }
        
        .sidebar-toggle:hover {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            letter-spacing: -0.02em;
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
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            border: 2px solid var(--bg-primary);
            box-shadow: var(--shadow-sm);
        }
        
        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-md);
        }
        
        /* Content Area */
        .content-wrapper {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Cards */
        .card {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--bg-primary);
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
            border-color: var(--border-color);
        }
        
        .card-header {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-light);
            border-radius: var(--radius-xl) var(--radius-xl) 0 0 !important;
            padding: 1.25rem 1.5rem;
        }
        
        .card-title {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1rem;
            letter-spacing: -0.01em;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Statistics Cards */
        .stat-card {
            background: var(--bg-primary);
            color: var(--text-primary);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-card.bg-primary::before {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }
        
        .stat-card.bg-success::before {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        
        .stat-card.bg-warning::before {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        
        .stat-card.bg-info::before {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
        }
        
        .stat-number {
            font-size: 2.25rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            letter-spacing: -0.02em;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .stat-icon {
            font-size: 1.5rem;
            color: var(--primary-color);
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
        
        /* Tables */
        .table {
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        
        .table thead th {
            background-color: var(--bg-tertiary);
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            color: var(--text-primary);
            padding: 1rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .table tbody td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
            font-size: 0.875rem;
        }
        
        .table tbody tr:hover {
            background-color: var(--bg-tertiary);
        }
        
        /* Buttons */
        .btn {
            border-radius: var(--radius-md);
            font-weight: 500;
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            transition: all 0.2s ease;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: var(--shadow-sm);
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-outline-primary {
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-1px);
        }
        
        /* Badges */
        .badge {
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-sm);
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .content-wrapper {
                padding: 1.5rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
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
            
            .content-wrapper {
                padding: 1rem;
            }
            
            .sidebar-toggle {
                display: block;
            }
            
            .page-title {
                font-size: 1.25rem;
            }
            
            .top-header {
                padding: 0 1rem;
            }
            
            .stat-number {
                font-size: 1.75rem;
            }
            
            .card-body {
                padding: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .content-wrapper {
                padding: 0.75rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .stat-number {
                font-size: 1.5rem;
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
            
            // Function to show panel
            function showPanel(panelName) {
                // Remove active class from all nav links
                navLinks.forEach(nl => nl.classList.remove('active'));
                
                // Hide all panel contents
                panelContents.forEach(panel => panel.classList.remove('active'));
                
                // Show target panel
                const targetPanelElement = document.getElementById(panelName + '-panel');
                if (targetPanelElement) {
                    targetPanelElement.classList.add('active');
                }
                
                // Add active class to corresponding nav link
                const targetNavLink = document.querySelector(`[data-panel="${panelName}"]`);
                if (targetNavLink) {
                    targetNavLink.classList.add('active');
                }
                
                // Update page title
                if (panelTitles[panelName]) {
                    pageTitle.textContent = panelTitles[panelName];
                }
                
                // Update URL without page reload
                const url = new URL(window.location);
                url.searchParams.set('panel', panelName);
                window.history.pushState({}, '', url);
            }
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetPanel = this.getAttribute('data-panel');
                    showPanel(targetPanel);
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
                showPanel(panel);
            });
            
            // Initialize panel based on URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            const initialPanel = urlParams.get('panel') || 'overview';
            showPanel(initialPanel);
    </script>
</body>
</html>