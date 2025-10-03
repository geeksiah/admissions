<?php
/**
 * Reports Panel - Analytics and Reporting
 */

try {
    $reportData = $reportModel->getApplicationStatistics();
    $paymentData = $reportModel->getPaymentStatistics();
} catch (Exception $e) {
    $reportData = [];
    $paymentData = [];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">Reports & Analytics</h2>
        <p class="text-muted mb-0">View detailed reports and analytics</p>
    </div>
    <div>
        <button class="btn btn-outline-primary me-2">
            <i class="bi bi-download me-2"></i>Export CSV
        </button>
        <button class="btn btn-primary">
            <i class="bi bi-printer me-2"></i>Print Report
        </button>
    </div>
</div>

<!-- Application Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number"><?php echo number_format($reportData['total_applications'] ?? 0); ?></div>
                        <div class="stat-label">Total Applications</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number"><?php echo number_format($reportData['approved_applications'] ?? 0); ?></div>
                        <div class="stat-label">Approved</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-danger text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number"><?php echo number_format($reportData['rejected_applications'] ?? 0); ?></div>
                        <div class="stat-label">Rejected</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-x-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number"><?php echo number_format($reportData['approval_rate'] ?? 0, 1); ?>%</div>
                        <div class="stat-label">Approval Rate</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-percent"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Application Trends</h5>
            </div>
            <div class="card-body">
                <canvas id="applicationTrendsChart" height="100"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Payment Methods</h5>
            </div>
            <div class="card-body">
                <canvas id="paymentMethodsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Statistics -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Detailed Statistics</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Count</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Submitted</td>
                        <td><?php echo number_format($reportData['submitted_applications'] ?? 0); ?></td>
                        <td><?php echo $reportData['total_applications'] > 0 ? number_format((($reportData['submitted_applications'] ?? 0) / $reportData['total_applications']) * 100, 1) : '0.0'; ?>%</td>
                    </tr>
                    <tr>
                        <td>Under Review</td>
                        <td><?php echo number_format($reportData['under_review_applications'] ?? 0); ?></td>
                        <td><?php echo $reportData['total_applications'] > 0 ? number_format((($reportData['under_review_applications'] ?? 0) / $reportData['total_applications']) * 100, 1) : '0.0'; ?>%</td>
                    </tr>
                    <tr>
                        <td>Approved</td>
                        <td><?php echo number_format($reportData['approved_applications'] ?? 0); ?></td>
                        <td><?php echo $reportData['total_applications'] > 0 ? number_format((($reportData['approved_applications'] ?? 0) / $reportData['total_applications']) * 100, 1) : '0.0'; ?>%</td>
                    </tr>
                    <tr>
                        <td>Rejected</td>
                        <td><?php echo number_format($reportData['rejected_applications'] ?? 0); ?></td>
                        <td><?php echo $reportData['total_applications'] > 0 ? number_format((($reportData['rejected_applications'] ?? 0) / $reportData['total_applications']) * 100, 1) : '0.0'; ?>%</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
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
                    beginAtZero: true
                }
            }
        }
    });
    
    // Payment Methods Chart
    const paymentCtx = document.getElementById('paymentMethodsChart').getContext('2d');
    new Chart(paymentCtx, {
        type: 'doughnut',
        data: {
            labels: ['Credit Card', 'Bank Transfer', 'Mobile Money', 'Cash'],
            datasets: [{
                data: [45, 25, 20, 10],
                backgroundColor: [
                    '#667eea',
                    '#764ba2',
                    '#10b981',
                    '#f59e0b'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
</script>
