<?php
// Ultra-minimal admin dashboard - no dependencies
session_start();

// Simple authentication check
if (!isset($_SESSION['user_id'])) {
    echo "<h1>Not Logged In</h1>";
    echo "<p><a href='/login'>Go to Login</a></p>";
    exit;
}

// Simple role check
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'super_admin', 'admissions_officer', 'reviewer'])) {
    echo "<h1>Access Denied</h1>";
    echo "<p>Your role: $role</p>";
    echo "<p><a href='/logout'>Logout</a></p>";
    exit;
}

// Get user info
$username = $_SESSION['username'] ?? 'Admin';
$firstName = $_SESSION['first_name'] ?? 'Admin';
$lastName = $_SESSION['last_name'] ?? 'User';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 bg-primary text-white p-3" style="min-height: 100vh;">
                <h4>Admin Panel</h4>
                <hr>
                <ul class="nav flex-column">
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="/admin/dashboard">Dashboard</a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="/admin/applications">Applications</a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="/admin/students">Students</a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="/admin/programs">Programs</a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="/admin/users">Users</a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="/logout">Logout</a>
                    </li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 p-4">
                <h1>Admin Dashboard</h1>
                <p>Welcome, <?php echo htmlspecialchars($firstName . ' ' . $lastName); ?>!</p>
                <p>Role: <?php echo htmlspecialchars($role); ?></p>
                
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3>0</h3>
                                <p>Applications</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3>0</h3>
                                <p>Students</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3>0</h3>
                                <p>Programs</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3>0</h3>
                                <p>Users</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h3>Quick Actions</h3>
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <a href="/admin/applications" class="btn btn-primary w-100">View Applications</a>
                        </div>
                        <div class="col-md-4 mb-2">
                            <a href="/admin/students" class="btn btn-success w-100">Manage Students</a>
                        </div>
                        <div class="col-md-4 mb-2">
                            <a href="/admin/programs" class="btn btn-info w-100">Programs</a>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <div class="alert alert-success">
                        <h4>✅ System Status</h4>
                        <ul>
                            <li>Authentication: Working</li>
                            <li>Session: Active</li>
                            <li>Dashboard: Loaded</li>
                            <li>Navigation: Ready</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>