<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
$offlineAppModel = new OfflineApplication($database);
$programModel = new Program($database);
$userModel = new User($database);

// Check admin access
requireRole(['admin', 'admissions_officer']);

$pageTitle = 'Bulk Upload Offline Applications';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'Offline Applications', 'url' => '/admin/offline-applications.php'],
    ['name' => 'Bulk Upload', 'url' => '/admin/offline-application-bulk-upload.php']
];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'upload_csv':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                        $result = $offlineAppModel->bulkImportFromCSV($_FILES['csv_file'], $_SESSION['user_id']);
                        
                        if ($result['success']) {
                            $message = "Successfully imported {$result['imported_count']} applications. ";
                            if ($result['error_count'] > 0) {
                                $message .= "{$result['error_count']} records had errors.";
                            }
                            $messageType = $result['error_count'] > 0 ? 'warning' : 'success';
                        } else {
                            $message = 'Failed to import applications: ' . implode(', ', $result['errors']);
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'Please select a valid CSV file.';
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'download_template':
                $offlineAppModel->downloadCSVTemplate();
                exit;
                break;
        }
    }
}

// Get programs for reference
$programs = $programModel->getActive();

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
                    <i class="bi bi-upload me-2"></i>Bulk Upload Offline Applications
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Instructions:</strong>
                    <ol class="mb-0 mt-2">
                        <li>Download the CSV template below</li>
                        <li>Fill in the application data following the template format</li>
                        <li>Upload the completed CSV file</li>
                        <li>Review any errors and correct them if needed</li>
                    </ol>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="action" value="upload_csv">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">Select CSV File *</label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" 
                               accept=".csv" required>
                        <div class="form-text">Only CSV files are allowed. Maximum file size: 10MB</div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-2"></i>Upload CSV File
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
                    <i class="bi bi-download me-2"></i>Download Template
                </h5>
            </div>
            <div class="card-body">
                <p>Download the CSV template to see the required format and column headers.</p>
                
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="download_template">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-download me-2"></i>Download Template
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-list-check me-2"></i>CSV Format Requirements
                </h5>
            </div>
            <div class="card-body">
                <h6>Required Columns:</h6>
                <ul class="small">
                    <li><strong>student_first_name</strong> - First name</li>
                    <li><strong>student_last_name</strong> - Last name</li>
                    <li><strong>program_id</strong> - Program ID (see list below)</li>
                    <li><strong>application_date</strong> - Date (YYYY-MM-DD)</li>
                    <li><strong>entry_method</strong> - Method (download, manual, mail)</li>
                </ul>
                
                <h6>Optional Columns:</h6>
                <ul class="small">
                    <li><strong>student_email</strong> - Email address</li>
                    <li><strong>student_phone</strong> - Phone number</li>
                    <li><strong>conversion_notes</strong> - Additional notes</li>
                </ul>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-mortarboard me-2"></i>Available Programs
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Program</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($programs as $program): ?>
                                <tr>
                                    <td><?php echo $program['id']; ?></td>
                                    <td><?php echo $program['program_name']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('csv_file');
    const file = fileInput.files[0];
    
    if (!file) {
        e.preventDefault();
        alert('Please select a CSV file to upload.');
        return;
    }
    
    // Check file type
    if (!file.name.toLowerCase().endsWith('.csv')) {
        e.preventDefault();
        alert('Please select a valid CSV file.');
        return;
    }
    
    // Check file size (10MB limit)
    if (file.size > 10 * 1024 * 1024) {
        e.preventDefault();
        alert('File size must be less than 10MB.');
        return;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Uploading...';
    submitBtn.disabled = true;
});
</script>

<?php include '../includes/footer.php'; ?>
