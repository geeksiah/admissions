<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
require_once '../classes/ReceiptManager.php';

// Check admin access
requireRole(['admin', 'admissions_officer']);

$pageTitle = 'Receipt Management';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'Receipt Management', 'url' => '/admin/receipt-management.php']
];

// Initialize receipt manager
$receiptManager = new ReceiptManager($database);

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'generate_receipt':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $paymentId = $_POST['payment_id'];
                    $receiptType = $_POST['receipt_type'];
                    
                    $result = $receiptManager->generateReceipt($paymentId, $receiptType);
                    
                    if ($result['success']) {
                        $message = 'Receipt generated successfully! Receipt Number: ' . $result['receipt_number'];
                        $messageType = 'success';
                    } else {
                        $message = 'Error: ' . $result['error'];
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'resend_receipt':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $receiptId = $_POST['receipt_id'];
                    
                    $result = $receiptManager->resendReceiptEmail($receiptId);
                    
                    if ($result['success']) {
                        $message = $result['message'];
                        $messageType = 'success';
                    } else {
                        $message = 'Error: ' . $result['error'];
                        $messageType = 'danger';
                    }
                }
                break;
        }
    }
}

// Handle file downloads/views
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'download':
            if (isset($_GET['id'])) {
                $receiptManager->downloadReceipt($_GET['id']);
            }
            break;
        case 'view':
            if (isset($_GET['id'])) {
                $receiptManager->viewReceipt($_GET['id']);
            }
            break;
    }
}

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = RECORDS_PER_PAGE;

// Get filter parameters
$filters = [];
if (!empty($_GET['receipt_number'])) {
    $filters['receipt_number'] = $_GET['receipt_number'];
}
if (!empty($_GET['student_name'])) {
    $filters['student_name'] = $_GET['student_name'];
}
if (!empty($_GET['receipt_type'])) {
    $filters['receipt_type'] = $_GET['receipt_type'];
}
if (!empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}

// Get receipts with pagination
$receiptsData = $receiptManager->listReceipts($filters, $page, $limit);
$receipts = $receiptsData['receipts'] ?? [];
$totalPages = $receiptsData['total_pages'] ?? 1;
$totalRecords = $receiptsData['total_records'] ?? 0;

// Get statistics
$statistics = $receiptManager->getReceiptStatistics(30);

