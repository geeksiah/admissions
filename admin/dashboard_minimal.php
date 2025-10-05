<?php
/**
 * Minimal Dashboard - Working version with authentication
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --radius-sm: 4px;
            --radius-md: 6px;
            --radius-lg: 8px;
            --radius-xl: 12px;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            overflow-x: hidden;
            line-height: 1.6;
            font-size: 14px;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            z-index: 1000;
            transition: transform 0.3s ease;
            overflow-y: hidden;
            box-shadow: var(--shadow-lg);
        }
        
        .sidebar-header {
            padding: 1.5rem 1rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-logo {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            margin: 0 auto 0.75rem;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
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
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: var(--radius-md);
            transition: all 0.2s ease;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
            color: white;
        }
        
        .nav-link.active {
            background-color: rgba(255,255,255,0.2);
            color: white;
            font-weight: 600;
        }
        
        .nav-link i {
            width: 16px;
            margin-right: 0.75rem;
            text-align: center;
            font-size: 0.875rem;
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
            max-width: 1400px;
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
        }
        
        .card:hover {
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-light);
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
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            transition: all 0.2s ease;
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
            height: 3px;
        }
        
        .stat-card.bg-primary::before {
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
            transform: translateY(-2px);
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
            opacity: 0.6;
        }
        
        /* Panel Content */
        .panel-content {
            display: none !important;
        }
        
        .panel-content.active {
            display: block !important;
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
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <h4 class="sidebar-title"><?php echo APP_NAME; ?></h4>
        </div>
        
        <ul class="nav flex-column sidebar-nav">
            <li class="nav-item">
                <a class="nav-link active" data-panel="overview">
                    <i class="bi bi-speedometer2"></i>
                    <span>Overview</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-panel="applications">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Applications</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-panel="students">
                    <i class="bi bi-people"></i>
                    <span>Students</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-panel="programs">
                    <i class="bi bi-mortarboard"></i>
                    <span>Programs</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-panel="settings">
                    <i class="bi bi-gear"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <h5 class="page-title" id="pageTitle">Dashboard Overview</h5>
            </div>
            
            <div class="header-right">
                <div class="user-dropdown">
                    <div class="user-avatar" title="<?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>">
                        <?php echo strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Overview Panel -->
            <div class="panel-content active" id="overview-panel">
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Welcome, <?php echo htmlspecialchars($currentUser['first_name']); ?>!</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">This is a minimal working dashboard. Navigation is functional and authentication is working.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="stat-card bg-primary">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-number">0</div>
                                    <div class="stat-label">Total Applications</div>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-file-earmark-text"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card bg-success">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-number">0</div>
                                    <div class="stat-label">Total Students</div>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-people"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card bg-warning">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-number">0</div>
                                    <div class="stat-label">Active Programs</div>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-mortarboard"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card bg-info">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-number">0</div>
                                    <div class="stat-label">Pending Payments</div>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-credit-card"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Applications Panel -->
            <div class="panel-content" id="applications-panel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Applications</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Applications panel content will go here.</p>
                    </div>
                </div>
            </div>
            
            <!-- Students Panel -->
            <div class="panel-content" id="students-panel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Students</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Students panel content will go here.</p>
                    </div>
                </div>
            </div>
            
            <!-- Programs Panel -->
            <div class="panel-content" id="programs-panel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Programs</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Programs panel content will go here.</p>
                    </div>
                </div>
            </div>
            
            <!-- Settings Panel -->
            <div class="panel-content" id="settings-panel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Settings</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Settings panel content will go here.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        console.log('Minimal dashboard script starting...');
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded - Minimal dashboard initializing navigation...');
            
            const navLinks = document.querySelectorAll('.sidebar-nav .nav-link[data-panel]');
            const panelContents = document.querySelectorAll('.panel-content');
            const pageTitle = document.getElementById('pageTitle');
            
            console.log('Found nav links:', navLinks.length);
            console.log('Found panel contents:', panelContents.length);
            
            // Debug: Log all nav links found
            navLinks.forEach((link, index) => {
                console.log(`Nav link ${index}:`, link.getAttribute('data-panel'), link.textContent.trim());
            });
            
            const panelTitles = {
                'overview': 'Dashboard Overview',
                'applications': 'Manage Applications',
                'students': 'Manage Students',
                'programs': 'Manage Programs',
                'settings': 'System Settings'
            };
            
            // Function to show panel (make it global)
            window.showPanel = function(panelName) {
                console.log('Switching to panel:', panelName);
                
                // Remove active class from all nav links
                navLinks.forEach(nl => nl.classList.remove('active'));
                
                // Hide all panel contents
                panelContents.forEach(panel => {
                    panel.classList.remove('active');
                    panel.style.display = 'none';
                });
                
                // Show target panel
                const targetPanelElement = document.getElementById(panelName + '-panel');
                if (targetPanelElement) {
                    console.log('Found target panel element');
                    targetPanelElement.classList.add('active');
                    targetPanelElement.style.display = 'block';
                } else {
                    console.error('Panel element not found:', panelName + '-panel');
                }
                
                // Add active class to corresponding nav link
                const targetNavLink = document.querySelector(`.sidebar-nav [data-panel="${panelName}"]`);
                if (targetNavLink) {
                    targetNavLink.classList.add('active');
                } else {
                    console.error('Nav link not found for panel:', panelName);
                }
                
                // Update page title
                if (panelTitles[panelName]) {
                    pageTitle.textContent = panelTitles[panelName];
                }
                
                // Update URL without page reload
                const url = new URL(window.location);
                url.searchParams.set('panel', panelName);
                window.history.pushState({}, '', url);
            };
            
            // Add click event listeners to navigation links
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetPanel = this.getAttribute('data-panel');
                    console.log('Navigation clicked:', targetPanel);
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
            console.log('Initializing with panel:', initialPanel);
            showPanel(initialPanel);
            
            console.log('Navigation initialization complete');
        });
        
        console.log('Minimal dashboard script loaded');
    </script>
</body>
</html>
