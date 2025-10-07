<?php
// Ultra-simple admin dashboard - no dependencies, no database
session_start();

// Basic authentication check
if (!isset($_SESSION['user_id'])) {
    echo "<h1>Not Authenticated</h1>";
    echo "<p>Please <a href='/login'>login</a> first.</p>";
    exit;
}

// Get user info from session
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Admin';
$firstName = $_SESSION['first_name'] ?? 'Admin';
$lastName = $_SESSION['last_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'admin';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">Admissions Management</a>
                <div class="navbar-nav ms-auto">
                    <span class="navbar-text me-3">Welcome, <?php echo htmlspecialchars($firstName . ' ' . $lastName); ?></span>
                    <a class="btn btn-outline-light btn-sm" href="/logout">Logout</a>
                </div>
            </div>
        </nav>
        
        <div class="container-fluid mt-4">
            <h1>Admin Dashboard</h1>
            <p>User ID: <?php echo $userId; ?></p>
            <p>Role: <?php echo $role; ?></p>
            
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
                    <div class="col-md-3 mb-2">
                        <a href="/admin/applications" class="btn btn-primary w-100">Applications</a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="/admin/students" class="btn btn-success w-100">Students</a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="/admin/programs" class="btn btn-info w-100">Programs</a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="/admin/users" class="btn btn-warning w-100">Users</a>
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
                        <li>PHP Version: <?php echo phpversion(); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
