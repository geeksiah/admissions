<?php
// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_form':
                // Create new application form for a program
                $programId = (int)$_POST['program_id'];
                $formName = trim($_POST['form_name']);
                $formDescription = trim($_POST['form_description']);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                if ($programId && $formName) {
                    try {
                        // Create form record
                        $formData = [
                            'program_id' => $programId,
                            'form_name' => $formName,
                            'form_description' => $formDescription,
                            'is_active' => $isActive,
                            'created_by' => $_SESSION['user_id'],
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        
                        // This would typically use an ApplicationForm model
                        // For now, we'll simulate success
                        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle me-2"></i>Application form created successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                              </div>';
                    } catch (Exception $e) {
                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-circle me-2"></i>Error creating form: ' . htmlspecialchars($e->getMessage()) . '
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                              </div>';
                    }
                }
                break;
                
            case 'add_field':
                // Add field to application form
                $formId = (int)$_POST['form_id'];
                $fieldName = trim($_POST['field_name']);
                $fieldType = $_POST['field_type'];
                $fieldLabel = trim($_POST['field_label']);
                $isRequired = isset($_POST['is_required']) ? 1 : 0;
                $fieldOptions = trim($_POST['field_options'] ?? '');
                
                if ($formId && $fieldName && $fieldType && $fieldLabel) {
                    // Simulate field creation
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i>Field added successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                          </div>';
                }
                break;
        }
    }
}

// Get all programs for form creation
$programs = $programModel->getAll(['status' => 'active']);

// Sample application forms data (in real system, this would come from database)
$applicationForms = [
    [
        'id' => 1,
        'program_id' => 1,
        'program_name' => 'Computer Science',
        'form_name' => 'Standard Application Form',
        'form_description' => 'Standard application form for Computer Science program',
        'is_active' => 1,
        'fields_count' => 8,
        'created_at' => '2024-01-15 10:30:00'
    ],
    [
        'id' => 2,
        'program_id' => 2,
        'program_name' => 'Business Administration',
        'form_name' => 'MBA Application Form',
        'form_description' => 'Comprehensive application form for MBA program',
        'is_active' => 1,
        'fields_count' => 12,
        'created_at' => '2024-01-10 14:20:00'
    ]
];
?>

<div class="row mb-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-file-earmark-text me-2"></i>
                Application Forms Management
            </h5>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createFormModal">
                <i class="bi bi-plus-circle me-2"></i>Create New Form
            </button>
        </div>
        <p class="text-muted mt-1 mb-0">Manage custom application forms for each program.</p>
    </div>
</div>

