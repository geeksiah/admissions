<?php
$smtpHost = $_SESSION['email_config']['smtp_host'] ?? '';
$smtpPort = $_SESSION['email_config']['smtp_port'] ?? '587';
$smtpUser = $_SESSION['email_config']['smtp_user'] ?? '';
$smtpPass = $_SESSION['email_config']['smtp_pass'] ?? '';
$smtpEncryption = $_SESSION['email_config']['smtp_encryption'] ?? 'tls';
$fromEmail = $_SESSION['email_config']['from_email'] ?? '';
$fromName = $_SESSION['email_config']['from_name'] ?? '';
?>

<div class="text-center mb-4">
    <h2 class="h4">Email Configuration</h2>
    <p class="text-muted">Configure email settings (optional - can be done later)</p>
</div>

<form method="POST">
    <div class="alert alert-info">
        <h6><i class="bi bi-info-circle me-2"></i>Email Setup</h6>
        <p class="mb-0">You can skip this step and configure email later from the admin panel. Email functionality is required for notifications and password resets.</p>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="smtp_host" class="form-label">
                    <i class="bi bi-server me-2"></i>
                    SMTP Host
                </label>
                <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                       value="<?php echo htmlspecialchars($smtpHost); ?>" 
                       placeholder="mail.yourdomain.com">
                <div class="form-text">Your email server hostname</div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="mb-3">
                <label for="smtp_port" class="form-label">
                    <i class="bi bi-hash me-2"></i>
                    SMTP Port
                </label>
                <select class="form-select" id="smtp_port" name="smtp_port">
                    <option value="587" <?php echo $smtpPort === '587' ? 'selected' : ''; ?>>587 (TLS)</option>
                    <option value="465" <?php echo $smtpPort === '465' ? 'selected' : ''; ?>>465 (SSL)</option>
                    <option value="25" <?php echo $smtpPort === '25' ? 'selected' : ''; ?>>25 (Plain)</option>
                </select>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="smtp_user" class="form-label">
                    <i class="bi bi-person me-2"></i>
                    SMTP Username
                </label>
                <input type="text" class="form-control" id="smtp_user" name="smtp_user" 
                       value="<?php echo htmlspecialchars($smtpUser); ?>" 
                       placeholder="noreply@yourdomain.com">
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="mb-3">
                <label for="smtp_pass" class="form-label">
                    <i class="bi bi-lock me-2"></i>
                    SMTP Password
                </label>
                <input type="password" class="form-control" id="smtp_pass" name="smtp_pass" 
                       value="<?php echo htmlspecialchars($smtpPass); ?>">
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="smtp_encryption" class="form-label">
                    <i class="bi bi-shield-lock me-2"></i>
                    Encryption
                </label>
                <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                    <option value="tls" <?php echo $smtpEncryption === 'tls' ? 'selected' : ''; ?>>TLS</option>
                    <option value="ssl" <?php echo $smtpEncryption === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    <option value="" <?php echo $smtpEncryption === '' ? 'selected' : ''; ?>>None</option>
                </select>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="mb-3">
                <label for="from_email" class="form-label">
                    <i class="bi bi-envelope me-2"></i>
                    From Email
                </label>
                <input type="email" class="form-control" id="from_email" name="from_email" 
                       value="<?php echo htmlspecialchars($fromEmail); ?>" 
                       placeholder="noreply@yourdomain.com">
            </div>
        </div>
    </div>
    
    <div class="mb-3">
        <label for="from_name" class="form-label">
            <i class="bi bi-building me-2"></i>
            From Name
        </label>
        <input type="text" class="form-control" id="from_name" name="from_name" 
               value="<?php echo htmlspecialchars($fromName); ?>" 
               placeholder="Your Institution Name">
    </div>
    
    <div class="alert alert-warning">
        <h6><i class="bi bi-exclamation-triangle me-2"></i>Common cPanel Email Settings</h6>
        <ul class="mb-0">
            <li><strong>Host:</strong> mail.yourdomain.com</li>
            <li><strong>Port:</strong> 587 (TLS) or 465 (SSL)</li>
            <li><strong>Username:</strong> Your full email address</li>
            <li><strong>Password:</strong> Your email password</li>
        </ul>
    </div>
    
    <div class="d-flex justify-content-between">
        <a href="?step=4" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>
            Back
        </a>
        
        <div>
            <button type="submit" name="skip_email" value="1" class="btn btn-outline-primary me-2">
                <i class="bi bi-skip-forward me-2"></i>
                Skip Email Setup
            </button>
            
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-arrow-right me-2"></i>
                Continue
            </button>
        </div>
    </div>
</form>
