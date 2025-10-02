<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
$reportModel = new Report($database);
$programModel = new Program($database);

// Check admin access
requireRole(['admin', 'admissions_officer']);

$pageTitle = 'Reports & Analytics';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'Reports', 'url' => '/admin/reports.php']
];

// Get report type
$reportType = $_GET['report'] ?? 'application_statistics';

// Get filters
$filters = [];
if (!empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}
if (!empty($_GET['program_id'])) {
    $filters['program_id'] = $_GET['program_id'];
}
if (!empty($_GET['year'])) {
    $filters['year'] = $_GET['year'];
}

// Get report data
$reportData = null;
$reportTitle = '';

switch ($reportType) {
    case 'application_statistics':
        $reportData = $reportModel->getApplicationStatistics($filters);
        $reportTitle = 'Application Statistics';
        break;
    case 'applications_by_program':
        $reportData = $reportModel->getApplicationsByProgram($filters);
        $reportTitle = 'Applications by Program';
        break;
    case 'applications_by_status':
        $reportData = $reportModel->getApplicationsByStatus($filters);
        $reportTitle = 'Applications by Status';
        break;
    case 'monthly_trends':
        $reportData = $reportModel->getMonthlyApplicationTrends($filters);
        $reportTitle = 'Monthly Application Trends';
        break;
    case 'student_demographics':
        $reportData = $reportModel->getStudentDemographics($filters);
        $reportTitle = 'Student Demographics';
        break;
    case 'payment_statistics':
        $reportData = $reportModel->getPaymentStatistics($filters);
        $reportTitle = 'Payment Statistics';
        break;
    case 'voucher_usage':
        $reportData = $reportModel->getVoucherUsageReport($filters);
        $reportTitle = 'Voucher Usage Report';
        break;
    case 'reviewer_performance':
        $reportData = $reportModel->getReviewerPerformance($filters);
        $reportTitle = 'Reviewer Performance';
        break;
    case 'system_activity':
        $reportData = $reportModel->getSystemActivity($filters);
        $reportTitle = 'System Activity';
        break;
}

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $reportData) {
    $filename = str_replace(' ', '_', strtolower($reportTitle)) . '_' . date('Y-m-d') . '.csv';
    $reportModel->exportToCSV($reportData, $filename);
    exit;
}

// Get programs for filter
$programs = $programModel->getActive();

// Get available report types
$reportTypes = $reportModel->getReportTypes();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h5 class="mb-0">
            <i class="bi bi-graph-up me-2"></i><?php echo $reportTitle; ?>
        </h5>
    </div>
    <div class="col-md-6">
        <div class="d-flex gap-2 justify-content-end">
            <?php if ($reportData && !empty($reportData)): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" 
                   class="btn btn-outline-success">
                    <i class="bi bi-download me-2"></i>Export CSV
                </a>
            <?php endif; ?>
            <button class="btn btn-outline-secondary" onclick="printPage()">
                <i class="bi bi-printer me-2"></i>Print
            </button>
        </div>
    </div>
</div>

