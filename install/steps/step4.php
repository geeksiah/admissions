<?php
$adminName = $_SESSION['admin_data']['full_name'] ?? '';
$adminEmail = $_SESSION['admin_data']['email'] ?? '';
$adminPhone = $_SESSION['admin_data']['phone'] ?? '';
?>

<div class="text-center mb-4">
    <h2 class="h4">Admin Account Creation</h2>
    <p class="text-muted">Create your administrator account</p>
</div>

<form method="POST" class="needs-validation" novalidate>
    <div class="mb-3">
        <label for="admin_name" class="form-label">
            <i class="bi bi-person me-2"></i>
            Full Name
        </label>
        <input type="text" class="form-control" id="admin_name" name="admin_name" 
               value="<?php echo htmlspecialchars($adminName); ?>" required>
    </div>
    
    <div class="mb-3">
        <label for="admin_email" class="form-label">
            <i class="bi bi-envelope me-2"></i>
            Email Address
        </label>
        <input type="email" class="form-control" id="admin_email" name="admin_email" 
               value="<?php echo htmlspecialchars($adminEmail); ?>" required>
        <div class="form-text">This will be your login username</div>
    </div>
    
    <div class="mb-3">
        <label for="admin_phone" class="form-label">
            <i class="bi bi-phone me-2"></i>
            Phone Number
        </label>
        <input type="tel" class="form-control" id="admin_phone" name="admin_phone" 
               value="<?php echo htmlspecialchars($adminPhone); ?>">
        <div class="form-text">Optional - for SMS notifications</div>
    </div>
    
    <div class="mb-3">
        <label for="admin_password" class="form-label">
            <i class="bi bi-lock me-2"></i>
            Password
        </label>
        <input type="password" class="form-control" id="admin_password" name="admin_password" 
               minlength="8" required>
        <div class="form-text">Minimum 8 characters</div>
    </div>
    
    <div class="mb-3">
        <label for="admin_password_confirm" class="form-label">
            <i class="bi bi-lock-fill me-2"></i>
            Confirm Password
        </label>
        <input type="password" class="form-control" id="admin_password_confirm" name="admin_password_confirm" 
               minlength="8" required>
    </div>
    
    <div class="alert alert-warning">
        <h6><i class="bi bi-shield-exclamation me-2"></i>Security Notice</h6>
        <p class="mb-0">Please choose a strong password. This account will have full administrative access to the system.</p>
    </div>
    
    <div class="d-flex justify-content-between">
        <a href="?step=3" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>
            Back
        </a>
        
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-arrow-right me-2"></i>
            Continue
        </button>
    </div>
</form>

<script>
// Password confirmation validation
document.getElementById('admin_password_confirm').addEventListener('input', function() {
    const password = document.getElementById('admin_password').value;
    const confirmPassword = this.value;
    
    if (password !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>
