<?php
/**
 * License Activation Page
 */

session_start();
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/LicenseManager.php';
require_once 'classes/AntiTampering.php';

// Initialize license manager
$database = new Database();
$licenseManager = new LicenseManager($database);
$antiTampering = new AntiTampering($database);

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $licenseKey = sanitizeInput($_POST['license_key']);
        
        if (empty($licenseKey)) {
            $message = 'Please enter a license key';
            $messageType = 'danger';
        } else {
            $result = $licenseManager->activateLicense($licenseKey);
            
            if ($result['success']) {
                $message = $result['message'];
                $messageType = 'success';
                
                // Redirect to login after successful activation
                header('refresh:3;url=' . APP_URL . '/login.php');
            } else {
                $message = $result['error'];
                $messageType = 'danger';
            }
        }
    } else {
        $message = 'Invalid security token';
        $messageType = 'danger';
    }
}

// Check if license is already valid
$licenseStatus = $licenseManager->getLicenseStatus();
if ($licenseStatus && $licenseStatus['status'] === 'valid') {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Activation - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .activation-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .logo i {
            font-size: 2rem;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="activation-card p-5">
                    <div class="text-center mb-4">
                        <div class="logo">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h2 class="fw-bold text-dark">License Activation</h2>
                        <p class="text-muted">Activate your <?php echo APP_NAME; ?> license to continue</p>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-4">
                            <label for="license_key" class="form-label fw-semibold">License Key</label>
                            <textarea class="form-control" id="license_key" name="license_key" rows="4" 
                                placeholder="Paste your license key here..." required></textarea>
                            <div class="form-text">
                                <i class="bi bi-info-circle me-1"></i>
                                Enter the license key provided with your purchase
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                            <i class="bi bi-check-circle me-2"></i>
                            Activate License
                        </button>
                    </form>

                    <div class="text-center">
                        <small class="text-muted">
                            <i class="bi bi-shield-lock me-1"></i>
                            Your license is securely validated and bound to this server
                        </small>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-white">
                                <i class="bi bi-shield-check fs-4 d-block mb-2"></i>
                                <small>Secure Activation</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-white">
                                <i class="bi bi-cpu fs-4 d-block mb-2"></i>
                                <small>Hardware Bound</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-white">
                                <i class="bi bi-clock fs-4 d-block mb-2"></i>
                                <small>24/7 Monitoring</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
