<?php
/**
 * Comprehensive Admissions Management System
 * Web-Based Installer
 */

// Prevent access if already installed
if (file_exists('../config/installed.lock')) {
    header('Location: ../index.php');
    exit;
}

// Start session for installer
session_start();

// Include installer functions
require_once 'installer-functions.php';

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 2:
            // Database configuration
            $dbHost = $_POST['db_host'] ?? 'localhost';
            $dbName = $_POST['db_name'] ?? '';
            $dbUser = $_POST['db_user'] ?? '';
            $dbPass = $_POST['db_pass'] ?? '';
            
            if (testDatabaseConnection($dbHost, $dbName, $dbUser, $dbPass)) {
                $_SESSION['db_config'] = [
                    'host' => $dbHost,
                    'name' => $dbName,
                    'user' => $dbUser,
                    'pass' => $dbPass
                ];
                header('Location: ?step=3');
                exit;
            } else {
                $error = 'Database connection failed. Please check your credentials.';
            }
            break;
            
        case 3:
            // Application configuration
            $_SESSION['app_config'] = [
                'app_name' => $_POST['app_name'] ?? '',
                'app_url' => $_POST['app_url'] ?? '',
                'timezone' => $_POST['timezone'] ?? 'UTC',
                'currency' => $_POST['currency'] ?? 'USD',
                'language' => $_POST['language'] ?? 'en'
            ];
            header('Location: ?step=4');
            exit;
            break;
            
        case 4:
            // Admin account creation
            $adminData = [
                'full_name' => $_POST['admin_name'] ?? '',
                'email' => $_POST['admin_email'] ?? '',
                'password' => $_POST['admin_password'] ?? '',
                'phone' => $_POST['admin_phone'] ?? ''
            ];
            
            if (validateAdminData($adminData)) {
                $_SESSION['admin_data'] = $adminData;
                header('Location: ?step=5');
                exit;
            } else {
                $error = 'Please fill in all required fields.';
            }
            break;
            
        case 5:
            // Email configuration (optional)
            $_SESSION['email_config'] = [
                'smtp_host' => $_POST['smtp_host'] ?? '',
                'smtp_port' => $_POST['smtp_port'] ?? '587',
                'smtp_user' => $_POST['smtp_user'] ?? '',
                'smtp_pass' => $_POST['smtp_pass'] ?? '',
                'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
                'from_email' => $_POST['from_email'] ?? '',
                'from_name' => $_POST['from_name'] ?? ''
            ];
            header('Location: ?step=6');
            exit;
            break;
            
        case 6:
            // Installation
            if (performInstallation()) {
                header('Location: ?step=7');
                exit;
            } else {
                $logContent = getInstallationLog();
                $error = 'Installation failed. Last error from log: ' . substr($logContent, -500);
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install - Admissions Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .installer-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .installer-header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .installer-body {
            padding: 2rem;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            color: white;
        }
        .step.active {
            background: #2563eb;
        }
        .step.completed {
            background: #10b981;
        }
        .step.pending {
            background: #e5e7eb;
            color: #6b7280;
        }
        .step-line {
            width: 50px;
            height: 2px;
            background: #e5e7eb;
            margin-top: 19px;
        }
        .step-line.completed {
            background: #10b981;
        }
        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }
        .btn-primary {
            background: #2563eb;
            border-color: #2563eb;
        }
        .btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="installer-container">
                    <div class="installer-header">
                        <h1 class="h3 mb-0">
                            <i class="bi bi-gear-fill me-2"></i>
                            Admissions Management System
                        </h1>
                        <p class="mb-0 mt-2">Installation Wizard</p>
                    </div>
                    
                    <div class="installer-body">
                        <!-- Step Indicator -->
                        <div class="step-indicator">
                            <?php for ($i = 1; $i <= 7; $i++): ?>
                                <div class="step <?php echo $i < $step ? 'completed' : ($i == $step ? 'active' : 'pending'); ?>">
                                    <?php echo $i < $step ? '<i class="bi bi-check"></i>' : $i; ?>
                                </div>
                                <?php if ($i < 7): ?>
                                    <div class="step-line <?php echo $i < $step ? 'completed' : ''; ?>"></div>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>

                        <!-- Error/Success Messages -->
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Step Content -->
                        <?php
                        switch ($step) {
                            case 1:
                                include 'steps/step1.php';
                                break;
                            case 2:
                                include 'steps/step2.php';
                                break;
                            case 3:
                                include 'steps/step3.php';
                                break;
                            case 4:
                                include 'steps/step4.php';
                                break;
                            case 5:
                                include 'steps/step5.php';
                                break;
                            case 6:
                                include 'steps/step6.php';
                                break;
                            case 7:
                                include 'steps/step7.php';
                                break;
                            default:
                                include 'steps/step1.php';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
