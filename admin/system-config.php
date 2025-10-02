<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
$systemConfig = new SystemConfig($database);

// Check admin access
requireRole(['admin']);

$pageTitle = 'System Configuration';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'System Configuration', 'url' => '/admin/system-config.php']
];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_config':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $configs = $_POST['config'] ?? [];
                    $success = true;
                    $errors = [];
                    
                    foreach ($configs as $key => $value) {
                        $type = $_POST['config_type'][$key] ?? 'string';
                        $description = $_POST['config_description'][$key] ?? null;
                        $isPublic = isset($_POST['config_public'][$key]);
                        
                        if (!$systemConfig->set($key, $value, $type, $description, $isPublic, $_SESSION['user_id'])) {
                            $success = false;
                            $errors[] = "Failed to update {$key}";
                        }
                    }
                    
                    if ($success) {
                        $message = 'Configuration updated successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Some configurations failed to update: ' . implode(', ', $errors);
                        $messageType = 'warning';
                    }
                }
                break;
                
            case 'add_config':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $key = sanitizeInput($_POST['new_config_key']);
                    $value = $_POST['new_config_value'];
                    $type = $_POST['new_config_type'];
                    $description = sanitizeInput($_POST['new_config_description']);
                    $isPublic = isset($_POST['new_config_public']);
                    
                    if ($systemConfig->set($key, $value, $type, $description, $isPublic, $_SESSION['user_id'])) {
                        $message = 'Configuration added successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to add configuration.';
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'delete_config':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $key = $_POST['config_key'];
                    if ($systemConfig->delete($key)) {
                        $message = 'Configuration deleted successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to delete configuration.';
                        $messageType = 'danger';
                    }
                }
                break;
        }
    }
}

// Get all configurations
$configurations = $systemConfig->getAll();

