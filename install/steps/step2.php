<?php
// Pre-fill with common values
$dbHost = $_SESSION['db_config']['host'] ?? 'localhost';
$dbName = $_SESSION['db_config']['name'] ?? '';
$dbUser = $_SESSION['db_config']['user'] ?? '';
$dbPass = $_SESSION['db_config']['pass'] ?? '';
?>

<div class="text-center mb-4">
    <h2 class="h4">Database Configuration</h2>
    <p class="text-muted">Enter your MySQL database connection details</p>
</div>

<form method="POST" class="needs-validation" novalidate>
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="db_host" class="form-label">
                    <i class="bi bi-server me-2"></i>
                    Database Host
                </label>
                <input type="text" class="form-control" id="db_host" name="db_host" 
                       value="<?php echo htmlspecialchars($dbHost); ?>" required>
                <div class="form-text">Usually 'localhost' for shared hosting</div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="mb-3">
                <label for="db_name" class="form-label">
                    <i class="bi bi-database me-2"></i>
                    Database Name
                </label>
                <input type="text" class="form-control" id="db_name" name="db_name" 
                       value="<?php echo htmlspecialchars($dbName); ?>" required>
                <div class="form-text">Create this database in cPanel first</div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="db_user" class="form-label">
                    <i class="bi bi-person me-2"></i>
                    Database Username
                </label>
                <input type="text" class="form-control" id="db_user" name="db_user" 
                       value="<?php echo htmlspecialchars($dbUser); ?>" required>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="mb-3">
                <label for="db_pass" class="form-label">
                    <i class="bi bi-lock me-2"></i>
                    Database Password
                </label>
                <input type="password" class="form-control" id="db_pass" name="db_pass" 
                       value="<?php echo htmlspecialchars($dbPass); ?>" required>
            </div>
        </div>
    </div>
    
    <div class="alert alert-info">
        <h6><i class="bi bi-info-circle me-2"></i>Hostinger Database Setup Instructions</h6>
        <ol class="mb-0">
            <li>Login to your Hostinger control panel</li>
            <li>Go to "Databases" → "MySQL Databases"</li>
            <li>Create a new database (e.g., "u279576488_admissions")</li>
            <li>Create a database user (e.g., "u279576488_admin")</li>
            <li>Add the user to the database with <strong>ALL PRIVILEGES</strong></li>
            <li>Use the full database name (with username prefix) above</li>
        </ol>
        <div class="mt-2">
            <strong>Important:</strong> On Hostinger, your database name will be prefixed with your username (e.g., <code>u279576488_admissions</code>)
        </div>
    </div>
    
    <div class="alert alert-warning">
        <h6><i class="bi bi-exclamation-triangle me-2"></i>Common Hostinger Issues</h6>
        <ul class="mb-0">
            <li>Make sure the database user has <strong>ALL PRIVILEGES</strong> on the database</li>
            <li>Use the full database name including the username prefix</li>
            <li>Database host is usually <code>localhost</code></li>
            <li>If you get "Access denied" error, check user permissions in cPanel</li>
        </ul>
    </div>
    
    <div class="d-flex justify-content-between">
        <a href="?step=1" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>
            Back
        </a>
        
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle me-2"></i>
            Test Connection & Continue
        </button>
    </div>
</form>

<script>
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
