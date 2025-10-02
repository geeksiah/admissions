<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
$studentModel = new Student($database);
$programModel = new Program($database);
$applicationModel = new Application($database);
$systemConfig = new SystemConfig($database);
$voucherModel = new Voucher($database);

// Check student access
requireRole(['student']);

// Check if voucher is required for application access
$voucherRequired = $systemConfig->isVoucherRequired();
$accessMode = $systemConfig->getApplicationAccessMode();

$pageTitle = 'Apply for Program';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'Apply', 'url' => '/student/apply.php']
];

// Get current user's student record
$currentUser = $userModel->getById($_SESSION['user_id']);
$student = $studentModel->getByEmail($currentUser['email']);

// Get available programs
$programs = $programModel->getActive();

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'validate_voucher') {
            // Validate voucher before allowing application
            if (validateCSRFToken($_POST['csrf_token'] ?? '')) {
                $pin = sanitizeInput($_POST['voucher_pin']);
                $serial = sanitizeInput($_POST['voucher_serial']);
                
                $voucher = $voucherModel->validateByPinSerial($pin, $serial);
                if ($voucher && $voucher['status'] === 'active') {
                    // Check if voucher is still valid
                    $today = date('Y-m-d');
                    if ($today >= $voucher['valid_from'] && $today <= $voucher['valid_until']) {
                        // Check if voucher has remaining uses
                        if ($voucher['used_count'] < $voucher['max_uses']) {
                            $_SESSION['validated_voucher'] = $voucher;
                            $message = 'Voucher validated successfully! You can now proceed with your application.';
                            $messageType = 'success';
                        } else {
                            $message = 'This voucher has reached its maximum usage limit.';
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'This voucher has expired.';
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Invalid voucher PIN or SERIAL. Please check and try again.';
                    $messageType = 'danger';
                }
            } else {
                $message = 'Invalid security token. Please try again.';
                $messageType = 'danger';
            }
        } elseif ($_POST['action'] === 'submit_application') {
            // Check if voucher is required and validated
            if ($voucherRequired && !isset($_SESSION['validated_voucher'])) {
                $message = 'Please validate your voucher before submitting the application.';
                $messageType = 'danger';
            } else {
                // Validate CSRF token
                if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                    $message = 'Invalid security token. Please try again.';
                    $messageType = 'danger';
                } else {
            $validator = new Validator($_POST);
            $validator->required(['program_id', 'notes'])
                     ->integer('program_id');
            
            if (!$validator->hasErrors()) {
                $data = $validator->getValidatedData();
                
                // Check if student exists
                if (!$student) {
                    $message = 'Student profile not found. Please contact administrator.';
                    $messageType = 'danger';
                } else {
                    // Check if program has capacity
                    $program = $programModel->getById($data['program_id']);
                    if (!$programModel->hasCapacity($data['program_id'])) {
                        $message = 'This program has reached its maximum capacity.';
                        $messageType = 'danger';
                    } else {
                        // Check if student already applied to this program
                        $existingApplications = $applicationModel->getByStudent($student['id']);
                        $alreadyApplied = false;
                        foreach ($existingApplications as $app) {
                            if ($app['program_id'] == $data['program_id'] && !in_array($app['status'], ['rejected', 'withdrawn'])) {
                                $alreadyApplied = true;
                                break;
                            }
                        }
                        
                        if ($alreadyApplied) {
                            $message = 'You have already applied to this program.';
                            $messageType = 'danger';
                        } else {
                            // Create application
                            $applicationData = [
                                'student_id' => $student['id'],
                                'program_id' => $data['program_id'],
                                'status' => 'submitted',
                                'priority' => 'medium',
                                'notes' => $data['notes']
                            ];
                            
                            if ($applicationModel->create($applicationData)) {
                                $message = 'Application submitted successfully! You will receive a confirmation email shortly.';
                                $messageType = 'success';
                                
                                // Redirect to applications page after successful submission
                                header('refresh:3;url=/student/applications.php');
                            } else {
                                $message = 'Failed to submit application. Please try again.';
                                $messageType = 'danger';
                            }
                        }
                    }
                }
            } else {
                $message = 'Please correct the errors below.';
                $messageType = 'danger';
            }
                }
            }
        }
    }
}

$csrfToken = generateCSRFToken();

include '../includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!$student): ?>
    <div class="alert alert-warning" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Student Profile Required:</strong> You need to complete your student profile before applying to programs. 
        <a href="/student/profile.php" class="alert-link">Complete your profile now</a>.
    </div>
