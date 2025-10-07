<?php
/**
 * Simplified Applications Panel - Safe for Navigation Testing
 */

// Get basic application data safely
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM applications");
    $stmt->execute();
    $totalApplications = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM applications WHERE status = 'pending'");
    $stmt->execute();
    $pendingApplications = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as approved FROM applications WHERE status = 'approved'");
    $stmt->execute();
    $approvedApplications = $stmt->fetchColumn();
    
} catch (Exception $e) {
    error_log("Applications query failed: " . $e->getMessage());
    $totalApplications = 0;
    $pendingApplications = 0;
    $approvedApplications = 0;
}
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="mb-0">Applications Management</h2>
            <button class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>
                Add Application
            </button>
        </div>
    </div>
</div>

<!-- Quick Stats -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h3 class="text-primary"><?php echo $totalApplications; ?></h3>
                <p class="text-muted mb-0">Total Applications</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-warning">
            <div class="card-body text-center">
                <h3 class="text-warning"><?php echo $pendingApplications; ?></h3>
                <p class="text-muted mb-0">Pending Review</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-body text-center">
                <h3 class="text-success"><?php echo $approvedApplications; ?></h3>
                <p class="text-muted mb-0">Approved</p>
            </div>
        </div>
    </div>
</div>

<!-- Applications Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-list-ul me-2"></i>
            Applications List
        </h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <h5>Applications Panel - Simplified Version</h5>
            <p>This is a simplified version of the applications panel for navigation testing.</p>
            <p>Navigation is working! You can now gradually restore the full panel content.</p>
            
            <div class="mt-3">
                <button class="btn btn-success me-2" onclick="alert('Applications panel is working!');">
                    <i class="bi bi-check-circle me-2"></i>
                    Test Panel Functionality
                </button>
                <button class="btn btn-info" onclick="showPanel('overview');">
                    <i class="bi bi-arrow-left me-2"></i>
                    Back to Overview
                </button>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Application ID</th>
                        <th>Student Name</th>
                        <th>Program</th>
                        <th>Status</th>
                        <th>Date Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-inbox display-4 d-block mb-2"></i>
                            No applications found. This is expected for a new system.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Panel-specific JavaScript
console.log('Applications panel loaded successfully');
</script>