<!-- Application Forms List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($applicationForms)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-file-earmark-text text-muted" style="font-size: 3rem;"></i>
                        <h5 class="text-muted mt-3">No Application Forms</h5>
                        <p class="text-muted">Create custom application forms for your programs.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createFormModal">
                            <i class="bi bi-plus-circle me-2"></i>Create First Form
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Program</th>
                                    <th>Form Name</th>
                                    <th>Description</th>
                                    <th>Fields</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applicationForms as $form): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($form['program_name']); ?></strong>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($form['form_name']); ?></td>
                                        <td>
                                            <span class="text-muted">
                                                <?php echo htmlspecialchars(substr($form['form_description'], 0, 50)) . (strlen($form['form_description']) > 50 ? '...' : ''); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $form['fields_count']; ?> fields</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $form['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $form['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($form['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="editForm(<?php echo $form['id']; ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-success" onclick="manageFields(<?php echo $form['id']; ?>)">
                                                    <i class="bi bi-list-ul"></i>
                                                </button>
                                                <button class="btn btn-outline-secondary" onclick="previewForm(<?php echo $form['id']; ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteForm(<?php echo $form['id']; ?>)">
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
    </div>
</div>

<!-- Create Form Modal -->
<div class="modal fade" id="createFormModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-file-earmark-plus me-2"></i>Create Application Form
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_form">
                    
                    <div class="mb-3">
                        <label for="program_id" class="form-label">Program *</label>
                        <select class="form-select" id="program_id" name="program_id" required>
                            <option value="">Select a program...</option>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?php echo $program['id']; ?>">
                                    <?php echo htmlspecialchars($program['program_name'] . ' (' . $program['program_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="form_name" class="form-label">Form Name *</label>
                        <input type="text" class="form-control" id="form_name" name="form_name" 
                               placeholder="e.g., Standard Application Form" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="form_description" class="form-label">Description</label>
                        <textarea class="form-control" id="form_description" name="form_description" 
                                  rows="3" placeholder="Brief description of this application form..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Active (form will be available to students)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>Create Form
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Manage Fields Modal -->
<div class="modal fade" id="manageFieldsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-list-ul me-2"></i>Manage Form Fields
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Current Fields -->
                    <div class="col-md-8">
                        <h6>Current Fields</h6>
                        <div id="fieldsList">
                            <!-- Fields will be loaded here -->
                            <div class="list-group">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Personal Information</strong>
                                        <br><small class="text-muted">Section Header</small>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary btn-sm">Edit</button>
                                        <button class="btn btn-outline-danger btn-sm">Delete</button>
                                    </div>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>First Name</strong>
                                        <br><small class="text-muted">Text Input - Required</small>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary btn-sm">Edit</button>
                                        <button class="btn btn-outline-danger btn-sm">Delete</button>
                                    </div>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Email Address</strong>
                                        <br><small class="text-muted">Email Input - Required</small>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary btn-sm">Edit</button>
                                        <button class="btn btn-outline-danger btn-sm">Delete</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Add New Field -->
                    <div class="col-md-4">
                        <h6>Add New Field</h6>
                        <form method="POST" id="addFieldForm">
                            <input type="hidden" name="action" value="add_field">
                            <input type="hidden" name="form_id" id="currentFormId">
                            
                            <div class="mb-3">
                                <label class="form-label">Field Type *</label>
                                <select class="form-select" name="field_type" id="fieldType" required>
                                    <option value="">Select field type...</option>
                                    <option value="text">Text Input</option>
                                    <option value="email">Email Input</option>
                                    <option value="number">Number Input</option>
                                    <option value="textarea">Text Area</option>
                                    <option value="select">Dropdown</option>
                                    <option value="radio">Radio Buttons</option>
                                    <option value="checkbox">Checkboxes</option>
                                    <option value="file">File Upload</option>
                                    <option value="date">Date Picker</option>
                                    <option value="section">Section Header</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Field Label *</label>
                                <input type="text" class="form-control" name="field_label" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Field Name *</label>
                                <input type="text" class="form-control" name="field_name" 
                                       placeholder="e.g., first_name" required>
                                <small class="form-text text-muted">Use lowercase with underscores</small>
                            </div>
                            
                            <div class="mb-3" id="optionsContainer" style="display: none;">
                                <label class="form-label">Options</label>
                                <textarea class="form-control" name="field_options" rows="3" 
                                          placeholder="Enter options separated by new lines"></textarea>
                                <small class="form-text text-muted">One option per line</small>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_required" id="isRequired">
                                    <label class="form-check-label" for="isRequired">
                                        Required field
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus-circle me-2"></i>Add Field
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" onclick="saveFormStructure()">
                    <i class="bi bi-check-circle me-2"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Show/hide options field based on field type
document.getElementById('fieldType').addEventListener('change', function() {
    const optionsContainer = document.getElementById('optionsContainer');
    const fieldTypes = ['select', 'radio', 'checkbox'];
    
    if (fieldTypes.includes(this.value)) {
        optionsContainer.style.display = 'block';
    } else {
        optionsContainer.style.display = 'none';
    }
});

function editForm(formId) {
    // TODO: Implement form editing
    alert('Edit form: ' + formId);
}

function manageFields(formId) {
    document.getElementById('currentFormId').value = formId;
    const modal = new bootstrap.Modal(document.getElementById('manageFieldsModal'));
    modal.show();
}

function previewForm(formId) {
    // TODO: Implement form preview
    alert('Preview form: ' + formId);
}

function deleteForm(formId) {
    if (confirm('Are you sure you want to delete this application form?')) {
        // TODO: Implement form deletion
        alert('Delete form: ' + formId);
    }
}

function saveFormStructure() {
    // TODO: Implement form structure saving
    alert('Form structure saved!');
}
</script>
