<?php
// Note: Config and database should already be loaded by the calling file
// This header is included by dashboard files, not directly accessed

// Only initialize if not already done
if (!isset($database)) {
    require_once 'config/config.php';
    require_once 'config/database.php';
    $database = new Database();
}

// Only load security if needed and not already loaded
if (!isset($security) && class_exists('Security')) {
    $security = new Security($database);
}

// Check if user is logged in (if session functions are available)
if (function_exists('isLoggedIn') && !isLoggedIn()) {
    header('Location: /login');
    exit;
}

$currentUser = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'first_name' => $_SESSION['first_name'] ?? '',
    'last_name' => $_SESSION['last_name'] ?? '',
    'email' => $_SESSION['email'] ?? '',
    'role' => $_SESSION['role']
];

// Get navigation items based on user role
$navigationItems = [];
switch ($currentUser['role']) {
    case 'admin':
        $navigationItems = [
            ['name' => 'Dashboard', 'url' => '/dashboard', 'icon' => 'bi-speedometer2'],
            ['name' => 'Applications', 'url' => '/admin/applications', 'icon' => 'bi-file-text'],
            ['name' => 'Students', 'url' => '/admin/students', 'icon' => 'bi-people'],
            ['name' => 'Programs', 'url' => '/admin/programs', 'icon' => 'bi-mortarboard'],
            ['name' => 'Users', 'url' => '/admin/users', 'icon' => 'bi-person-gear'],
            ['name' => 'Reports', 'url' => '/admin/reports', 'icon' => 'bi-graph-up'],
            ['name' => 'Settings', 'url' => '/admin/settings', 'icon' => 'bi-gear']
        ];
        break;
    case 'admissions_officer':
        $navigationItems = [
            ['name' => 'Dashboard', 'url' => '/dashboard', 'icon' => 'bi-speedometer2'],
            ['name' => 'Applications', 'url' => '/admin/applications', 'icon' => 'bi-file-text'],
            ['name' => 'Students', 'url' => '/admin/students', 'icon' => 'bi-people'],
            ['name' => 'Programs', 'url' => '/admin/programs', 'icon' => 'bi-mortarboard'],
            ['name' => 'Reports', 'url' => '/admin/reports', 'icon' => 'bi-graph-up']
        ];
        break;
    case 'reviewer':
        $navigationItems = [
            ['name' => 'Dashboard', 'url' => '/dashboard', 'icon' => 'bi-speedometer2'],
            ['name' => 'My Reviews', 'url' => '/admin/applications', 'icon' => 'bi-clipboard-check'],
            ['name' => 'Applications', 'url' => '/admin/applications', 'icon' => 'bi-file-text']
        ];
        break;
    case 'student':
        $navigationItems = [
            ['name' => 'Dashboard', 'url' => '/dashboard', 'icon' => 'bi-speedometer2'],
            ['name' => 'My Applications', 'url' => '/student/applications', 'icon' => 'bi-file-text'],
            ['name' => 'Apply Now', 'url' => '/student/apply', 'icon' => 'bi-plus-circle'],
            ['name' => 'Programs', 'url' => '/student/programs', 'icon' => 'bi-mortarboard']
        ];
        break;
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --sidebar-width: 250px;
            --header-height: 60px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            z-index: 1000;
            transition: all 0.3s ease;
            overflow-y: auto;
        }
        
        .sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar-header {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .sidebar-header h4 {
            margin: 0;
            font-weight: 600;
            transition: opacity 0.3s ease;
        }
        
        .sidebar.collapsed .sidebar-header h4 {
            opacity: 0;
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
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 0;
        }
        
        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border-right: 3px solid white;
        }
        
        .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
            text-align: center;
        }
        
        .sidebar.collapsed .nav-link span {
            opacity: 0;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        .main-content.expanded {
            margin-left: 70px;
        }
        
        .top-header {
            background: white;
            height: var(--header-height);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        .header-left {
            display: flex;
            align-items: center;
        }
        
        .sidebar-toggle {
            background: none;
            border: none;
            font-size: 1.25rem;
            color: #6c757d;
            cursor: pointer;
            margin-right: 1rem;
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
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            cursor: pointer;
        }
        
        .dropdown-menu {
            min-width: 200px;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .content-wrapper {
            padding: 2rem;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        
        .breadcrumb {
            background: none;
            padding: 0;
            margin: 0.5rem 0 0 0;
        }
        
        .breadcrumb-item + .breadcrumb-item::before {
            content: ">";
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #e9ecef;
            border-radius: 10px 10px 0 0 !important;
            padding: 1.25rem;
        }
        
        .card-title {
            margin: 0;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 6px;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        }
        
        .alert {
            border: none;
            border-radius: 8px;
        }
        
        .table {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
        }
        
        .badge {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
        }
        
        .status-submitted { background-color: #6c757d; }
        .status-under-review { background-color: #fd7e14; }
        .status-approved { background-color: #198754; }
        .status-rejected { background-color: #dc3545; }
        .status-waitlisted { background-color: #0dcaf0; }
        
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
            <h4><i class="bi bi-mortarboard-fill me-2"></i><span>Admissions</span></h4>
        </div>
        
        <ul class="nav flex-column sidebar-nav">
            <?php foreach ($navigationItems as $item): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], $item['url']) !== false) ? 'active' : ''; ?>" 
                       href="<?php echo $item['url']; ?>">
                        <i class="<?php echo $item['icon']; ?>"></i>
                        <span><?php echo $item['name']; ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
                <h5 class="mb-0"><?php echo isset($pageTitle) ? $pageTitle : 'Dashboard'; ?></h5>
            </div>
            
            <div class="header-right">
                <div class="user-dropdown">
                    <div class="user-avatar" data-bs-toggle="dropdown">
                        <?php echo strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)); ?>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header"><?php echo $currentUser['first_name'] . ' ' . $currentUser['last_name']; ?></h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/profile"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="/admin/settings"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/logout"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <?php if (isset($pageTitle)): ?>
                <div class="page-header">
                    <h1 class="page-title"><?php echo $pageTitle; ?></h1>
                    <?php if (isset($breadcrumbs)): ?>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <?php foreach ($breadcrumbs as $index => $crumb): ?>
                                    <?php if ($index === count($breadcrumbs) - 1): ?>
                                        <li class="breadcrumb-item active"><?php echo $crumb['name']; ?></li>
                                    <?php else: ?>
                                        <li class="breadcrumb-item">
                                            <a href="<?php echo $crumb['url']; ?>"><?php echo $crumb['name']; ?></a>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ol>
                        </nav>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
