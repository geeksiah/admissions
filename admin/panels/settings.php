<?php
/**
 * Settings Panel - System Configuration
 */

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $configModel = new SystemConfig($pdo);
    $success = false;
    $message = '';
    
    switch ($_POST['action']) {
        case 'branding':
            $brandingSettings = [
                'institution_name' => $_POST['institution_name'] ?? '',
                'institution_address' => $_POST['institution_address'] ?? '',
                'institution_phone' => $_POST['institution_phone'] ?? '',
                'institution_email' => $_POST['institution_email'] ?? '',
                'institution_website' => $_POST['institution_website'] ?? '',
                'primary_color' => $_POST['primary_color'] ?? '#667eea',
                'secondary_color' => $_POST['secondary_color'] ?? '#764ba2'
            ];
            
            // Handle logo upload
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/logos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $maxSize = 2 * 1024 * 1024; // 2MB
                
                if (in_array($_FILES['logo']['type'], $allowedTypes) && $_FILES['logo']['size'] <= $maxSize) {
                    $extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                    $filename = 'logo_' . time() . '.' . $extension;
                    $filepath = $uploadDir . $filename;
                    
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $filepath)) {
                        $brandingSettings['logo_url'] = 'uploads/logos/' . $filename;
                    }
                }
            }
            
            $success = $configModel->saveBrandingSettings($brandingSettings);
            $message = $success ? 'Branding settings updated successfully!' : 'Failed to update branding settings.';
            break;
            
        case 'currency':
            $currencySettings = [
                'default_currency' => $_POST['default_currency'] ?? 'USD',
                'available_currencies' => $_POST['available_currencies'] ?? 'USD,EUR,GBP,NGN',
                'exchange_rate_api' => $_POST['exchange_rate_api'] ?? 'free',
                'auto_update_rates' => isset($_POST['auto_update_rates']) ? '1' : '0',
                'rate_update_frequency' => $_POST['rate_update_frequency'] ?? 'daily'
            ];
            
            $success = $configModel->saveCurrencySettings($currencySettings);
            $message = $success ? 'Currency settings updated successfully!' : 'Failed to update currency settings.';
            break;
            
        case 'payment':
            $paymentSettings = [
                'currency' => $_POST['currency'] ?? 'USD',
                'currency_symbol' => $_POST['currency_symbol'] ?? '$',
                'currency_position' => $_POST['currency_position'] ?? 'before',
                'decimal_places' => $_POST['decimal_places'] ?? '2',
                'thousand_separator' => $_POST['thousand_separator'] ?? ',',
                'decimal_separator' => $_POST['decimal_separator'] ?? '.',
                'paystack_public_key' => $_POST['paystack_public_key'] ?? '',
                'paystack_secret_key' => $_POST['paystack_secret_key'] ?? '',
                'flutterwave_public_key' => $_POST['flutterwave_public_key'] ?? '',
                'flutterwave_secret_key' => $_POST['flutterwave_secret_key'] ?? '',
                'stripe_public_key' => $_POST['stripe_public_key'] ?? '',
                'stripe_secret_key' => $_POST['stripe_secret_key'] ?? ''
            ];
            
            $success = $configModel->savePaymentSettings($paymentSettings);
            $message = $success ? 'Payment settings updated successfully!' : 'Failed to update payment settings.';
            break;
            
        case 'email':
            $emailSettings = [
                'smtp_host' => $_POST['smtp_host'] ?? '',
                'smtp_port' => $_POST['smtp_port'] ?? '587',
                'smtp_username' => $_POST['smtp_username'] ?? '',
                'smtp_password' => $_POST['smtp_password'] ?? '',
                'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
                'from_email' => $_POST['from_email'] ?? '',
                'from_name' => $_POST['from_name'] ?? ''
            ];
            
            $success = $configModel->saveEmailSettings($emailSettings);
            $message = $success ? 'Email settings updated successfully!' : 'Failed to update email settings.';
            break;
            
        case 'payment':
            $paymentSettings = [
                'currency' => $_POST['currency'] ?? 'USD',
                'paystack_public_key' => $_POST['paystack_public_key'] ?? '',
                'paystack_secret_key' => $_POST['paystack_secret_key'] ?? '',
                'flutterwave_public_key' => $_POST['flutterwave_public_key'] ?? '',
                'flutterwave_secret_key' => $_POST['flutterwave_secret_key'] ?? '',
                'stripe_public_key' => $_POST['stripe_public_key'] ?? '',
                'stripe_secret_key' => $_POST['stripe_secret_key'] ?? ''
            ];
            
            $success = $configModel->savePaymentSettings($paymentSettings);
            $message = $success ? 'Payment settings updated successfully!' : 'Failed to update payment settings.';
            break;
    }
    
    if ($success) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>' . htmlspecialchars($message) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
    } else {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>' . htmlspecialchars($message) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
    }
}

