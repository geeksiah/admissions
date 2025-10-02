<?php
$requirements = checkSystemRequirements();
$permissions = checkFilePermissions();
$allRequirementsMet = true;
$allPermissionsOk = true;

foreach ($requirements as $req) {
    if (!$req['status']) {
        $allRequirementsMet = false;
        break;
    }
}

foreach ($permissions as $perm) {
    if (!$perm['writable']) {
        $allPermissionsOk = false;
        break;
    }
}
?>

<div class="text-center mb-4">
    <h2 class="h4">System Requirements Check</h2>
    <p class="text-muted">Let's verify your server meets the minimum requirements</p>
</div>

<div class="row">
    <div class="col-md-6">
        <h5 class="mb-3">
            <i class="bi bi-gear me-2"></i>
            PHP Requirements
        </h5>
        
        <?php foreach ($requirements as $name => $req): ?>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span><?php echo $name; ?></span>
                <div>
                    <span class="badge bg-<?php echo $req['status'] ? 'success' : 'danger'; ?> me-2">
                        <?php echo $req['current']; ?>
                    </span>
                    <?php if ($req['status']): ?>
                        <i class="bi bi-check-circle text-success"></i>
                    <?php else: ?>
                        <i class="bi bi-x-circle text-danger"></i>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="col-md-6">
        <h5 class="mb-3">
            <i class="bi bi-folder me-2"></i>
            Directory Permissions
        </h5>
        
        <?php foreach ($permissions as $name => $perm): ?>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span><?php echo ucfirst($name); ?></span>
                <div>
                    <span class="badge bg-<?php echo $perm['writable'] ? 'success' : 'danger'; ?> me-2">
                        <?php echo $perm['status']; ?>
                    </span>
                    <?php if ($perm['writable']): ?>
                        <i class="bi bi-check-circle text-success"></i>
                    <?php else: ?>
                        <i class="bi bi-x-circle text-danger"></i>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if (!$allRequirementsMet || !$allPermissionsOk): ?>
    <div class="alert alert-danger mt-4">
        <h6><i class="bi bi-exclamation-triangle me-2"></i>Requirements Not Met</h6>
        <ul class="mb-0">
            <?php if (!$allRequirementsMet): ?>
                <li>Please ensure all PHP extensions are installed and enabled</li>
            <?php endif; ?>
            <?php if (!$allPermissionsOk): ?>
                <li>Please set proper write permissions on the required directories</li>
            <?php endif; ?>
        </ul>
        <p class="mb-0 mt-2">
            <strong>Need help?</strong> Contact your hosting provider or system administrator.
        </p>
    </div>
    
    <div class="text-center">
        <button type="button" class="btn btn-primary" onclick="location.reload()">
            <i class="bi bi-arrow-clockwise me-2"></i>
            Recheck Requirements
        </button>
    </div>
<?php else: ?>
    <div class="alert alert-success mt-4">
        <h6><i class="bi bi-check-circle me-2"></i>All Requirements Met!</h6>
        <p class="mb-0">Your server is ready for installation. Click continue to proceed.</p>
    </div>
    
    <div class="text-center">
        <a href="?step=2" class="btn btn-primary btn-lg">
            <i class="bi bi-arrow-right me-2"></i>
            Continue to Database Setup
        </a>
    </div>
<?php endif; ?>
