<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
$voucherModel = new Voucher($database);
$programModel = new Program($database);
$userModel = new User($database);

// Check admin access
requireRole(['admin', 'admissions_officer']);

$pageTitle = 'Voucher Management';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'Vouchers', 'url' => '/admin/vouchers.php']
];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_voucher':
                // Create new voucher
                $validator = new Validator($_POST);
                $validator->required(['voucher_type', 'discount_value', 'valid_from', 'valid_until'])
                         ->numeric('discount_value')
                         ->date('valid_from')
                         ->date('valid_until');
                
                if (!$validator->hasErrors()) {
                    $data = $validator->getValidatedData();
                    $data['created_by'] = $_SESSION['user_id'];
                    
                    // Check if voucher code already exists (if provided)
                    if (!empty($data['voucher_code']) && $voucherModel->codeExists($data['voucher_code'])) {
                        $message = 'Voucher code already exists.';
                        $messageType = 'danger';
                    } else {
                        if ($voucherModel->create($data)) {
                            $message = 'Voucher created successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to create voucher. Please try again.';
                            $messageType = 'danger';
                        }
                    }
                } else {
                    $message = 'Please correct the errors below.';
                    $messageType = 'danger';
                }
                break;
                
            case 'create_bulk_vouchers':
                // Create vouchers in bulk
                $validator = new Validator($_POST);
                $validator->required(['voucher_type', 'discount_value', 'valid_from', 'valid_until', 'quantity'])
                         ->numeric('discount_value')
                         ->numeric('quantity')
                         ->date('valid_from')
                         ->date('valid_until');
                
                if (!$validator->hasErrors()) {
                    $data = $validator->getValidatedData();
                    $data['created_by'] = $_SESSION['user_id'];
                    $quantity = (int)$data['quantity'];
                    
                    if ($quantity > 0 && $quantity <= 1000) { // Limit bulk creation to 1000 vouchers
                        $result = $voucherModel->createBulk($data, $quantity);
                        if ($result['success']) {
                            $message = "Successfully created {$result['created_count']} vouchers!";
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to create vouchers: ' . implode(', ', $result['errors'] ?? ['Unknown error']);
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'Quantity must be between 1 and 1000.';
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Please correct the errors below.';
                    $messageType = 'danger';
                }
                break;
                
            case 'update_voucher':
                // Update voucher
                $voucherId = $_POST['voucher_id'];
                $validator = new Validator($_POST);
                $validator->required(['voucher_code', 'voucher_type', 'discount_value'])
                         ->numeric('discount_value');
                
                if (!$validator->hasErrors()) {
                    $data = $validator->getValidatedData();
                    
                    // Check if voucher code exists for other vouchers
                    if (!$voucherModel->codeExists($data['voucher_code'], $voucherId)) {
                        if ($voucherModel->update($voucherId, $data)) {
                            $message = 'Voucher updated successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to update voucher. Please try again.';
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'Voucher code already exists for another voucher.';
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Please correct the errors below.';
                    $messageType = 'danger';
                }
                break;
                
            case 'delete_voucher':
                // Delete voucher
                $voucherId = $_POST['voucher_id'];
                if ($voucherModel->delete($voucherId)) {
                    $message = 'Voucher deleted successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete voucher. Please try again.';
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = RECORDS_PER_PAGE;

// Get filter parameters
$filters = [];
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (!empty($_GET['voucher_type'])) {
    $filters['voucher_type'] = $_GET['voucher_type'];
}

// Get vouchers with pagination
$vouchersData = $voucherModel->getAll($page, $limit, $filters);
$vouchers = $vouchersData['vouchers'] ?? [];
$totalPages = $vouchersData['pages'] ?? 1;

// Get programs for filter
$programs = $programModel->getActive();

// Get voucher statistics
$statistics = $voucherModel->getStatistics();

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
                        <h4 class="mb-0"><?php echo $statistics['total_vouchers'] ?? 0; ?></h4>
                        <p class="mb-0">Total Vouchers</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-ticket-perforated display-4"></i>
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
                        <h4 class="mb-0"><?php echo $statistics['active_vouchers'] ?? 0; ?></h4>
                        <p class="mb-0">Active Vouchers</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-check-circle display-4"></i>
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
                        <h4 class="mb-0"><?php echo $statistics['total_uses'] ?? 0; ?></h4>
                        <p class="mb-0">Total Uses</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-graph-up display-4"></i>
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
                        <h4 class="mb-0"><?php echo formatCurrency($statistics['total_discount_given'] ?? 0); ?></h4>
                        <p class="mb-0">Total Discount Given</p>
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
        <div class="btn-group" role="group">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createVoucherModal">
                <i class="bi bi-plus-circle me-2"></i>Create New Voucher
            </button>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkCreateModal">
                <i class="bi bi-plus-square me-2"></i>Bulk Create
            </button>
        </div>
    </div>
    <div class="col-md-6">
        <div class="d-flex gap-2 justify-content-end">
            <button class="btn btn-outline-secondary" onclick="exportTableToCSV('vouchersTable', 'vouchers.csv')">
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
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                       placeholder="Voucher code or description">
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo ($_GET['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($_GET['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="expired" <?php echo ($_GET['status'] ?? '') === 'expired' ? 'selected' : ''; ?>>Expired</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="voucher_type" class="form-label">Type</label>
                <select class="form-select" id="voucher_type" name="voucher_type">
                    <option value="">All Types</option>
                    <option value="percentage" <?php echo ($_GET['voucher_type'] ?? '') === 'percentage' ? 'selected' : ''; ?>>Percentage</option>
                    <option value="fixed_amount" <?php echo ($_GET['voucher_type'] ?? '') === 'fixed_amount' ? 'selected' : ''; ?>>Fixed Amount</option>
                    <option value="full_waiver" <?php echo ($_GET['voucher_type'] ?? '') === 'full_waiver' ? 'selected' : ''; ?>>Full Waiver</option>
                </select>
            </div>
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

<!-- Vouchers Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-ticket-perforated me-2"></i>Vouchers
            <span class="badge bg-primary ms-2"><?php echo $vouchersData['total'] ?? 0; ?></span>
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($vouchers)): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="vouchersTable">
                    <thead>
                        <tr>
                            <th>Voucher Code</th>
                            <th>PIN / SERIAL</th>
                            <th>Type</th>
                            <th>Discount Value</th>
                            <th>Usage</th>
                            <th>Valid Period</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vouchers as $voucher): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $voucher['voucher_code']; ?></strong>
                                    <?php if ($voucher['description']): ?>
                                        <br><small class="text-muted"><?php echo $voucher['description']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small">
                                        <strong>PIN:</strong> <?php echo $voucher['pin']; ?><br>
                                        <strong>SERIAL:</strong> <?php echo $voucher['serial']; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $voucher['voucher_type'] === 'percentage' ? 'info' : ($voucher['voucher_type'] === 'fixed_amount' ? 'warning' : 'success'); ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $voucher['voucher_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    if ($voucher['voucher_type'] === 'percentage') {
                                        echo $voucher['discount_value'] . '%';
                                    } elseif ($voucher['voucher_type'] === 'fixed_amount') {
                                        echo formatCurrency($voucher['discount_value']);
                                    } else {
                                        echo '100%';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php echo $voucher['used_count'] . '/' . $voucher['max_uses']; ?>
                                </td>
                                <td>
                                    <small>
                                        <?php echo formatDate($voucher['valid_from']); ?><br>
                                        to <?php echo formatDate($voucher['valid_until']); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = 'secondary';
                                    if ($voucher['status'] === 'active') {
                                        $now = date('Y-m-d');
                                        if ($voucher['valid_from'] <= $now && $voucher['valid_until'] >= $now) {
                                            $statusClass = 'success';
                                        } else {
                                            $statusClass = 'warning';
                                        }
                                    } elseif ($voucher['status'] === 'inactive') {
                                        $statusClass = 'secondary';
                                    } else {
                                        $statusClass = 'danger';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                        <?php echo ucfirst($voucher['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $voucher['created_by_first_name'] . ' ' . $voucher['created_by_last_name']; ?>
                                    <br><small class="text-muted"><?php echo formatDate($voucher['created_at']); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="viewVoucher(<?php echo $voucher['id']; ?>)"
                                                title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning" 
                                                onclick="editVoucher(<?php echo $voucher['id']; ?>)"
                                                title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-info" 
                                                onclick="viewUsage(<?php echo $voucher['id']; ?>)"
                                                title="View Usage">
                                            <i class="bi bi-graph-up"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteVoucher(<?php echo $voucher['id']; ?>)"
                                                title="Delete">
                                            <i class="bi bi-trash"></i>
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
                <nav aria-label="Vouchers pagination">
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
                <i class="bi bi-ticket-perforated display-1 text-muted"></i>
                <h4 class="text-muted mt-3">No Vouchers Found</h4>
                <p class="text-muted">No vouchers match your current filters.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createVoucherModal">
                    <i class="bi bi-plus-circle me-2"></i>Create First Voucher
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Voucher Modal -->
<div class="modal fade" id="createVoucherModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Voucher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="createVoucherForm">
                <input type="hidden" name="action" value="create_voucher">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="voucher_code" class="form-label">Voucher Code *</label>
                                <input type="text" class="form-control" id="voucher_code" name="voucher_code" required>
                                <div class="form-text">Leave empty to auto-generate</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="voucher_type" class="form-label">Voucher Type *</label>
                                <select class="form-select" id="voucher_type" name="voucher_type" required>
                                    <option value="">Select Type</option>
                                    <option value="percentage">Percentage Discount</option>
                                    <option value="fixed_amount">Fixed Amount Discount</option>
                                    <option value="full_waiver">Full Waiver</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="discount_value" class="form-label">Discount Value *</label>
                                <input type="number" class="form-control" id="discount_value" name="discount_value" 
                                       step="0.01" min="0" required>
                                <div class="form-text">Percentage (1-100) or fixed amount in dollars</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="max_uses" class="form-label">Maximum Uses</label>
                                <input type="number" class="form-control" id="max_uses" name="max_uses" min="1" value="1">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="valid_from" class="form-label">Valid From *</label>
                                <input type="date" class="form-control" id="valid_from" name="valid_from" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="valid_until" class="form-label">Valid Until *</label>
                                <input type="date" class="form-control" id="valid_until" name="valid_until" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Voucher</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Create Vouchers Modal -->
<div class="modal fade" id="bulkCreateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Create Vouchers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="bulkCreateForm">
                <input type="hidden" name="action" value="create_bulk_vouchers">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        This will create multiple vouchers with unique PIN and SERIAL numbers. Each voucher will have the same settings but unique identifiers.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bulk_quantity" class="form-label">Quantity *</label>
                                <input type="number" class="form-control" id="bulk_quantity" name="quantity" 
                                       min="1" max="1000" required>
                                <div class="form-text">Maximum 1000 vouchers per batch</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bulk_voucher_type" class="form-label">Voucher Type *</label>
                                <select class="form-select" id="bulk_voucher_type" name="voucher_type" required>
                                    <option value="">Select Type</option>
                                    <option value="percentage">Percentage Discount</option>
                                    <option value="fixed_amount">Fixed Amount Discount</option>
                                    <option value="full_waiver">Full Waiver</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bulk_discount_value" class="form-label">Discount Value *</label>
                                <input type="number" class="form-control" id="bulk_discount_value" name="discount_value" 
                                       step="0.01" min="0" required>
                                <div class="form-text">Percentage (1-100) or fixed amount in dollars</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bulk_max_uses" class="form-label">Maximum Uses</label>
                                <input type="number" class="form-control" id="bulk_max_uses" name="max_uses" min="1" value="1">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bulk_valid_from" class="form-label">Valid From *</label>
                                <input type="date" class="form-control" id="bulk_valid_from" name="valid_from" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bulk_valid_until" class="form-label">Valid Until *</label>
                                <input type="date" class="form-control" id="bulk_valid_until" name="valid_until" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bulk_description" class="form-label">Description</label>
                        <textarea class="form-control" id="bulk_description" name="description" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Vouchers</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewVoucher(voucherId) {
    window.location.href = '/admin/voucher-details.php?id=' + voucherId;
}

function editVoucher(voucherId) {
    window.location.href = '/admin/edit-voucher.php?id=' + voucherId;
}

function viewUsage(voucherId) {
    window.location.href = '/admin/voucher-usage.php?id=' + voucherId;
}

function deleteVoucher(voucherId) {
    if (confirm('Are you sure you want to delete this voucher? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_voucher">
            <input type="hidden" name="voucher_id" value="${voucherId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Auto-generate voucher code
document.getElementById('voucher_code').addEventListener('blur', function() {
    if (!this.value) {
        const type = document.getElementById('voucher_type').value;
        const prefix = type === 'full_waiver' ? 'WAIVER' : 'VOUCHER';
        this.value = prefix + Math.random().toString(36).substr(2, 6).toUpperCase();
    }
});

// Set default dates
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    const nextMonth = new Date();
    nextMonth.setMonth(nextMonth.getMonth() + 1);
    const nextMonthStr = nextMonth.toISOString().split('T')[0];
    
    // Set dates for single voucher creation
    document.getElementById('valid_from').value = today;
    document.getElementById('valid_until').value = nextMonthStr;
    
    // Set dates for bulk voucher creation
    document.getElementById('bulk_valid_from').value = today;
    document.getElementById('bulk_valid_until').value = nextMonthStr;
});
</script>

<?php include '../includes/footer.php'; ?>
