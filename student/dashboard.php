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
require_once '../models/Program.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../student-login.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

$security = new Security($database);
$userModel = new User($pdo);
$studentModel = new Student($pdo);
$applicationModel = new Application($pdo);
$programModel = new Program($pdo);

// Get current user data
$currentUser = $userModel->getById($_SESSION['user_id']);
$student = $studentModel->getByEmail($currentUser['email'] ?? '') ?: [];

// If no student record exists, create one from user data
if (!$student && $currentUser) {
    $studentData = [
        'user_id' => $currentUser['id'],
        'first_name' => $currentUser['first_name'],
        'last_name' => $currentUser['last_name'],
        'email' => $currentUser['email'],
        'phone' => $currentUser['phone'] ?? null,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    if ($studentModel->create($studentData)) {
        $student = $studentModel->getByEmail($currentUser['email']);
    }
}

// If still no student record, redirect to login
if (!$student) {
    header('Location: ../student-login.php');
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

// Get available programs for new applications
$availablePrograms = $programModel->getAll(['status' => 'active']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --sidebar-width: 260px;
            --header-height: 60px;
            --text-primary: #1a202c;
            --text-secondary: #4a5568;
            --text-muted: #718096;
            --bg-primary: #ffffff;
            --bg-secondary: #f7fafc;
            --bg-tertiary: #edf2f7;
            --border-color: #e2e8f0;
            --border-light: #f7fafc;
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --radius-sm: 4px;
            --radius-md: 6px;
            --radius-lg: 8px;
            --radius-xl: 12px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        /* Custom Scrollbars */
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
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            z-index: 1000;
            transition: all 0.3s ease;
            overflow-y: auto;
            box-shadow: var(--shadow-md);
        }
        
        .sidebar-header {
            padding: 1.5rem 1rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-title {
            font-size: 0.875rem;
            font-weight: 600;
            margin: 0;
            opacity: 0.9;
        }
        
        .sidebar-nav {
            padding: 0.5rem 0;
        }
        
        .nav-item {
            margin: 0.125rem 0.75rem;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.2s ease;
            border-radius: var(--radius-md);
            position: relative;
            font-weight: 500;
            font-size: 0.875rem;
        }
        
        .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(2px);
        }
        
        .nav-link.active {
            background-color: rgba(255,255,255,0.15);
            color: white;
        }
        
        .nav-link i {
            width: 16px;
            margin-right: 0.75rem;
            text-align: center;
            font-size: 0.875rem;
            flex-shrink: 0;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
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
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 999;
            border-bottom: 1px solid var(--border-color);
        }
        
        .page-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.75rem;
            border: 2px solid var(--bg-primary);
            box-shadow: var(--shadow-sm);
        }
        
        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-md);
        }
        
        /* Content Area */
        .content-wrapper {
            padding: 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Cards */
        .card {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 1rem;
            transition: all 0.2s ease;
            background: var(--bg-primary);
            overflow: hidden;
        }
        
        .card:hover {
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-light);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0 !important;
            padding: 1rem 1.25rem;
        }
        
        .card-title {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.875rem;
        }
        
        .card-body {
            padding: 1.25rem;
        }
        
        /* Statistics Cards */
        .stat-card {
            background: var(--bg-primary);
            color: var(--text-primary);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            transition: all 0.2s ease;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
            height: 100%;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-color);
        }
        
        .stat-card.bg-success::before {
            background: #10b981;
        }
        
        .stat-card.bg-warning::before {
            background: #f59e0b;
        }
        
        .stat-card.bg-info::before {
            background: #06b6d4;
        }
        
        .stat-card:hover {
            box-shadow: var(--shadow-md);
        }
        
        .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--text-primary);
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .stat-icon {
            font-size: 1.25rem;
            color: var(--primary-color);
            opacity: 0.8;
        }
        
        /* Panel Content */
        .panel-content {
            display: none;
        }
        
        .panel-content.active {
            display: block;
        }
        
        /* Responsive Design */
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
            
            .stat-number {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h1 class="sidebar-title">
                <i class="bi bi-mortarboard me-2"></i>Student Portal
            </h1>
        </div>
        
        <div class="sidebar-nav">
            <div class="nav-item">
                <a href="#" class="nav-link active" data-panel="overview">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="#" class="nav-link" data-panel="applications">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>My Applications</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="#" class="nav-link" data-panel="programs">
                    <i class="bi bi-book"></i>
                    <span>Available Programs</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="#" class="nav-link" data-panel="profile">
                    <i class="bi bi-person"></i>
                    <span>Profile</span>
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="top-header">
            <div class="header-left">
                <h5 class="page-title" id="pageTitle">Student Dashboard</h5>
            </div>
            
            <div class="header-right">
                <div class="user-dropdown">
                    <div class="user-avatar" data-bs-toggle="dropdown">
                        <?php echo strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)); ?>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header"><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" data-panel="profile"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Overview Panel -->
            <div class="panel-content active" id="overview-panel">
                <?php include 'panels/overview.php'; ?>
            </div>
            
            <!-- Applications Panel -->
            <div class="panel-content" id="applications-panel">
                <?php include 'panels/applications.php'; ?>
            </div>
            
            <!-- Programs Panel -->
            <div class="panel-content" id="programs-panel">
                <?php include 'panels/programs.php'; ?>
            </div>
            
            <!-- Profile Panel -->
            <div class="panel-content" id="profile-panel">
                <?php include 'panels/profile.php'; ?>
            </div>
        </div>
    </main>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav-link[data-panel]');
            const panelContents = document.querySelectorAll('.panel-content');
            const pageTitle = document.getElementById('pageTitle');
            
            const panelTitles = {
                'overview': 'Student Dashboard',
                'applications': 'My Applications',
                'programs': 'Available Programs',
                'profile': 'My Profile'
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
        });
    </script>
</body>
</html>