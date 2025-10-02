<?php
$appName = $_SESSION['app_config']['app_name'] ?? '';
$appUrl = $_SESSION['app_config']['app_url'] ?? '';
$timezone = $_SESSION['app_config']['timezone'] ?? 'UTC';
$currency = $_SESSION['app_config']['currency'] ?? 'USD';
$language = $_SESSION['app_config']['language'] ?? 'en';
?>

<div class="text-center mb-4">
    <h2 class="h4">Application Configuration</h2>
    <p class="text-muted">Configure your institution's basic settings</p>
</div>

<form method="POST" class="needs-validation" novalidate>
    <div class="mb-3">
        <label for="app_name" class="form-label">
            <i class="bi bi-building me-2"></i>
            Institution Name
        </label>
        <input type="text" class="form-control" id="app_name" name="app_name" 
               value="<?php echo htmlspecialchars($appName); ?>" required>
        <div class="form-text">Your school or institution name</div>
    </div>
    
    <div class="mb-3">
        <label for="app_url" class="form-label">
            <i class="bi bi-globe me-2"></i>
            Application URL
        </label>
        <input type="url" class="form-control" id="app_url" name="app_url" 
               value="<?php echo htmlspecialchars($appUrl); ?>" required>
        <div class="form-text">Full URL where the system will be accessed</div>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <div class="mb-3">
                <label for="timezone" class="form-label">
                    <i class="bi bi-clock me-2"></i>
                    Timezone
                </label>
                <select class="form-select" id="timezone" name="timezone" required>
                    <option value="UTC" <?php echo $timezone === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                    <option value="Africa/Accra" <?php echo $timezone === 'Africa/Accra' ? 'selected' : ''; ?>>Africa/Accra (Ghana)</option>
                    <option value="Africa/Lagos" <?php echo $timezone === 'Africa/Lagos' ? 'selected' : ''; ?>>Africa/Lagos (Nigeria)</option>
                    <option value="Africa/Nairobi" <?php echo $timezone === 'Africa/Nairobi' ? 'selected' : ''; ?>>Africa/Nairobi (Kenya)</option>
                    <option value="Africa/Cairo" <?php echo $timezone === 'Africa/Cairo' ? 'selected' : ''; ?>>Africa/Cairo (Egypt)</option>
                    <option value="Africa/Johannesburg" <?php echo $timezone === 'Africa/Johannesburg' ? 'selected' : ''; ?>>Africa/Johannesburg (South Africa)</option>
                </select>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="mb-3">
                <label for="currency" class="form-label">
                    <i class="bi bi-currency-dollar me-2"></i>
                    Currency
                </label>
                <select class="form-select" id="currency" name="currency" required>
                    <option value="USD" <?php echo $currency === 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                    <option value="GHS" <?php echo $currency === 'GHS' ? 'selected' : ''; ?>>GHS - Ghana Cedi</option>
                    <option value="NGN" <?php echo $currency === 'NGN' ? 'selected' : ''; ?>>NGN - Nigerian Naira</option>
                    <option value="KES" <?php echo $currency === 'KES' ? 'selected' : ''; ?>>KES - Kenyan Shilling</option>
                    <option value="ZAR" <?php echo $currency === 'ZAR' ? 'selected' : ''; ?>>ZAR - South African Rand</option>
                    <option value="EGP" <?php echo $currency === 'EGP' ? 'selected' : ''; ?>>EGP - Egyptian Pound</option>
                </select>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="mb-3">
                <label for="language" class="form-label">
                    <i class="bi bi-translate me-2"></i>
                    Language
                </label>
                <select class="form-select" id="language" name="language" required>
                    <option value="en" <?php echo $language === 'en' ? 'selected' : ''; ?>>English</option>
                    <option value="fr" <?php echo $language === 'fr' ? 'selected' : ''; ?>>Français</option>
                </select>
            </div>
        </div>
    </div>
    
    <div class="d-flex justify-content-between">
        <a href="?step=2" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>
            Back
        </a>
        
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-arrow-right me-2"></i>
            Continue
        </button>
    </div>
</form>
