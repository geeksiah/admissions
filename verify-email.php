<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database and models
$database = new Database();
require_once 'classes/EmailVerificationManager.php';

$pageTitle = 'Email Verification';
$message = '';
$messageType = '';

// Handle email verification
if (isset($_GET['token'])) {
    $verificationToken = $_GET['token'];
    
    $verificationManager = new EmailVerificationManager($database);
    $result = $verificationManager->verifyEmail($verificationToken);
    
    if ($result['success']) {
        $message = 'Your email has been verified successfully! You can now access all features of the application system.';
        $messageType = 'success';
    } else {
        $message = 'Email verification failed: ' . $result['error'];
        $messageType = 'danger';
    }
} else {
    $message = 'Invalid verification link. Please check your email for the correct verification link.';
    $messageType = 'warning';
}

include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header text-center">
                    <h4 class="mb-0">
                        <i class="bi bi-envelope-check me-2"></i>Email Verification
                    </h4>
                </div>
                <div class="card-body text-center">
                    <?php if ($messageType === 'success'): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            <?php echo $message; ?>
                        </div>
                        <div class="mt-4">
                            <a href="login.php" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login to Your Account
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-<?php echo $messageType; ?>">
                            <i class="bi bi-<?php echo $messageType === 'danger' ? 'exclamation-triangle' : 'info-circle'; ?> me-2"></i>
                            <?php echo $message; ?>
                        </div>
                        <div class="mt-4">
                            <a href="login.php" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                            </a>
                            <a href="contact.php" class="btn btn-outline-secondary ms-2">
                                <i class="bi bi-question-circle me-2"></i>Need Help?
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
