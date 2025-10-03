<?php
/**
 * Payments Panel - Payment Management
 */

// Get payments with pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    $payments = $paymentModel->getAll($limit, $offset);
    $totalPayments = $paymentModel->getTotalCount();
    $totalPages = ceil($totalPayments / $limit);
} catch (Exception $e) {
    $payments = [];
    $totalPayments = 0;
    $totalPages = 0;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">Payment Management</h2>
        <p class="text-muted mb-0">Manage payment transactions</p>
    </div>
    <div>
        <button class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>Record Payment
        </button>
    </div>
</div>

<!-- Payment Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
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
    
    <div class="col-md-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number"><?php echo number_format($paymentStats['completed_payments'] ?? 0); ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-number"><?php echo number_format($paymentStats['pending_payments'] ?? 0); ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-clock"></i>
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
                        <div class="stat-number"><?php echo number_format($totalPayments); ?></div>
                        <div class="stat-label">Total Payments</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-credit-card"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Payments (<?php echo number_format($totalPayments); ?>)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($payments)): ?>
            <div class="text-center py-5">
                <i class="bi bi-credit-card text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-2">No payments found</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Student</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td>
                                    <strong>#<?php echo str_pad($payment['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($payment['student_name'] ?? 'Unknown'); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($payment['student_email'] ?? ''); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <strong>$<?php echo number_format($payment['amount'], 2); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_type'] ?? 'Unknown')); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($payment['payment_method'] ?? 'Unknown'); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        switch($payment['status']) {
                                            case 'completed': echo 'success'; break;
                                            case 'pending': echo 'warning'; break;
                                            case 'failed': echo 'danger'; break;
                                            default: echo 'secondary';
                                        }
                                    ?>">
                                        <?php echo ucfirst($payment['status'] ?? 'Unknown'); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y H:i', strtotime($payment['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" title="View">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-success" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
