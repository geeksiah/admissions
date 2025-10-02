<?php
/**
 * License Management Dashboard
 */

session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Security.php';
require_once '../classes/LicenseManager.php';
require_once '../classes/AntiTampering.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$security = new Security($database);
$licenseManager = new LicenseManager($database);
$antiTampering = new AntiTampering($database);

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'activate_license':
                if (validateCSRFToken($_POST['csrf_token'] ?? '')) {
                    $licenseKey = sanitizeInput($_POST['license_key']);
                    $result = $licenseManager->activateLicense($licenseKey);
                    
                    if ($result['success']) {
                        $message = $result['message'];
                        $messageType = 'success';
                    } else {
                        $message = $result['error'];
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'deactivate_license':
                if (validateCSRFToken($_POST['csrf_token'] ?? '')) {
                    $result = $licenseManager->deactivateLicense();
                    
                    if ($result['success']) {
                        $message = $result['message'];
                        $messageType = 'success';
                    } else {
                        $message = $result['error'];
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'generate_integrity_hashes':
                if (validateCSRFToken($_POST['csrf_token'] ?? '')) {
                    $hashes = $antiTampering->generateIntegrityHashes();
                    $message = 'Integrity hashes generated successfully';
                    $messageType = 'success';
                }
                break;
        }
    }
}

// Get license information
$licenseInfo = $licenseManager->getLicenseInfo();
$licenseStatus = $licenseManager->getLicenseStatus();
$tamperingStats = $antiTampering->getTamperingStats();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">License Management</h1>
                    <p class="text-muted">Manage system license and security</p>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="refreshLicenseInfo()">
                        <i class="bi bi-arrow-clockwise me-2"></i>
                        Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- License Status -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-shield-check me-2"></i>
                        License Status
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($licenseStatus): ?>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-<?php echo $licenseStatus['status'] === 'valid' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($licenseStatus['status']); ?>
                                    </h4>
                                    <p class="text-muted">Status</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4><?php echo $licenseStatus['days_until_expiry'] ?? 'N/A'; ?></h4>
                                    <p class="text-muted">Days Until Expiry</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4><?php echo $licenseStatus['usage']['users'] ?? 0; ?> / <?php echo $licenseStatus['limits']['max_users'] ?? 0; ?></h4>
                                    <p class="text-muted">Users</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4><?php echo $licenseStatus['usage']['applications'] ?? 0; ?> / <?php echo $licenseStatus['limits']['max_applications'] ?? 0; ?></h4>
                                    <p class="text-muted">Applications</p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($licenseStatus['status'] !== 'valid'): ?>
                            <div class="alert alert-warning mt-3">
                                <strong>License Issue:</strong> <?php echo htmlspecialchars($licenseStatus['message']); ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <strong>No License Found:</strong> Please activate a license to continue using the system.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- License Activation -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-key me-2"></i>
                        License Activation
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="activate_license">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="license_key" class="form-label">License Key</label>
                            <textarea class="form-control" id="license_key" name="license_key" rows="4" 
                                placeholder="Paste your license key here..." required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>
                            Activate License
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- License Information -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        License Information
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($licenseInfo): ?>
                        <dl class="row">
                            <dt class="col-sm-4">Customer:</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($licenseInfo['license_data']['customer_name']); ?></dd>
                            
                            <dt class="col-sm-4">License Type:</dt>
                            <dd class="col-sm-8">
                                <span class="badge bg-primary">
                                    <?php echo ucfirst($licenseInfo['license_data']['license_type']); ?>
                                </span>
                            </dd>
                            
                            <dt class="col-sm-4">Expiry Date:</dt>
                            <dd class="col-sm-8"><?php echo date('Y-m-d', strtotime($licenseInfo['license_data']['expiry_date'])); ?></dd>
                            
                            <dt class="col-sm-4">Hardware ID:</dt>
                            <dd class="col-sm-8">
                                <code><?php echo htmlspecialchars($licenseInfo['hardware_id']); ?></code>
                            </dd>
                            
                            <dt class="col-sm-4">Features:</dt>
                            <dd class="col-sm-8">
                                <?php foreach ($licenseInfo['license_data']['features'] as $feature): ?>
                                    <span class="badge bg-success me-1"><?php echo htmlspecialchars($feature); ?></span>
                                <?php endforeach; ?>
                            </dd>
                        </dl>
                        
                        <form method="POST" class="mt-3">
                            <input type="hidden" name="action" value="deactivate_license">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to deactivate the license?')">
                                <i class="bi bi-x-circle me-2"></i>
                                Deactivate License
                            </button>
                        </form>
                    <?php else: ?>
                        <p class="text-muted">No license information available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Security Monitoring -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-shield-exclamation me-2"></i>
                        Security Monitoring
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Tampering Attempts (Last 30 Days)</h6>
                            <?php if (!empty($tamperingStats)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Type</th>
                                                <th>Count</th>
                                                <th>Last Attempt</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tamperingStats as $stat): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($stat['attempt_type']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $stat['count'] > 10 ? 'danger' : ($stat['count'] > 5 ? 'warning' : 'success'); ?>">
                                                            <?php echo $stat['count']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('Y-m-d H:i', strtotime($stat['last_attempt'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No tampering attempts detected.</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <h6>Security Actions</h6>
                            <form method="POST">
                                <input type="hidden" name="action" value="generate_integrity_hashes">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                
                                <button type="submit" class="btn btn-warning mb-2">
                                    <i class="bi bi-shield-check me-2"></i>
                                    Generate Integrity Hashes
                                </button>
                            </form>
                            
                            <div class="alert alert-info">
                                <small>
                                    <strong>Note:</strong> Generate integrity hashes after any system updates 
                                    to maintain security monitoring.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function refreshLicenseInfo() {
    location.reload();
}

// Auto-refresh every 5 minutes
setInterval(function() {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 300000);
</script>

<?php include '../includes/footer.php'; ?>