// Get recent payments for receipt generation
$stmt = $database->prepare("
    SELECT pt.id, pt.transaction_id, pt.amount, pt.currency, pt.status, pt.paid_at,
           a.application_id, s.first_name, s.last_name, s.email, p.program_name
    FROM payment_transactions pt
    JOIN applications a ON pt.application_id = a.id
    JOIN students s ON a.student_id = s.id
    JOIN programs p ON a.program_id = p.id
    WHERE pt.status = 'completed'
    ORDER BY pt.paid_at DESC
    LIMIT 50
");
$stmt->execute();
$recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $totalRecords; ?></h4>
                        <p class="mb-0">Total Receipts</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-receipt display-4"></i>
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
                        <h4 class="mb-0"><?php echo count($statistics); ?></h4>
                        <p class="mb-0">Receipt Types</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-tags display-4"></i>
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
                        <h4 class="mb-0"><?php echo count($recentPayments); ?></h4>
                        <p class="mb-0">Recent Payments</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-credit-card display-4"></i>
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
                        <h4 class="mb-0"><?php echo array_sum(array_column($statistics, 'total_amount')); ?></h4>
                        <p class="mb-0">Total Amount</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-currency-dollar display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateReceiptModal">
            <i class="bi bi-plus-circle me-2"></i>Generate Receipt
        </button>
    </div>
    <div class="col-md-6">
        <div class="d-flex gap-2 justify-content-end">
            <button class="btn btn-outline-secondary" onclick="exportTableToCSV('receiptsTable', 'receipts.csv')">
                <i class="bi bi-download me-2"></i>Export CSV
            </button>
            <button class="btn btn-outline-secondary" onclick="printPage()">
                <i class="bi bi-printer me-2"></i>Print
            </button>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="receipt_number" class="form-label">Receipt Number</label>
                <input type="text" class="form-control" id="receipt_number" name="receipt_number" 
                       value="<?php echo htmlspecialchars($_GET['receipt_number'] ?? ''); ?>" 
                       placeholder="Receipt number">
            </div>
            <div class="col-md-3">
                <label for="student_name" class="form-label">Student Name</label>
                <input type="text" class="form-control" id="student_name" name="student_name" 
                       value="<?php echo htmlspecialchars($_GET['student_name'] ?? ''); ?>" 
                       placeholder="Student name">
            </div>
            <div class="col-md-2">
                <label for="receipt_type" class="form-label">Type</label>
                <select class="form-select" id="receipt_type" name="receipt_type">
                    <option value="">All Types</option>
                    <option value="payment" <?php echo ($_GET['receipt_type'] ?? '') === 'payment' ? 'selected' : ''; ?>>Payment</option>
                    <option value="refund" <?php echo ($_GET['receipt_type'] ?? '') === 'refund' ? 'selected' : ''; ?>>Refund</option>
                    <option value="adjustment" <?php echo ($_GET['receipt_type'] ?? '') === 'adjustment' ? 'selected' : ''; ?>>Adjustment</option>
                    <option value="other" <?php echo ($_GET['receipt_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label">Date From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" 
                       value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label">Date To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" 
                       value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>">
            </div>
        </form>
    </div>
</div>

<!-- Receipts Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-receipt me-2"></i>Receipts
            <span class="badge bg-primary ms-2"><?php echo $totalRecords; ?></span>
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($receipts)): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="receiptsTable">
                    <thead>
                        <tr>
                            <th>Receipt Number</th>
                            <th>Student</th>
                            <th>Program</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Generated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($receipts as $receipt): ?>
                            <tr>
                                <td>
                                    <code><?php echo htmlspecialchars($receipt['receipt_number']); ?></code>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($receipt['student_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($receipt['student_email']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($receipt['program_name']); ?>
                                </td>
                                <td>
                                    <strong><?php echo $receipt['currency'] . ' ' . number_format($receipt['amount'], 2); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo ucfirst($receipt['receipt_type']); ?></span>
                                </td>
                                <td>
                                    <?php echo formatDate($receipt['generated_at']); ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="?action=view&id=<?php echo $receipt['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="?action=download&id=<?php echo $receipt['id']; ?>" 
                                           class="btn btn-sm btn-outline-success" title="Download">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <button class="btn btn-sm btn-outline-info" 
                                                onclick="resendReceipt(<?php echo $receipt['id']; ?>)"
                                                title="Resend Email">
                                            <i class="bi bi-send"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Receipts pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($filters); ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($filters); ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-receipt display-1 text-muted"></i>
                <h4 class="text-muted mt-3">No Receipts Found</h4>
                <p class="text-muted">No receipts match your current filters.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateReceiptModal">
                    <i class="bi bi-plus-circle me-2"></i>Generate First Receipt
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Generate Receipt Modal -->
<div class="modal fade" id="generateReceiptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="generateReceiptForm">
                <input type="hidden" name="action" value="generate_receipt">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="payment_id" class="form-label">Payment *</label>
                        <select class="form-select" id="payment_id" name="payment_id" required>
                            <option value="">Select Payment</option>
                            <?php foreach ($recentPayments as $payment): ?>
                                <option value="<?php echo $payment['id']; ?>">
                                    <?php echo htmlspecialchars($payment['transaction_id'] . ' - ' . $payment['first_name'] . ' ' . $payment['last_name'] . ' (' . $payment['currency'] . ' ' . number_format($payment['amount'], 2) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="receipt_type" class="form-label">Receipt Type *</label>
                        <select class="form-select" id="receipt_type" name="receipt_type" required>
                            <option value="payment">Payment Receipt</option>
                            <option value="refund">Refund Receipt</option>
                            <option value="adjustment">Adjustment Receipt</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        This will generate a PDF receipt for the selected payment.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-receipt me-2"></i>Generate Receipt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resendReceipt(receiptId) {
    if (confirm('Are you sure you want to resend this receipt via email?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="resend_receipt">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="receipt_id" value="${receiptId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Form validation
document.getElementById('generateReceiptForm').addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('select[required]');
    let hasErrors = false;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            hasErrors = true;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    if (hasErrors) {
        e.preventDefault();
        alert('Please fill in all required fields.');
    }
});
</script>

<?php include '../includes/footer.php'; ?>
