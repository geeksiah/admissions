<?php
/**
 * Programs Panel - Full CRUD Operations
 */

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['ajax_action']) {
            case 'get_program':
                $id = $_POST['id'] ?? 0;
                $program = $programModel->getById($id);
                echo json_encode(['success' => true, 'data' => $program]);
                exit;
                
            case 'create_program':
                $data = [
                    'program_name' => $_POST['program_name'] ?? '',
                    'program_code' => $_POST['program_code'] ?? '',
                    'degree_level' => $_POST['degree_level'] ?? '',
                    'department' => $_POST['department'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'requirements' => $_POST['requirements'] ?? '',
                    'duration' => $_POST['duration'] ?? '',
                    'credits' => $_POST['credits'] ?? '',
                    'application_fee' => $_POST['application_fee'] ?? 0,
                    'status' => $_POST['status'] ?? 'active'
                ];
                
                $result = $programModel->create($data);
                echo json_encode(['success' => $result]);
                exit;
                
            case 'update_program':
                $id = $_POST['id'] ?? 0;
                $data = [
                    'program_name' => $_POST['program_name'] ?? '',
                    'program_code' => $_POST['program_code'] ?? '',
                    'degree_level' => $_POST['degree_level'] ?? '',
                    'department' => $_POST['department'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'requirements' => $_POST['requirements'] ?? '',
                    'duration' => $_POST['duration'] ?? '',
                    'credits' => $_POST['credits'] ?? '',
                    'application_fee' => $_POST['application_fee'] ?? 0,
                    'status' => $_POST['status'] ?? 'active'
                ];
                
                $result = $programModel->update($id, $data);
                echo json_encode(['success' => $result]);
                exit;
                
            case 'delete_program':
                $id = $_POST['id'] ?? 0;
                $result = $programModel->delete($id);
                echo json_encode(['success' => $result]);
                exit;
                
            case 'toggle_status':
                $id = $_POST['id'] ?? 0;
                $result = $programModel->toggleStatus($id);
                echo json_encode(['success' => $result]);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Get programs with pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    $programs = $programModel->getAll($limit, $offset);
    $totalPrograms = $programModel->getTotalCount();
    $totalPages = ceil($totalPrograms / $limit);
} catch (Exception $e) {
    $programs = [];
    $totalPrograms = 0;
    $totalPages = 0;
}
?>

<!-- Programs Management -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">Program Management</h2>
        <p class="text-muted mb-0">Manage academic programs and courses</p>
    </div>
    <div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProgramModal">
            <i class="bi bi-plus-lg me-2"></i>Add Program
        </button>
    </div>
</div>

<!-- Programs Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Programs (<?php echo number_format($totalPrograms); ?>)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($programs)): ?>
            <div class="text-center py-5">
                <i class="bi bi-mortarboard text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-2">No programs found</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                    <i class="bi bi-plus-lg me-2"></i>Add First Program
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Program Name</th>
                            <th>Code</th>
                            <th>Level</th>
                            <th>Department</th>
                            <th>Duration</th>
                            <th>Fee</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($programs as $program): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($program['program_name']); ?></strong>
                                        <?php if (!empty($program['description'])): ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars(substr($program['description'], 0, 50)) . (strlen($program['description']) > 50 ? '...' : ''); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($program['program_code']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($program['degree_level']); ?></td>
                                <td><?php echo htmlspecialchars($program['department']); ?></td>
                                <td><?php echo htmlspecialchars($program['duration']); ?></td>
                                <td>$<?php echo number_format($program['application_fee'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $program['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($program['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="viewProgram(<?php echo $program['id']; ?>)" title="View">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-success" onclick="editProgram(<?php echo $program['id']; ?>)" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-warning" onclick="toggleProgramStatus(<?php echo $program['id']; ?>)" title="Toggle Status">
                                            <i class="bi bi-toggle-<?php echo $program['status'] === 'active' ? 'on' : 'off'; ?>"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="deleteProgram(<?php echo $program['id']; ?>)" title="Delete">
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
            <nav aria-label="Programs pagination">
                <ul class="pagination justify-content-center mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Add Program Modal -->
<div class="modal fade" id="addProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Program</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addProgramForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Program Name *</label>
                                <input type="text" class="form-control" name="program_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Program Code *</label>
                                <input type="text" class="form-control" name="program_code" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Degree Level *</label>
                                <select class="form-select" name="degree_level" required>
                                    <option value="">Select Level</option>
                                    <option value="Certificate">Certificate</option>
                                    <option value="Diploma">Diploma</option>
                                    <option value="Bachelor">Bachelor's Degree</option>
                                    <option value="Master">Master's Degree</option>
                                    <option value="Doctorate">Doctorate</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Department *</label>
                                <input type="text" class="form-control" name="department" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Requirements</label>
                        <textarea class="form-control" name="requirements" rows="3" placeholder="List the admission requirements..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Duration</label>
                                <input type="text" class="form-control" name="duration" placeholder="e.g., 4 years">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Credits</label>
                                <input type="number" class="form-control" name="credits" placeholder="120">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Application Fee</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="application_fee" step="0.01" min="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Program</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Program Modal -->
<div class="modal fade" id="editProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Program</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editProgramForm">
                <div class="modal-body">
                    <input type="hidden" id="editProgramId">
                    <!-- Same form fields as add modal -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Program Name *</label>
                                <input type="text" class="form-control" id="editProgramName" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Program Code *</label>
                                <input type="text" class="form-control" id="editProgramCode" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Degree Level *</label>
                                <select class="form-select" id="editDegreeLevel" required>
                                    <option value="">Select Level</option>
                                    <option value="Certificate">Certificate</option>
                                    <option value="Diploma">Diploma</option>
                                    <option value="Bachelor">Bachelor's Degree</option>
                                    <option value="Master">Master's Degree</option>
                                    <option value="Doctorate">Doctorate</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Department *</label>
                                <input type="text" class="form-control" id="editDepartment" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="editDescription" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Requirements</label>
                        <textarea class="form-control" id="editRequirements" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Duration</label>
                                <input type="text" class="form-control" id="editDuration">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Credits</label>
                                <input type="number" class="form-control" id="editCredits">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Application Fee</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="editApplicationFee" step="0.01" min="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="editStatus">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Program</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Program management functions
function viewProgram(id) {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax_action=get_program&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const program = data.data;
            alert(`Program: ${program.program_name}\nCode: ${program.program_code}\nLevel: ${program.degree_level}\nDepartment: ${program.department}`);
        }
    });
}

function editProgram(id) {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax_action=get_program&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const program = data.data;
            document.getElementById('editProgramId').value = id;
            document.getElementById('editProgramName').value = program.program_name;
            document.getElementById('editProgramCode').value = program.program_code;
            document.getElementById('editDegreeLevel').value = program.degree_level;
            document.getElementById('editDepartment').value = program.department;
            document.getElementById('editDescription').value = program.description || '';
            document.getElementById('editRequirements').value = program.requirements || '';
            document.getElementById('editDuration').value = program.duration || '';
            document.getElementById('editCredits').value = program.credits || '';
            document.getElementById('editApplicationFee').value = program.application_fee || '';
            document.getElementById('editStatus').value = program.status;
            new bootstrap.Modal(document.getElementById('editProgramModal')).show();
        }
    });
}

