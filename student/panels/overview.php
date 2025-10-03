<?php
// Get recent applications (last 5)
$recentApplications = array_slice($applications, 0, 5);
?>

<!-- Statistics Cards -->
<div class="row mb-3">
    <div class="col-xl-3 col-lg-6 col-md-6 mb-3">
        <div class="stat-card bg-primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-lg-6 col-md-6 mb-3">
        <div class="stat-card bg-warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($stats['pending']); ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-clock"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-lg-6 col-md-6 mb-3">
        <div class="stat-card bg-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($stats['approved']); ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-lg-6 col-md-6 mb-3">
        <div class="stat-card bg-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($stats['under_review']); ?></div>
                    <div class="stat-label">Under Review</div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-eye"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Applications -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    Recent Applications
                </h5>
            </div>
            <div class="card-body" style="padding: 1rem;">
                <?php if (empty($recentApplications)): ?>
                    <div class="text-center py-3">
                        <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
                        <p class="text-muted mt-2 mb-0">No applications yet</p>
                        <a href="#" class="btn btn-primary btn-sm mt-2" data-panel="applications">
                            <i class="bi bi-plus-circle me-2"></i>Start New Application
                        </a>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentApplications as $app): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0 py-2">
                                <div>
                                    <h6 class="mb-1" style="font-size: 0.875rem;">
                                        <?php echo htmlspecialchars($app['program_name'] ?? 'Unknown Program'); ?>
                                    </h6>
                                    <small class="text-muted" style="font-size: 0.75rem;">
                                        <i class="bi bi-calendar me-1"></i>
                                        <?php echo date('M j, Y', strtotime($app['created_at'])); ?>
                                    </small>
                                </div>
                                <span class="badge bg-<?php 
                                    switch($app['status']) {
                                        case 'approved': echo 'success'; break;
                                        case 'rejected': echo 'danger'; break;
                                        case 'pending': echo 'warning'; break;
                                        case 'under_review': echo 'info'; break;
                                        default: echo 'secondary';
                                    }
                                ?>" style="font-size: 0.625rem;">
                                    <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-2">
                        <a href="#" class="btn btn-outline-primary btn-sm" data-panel="applications" style="font-size: 0.75rem;">
                            View All Applications
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row">
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-plus-circle me-2"></i>
                    Quick Actions
                </h5>
            </div>
            <div class="card-body" style="padding: 1rem;">
                <div class="d-grid gap-2">
                    <a href="#" class="btn btn-primary btn-sm" data-panel="applications">
                        <i class="bi bi-file-earmark-plus me-2"></i>New Application
                    </a>
                    <a href="#" class="btn btn-outline-primary btn-sm" data-panel="programs">
                        <i class="bi bi-book me-2"></i>Browse Programs
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    Application Status Guide
                </h5>
            </div>
            <div class="card-body" style="padding: 1rem;">
                <div class="small">
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge bg-warning me-2" style="font-size: 0.625rem;">Pending</span>
                        <span style="font-size: 0.75rem;">Awaiting review</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge bg-info me-2" style="font-size: 0.625rem;">Under Review</span>
                        <span style="font-size: 0.75rem;">Being evaluated</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge bg-success me-2" style="font-size: 0.625rem;">Approved</span>
                        <span style="font-size: 0.75rem;">Application accepted</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-danger me-2" style="font-size: 0.625rem;">Rejected</span>
                        <span style="font-size: 0.75rem;">Application declined</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
