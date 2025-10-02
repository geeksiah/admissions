<?php
/**
 * Performance Monitor Dashboard
 */

session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Security.php';
require_once '../classes/PerformanceMonitor.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$security = new Security($database);
$performanceMonitor = new PerformanceMonitor();

// Get performance data
$currentPerformance = $performanceMonitor->getSummary();
$statistics = $performanceMonitor->getStatistics(30); // Last 30 days
$alerts = $performanceMonitor->checkPerformanceThresholds();
$report = $performanceMonitor->generateReport();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Performance Monitor</h1>
                    <p class="text-muted">System performance metrics and monitoring</p>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="refreshPerformance()">
                        <i class="bi bi-arrow-clockwise me-2"></i>
                        Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Alerts -->
    <?php if (!empty($alerts)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-warning">
                    <h6><i class="bi bi-exclamation-triangle me-2"></i>Performance Alerts</h6>
                    <ul class="mb-0">
                        <?php foreach ($alerts as $alert): ?>
                            <li><?php echo htmlspecialchars($alert); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Current Performance Metrics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo $currentPerformance['total_execution_time']; ?>s</h4>
                            <p class="mb-0">Execution Time</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-stopwatch" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo $currentPerformance['total_memory_used']; ?></h4>
                            <p class="mb-0">Memory Used</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-memory" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo $currentPerformance['total_queries']; ?></h4>
                            <p class="mb-0">Database Queries</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-database" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo $currentPerformance['slow_queries']; ?></h4>
                            <p class="mb-0">Slow Queries</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-exclamation-triangle" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Query Performance -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-database me-2"></i>
                        Database Query Performance
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($currentPerformance['queries'])): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Query</th>
                                        <th>Duration</th>
                                        <th>Results</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($currentPerformance['queries'] as $query): ?>
                                        <tr class="<?php echo $query['duration'] > 0.1 ? 'table-warning' : ''; ?>">
                                            <td>
                                                <small><?php echo htmlspecialchars(substr($query['sql'], 0, 50)) . '...'; ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $query['duration'] > 0.1 ? 'warning' : 'success'; ?>">
                                                    <?php echo round($query['duration'], 4); ?>s
                                                </span>
                                            </td>
                                            <td><?php echo $query['result_count']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No queries executed yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Performance Statistics -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-graph-up me-2"></i>
                        Performance Statistics (30 Days)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($statistics)): ?>
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-3">
                                    <small class="text-muted">Average Execution Time</small>
                                    <h6><?php echo $statistics['average_execution_time']; ?>s</h6>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <small class="text-muted">Max Execution Time</small>
                                    <h6><?php echo $statistics['max_execution_time']; ?>s</h6>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <small class="text-muted">Average Queries/Request</small>
                                    <h6><?php echo $statistics['average_queries_per_request']; ?></h6>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <small class="text-muted">Total Requests</small>
                                    <h6><?php echo $statistics['total_requests']; ?></h6>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <small class="text-muted">Performance Trend</small>
                                    <h6>
                                        <span class="badge bg-<?php echo $statistics['performance_trend'] === 'improving' ? 'success' : ($statistics['performance_trend'] === 'degrading' ? 'danger' : 'info'); ?>">
                                            <?php echo ucfirst($statistics['performance_trend']); ?>
                                        </span>
                                    </h6>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No performance data available yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recommendations -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightbulb me-2"></i>
                        Performance Recommendations
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($report['recommendations'])): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($report['recommendations'] as $recommendation): ?>
                                <li class="list-group-item">
                                    <i class="bi bi-arrow-right me-2"></i>
                                    <?php echo htmlspecialchars($recommendation); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">No recommendations at this time.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function refreshPerformance() {
    location.reload();
}

// Auto-refresh every 30 seconds
setInterval(function() {
    // Only refresh if no user interaction in the last 30 seconds
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 30000);
</script>

<?php include '../includes/footer.php'; ?>