function deleteProgram(id) {
    if (confirm('Are you sure you want to delete this program? This action cannot be undone.')) {
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'ajax_action=delete_program&id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to delete program');
            }
        });
    }
}

function toggleProgramStatus(id) {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax_action=toggle_status&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to toggle program status');
        }
    });
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Add program form
    document.getElementById('addProgramForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('ajax_action', 'create_program');
        formData.append('program_name', this.program_name.value);
        formData.append('program_code', this.program_code.value);
        formData.append('degree_level', this.degree_level.value);
        formData.append('department', this.department.value);
        formData.append('description', this.description.value);
        formData.append('requirements', this.requirements.value);
        formData.append('duration', this.duration.value);
        formData.append('credits', this.credits.value);
        formData.append('application_fee', this.application_fee.value);
        formData.append('status', this.status.value);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('addProgramModal')).hide();
                location.reload();
            } else {
                alert('Failed to create program');
            }
        });
    });
    
    // Edit program form
    document.getElementById('editProgramForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('ajax_action', 'update_program');
        formData.append('id', document.getElementById('editProgramId').value);
        formData.append('program_name', document.getElementById('editProgramName').value);
        formData.append('program_code', document.getElementById('editProgramCode').value);
        formData.append('degree_level', document.getElementById('editDegreeLevel').value);
        formData.append('department', document.getElementById('editDepartment').value);
        formData.append('description', document.getElementById('editDescription').value);
        formData.append('requirements', document.getElementById('editRequirements').value);
        formData.append('duration', document.getElementById('editDuration').value);
        formData.append('credits', document.getElementById('editCredits').value);
        formData.append('application_fee', document.getElementById('editApplicationFee').value);
        formData.append('status', document.getElementById('editStatus').value);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('editProgramModal')).hide();
                location.reload();
            } else {
                alert('Failed to update program');
            }
        });
    });
});
</script>
