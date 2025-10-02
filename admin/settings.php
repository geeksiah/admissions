<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
$systemConfigModel = new SystemConfig($database);
$programModel = new Program($database);

// Check admin access
requireRole(['admin']);

$pageTitle = 'System Settings';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'Settings', 'url' => '/admin/settings.php']
];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_general_settings':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $settings = [
                        'school_name' => $_POST['school_name'],
                        'school_address' => $_POST['school_address'],
                        'school_phone' => $_POST['school_phone'],
                        'school_email' => $_POST['school_email'],
                        'school_website' => $_POST['school_website'],
                        'copyright_text' => $_POST['copyright_text'],
                        'application_theme_color' => $_POST['application_theme_color'],
                        'application_logo' => $_POST['application_logo']
                    ];
                    
                    $success = true;
                    foreach ($settings as $key => $value) {
                        if (!$systemConfigModel->set($key, $value, 'string', $_SESSION['user_id'])) {
                            $success = false;
                        }
                    }
                    
                    if ($success) {
                        $message = 'General settings updated successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to update some settings. Please try again.';
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'update_application_settings':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $settings = [
                        'application_access_mode' => $_POST['application_access_mode'],
                        'voucher_required_for_application' => isset($_POST['voucher_required_for_application']) ? 'true' : 'false',
                        'multiple_fee_structure' => isset($_POST['multiple_fee_structure']) ? 'true' : 'false',
                        'offline_applications_enabled' => isset($_POST['offline_applications_enabled']) ? 'true' : 'false',
                        'email_verification_required' => isset($_POST['email_verification_required']) ? 'true' : 'false',
                        'auto_approve_applications' => isset($_POST['auto_approve_applications']) ? 'true' : 'false'
                    ];
                    
                    $success = true;
                    foreach ($settings as $key => $value) {
                        if (!$systemConfigModel->set($key, $value, 'string', $_SESSION['user_id'])) {
                            $success = false;
                        }
                    }
                    
                    if ($success) {
                        $message = 'Application settings updated successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to update some settings. Please try again.';
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'update_notification_settings':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $settings = [
                        'email_enabled' => isset($_POST['email_enabled']) ? 'true' : 'false',
                        'sms_enabled' => isset($_POST['sms_enabled']) ? 'true' : 'false',
                        'email_provider_default' => $_POST['email_provider_default'],
                        'sms_provider_default' => $_POST['sms_provider_default'],
                        'notification_reminder_days' => $_POST['notification_reminder_days']
                    ];
                    
                    $success = true;
                    foreach ($settings as $key => $value) {
                        if (!$systemConfigModel->set($key, $value, 'string', $_SESSION['user_id'])) {
                            $success = false;
                        }
                    }
                    
                    if ($success) {
                        $message = 'Notification settings updated successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to update some settings. Please try again.';
                        $messageType = 'danger';
                    }
                }
                break;
        }
    }
}

// Get current settings
$generalSettings = [
    'school_name' => $systemConfigModel->get('school_name', 'University Name'),
    'school_address' => $systemConfigModel->get('school_address', ''),
    'school_phone' => $systemConfigModel->get('school_phone', ''),
    'school_email' => $systemConfigModel->get('school_email', ''),
    'school_website' => $systemConfigModel->get('school_website', ''),
    'copyright_text' => $systemConfigModel->get('copyright_text', '© 2024 University Name. All rights reserved.'),
    'application_theme_color' => $systemConfigModel->get('application_theme_color', '#007bff'),
    'application_logo' => $systemConfigModel->get('application_logo', '')
];

$applicationSettings = [
    'application_access_mode' => $systemConfigModel->get('application_access_mode', 'payment'),
    'voucher_required_for_application' => $systemConfigModel->get('voucher_required_for_application', 'false'),
    'multiple_fee_structure' => $systemConfigModel->get('multiple_fee_structure', 'false'),
    'offline_applications_enabled' => $systemConfigModel->get('offline_applications_enabled', 'true'),
    'email_verification_required' => $systemConfigModel->get('email_verification_required', 'true'),
    'auto_approve_applications' => $systemConfigModel->get('auto_approve_applications', 'false')
];

$notificationSettings = [
    'email_enabled' => $systemConfigModel->get('email_enabled', 'true'),
    'sms_enabled' => $systemConfigModel->get('sms_enabled', 'true'),
    'email_provider_default' => $systemConfigModel->get('email_provider_default', 'smtp'),
    'sms_provider_default' => $systemConfigModel->get('sms_provider_default', 'twilio'),
    'notification_reminder_days' => $systemConfigModel->get('notification_reminder_days', '7')
];