<?php else: ?>
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-file-plus me-2"></i>New Application
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($voucherRequired && !isset($_SESSION['validated_voucher'])): ?>
                        <!-- Voucher Validation Section -->
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Voucher Required:</strong> You need to validate your voucher before you can submit an application.
                        </div>
                        
                        <form method="POST" id="voucherForm" class="mb-4">
                            <input type="hidden" name="action" value="validate_voucher">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="voucher_pin" class="form-label">Voucher PIN *</label>
                                        <input type="text" class="form-control" id="voucher_pin" name="voucher_pin" 
                                               placeholder="Enter 6-digit PIN" maxlength="6" required>
                                        <div class="form-text">Enter the 6-digit PIN from your voucher</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="voucher_serial" class="form-label">Voucher SERIAL *</label>
                                        <input type="text" class="form-control" id="voucher_serial" name="voucher_serial" 
                                               placeholder="Enter 12-character SERIAL" maxlength="12" required>
                                        <div class="form-text">Enter the 12-character SERIAL from your voucher</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>Validate Voucher
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center">
                            <p class="text-muted">Don't have a voucher? <a href="/contact">Contact our admissions office</a></p>
                        </div>
                    <?php else: ?>
                        <!-- Application Form -->
                        <form method="POST" id="applicationForm">
                        <input type="hidden" name="action" value="submit_application">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        
                        <div class="mb-4">
                            <h6 class="text-primary mb-3">Student Information</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Name</label>
                                        <input type="text" class="form-control" 
                                               value="<?php echo $student['first_name'] . ' ' . $student['last_name']; ?>" 
                                               readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Student ID</label>
                                        <input type="text" class="form-control" 
                                               value="<?php echo $student['student_id']; ?>" 
                                               readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" 
                                               value="<?php echo $student['email']; ?>" 
                                               readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="tel" class="form-control" 
                                               value="<?php echo $student['phone']; ?>" 
                                               readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="text-primary mb-3">Program Selection</h6>
                            <div class="mb-3">
                                <label for="program_id" class="form-label">Select Program *</label>
                                <select class="form-select" id="program_id" name="program_id" required>
                                    <option value="">Choose a program...</option>
                                    <?php foreach ($programs as $program): ?>
                                        <option value="<?php echo $program['id']; ?>" 
                                                data-fee="<?php echo $program['application_fee']; ?>"
                                                data-deadline="<?php echo $program['application_deadline']; ?>"
                                                data-capacity="<?php echo $program['current_enrolled'] . '/' . ($program['max_capacity'] ?? '∞'); ?>">
                                            <?php echo $program['program_name']; ?> (<?php echo $program['program_code']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Program Details -->
                            <div id="programDetails" class="card bg-light" style="display: none;">
                                <div class="card-body">
                                    <h6 class="card-title">Program Information</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Application Fee:</strong> <span id="applicationFee">-</span></p>
                                            <p><strong>Application Deadline:</strong> <span id="applicationDeadline">-</span></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Current Capacity:</strong> <span id="programCapacity">-</span></p>
                                            <p><strong>Degree Level:</strong> <span id="degreeLevel">-</span></p>
                                        </div>
                                    </div>
                                    <div id="programDescription" class="mt-2"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="text-primary mb-3">Additional Information</h6>
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes or Comments</label>
                                <textarea class="form-control" id="notes" name="notes" rows="4" 
                                          placeholder="Any additional information you'd like to include with your application..."></textarea>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="text-primary mb-3">Required Documents</h6>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Note:</strong> You will be able to upload required documents after submitting your application. 
                                Required documents typically include:
                                <ul class="mb-0 mt-2">
                                    <li>Academic transcripts</li>
                                    <li>Statement of purpose</li>
                                    <li>Letters of recommendation</li>
                                    <li>Test scores (if applicable)</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="/student/programs.php" class="btn btn-outline-secondary me-md-2">
                                <i class="bi bi-arrow-left me-2"></i>Back to Programs
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-2"></i>Submit Application
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Application Guidelines -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>Application Guidelines
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            Review program requirements carefully
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            Ensure all information is accurate
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            Submit before the deadline
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            Upload required documents
                        </li>
                        <li class="mb-0">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            Track your application status
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- My Applications -->
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-file-text me-2"></i>My Applications
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    $myApplications = $applicationModel->getByStudent($student['id']);
                    if (!empty($myApplications)):
                    ?>
                        <?php foreach (array_slice($myApplications, 0, 3) as $app): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <small class="text-muted"><?php echo $app['program_name']; ?></small>
                                    <br>
                                    <span class="badge status-<?php echo str_replace('_', '-', $app['status']); ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $app['status'])); ?>
                                    </span>
                                </div>
                                <small class="text-muted"><?php echo formatDate($app['application_date']); ?></small>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($myApplications) > 3): ?>
                            <div class="text-center mt-3">
                                <a href="/student/applications.php" class="btn btn-sm btn-outline-primary">
                                    View All Applications
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">No applications submitted yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
// Program selection handler
document.getElementById('program_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const programDetails = document.getElementById('programDetails');
    
    if (this.value) {
        // Show program details
        programDetails.style.display = 'block';
        
        // Update program information
        document.getElementById('applicationFee').textContent = 
            selectedOption.dataset.fee ? '$' + parseFloat(selectedOption.dataset.fee).toFixed(2) : 'N/A';
        
        document.getElementById('applicationDeadline').textContent = 
            selectedOption.dataset.deadline ? new Date(selectedOption.dataset.deadline).toLocaleDateString() : 'N/A';
        
        document.getElementById('programCapacity').textContent = selectedOption.dataset.capacity || 'N/A';
        
        // Get degree level from option text
        const degreeLevel = selectedOption.textContent.match(/\(([^)]+)\)/);
        document.getElementById('degreeLevel').textContent = degreeLevel ? degreeLevel[1] : 'N/A';
        
        // You could fetch and display program description here via AJAX
        document.getElementById('programDescription').innerHTML = 
            '<p class="text-muted">Program details will be displayed here.</p>';
    } else {
        programDetails.style.display = 'none';
    }
});

// Form validation
document.getElementById('applicationForm').addEventListener('submit', function(e) {
    const programId = document.getElementById('program_id').value;
    const notes = document.getElementById('notes').value.trim();
    
    if (!programId) {
        e.preventDefault();
        alert('Please select a program.');
        return;
    }
    
    if (!notes) {
        e.preventDefault();
        alert('Please provide some notes or comments.');
        return;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Submitting...';
    submitBtn.disabled = true;
});
</script>

<?php include '../includes/footer.php'; ?>
