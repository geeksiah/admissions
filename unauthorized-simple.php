<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - <?php echo APP_NAME ?? 'Admissions System'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body text-center p-5">
                        <i class="bi bi-shield-exclamation display-1 text-danger mb-4"></i>
                        <h1 class="h3 mb-3">Access Denied</h1>
                        <p class="text-muted mb-4">
                            You don't have permission to access this resource.
                        </p>
                        
                        <div class="mb-4">
                            <p><strong>Current User:</strong><br>
                               <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] ?? 'Unknown'; ?></p>
                            <p><strong>Role:</strong><br>
                               <span class="badge bg-secondary"><?php echo ucwords(str_replace('_', ' ', $_SESSION['role'] ?? 'Unknown')); ?></span></p>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <a href="/dashboard" class="btn btn-primary">
                                <i class="bi bi-house me-2"></i>Go to Dashboard
                            </a>
                            <a href="/logout" class="btn btn-outline-danger">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