include '../includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">Settings Categories</h6>
            </div>
            <div class="list-group list-group-flush">
                <a href="#general" class="list-group-item list-group-item-action active" data-bs-toggle="tab">
                    <i class="bi bi-gear me-2"></i>General Settings
                </a>
                <a href="#application" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                    <i class="bi bi-file-earmark-text me-2"></i>Application Settings
                </a>
                <a href="#notifications" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                    <i class="bi bi-bell me-2"></i>Notification Settings
                </a>
                <a href="#branding" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                    <i class="bi bi-palette me-2"></i>Branding & Theme
                </a>
                <a href="#security" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                    <i class="bi bi-shield-lock me-2"></i>Security Settings
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="tab-content">
            <!-- General Settings -->
            <div class="tab-pane fade show active" id="general">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-gear me-2"></i>General Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="generalSettingsForm">
                            <input type="hidden" name="action" value="update_general_settings">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="school_name" class="form-label">School Name *</label>
                                        <input type="text" class="form-control" id="school_name" name="school_name" 
                                               value="<?php echo htmlspecialchars($generalSettings['school_name']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="school_email" class="form-label">School Email *</label>
                                        <input type="email" class="form-control" id="school_email" name="school_email" 
                                               value="<?php echo htmlspecialchars($generalSettings['school_email']); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="school_phone" class="form-label">School Phone</label>
                                        <input type="tel" class="form-control" id="school_phone" name="school_phone" 
                                               value="<?php echo htmlspecialchars($generalSettings['school_phone']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="school_website" class="form-label">School Website</label>
                                        <input type="url" class="form-control" id="school_website" name="school_website" 
                                               value="<?php echo htmlspecialchars($generalSettings['school_website']); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="school_address" class="form-label">School Address</label>
                                <textarea class="form-control" id="school_address" name="school_address" rows="3"><?php echo htmlspecialchars($generalSettings['school_address']); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="copyright_text" class="form-label">Copyright Text</label>
                                <input type="text" class="form-control" id="copyright_text" name="copyright_text" 
                                       value="<?php echo htmlspecialchars($generalSettings['copyright_text']); ?>">
                                <div class="form-text">Use {year} to automatically insert current year</div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>Update General Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Application Settings -->
            <div class="tab-pane fade" id="application">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-file-earmark-text me-2"></i>Application Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="applicationSettingsForm">
                            <input type="hidden" name="action" value="update_application_settings">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-4">
                                <h6>Application Access Mode</h6>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="application_access_mode" id="access_payment" 
                                           value="payment" <?php echo $applicationSettings['application_access_mode'] === 'payment' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="access_payment">
                                        Payment-based Access
                                        <small class="text-muted d-block">Students pay during or after application submission</small>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="application_access_mode" id="access_voucher" 
                                           value="voucher" <?php echo $applicationSettings['application_access_mode'] === 'voucher' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="access_voucher">
                                        Voucher-based Access
                                        <small class="text-muted d-block">Students must purchase and enter voucher PIN/SERIAL to access application</small>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <h6>Application Features</h6>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="voucher_required_for_application" name="voucher_required_for_application" 
                                           <?php echo $applicationSettings['voucher_required_for_application'] === 'true' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="voucher_required_for_application">
                                        Require Voucher for Application
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="multiple_fee_structure" name="multiple_fee_structure" 
                                           <?php echo $applicationSettings['multiple_fee_structure'] === 'true' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="multiple_fee_structure">
                                        Enable Multiple Fee Structure
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="offline_applications_enabled" name="offline_applications_enabled" 
                                           <?php echo $applicationSettings['offline_applications_enabled'] === 'true' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="offline_applications_enabled">
                                        Enable Offline Applications
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="email_verification_required" name="email_verification_required" 
                                           <?php echo $applicationSettings['email_verification_required'] === 'true' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="email_verification_required">
                                        Require Email Verification
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="auto_approve_applications" name="auto_approve_applications" 
                                           <?php echo $applicationSettings['auto_approve_applications'] === 'true' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="auto_approve_applications">
                                        Auto-approve Applications
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>Update Application Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Notification Settings -->
            <div class="tab-pane fade" id="notifications">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-bell me-2"></i>Notification Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="notificationSettingsForm">
                            <input type="hidden" name="action" value="update_notification_settings">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-4">
                                <h6>Notification Channels</h6>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="email_enabled" name="email_enabled" 
                                           <?php echo $notificationSettings['email_enabled'] === 'true' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="email_enabled">
                                        Enable Email Notifications
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="sms_enabled" name="sms_enabled" 
                                           <?php echo $notificationSettings['sms_enabled'] === 'true' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="sms_enabled">
                                        Enable SMS Notifications
                                    </label>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email_provider_default" class="form-label">Default Email Provider</label>
                                        <select class="form-select" id="email_provider_default" name="email_provider_default">
                                            <option value="smtp" <?php echo $notificationSettings['email_provider_default'] === 'smtp' ? 'selected' : ''; ?>>SMTP</option>
                                            <option value="sendgrid" <?php echo $notificationSettings['email_provider_default'] === 'sendgrid' ? 'selected' : ''; ?>>SendGrid</option>
                                            <option value="mailgun" <?php echo $notificationSettings['email_provider_default'] === 'mailgun' ? 'selected' : ''; ?>>Mailgun</option>
                                            <option value="ses" <?php echo $notificationSettings['email_provider_default'] === 'ses' ? 'selected' : ''; ?>>Amazon SES</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="sms_provider_default" class="form-label">Default SMS Provider</label>
                                        <select class="form-select" id="sms_provider_default" name="sms_provider_default">
                                            <option value="twilio" <?php echo $notificationSettings['sms_provider_default'] === 'twilio' ? 'selected' : ''; ?>>Twilio</option>
                                            <option value="vonage" <?php echo $notificationSettings['sms_provider_default'] === 'vonage' ? 'selected' : ''; ?>>Vonage</option>
                                            <option value="africas_talking" <?php echo $notificationSettings['sms_provider_default'] === 'africas_talking' ? 'selected' : ''; ?>>Africa's Talking</option>
                                            <option value="hubtel_sms" <?php echo $notificationSettings['sms_provider_default'] === 'hubtel_sms' ? 'selected' : ''; ?>>Hubtel SMS</option>
                                            <option value="bulksms" <?php echo $notificationSettings['sms_provider_default'] === 'bulksms' ? 'selected' : ''; ?>>BulkSMS</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notification_reminder_days" class="form-label">Reminder Days</label>
                                <input type="number" class="form-control" id="notification_reminder_days" name="notification_reminder_days" 
                                       value="<?php echo $notificationSettings['notification_reminder_days']; ?>" min="1" max="30">
                                <div class="form-text">Number of days before due dates to send reminders</div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>Update Notification Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Branding & Theme -->
            <div class="tab-pane fade" id="branding">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-palette me-2"></i>Branding & Theme
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="brandingForm">
                            <input type="hidden" name="action" value="update_general_settings">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="application_theme_color" class="form-label">Application Theme Color</label>
                                        <input type="color" class="form-control form-control-color" id="application_theme_color" 
                                               name="application_theme_color" value="<?php echo $generalSettings['application_theme_color']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="application_logo" class="form-label">Application Logo URL</label>
                                        <input type="url" class="form-control" id="application_logo" name="application_logo" 
                                               value="<?php echo htmlspecialchars($generalSettings['application_logo']); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Logo Preview</label>
                                <div class="border rounded p-3 text-center" style="min-height: 100px;">
                                    <?php if ($generalSettings['application_logo']): ?>
                                        <img src="<?php echo htmlspecialchars($generalSettings['application_logo']); ?>" 
                                             alt="School Logo" class="img-fluid" style="max-height: 80px;">
                                    <?php else: ?>
                                        <i class="bi bi-image display-4 text-muted"></i>
                                        <p class="text-muted mt-2">No logo uploaded</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>Update Branding
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Security Settings -->
            <div class="tab-pane fade" id="security">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-shield-lock me-2"></i>Security Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Security settings are managed through the main configuration file. Contact your system administrator for changes.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Current Security Features</h6>
                                        <ul class="list-unstyled mb-0">
                                            <li><i class="bi bi-check-circle text-success me-2"></i>CSRF Protection</li>
                                            <li><i class="bi bi-check-circle text-success me-2"></i>SQL Injection Prevention</li>
                                            <li><i class="bi bi-check-circle text-success me-2"></i>XSS Protection</li>
                                            <li><i class="bi bi-check-circle text-success me-2"></i>File Upload Validation</li>
                                            <li><i class="bi bi-check-circle text-success me-2"></i>Session Security</li>
                                            <li><i class="bi bi-check-circle text-success me-2"></i>Password Hashing</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Access Control</h6>
                                        <ul class="list-unstyled mb-0">
                                            <li><i class="bi bi-check-circle text-success me-2"></i>Role-based Access</li>
                                            <li><i class="bi bi-check-circle text-success me-2"></i>Login Attempts Limiting</li>
                                            <li><i class="bi bi-check-circle text-success me-2"></i>Secure Headers</li>
                                            <li><i class="bi bi-check-circle text-success me-2"></i>Input Validation</li>
                                            <li><i class="bi bi-check-circle text-success me-2"></i>Error Handling</li>
                                            <li><i class="bi bi-check-circle text-success me-2"></i>Audit Logging</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Update logo preview when URL changes
document.getElementById('application_logo').addEventListener('input', function() {
    const preview = document.querySelector('#branding .border img');
    const placeholder = document.querySelector('#branding .border i');
    const text = document.querySelector('#branding .border p');
    
    if (this.value) {
        if (preview) {
            preview.src = this.value;
        } else {
            placeholder.style.display = 'none';
            text.style.display = 'none';
            const img = document.createElement('img');
            img.src = this.value;
            img.alt = 'School Logo';
            img.className = 'img-fluid';
            img.style.maxHeight = '80px';
            document.querySelector('#branding .border').appendChild(img);
        }
    } else {
        if (preview) {
            preview.remove();
        }
        placeholder.style.display = 'block';
        text.style.display = 'block';
    }
});

// Form validation
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const requiredFields = this.querySelectorAll('input[required], select[required]');
        let hasErrors = false;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                hasErrors = true;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        if (hasErrors) {
            e.preventDefault();
            alert('Please fill in all required fields.');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