// Get current settings
$configModel = new SystemConfig($pdo);
$brandingSettings = $configModel->getBrandingSettings();
$emailSettings = $configModel->getEmailSettings();
$paymentSettings = $configModel->getPaymentSettings();
$currencySettings = $configModel->getCurrencySettings();
?>

<div class="row">
    <div class="col-lg-3">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Settings Categories</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <a href="#" class="list-group-item list-group-item-action active" data-settings-tab="branding">
                        <i class="bi bi-palette me-2"></i>Branding & Theme
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" data-settings-tab="email">
                        <i class="bi bi-envelope me-2"></i>Email Settings
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" data-settings-tab="currency">
                        <i class="bi bi-currency-exchange me-2"></i>Currency Settings
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" data-settings-tab="payment">
                        <i class="bi bi-credit-card me-2"></i>Payment Gateways
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" data-settings-tab="security">
                        <i class="bi bi-shield-check me-2"></i>Security
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" data-settings-tab="general">
                        <i class="bi bi-gear me-2"></i>General
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-9">
        <!-- Branding Settings -->
        <div class="settings-tab active" id="branding-tab">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-palette me-2"></i>Branding & Theme Settings
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="branding">
                        
                        <!-- Logo Upload -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Institution Logo</label>
                                <input type="file" class="form-control" name="logo" accept="image/*">
                                <small class="form-text text-muted">Upload PNG, JPG, or GIF (max 2MB)</small>
                            </div>
                            <div class="col-md-6">
                                <?php if (!empty($brandingSettings['logo_url'])): ?>
                                    <label class="form-label">Current Logo</label>
                                    <div class="mt-2">
                                        <img src="../<?php echo htmlspecialchars($brandingSettings['logo_url']); ?>" 
                                             alt="Current Logo" class="img-thumbnail" style="max-height: 100px;">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Admin Avatar Upload -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Admin Profile Picture</label>
                                <input type="file" class="form-control" name="admin_avatar" accept="image/*">
                                <small class="form-text text-muted">Upload PNG, JPG, or GIF (max 2MB)</small>
                            </div>
                            <div class="col-md-6">
                                <?php if (!empty($brandingSettings['admin_avatar'])): ?>
                                    <label class="form-label">Current Avatar</label>
                                    <div class="mt-2">
                                        <img src="../<?php echo htmlspecialchars($brandingSettings['admin_avatar']); ?>" 
                                             alt="Current Avatar" class="img-thumbnail rounded-circle" style="max-height: 80px; max-width: 80px;">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Institution Information -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Institution Name *</label>
                                <input type="text" class="form-control" name="institution_name" 
                                       value="<?php echo htmlspecialchars($brandingSettings['institution_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Institution Email</label>
                                <input type="email" class="form-control" name="institution_email" 
                                       value="<?php echo htmlspecialchars($brandingSettings['institution_email']); ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Institution Phone</label>
                                <input type="tel" class="form-control" name="institution_phone" 
                                       value="<?php echo htmlspecialchars($brandingSettings['institution_phone']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Institution Website</label>
                                <input type="url" class="form-control" name="institution_website" 
                                       value="<?php echo htmlspecialchars($brandingSettings['institution_website']); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Institution Address</label>
                            <textarea class="form-control" name="institution_address" rows="3"><?php echo htmlspecialchars($brandingSettings['institution_address']); ?></textarea>
                        </div>
                        
                        <!-- Color Scheme -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Primary Color</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" name="primary_color" 
                                           value="<?php echo htmlspecialchars($brandingSettings['primary_color']); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($brandingSettings['primary_color']); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Secondary Color</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" name="secondary_color" 
                                           value="<?php echo htmlspecialchars($brandingSettings['secondary_color']); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($brandingSettings['secondary_color']); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Update Branding Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Email Settings -->
        <div class="settings-tab" id="email-tab">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-envelope me-2"></i>Email Configuration
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="email">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">SMTP Host</label>
                                <input type="text" class="form-control" name="smtp_host" 
                                       value="<?php echo htmlspecialchars($emailSettings['smtp_host']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SMTP Port</label>
                                <input type="number" class="form-control" name="smtp_port" 
                                       value="<?php echo htmlspecialchars($emailSettings['smtp_port']); ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">SMTP Username</label>
                                <input type="text" class="form-control" name="smtp_username" 
                                       value="<?php echo htmlspecialchars($emailSettings['smtp_username']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SMTP Password</label>
                                <input type="password" class="form-control" name="smtp_password" 
                                       value="<?php echo htmlspecialchars($emailSettings['smtp_password']); ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Encryption</label>
                                <select class="form-select" name="smtp_encryption">
                                    <option value="none" <?php echo $emailSettings['smtp_encryption'] === 'none' ? 'selected' : ''; ?>>None</option>
                                    <option value="tls" <?php echo $emailSettings['smtp_encryption'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    <option value="ssl" <?php echo $emailSettings['smtp_encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">From Email</label>
                                <input type="email" class="form-control" name="from_email" 
                                       value="<?php echo htmlspecialchars($emailSettings['from_email']); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">From Name</label>
                            <input type="text" class="form-control" name="from_name" 
                                   value="<?php echo htmlspecialchars($emailSettings['from_name']); ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Update Email Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Currency Settings -->
        <div class="settings-tab" id="currency-tab">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-currency-exchange me-2"></i>Currency Configuration
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="currency">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Default Currency</label>
                                <select class="form-select" name="default_currency">
                                    <option value="USD" <?php echo ($currencySettings['default_currency'] ?? 'USD') === 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                                    <option value="EUR" <?php echo ($currencySettings['default_currency'] ?? 'USD') === 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                                    <option value="GBP" <?php echo ($currencySettings['default_currency'] ?? 'USD') === 'GBP' ? 'selected' : ''; ?>>GBP - British Pound</option>
                                    <option value="NGN" <?php echo ($currencySettings['default_currency'] ?? 'USD') === 'NGN' ? 'selected' : ''; ?>>NGN - Nigerian Naira</option>
                                    <option value="JPY" <?php echo ($currencySettings['default_currency'] ?? 'USD') === 'JPY' ? 'selected' : ''; ?>>JPY - Japanese Yen</option>
                                    <option value="CAD" <?php echo ($currencySettings['default_currency'] ?? 'USD') === 'CAD' ? 'selected' : ''; ?>>CAD - Canadian Dollar</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Available Currencies</label>
                                <input type="text" class="form-control" name="available_currencies" 
                                       value="<?php echo htmlspecialchars($currencySettings['available_currencies'] ?? 'USD,EUR,GBP,NGN'); ?>" 
                                       placeholder="USD,EUR,GBP,NGN">
                                <small class="form-text text-muted">Comma-separated currency codes</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Exchange Rate API</label>
                                <select class="form-select" name="exchange_rate_api">
                                    <option value="free" <?php echo ($currencySettings['exchange_rate_api'] ?? 'free') === 'free' ? 'selected' : ''; ?>>Free API</option>
                                    <option value="premium" <?php echo ($currencySettings['exchange_rate_api'] ?? 'free') === 'premium' ? 'selected' : ''; ?>>Premium API</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Rate Update Frequency</label>
                                <select class="form-select" name="rate_update_frequency">
                                    <option value="daily" <?php echo ($currencySettings['rate_update_frequency'] ?? 'daily') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                    <option value="weekly" <?php echo ($currencySettings['rate_update_frequency'] ?? 'daily') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="monthly" <?php echo ($currencySettings['rate_update_frequency'] ?? 'daily') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="auto_update_rates" id="auto_update_rates" 
                                       <?php echo ($currencySettings['auto_update_rates'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="auto_update_rates">
                                    Automatically update exchange rates
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Update Currency Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Payment Settings -->
        <div class="settings-tab" id="payment-tab">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-credit-card me-2"></i>Payment Gateway Configuration
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="payment">
                        
                        <div class="mb-3">
                            <label class="form-label">Default Currency</label>
                            <select class="form-select" name="currency">
                                <option value="USD" <?php echo $paymentSettings['currency'] === 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                                <option value="GHS" <?php echo $paymentSettings['currency'] === 'GHS' ? 'selected' : ''; ?>>GHS - Ghana Cedi</option>
                                <option value="NGN" <?php echo $paymentSettings['currency'] === 'NGN' ? 'selected' : ''; ?>>NGN - Nigerian Naira</option>
                                <option value="KES" <?php echo $paymentSettings['currency'] === 'KES' ? 'selected' : ''; ?>>KES - Kenyan Shilling</option>
                                <option value="ZAR" <?php echo $paymentSettings['currency'] === 'ZAR' ? 'selected' : ''; ?>>ZAR - South African Rand</option>
                            </select>
                        </div>
                        
                        <!-- Paystack -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">Paystack Configuration</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Public Key</label>
                                        <input type="text" class="form-control" name="paystack_public_key" 
                                               value="<?php echo htmlspecialchars($paymentSettings['paystack_public_key']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Secret Key</label>
                                        <input type="password" class="form-control" name="paystack_secret_key" 
                                               value="<?php echo htmlspecialchars($paymentSettings['paystack_secret_key']); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Flutterwave -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">Flutterwave Configuration</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Public Key</label>
                                        <input type="text" class="form-control" name="flutterwave_public_key" 
                                               value="<?php echo htmlspecialchars($paymentSettings['flutterwave_public_key']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Secret Key</label>
                                        <input type="password" class="form-control" name="flutterwave_secret_key" 
                                               value="<?php echo htmlspecialchars($paymentSettings['flutterwave_secret_key']); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Stripe -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">Stripe Configuration</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Publishable Key</label>
                                        <input type="text" class="form-control" name="stripe_public_key" 
                                               value="<?php echo htmlspecialchars($paymentSettings['stripe_public_key']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Secret Key</label>
                                        <input type="password" class="form-control" name="stripe_secret_key" 
                                               value="<?php echo htmlspecialchars($paymentSettings['stripe_secret_key']); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Update Payment Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Security Settings -->
        <div class="settings-tab" id="security-tab">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-shield-check me-2"></i>Security Settings
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Security settings are managed through the main configuration files. 
                        Contact your system administrator for changes.
                    </div>
                </div>
            </div>
        </div>
        
        <!-- General Settings -->
        <div class="settings-tab" id="general-tab">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-gear me-2"></i>General Settings
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        General system settings are configured through the main application configuration.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.settings-tab {
    display: none;
}

.settings-tab.active {
    display: block;
}

.list-group-item-action.active {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const settingsTabs = document.querySelectorAll('[data-settings-tab]');
    const tabContents = document.querySelectorAll('.settings-tab');
    
    settingsTabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all tabs
            settingsTabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Show corresponding content
            const targetTab = this.getAttribute('data-settings-tab');
            const targetContent = document.getElementById(targetTab + '-tab');
            if (targetContent) {
                targetContent.classList.add('active');
            }
        });
    });
    
    // Color picker updates
    document.querySelectorAll('input[type="color"]').forEach(colorPicker => {
        colorPicker.addEventListener('change', function() {
            const textInput = this.parentNode.querySelector('input[type="text"]');
            if (textInput) {
                textInput.value = this.value;
            }
        });
    });
});
</script>
