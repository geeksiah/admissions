<?php
/**
 * System Panel - System Administration
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">System Administration</h2>
        <p class="text-muted mb-0">System settings and maintenance</p>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">System Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <td><strong>PHP Version</strong></td>
                        <td><?php echo phpversion(); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Server</strong></td>
                        <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Database</strong></td>
                        <td>MySQL</td>
                    </tr>
                    <tr>
                        <td><strong>Application Version</strong></td>
                        <td><?php echo APP_VERSION ?? '1.0.0'; ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">System Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-primary">
                        <i class="bi bi-download me-2"></i>Backup Database
                    </button>
                    <button class="btn btn-outline-success">
                        <i class="bi bi-upload me-2"></i>Restore Database
                    </button>
                    <button class="btn btn-outline-info">
                        <i class="bi bi-arrow-clockwise me-2"></i>Clear Cache
                    </button>
                    <button class="btn btn-outline-warning">
                        <i class="bi bi-gear me-2"></i>System Settings
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">System Logs</h5>
    </div>
    <div class="card-body">
        <div class="text-center py-5">
            <i class="bi bi-shield-check text-muted" style="font-size: 3rem;"></i>
            <p class="text-muted mt-2">System monitoring features coming soon</p>
        </div>
    </div>
</div>
