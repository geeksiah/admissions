<?php
/**
 * Overview Panel - Dashboard Statistics and Recent Activity
 */

// Get additional data for overview
try {
    $paymentStats = $paymentModel->getStatistics();
    $monthlyApplications = $reportModel->getApplicationTrends(6);
} catch (Exception $e) {
    $paymentStats = [
        'total_revenue' => 0,
        'pending_payments' => 0,
        'completed_payments' => 0
    ];
    $monthlyApplications = [];
}
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-lg-6 mb-3">
        <div class="stat-card bg-primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($stats['total_applications'] ?? 0); ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-lg-6 mb-3">
        <div class="stat-card bg-warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($stats['pending_applications'] ?? 0); ?></div>
                    <div class="stat-label">Pending Review</div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-clock"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-lg-6 mb-3">
        <div class="stat-card bg-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($stats['approved_applications'] ?? 0); ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-lg-6 mb-3">
        <div class="stat-card bg-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number">$<?php echo number_format($paymentStats['total_revenue'] ?? 0, 0); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-currency-dollar"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <div class="col-lg-8 mb-3">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-graph-up me-2"></i>
                    Application Trends
                </h5>
            </div>
            <div class="card-body">
                <canvas id="applicationTrendsChart" height="100"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 mb-3">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-pie-chart me-2"></i>
                    Application Status
                </h5>
            </div>
            <div class="card-body">
                <canvas id="applicationStatusChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="row">
    <div class="col-lg-6 mb-3">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    Recent Applications
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($recentApplications)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">No recent applications</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentApplications as $app): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($app['program_name'] ?? 'Unknown Program'); ?></small>
                                    <br>
                                    <small class="text-muted">
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
                                ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="#" class="btn btn-outline-primary btn-sm" data-panel="applications">
                            View All Applications
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6 mb-3">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-people me-2"></i>
                    Recent Students
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($recentStudents)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-person text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">No recent students</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentStudents as $student): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                    <br>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar me-1"></i>
                                        <?php echo date('M j, Y', strtotime($student['created_at'])); ?>
                                    </small>
                                </div>
                                <span class="badge bg-<?php echo ($student['status'] ?? 'active') === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($student['status'] ?? 'active'); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="#" class="btn btn-outline-primary btn-sm" data-panel="students">
                            View All Students
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Popular Programs -->
<?php if (!empty($popularPrograms)): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-mortarboard me-2"></i>
                    Popular Programs
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($popularPrograms as $program): ?>
                        <div class="col-md-4 mb-3">
                            <div class="d-flex align-items-center p-3 border rounded">
                                <div class="me-3">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                        <i class="bi bi-mortarboard"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($program['program_name']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($program['degree_level']); ?></small>
                                    <br>
                                    <span class="badge bg-primary">
                                        <?php echo number_format($program['application_count']); ?> applications
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="#" class="btn btn-outline-primary" data-panel="programs">
                        View All Programs
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Initialize Charts
document.addEventListener('DOMContentLoaded', function() {
    // Application Trends Chart
    const trendsCtx = document.getElementById('applicationTrendsChart').getContext('2d');
    new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Applications',
                data: [12, 19, 3, 5, 2, 3],
                borderColor: 'rgb(102, 126, 234)',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    
    // Application Status Chart
    const statusCtx = document.getElementById('applicationStatusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Approved', 'Pending', 'Under Review', 'Rejected'],
            datasets: [{
                data: [
                    <?php echo $stats['approved_applications'] ?? 0; ?>,
                    <?php echo $stats['pending_applications'] ?? 0; ?>,
                    <?php echo $stats['under_review_applications'] ?? 0; ?>,
                    <?php echo $stats['rejected_applications'] ?? 0; ?>
                ],
                backgroundColor: [
                    '#10b981',
                    '#f59e0b',
                    '#06b6d4',
                    '#ef4444'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                }
            }
        }
    });
});
</script>