<!-- Report Type Selection -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="report" class="form-label">Report Type</label>
                <select class="form-select" id="report" name="report" onchange="this.form.submit()">
                    <?php foreach ($reportTypes as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $reportType === $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label">From Date</label>
                <input type="date" class="form-control" id="date_from" name="date_from" 
                       value="<?php echo $_GET['date_from'] ?? ''; ?>">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label">To Date</label>
                <input type="date" class="form-control" id="date_to" name="date_to" 
                       value="<?php echo $_GET['date_to'] ?? ''; ?>">
            </div>
            <?php if (in_array($reportType, ['applications_by_program', 'application_statistics'])): ?>
            <div class="col-md-3">
                <label for="program_id" class="form-label">Program</label>
                <select class="form-select" id="program_id" name="program_id">
                    <option value="">All Programs</option>
                    <?php foreach ($programs as $program): ?>
                        <option value="<?php echo $program['id']; ?>" 
                                <?php echo ($_GET['program_id'] ?? '') == $program['id'] ? 'selected' : ''; ?>>
                            <?php echo $program['program_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php if ($reportType === 'monthly_trends'): ?>
            <div class="col-md-2">
                <label for="year" class="form-label">Year</label>
                <select class="form-select" id="year" name="year">
                    <option value="">All Years</option>
                    <?php for ($year = date('Y'); $year >= date('Y') - 5; $year--): ?>
                        <option value="<?php echo $year; ?>" <?php echo ($_GET['year'] ?? '') == $year ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Report Content -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-bar-chart me-2"></i><?php echo $reportTitle; ?>
            <?php if ($reportData && is_array($reportData) && count($reportData) > 0): ?>
                <span class="badge bg-primary ms-2"><?php echo count($reportData); ?> records</span>
            <?php endif; ?>
        </h5>
    </div>
    <div class="card-body">
        <?php if ($reportData): ?>
            <?php if ($reportType === 'application_statistics'): ?>
                <!-- Application Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h3><?php echo number_format($reportData['total_applications'] ?? 0); ?></h3>
                                <p class="mb-0">Total Applications</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h3><?php echo number_format($reportData['approved_applications'] ?? 0); ?></h3>
                                <p class="mb-0">Approved</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h3><?php echo number_format($reportData['rejected_applications'] ?? 0); ?></h3>
                                <p class="mb-0">Rejected</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h3><?php echo number_format($reportData['approval_rate'] ?? 0, 1); ?>%</h3>
                                <p class="mb-0">Approval Rate</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped">
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
                            <tr>
                                <td>Waitlisted</td>
                                <td><?php echo number_format($reportData['waitlisted_applications'] ?? 0); ?></td>
                                <td><?php echo $reportData['total_applications'] > 0 ? number_format((($reportData['waitlisted_applications'] ?? 0) / $reportData['total_applications']) * 100, 1) : '0.0'; ?>%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
            <?php elseif ($reportType === 'applications_by_program'): ?>
                <!-- Applications by Program -->
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Program</th>
                                <th>Department</th>
                                <th>Degree Level</th>
                                <th>Total Applications</th>
                                <th>Approved</th>
                                <th>Rejected</th>
                                <th>Approval Rate</th>
                                <th>Capacity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $row['program_name']; ?></strong>
                                        <br><small class="text-muted"><?php echo $row['program_code']; ?></small>
                                    </td>
                                    <td><?php echo $row['department']; ?></td>
                                    <td><?php echo ucfirst($row['degree_level']); ?></td>
                                    <td><?php echo number_format($row['total_applications']); ?></td>
                                    <td><?php echo number_format($row['approved_applications']); ?></td>
                                    <td><?php echo number_format($row['rejected_applications']); ?></td>
                                    <td><?php echo number_format($row['approval_rate'], 1); ?>%</td>
                                    <td><?php echo $row['current_enrolled'] . '/' . ($row['max_capacity'] ?? '∞'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
            <?php elseif ($reportType === 'payment_statistics'): ?>
                <!-- Payment Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h3><?php echo number_format($reportData['total_transactions']); ?></h3>
                                <p class="mb-0">Total Transactions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h3><?php echo formatCurrency($reportData['total_revenue']); ?></h3>
                                <p class="mb-0">Total Revenue</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h3><?php echo formatCurrency($reportData['average_payment']); ?></h3>
                                <p class="mb-0">Average Payment</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h3><?php echo number_format(($reportData['completed_transactions'] / $reportData['total_transactions']) * 100, 1); ?>%</h3>
                                <p class="mb-0">Success Rate</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Payment Method</th>
                                <th>Count</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Credit Card</td>
                                <td><?php echo number_format($reportData['credit_card_payments']); ?></td>
                                <td><?php echo number_format(($reportData['credit_card_payments'] / $reportData['total_transactions']) * 100, 1); ?>%</td>
                            </tr>
                            <tr>
                                <td>Bank Transfer</td>
                                <td><?php echo number_format($reportData['bank_transfer_payments']); ?></td>
                                <td><?php echo number_format(($reportData['bank_transfer_payments'] / $reportData['total_transactions']) * 100, 1); ?>%</td>
                            </tr>
                            <tr>
                                <td>Cash</td>
                                <td><?php echo number_format($reportData['cash_payments']); ?></td>
                                <td><?php echo number_format(($reportData['cash_payments'] / $reportData['total_transactions']) * 100, 1); ?>%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
            <?php elseif ($reportType === 'student_demographics'): ?>
                <!-- Student Demographics -->
                <div class="row">
                    <div class="col-md-4">
                        <h6>Gender Distribution</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Gender</th>
                                        <th>Count</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['gender'] as $row): ?>
                                        <tr>
                                            <td><?php echo ucfirst($row['gender']); ?></td>
                                            <td><?php echo number_format($row['count']); ?></td>
                                            <td><?php echo number_format($row['percentage'], 1); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <h6>Age Distribution</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Age Group</th>
                                        <th>Count</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['age'] as $row): ?>
                                        <tr>
                                            <td><?php echo $row['age_group']; ?></td>
                                            <td><?php echo number_format($row['count']); ?></td>
                                            <td><?php echo number_format($row['percentage'], 1); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <h6>Top Nationalities</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Nationality</th>
                                        <th>Count</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['nationality'] as $row): ?>
                                        <tr>
                                            <td><?php echo $row['nationality']; ?></td>
                                            <td><?php echo number_format($row['count']); ?></td>
                                            <td><?php echo number_format($row['percentage'], 1); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Generic table for other reports -->
                <?php if (is_array($reportData) && !empty($reportData)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <?php foreach (array_keys($reportData[0]) as $header): ?>
                                        <th><?php echo ucwords(str_replace('_', ' ', $header)); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $value): ?>
                                            <td><?php echo is_numeric($value) ? number_format($value) : htmlspecialchars($value); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-graph-up display-1 text-muted"></i>
                        <h4 class="text-muted mt-3">No Data Available</h4>
                        <p class="text-muted">No data found for the selected criteria.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-graph-up display-1 text-muted"></i>
                <h4 class="text-muted mt-3">No Data Available</h4>
                <p class="text-muted">No data found for the selected criteria.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Set default date range to current year
document.addEventListener('DOMContentLoaded', function() {
    const currentYear = new Date().getFullYear();
    const startOfYear = currentYear + '-01-01';
    const endOfYear = currentYear + '-12-31';
    
    if (!document.getElementById('date_from').value) {
        document.getElementById('date_from').value = startOfYear;
    }
    if (!document.getElementById('date_to').value) {
        document.getElementById('date_to').value = endOfYear;
    }
});
</script>

<?php include '../includes/footer.php'; ?>
