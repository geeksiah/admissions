<?php
// Clear session data
session_destroy();
?>

<div class="text-center mb-4">
    <div class="mb-3">
        <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
    </div>
    <h2 class="h4 text-success">Installation Complete!</h2>
    <p class="text-muted">Your Admissions Management System is ready to use</p>
</div>

<div class="alert alert-success">
    <h6><i class="bi bi-trophy me-2"></i>Congratulations!</h6>
    <p class="mb-0">Your system has been successfully installed and configured. You can now start managing admissions.</p>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card border-primary">
            <div class="card-body text-center">
                <i class="bi bi-gear-fill text-primary mb-3" style="font-size: 2rem;"></i>
                <h5 class="card-title">Admin Dashboard</h5>
                <p class="card-text">Access your administrative panel to manage applications, users, and system settings.</p>
                <a href="../admin/dashboard.php" class="btn btn-primary">
                    <i class="bi bi-arrow-right me-2"></i>
                    Go to Admin Panel
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card border-success">
            <div class="card-body text-center">
                <i class="bi bi-people-fill text-success mb-3" style="font-size: 2rem;"></i>
                <h5 class="card-title">Student Portal</h5>
                <p class="card-text">Students can access the application portal to submit their applications.</p>
                <a href="../student/apply.php" class="btn btn-success">
                    <i class="bi bi-arrow-right me-2"></i>
                    View Student Portal
                </a>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-warning">
    <h6><i class="bi bi-exclamation-triangle me-2"></i>Important Next Steps</h6>
    <ol class="mb-0">
        <li><strong>Set up cron jobs:</strong> Add this command to your cPanel cron jobs:
            <br><code>*/5 * * * * php <?php echo dirname(__DIR__); ?>/cron.php</code>
        </li>
        <li><strong>Configure programs:</strong> Add your academic programs in the admin panel</li>
        <li><strong>Set up payment gateways:</strong> Configure your preferred payment methods</li>
        <li><strong>Test email functionality:</strong> Send a test email to verify SMTP settings</li>
        <li><strong>Create application forms:</strong> Customize your application requirements</li>
    </ol>
</div>

<div class="alert alert-info">
    <h6><i class="bi bi-info-circle me-2"></i>Getting Help</h6>
    <ul class="mb-0">
        <li><strong>Documentation:</strong> Check the README.md file for detailed instructions</li>
        <li><strong>Support:</strong> Contact your system administrator for technical support</li>
        <li><strong>Updates:</strong> Keep your system updated for security and new features</li>
    </ul>
</div>

<div class="text-center">
    <a href="../index.php" class="btn btn-primary btn-lg">
        <i class="bi bi-house me-2"></i>
        Go to Homepage
    </a>
</div>

<div class="mt-4 text-center">
    <small class="text-muted">
        <i class="bi bi-shield-check me-1"></i>
        Installation completed on <?php echo date('F j, Y \a\t g:i A'); ?>
    </small>
</div>
