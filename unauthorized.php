<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/SecurityManager.php';

$database = new Database();
$security = new SecurityManager($database);

// If user is not authenticated, redirect to appropriate login
if (!$security->isAuthenticated()) {
    // Check if this is a student-related request
    $currentPath = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($currentPath, 'student') !== false) {
        header('Location: student-login.php');
    } else {
        header('Location: login.php');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .unauthorized-container {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 90%;
        }
        .icon {
            font-size: 4rem;
            color: #ffc107;
            margin-bottom: 1rem;
        }
        .error-message {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 1rem;
        }
        .error-description {
            color: #666;
            margin-bottom: 2rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 500;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        .btn-secondary {
            border: 2px solid #667eea;
            color: #667eea;
            background: transparent;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 500;
            margin-left: 1rem;
        }
        .btn-secondary:hover {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <div class="unauthorized-container">
        <div class="icon">
            <i class="bi bi-shield-exclamation"></i>
        </div>
        <div class="error-message">Access Denied</div>
        <div class="error-description">
            You don't have the required permissions to access this page. Please contact your administrator if you believe this is an error.
        </div>
        <div>
            <a href="/" class="btn btn-primary">
                <i class="bi bi-house me-2"></i>Go Home
            </a>
            <a href="logout.php" class="btn btn-secondary">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
            </a>
        </div>
    </div>
</body>
</html>