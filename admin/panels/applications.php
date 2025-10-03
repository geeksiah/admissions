<?php
/**
 * Applications Panel - Full CRUD Operations
 */

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['ajax_action']) {
            case 'get_application':
                $id = $_POST['id'] ?? 0;
                $application = $applicationModel->getById($id);
                echo json_encode(['success' => true, 'data' => $application]);
                exit;
                
            case 'update_status':
                $id = $_POST['id'] ?? 0;
                $status = $_POST['status'] ?? '';
                $notes = $_POST['notes'] ?? '';
                
                $result = $applicationModel->updateStatus($id, $status, $notes);
                echo json_encode(['success' => $result]);
                exit;
                
            case 'delete_application':
                $id = $_POST['id'] ?? 0;
                $result = $applicationModel->delete($id);
                echo json_encode(['success' => $result]);
                exit;
                
            case 'bulk_action':
                $action = $_POST['bulk_action'] ?? '';
                $ids = $_POST['ids'] ?? [];
                
                $result = false;
                switch ($action) {
                    case 'approve':
                        $result = $applicationModel->bulkUpdateStatus($ids, 'approved');
                        break;
                    case 'reject':
                        $result = $applicationModel->bulkUpdateStatus($ids, 'rejected');
                        break;
                    case 'delete':
                        $result = $applicationModel->bulkDelete($ids);
                        break;
                }
                
                echo json_encode(['success' => $result]);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Get applications with pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$filters = [
    'status' => $_GET['status'] ?? '',
    'program' => $_GET['program'] ?? '',
    'search' => $_GET['search'] ?? ''
];

try {
    $applications = $applicationModel->getAll($filters, $limit, $offset);
    $totalApplications = $applicationModel->getTotalCount($filters);
    $totalPages = ceil($totalApplications / $limit);
} catch (Exception $e) {
    $applications = [];
    $totalApplications = 0;
    $totalPages = 0;
}

// Get programs for filter
try {
    $allPrograms = $programModel->getAll();
} catch (Exception $e) {
    $allPrograms = [];
}
?>

<!-- Applications Management -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">Application Management</h2>
        <p class="text-muted mb-0">Manage and process student applications</p>
    </div>
    <div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addApplicationModal">
            <i class="bi bi-plus-lg me-2"></i>Add Application
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" id="filterForm">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status" onchange="document.getElementById('filterForm').submit()">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="under_review" <?php echo $filters['status'] === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                        <option value="approved" <?php echo $filters['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $filters['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Program</label>
                    <select class="form-select" name="program" onchange="document.getElementById('filterForm').submit()">
                        <option value="">All Programs</option>
                        <?php foreach ($allPrograms as $program): ?>
                            <option value="<?php echo $program['id']; ?>" <?php echo $filters['program'] == $program['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($program['program_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" placeholder="Search by name, email, or application ID..." 
                           value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-outline-primary d-block w-100">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Actions -->
<div class="card mb-4" id="bulkActionsCard" style="display: none;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <span id="selectedCount">0</span> applications selected
            </div>
            <div>
                <button class="btn btn-success btn-sm me-2" onclick="bulkAction('approve')">
                    <i class="bi bi-check-lg"></i> Approve Selected
                </button>
                <button class="btn btn-danger btn-sm me-2" onclick="bulkAction('reject')">
                    <i class="bi bi-x-lg"></i> Reject Selected
                </button>
                <button class="btn btn-outline-danger btn-sm" onclick="bulkAction('delete')">
                    <i class="bi bi-trash"></i> Delete Selected
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Applications Table -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Applications (<?php echo number_format($totalApplications); ?>)</h5>
            <div>
                <button class="btn btn-outline-secondary btn-sm" onclick="selectAll()">
                    <i class="bi bi-check-square"></i> Select All
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="selectNone()">
                    <i class="bi bi-square"></i> Select None
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($applications)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-2">No applications found</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th width="50">
                                <input type="checkbox" class="form-check-input" id="selectAllCheckbox">
                            </th>
                            <th>Application ID</th>
                            <th>Student Name</th>
                            <th>Program</th>
                            <th>Status</th>
                            <th>Applied Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input application-checkbox" value="<?php echo $app['id']; ?>">
                                </td>
                                <td>
                                    <strong>#<?php echo str_pad($app['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($app['email']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <?php echo htmlspecialchars($app['program_name'] ?? 'Unknown Program'); ?>
                                    </span>
                                </td>
                                <td>
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
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($app['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="viewApplication(<?php echo $app['id']; ?>)" title="View">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-success" onclick="editApplication(<?php echo $app['id']; ?>)" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="deleteApplication(<?php echo $app['id']; ?>)" title="Delete">
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
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav aria-label="Applications pagination">
                <ul class="pagination justify-content-center mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($filters); ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
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
        </div>
    <?php endif; ?>
</div>

<!-- View Application Modal -->
<div class="modal fade" id="viewApplicationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Application Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="applicationDetails">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Application Modal -->
<div class="modal fade" id="editApplicationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Application Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editApplicationForm">
                <div class="modal-body">
                    <input type="hidden" id="editApplicationId">
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="editApplicationStatus" required>
                            <option value="pending">Pending</option>
                            <option value="under_review">Under Review</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="editApplicationNotes" rows="3" placeholder="Add notes about this application..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Application management functions
function viewApplication(id) {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax_action=get_application&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const app = data.data;
            document.getElementById('applicationDetails').innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Student Information</h6>
                        <p><strong>Name:</strong> ${app.first_name} ${app.last_name}</p>
                        <p><strong>Email:</strong> ${app.email}</p>
                        <p><strong>Phone:</strong> ${app.phone || 'N/A'}</p>
                        <p><strong>Date of Birth:</strong> ${app.date_of_birth || 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Application Details</h6>
                        <p><strong>Program:</strong> ${app.program_name || 'Unknown'}</p>
                        <p><strong>Status:</strong> <span class="badge bg-primary">${app.status}</span></p>
                        <p><strong>Applied:</strong> ${new Date(app.created_at).toLocaleDateString()}</p>
                        <p><strong>Application ID:</strong> #${String(app.id).padStart(6, '0')}</p>
                    </div>
                </div>
                ${app.notes ? `<div class="mt-3"><h6>Notes</h6><p>${app.notes}</p></div>` : ''}
            `;
            new bootstrap.Modal(document.getElementById('viewApplicationModal')).show();
        }
    });
}

function editApplication(id) {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax_action=get_application&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const app = data.data;
            document.getElementById('editApplicationId').value = id;
            document.getElementById('editApplicationStatus').value = app.status;
            document.getElementById('editApplicationNotes').value = app.notes || '';
            new bootstrap.Modal(document.getElementById('editApplicationModal')).show();
        }
    });
}

function deleteApplication(id) {
    if (confirm('Are you sure you want to delete this application? This action cannot be undone.')) {
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'ajax_action=delete_application&id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to delete application');
            }
        });
    }
}

// Bulk actions
function bulkAction(action) {
    const checkboxes = document.querySelectorAll('.application-checkbox:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);
    
    if (ids.length === 0) {
        alert('Please select at least one application');
        return;
    }
    
    let confirmMessage = '';
    switch (action) {
        case 'approve':
            confirmMessage = `Are you sure you want to approve ${ids.length} application(s)?`;
            break;
        case 'reject':
            confirmMessage = `Are you sure you want to reject ${ids.length} application(s)?`;
            break;
        case 'delete':
            confirmMessage = `Are you sure you want to delete ${ids.length} application(s)? This action cannot be undone.`;
            break;
    }
    
    if (confirm(confirmMessage)) {
        const formData = new FormData();
        formData.append('ajax_action', 'bulk_action');
        formData.append('bulk_action', action);
        formData.append('ids', JSON.stringify(ids));
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to perform bulk action');
            }
        });
    }
}

// Checkbox management
function selectAll() {
    document.querySelectorAll('.application-checkbox').forEach(cb => cb.checked = true);
    document.getElementById('selectAllCheckbox').checked = true;
    updateBulkActions();
}

function selectNone() {
    document.querySelectorAll('.application-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAllCheckbox').checked = false;
    updateBulkActions();
}

function updateBulkActions() {
    const checked = document.querySelectorAll('.application-checkbox:checked');
    const count = checked.length;
    
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('bulkActionsCard').style.display = count > 0 ? 'block' : 'none';
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Select all checkbox
    document.getElementById('selectAllCheckbox').addEventListener('change', function() {
        document.querySelectorAll('.application-checkbox').forEach(cb => {
            cb.checked = this.checked;
        });
        updateBulkActions();
    });
    
    // Individual checkboxes
    document.querySelectorAll('.application-checkbox').forEach(cb => {
        cb.addEventListener('change', updateBulkActions);
    });
    
    // Edit form submission
    document.getElementById('editApplicationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('ajax_action', 'update_status');
        formData.append('id', document.getElementById('editApplicationId').value);
        formData.append('status', document.getElementById('editApplicationStatus').value);
        formData.append('notes', document.getElementById('editApplicationNotes').value);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('editApplicationModal')).hide();
                location.reload();
            } else {
                alert('Failed to update application');
            }
        });
    });
});
</script>