include '../includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-gear me-2"></i>System Configuration
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="configForm">
                    <input type="hidden" name="action" value="update_config">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Configuration Key</th>
                                    <th>Value</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Public</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($configurations as $key => $config): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $key; ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($config['type'] === 'boolean'): ?>
                                                <select class="form-select form-select-sm" name="config[<?php echo $key; ?>]">
                                                    <option value="true" <?php echo $config['value'] ? 'selected' : ''; ?>>True</option>
                                                    <option value="false" <?php echo !$config['value'] ? 'selected' : ''; ?>>False</option>
                                                </select>
                                            <?php elseif ($config['type'] === 'json'): ?>
                                                <textarea class="form-control form-control-sm" name="config[<?php echo $key; ?>]" rows="2"><?php echo is_array($config['value']) ? json_encode($config['value'], JSON_PRETTY_PRINT) : $config['value']; ?></textarea>
                                            <?php elseif ($config['type'] === 'array'): ?>
                                                <input type="text" class="form-control form-control-sm" name="config[<?php echo $key; ?>]" 
                                                       value="<?php echo is_array($config['value']) ? implode(',', $config['value']) : $config['value']; ?>">
                                            <?php else: ?>
                                                <input type="text" class="form-control form-control-sm" name="config[<?php echo $key; ?>]" 
                                                       value="<?php echo htmlspecialchars($config['value']); ?>">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" name="config_type[<?php echo $key; ?>]">
                                                <option value="string" <?php echo $config['type'] === 'string' ? 'selected' : ''; ?>>String</option>
                                                <option value="integer" <?php echo $config['type'] === 'integer' ? 'selected' : ''; ?>>Integer</option>
                                                <option value="boolean" <?php echo $config['type'] === 'boolean' ? 'selected' : ''; ?>>Boolean</option>
                                                <option value="json" <?php echo $config['type'] === 'json' ? 'selected' : ''; ?>>JSON</option>
                                                <option value="array" <?php echo $config['type'] === 'array' ? 'selected' : ''; ?>>Array</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm" name="config_description[<?php echo $key; ?>]" 
                                                   value="<?php echo htmlspecialchars($config['description']); ?>">
                                        </td>
                                        <td>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="config_public[<?php echo $key; ?>]" 
                                                       <?php echo $config['is_public'] ? 'checked' : ''; ?>>
                                            </div>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteConfig('<?php echo $key; ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Save Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-plus-circle me-2"></i>Add New Configuration
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="addConfigForm">
                    <input type="hidden" name="action" value="add_config">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="new_config_key" class="form-label">Configuration Key *</label>
                        <input type="text" class="form-control" id="new_config_key" name="new_config_key" required>
                        <div class="form-text">Use lowercase with underscores (e.g., new_feature_enabled)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_config_value" class="form-label">Value *</label>
                        <input type="text" class="form-control" id="new_config_value" name="new_config_value" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_config_type" class="form-label">Type *</label>
                        <select class="form-select" id="new_config_type" name="new_config_type" required>
                            <option value="string">String</option>
                            <option value="integer">Integer</option>
                            <option value="boolean">Boolean</option>
                            <option value="json">JSON</option>
                            <option value="array">Array</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_config_description" class="form-label">Description</label>
                        <textarea class="form-control" id="new_config_description" name="new_config_description" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="new_config_public" name="new_config_public">
                            <label class="form-check-label" for="new_config_public">
                                Public (visible to frontend)
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-plus me-2"></i>Add Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-info-circle me-2"></i>Configuration Types
                </h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-4">String</dt>
                    <dd class="col-sm-8">Text value</dd>
                    
                    <dt class="col-sm-4">Integer</dt>
                    <dd class="col-sm-8">Numeric value</dd>
                    
                    <dt class="col-sm-4">Boolean</dt>
                    <dd class="col-sm-8">True/False value</dd>
                    
                    <dt class="col-sm-4">JSON</dt>
                    <dd class="col-sm-8">JSON object/array</dd>
                    
                    <dt class="col-sm-4">Array</dt>
                    <dd class="col-sm-8">Comma-separated values</dd>
                </dl>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-lightbulb me-2"></i>Quick Settings
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-primary" onclick="setApplicationMode('payment')">
                        <i class="bi bi-credit-card me-2"></i>Enable Payment Mode
                    </button>
                    <button class="btn btn-outline-success" onclick="setApplicationMode('voucher')">
                        <i class="bi bi-ticket-perforated me-2"></i>Enable Voucher Mode
                    </button>
                    <button class="btn btn-outline-info" onclick="toggleMultipleFees()">
                        <i class="bi bi-list-ul me-2"></i>Toggle Multiple Fees
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfigModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this configuration? This action cannot be undone.</p>
                <p><strong>Configuration Key:</strong> <span id="deleteConfigKey"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" class="d-inline" id="deleteConfigForm">
                    <input type="hidden" name="action" value="delete_config">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="config_key" id="deleteConfigKeyInput">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deleteConfig(key) {
    document.getElementById('deleteConfigKey').textContent = key;
    document.getElementById('deleteConfigKeyInput').value = key;
    new bootstrap.Modal(document.getElementById('deleteConfigModal')).show();
}

function setApplicationMode(mode) {
    if (confirm(`Are you sure you want to set application access mode to ${mode}?`)) {
        // Update the form values
        const configForm = document.getElementById('configForm');
        const modeInput = configForm.querySelector('input[name="config[application_access_mode]"]');
        if (modeInput) {
            modeInput.value = mode;
        }
        
        // Also update voucher requirement
        const voucherCheckbox = configForm.querySelector('input[name="config_public[voucher_required_for_application]"]');
        if (voucherCheckbox) {
            voucherCheckbox.checked = (mode === 'voucher');
        }
        
        // Submit the form
        configForm.submit();
    }
}

function toggleMultipleFees() {
    const configForm = document.getElementById('configForm');
    const feesCheckbox = configForm.querySelector('input[name="config_public[multiple_fee_structure]"]');
    if (feesCheckbox) {
        feesCheckbox.checked = !feesCheckbox.checked;
        configForm.submit();
    }
}

// Form validation
document.getElementById('configForm').addEventListener('submit', function(e) {
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

document.getElementById('addConfigForm').addEventListener('submit', function(e) {
    const key = document.getElementById('new_config_key').value;
    const value = document.getElementById('new_config_value').value;
    
    if (!key || !value) {
        e.preventDefault();
        alert('Please fill in all required fields.');
        return;
    }
    
    // Validate key format
    if (!/^[a-z][a-z0-9_]*$/.test(key)) {
        e.preventDefault();
        alert('Configuration key must start with a letter and contain only lowercase letters, numbers, and underscores.');
        return;
    }
});
</script>

<?php include '../includes/footer.php'; ?>
