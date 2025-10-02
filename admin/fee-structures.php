<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
$feeStructureModel = new FeeStructure($database);
$programModel = new Program($database);

// Check admin access
requireRole(['admin', 'admissions_officer']);

$pageTitle = 'Fee Structure Management';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'Fee Structures', 'url' => '/admin/fee-structures.php']
];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_fee':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $validator = new Validator($_POST);
                    $validator->required(['fee_name', 'fee_type', 'amount'])
                             ->numeric('amount');
                    
                    if (!$validator->hasErrors()) {
                        $data = $validator->getValidatedData();
                        $data['created_by'] = $_SESSION['user_id'];
                        
                        // Check if fee already exists for this program and type
                        if (!empty($data['program_id']) && $feeStructureModel->feeExists($data['program_id'], $data['fee_type'])) {
                            $message = 'A fee of this type already exists for the selected program.';
                            $messageType = 'danger';
                        } else {
                            if ($feeStructureModel->create($data)) {
                                $message = 'Fee structure created successfully!';
                                $messageType = 'success';
                            } else {
                                $message = 'Failed to create fee structure. Please try again.';
                                $messageType = 'danger';
                            }
                        }
                    } else {
                        $message = 'Please correct the errors below.';
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'update_fee':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $feeId = $_POST['fee_id'];
                    $validator = new Validator($_POST);
                    $validator->required(['fee_name', 'fee_type', 'amount'])
                             ->numeric('amount');
                    
                    if (!$validator->hasErrors()) {
                        $data = $validator->getValidatedData();
                        
                        // Check if fee already exists for this program and type (excluding current fee)
                        if (!empty($data['program_id']) && $feeStructureModel->feeExists($data['program_id'], $data['fee_type'], $feeId)) {
                            $message = 'A fee of this type already exists for the selected program.';
                            $messageType = 'danger';
                        } else {
                            if ($feeStructureModel->update($feeId, $data)) {
                                $message = 'Fee structure updated successfully!';
                                $messageType = 'success';
                            } else {
                                $message = 'Failed to update fee structure. Please try again.';
                                $messageType = 'danger';
                            }
                        }
                    } else {
                        $message = 'Please correct the errors below.';
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'delete_fee':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $feeId = $_POST['fee_id'];
                    if ($feeStructureModel->delete($feeId)) {
                        $message = 'Fee structure deleted successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to delete fee structure. Please try again.';
                        $messageType = 'danger';
                    }
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
if (!empty($_GET['fee_type'])) {
    $filters['fee_type'] = $_GET['fee_type'];
}
if (!empty($_GET['program_id'])) {
    $filters['program_id'] = $_GET['program_id'];
}
if (!empty($_GET['is_active'])) {
    $filters['is_active'] = $_GET['is_active'];
}

// Get fee structures with pagination
$feesData = $feeStructureModel->getAll($page, $limit, $filters);
$fees = $feesData['fees'] ?? [];
$totalPages = $feesData['pages'] ?? 1;

// Get programs for filter
$programs = $programModel->getActive();

// Get fee types
$feeTypes = $feeStructureModel->getFeeTypes();

// Get statistics
$statistics = $feeStructureModel->getStatistics();

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
                        <h4 class="mb-0"><?php echo $statistics['total_fees']; ?></h4>
                        <p class="mb-0">Total Fees</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-currency-dollar display-4"></i>
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
                        <h4 class="mb-0"><?php echo $statistics['active_fees']; ?></h4>
                        <p class="mb-0">Active Fees</p>
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
                        <h4 class="mb-0"><?php echo $statistics['global_fees']; ?></h4>
                        <p class="mb-0">Global Fees</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-globe display-4"></i>
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
                        <h4 class="mb-0"><?php echo formatCurrency($statistics['total_amount']); ?></h4>
                        <p class="mb-0">Total Amount</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-calculator display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createFeeModal">
            <i class="bi bi-plus-circle me-2"></i>Add New Fee Structure
        </button>
    </div>
    <div class="col-md-6">
        <div class="d-flex gap-2 justify-content-end">
            <button class="btn btn-outline-secondary" onclick="exportTableToCSV('feesTable', 'fee_structures.csv')">
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
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                       placeholder="Fee name or description">
            </div>
            <div class="col-md-2">
                <label for="fee_type" class="form-label">Fee Type</label>
                <select class="form-select" id="fee_type" name="fee_type">
                    <option value="">All Types</option>
                    <?php foreach ($feeTypes as $value => $label): ?>
                        <option value="<?php echo $value; ?>" 
                                <?php echo ($_GET['fee_type'] ?? '') === $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="program_id" class="form-label">Program</label>
                <select class="form-select" id="program_id" name="program_id">
                    <option value="">All Programs</option>
                    <option value="0" <?php echo ($_GET['program_id'] ?? '') === '0' ? 'selected' : ''; ?>>Global Fees</option>
                    <?php foreach ($programs as $program): ?>
                        <option value="<?php echo $program['id']; ?>" 
                                <?php echo ($_GET['program_id'] ?? '') == $program['id'] ? 'selected' : ''; ?>>
                            <?php echo $program['program_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="is_active" class="form-label">Status</label>
                <select class="form-select" id="is_active" name="is_active">
                    <option value="">All Status</option>
                    <option value="1" <?php echo ($_GET['is_active'] ?? '') === '1' ? 'selected' : ''; ?>>Active</option>
                    <option value="0" <?php echo ($_GET['is_active'] ?? '') === '0' ? 'selected' : ''; ?>>Inactive</option>
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

<!-- Fee Structures Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-currency-dollar me-2"></i>Fee Structures
            <span class="badge bg-primary ms-2"><?php echo $feesData['total'] ?? 0; ?></span>
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($fees)): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="feesTable">
                    <thead>
                        <tr>
                            <th>Fee Name</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Program</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fees as $fee): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $fee['fee_name']; ?></strong>
                                    <?php if ($fee['description']): ?>
                                        <br><small class="text-muted"><?php echo $fee['description']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $feeTypes[$fee['fee_type']] ?? ucfirst($fee['fee_type']); ?></span>
                                </td>
                                <td>
                                    <?php if ($fee['is_percentage']): ?>
                                        <?php echo $fee['amount']; ?>% of <?php echo formatCurrency($fee['percentage_of']); ?>
                                    <?php else: ?>
                                        <?php echo formatCurrency($fee['amount']); ?>
                                    <?php endif; ?>
                                    <br><small class="text-muted"><?php echo $fee['currency']; ?></small>
                                </td>
                                <td>
                                    <?php if ($fee['program_name']): ?>
                                        <?php echo $fee['program_name']; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Global Fee</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $fee['due_date'] ? formatDate($fee['due_date']) : 'N/A'; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $fee['is_active'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $fee['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $fee['created_by_first_name'] . ' ' . $fee['created_by_last_name']; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="editFee(<?php echo $fee['id']; ?>)"
                                                title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteFee(<?php echo $fee['id']; ?>)"
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
                <nav aria-label="Fee structures pagination">
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
                <i class="bi bi-currency-dollar display-1 text-muted"></i>
                <h4 class="text-muted mt-3">No Fee Structures Found</h4>
                <p class="text-muted">No fee structures match your current filters.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createFeeModal">
                    <i class="bi bi-plus-circle me-2"></i>Add First Fee Structure
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Fee Modal -->
<div class="modal fade" id="createFeeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Fee Structure</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="createFeeForm">
                <input type="hidden" name="action" value="create_fee">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="fee_name" class="form-label">Fee Name *</label>
                                <input type="text" class="form-control" id="fee_name" name="fee_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="fee_type" class="form-label">Fee Type *</label>
                                <select class="form-select" id="fee_type" name="fee_type" required>
                                    <option value="">Select Fee Type</option>
                                    <?php foreach ($feeTypes as $value => $label): ?>
                                        <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount *</label>
                                <input type="number" class="form-control" id="amount" name="amount" 
                                       step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="currency" class="form-label">Currency</label>
                                <select class="form-select" id="currency" name="currency">
                                    <option value="USD">USD</option>
                                    <option value="GHS">GHS</option>
                                    <option value="NGN">NGN</option>
                                    <option value="KES">KES</option>
                                    <option value="EUR">EUR</option>
                                    <option value="GBP">GBP</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="program_id" class="form-label">Program</label>
                                <select class="form-select" id="program_id" name="program_id">
                                    <option value="">Global Fee (All Programs)</option>
                                    <?php foreach ($programs as $program): ?>
                                        <option value="<?php echo $program['id']; ?>">
                                            <?php echo $program['program_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="due_date" class="form-label">Due Date</label>
                                <input type="date" class="form-control" id="due_date" name="due_date">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="late_fee_amount" class="form-label">Late Fee Amount</label>
                                <input type="number" class="form-control" id="late_fee_amount" name="late_fee_amount" 
                                       step="0.01" min="0" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="late_fee_grace_days" class="form-label">Grace Period (Days)</label>
                                <input type="number" class="form-control" id="late_fee_grace_days" name="late_fee_grace_days" 
                                       min="0" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_mandatory" name="is_mandatory" checked>
                                <label class="form-check-label" for="is_mandatory">
                                    Mandatory Fee
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                <label class="form-check-label" for="is_active">
                                    Active
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Fee Structure</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editFee(feeId) {
    // Implement edit functionality
    window.location.href = '/admin/edit-fee-structure.php?id=' + feeId;
}

function deleteFee(feeId) {
    if (confirm('Are you sure you want to delete this fee structure? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_fee">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="fee_id" value="${feeId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Form validation
document.getElementById('createFeeForm').addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('input[required], select[required]');
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
