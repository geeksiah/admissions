<?php
// Check if we're in application form mode
$isApplicationForm = isset($_GET['form']) && $_GET['form'] === 'new';
$selectedProgramId = isset($_GET['program_id']) ? (int)$_GET['program_id'] : null;
$currentStep = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Get available programs for selection
$availablePrograms = $programModel->getAll(['status' => 'active']);

// If we're in form mode and have a program selected, get program details
$selectedProgram = null;
if ($selectedProgramId && $isApplicationForm) {
    $selectedProgram = $programModel->getById($selectedProgramId);
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action']) && $_POST['action'] === 'submit_application') {
        // Handle multi-step application submission
        $step = (int)$_POST['step'];
        $programId = (int)$_POST['program_id'];
        
        try {
            // For now, we'll handle the basic application
            // In a real system, this would save step-by-step data
            $applicationData = [
                'student_id' => $student['id'],
                'program_id' => $programId,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            if ($applicationModel->create($applicationData)) {
                // Send notification
                try {
                    require_once '../classes/NotificationManager.php';
                    $notificationManager = new NotificationManager($database, $systemConfig);
                    
                    $program = $programModel->getById($programId);
                    $notificationManager->sendNotification($_SESSION['user_id'], 'application_submitted', [
                        'student_name' => $currentUser['first_name'] . ' ' . $currentUser['last_name'],
                        'program_name' => $program['program_name'] ?? 'Unknown Program',
                        'application_id' => 'APP' . str_pad($programId, 4, '0', STR_PAD_LEFT),
                        'program_code' => $program['program_code'] ?? ''
                    ], ['email', 'push']);
                } catch (Exception $e) {
                    // Log notification error but don't fail the application
                    error_log("Notification error: " . $e->getMessage());
                }
                
                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>Application submitted successfully! You will receive a confirmation email shortly.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                      </div>';
                
                // Redirect back to applications list
                echo '<script>window.location.href = "?panel=applications";</script>';
            }
        } catch (Exception $e) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i>Error submitting application: ' . htmlspecialchars($e->getMessage()) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>';
        }
    }
}
?>

<?php if ($isApplicationForm && $selectedProgram): ?>
    <!-- Application Form with Breadcrumbs -->
    <div class="row mb-3">
        <div class="col-12">
            <!-- Breadcrumb Navigation -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="#" onclick="showPanel('applications')" style="text-decoration: none;">
                            <i class="bi bi-file-earmark-text me-1"></i>My Applications
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        Apply to <?php echo htmlspecialchars($selectedProgram['program_name']); ?>
                    </li>
                </ol>
            </nav>
            
            <!-- Progress Steps -->
            <div class="progress-steps mb-4">
                <div class="d-flex justify-content-between">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div class="step <?php echo $i <= $currentStep ? 'active' : ''; ?> <?php echo $i < $currentStep ? 'completed' : ''; ?>">
                            <div class="step-circle">
                                <?php if ($i < $currentStep): ?>
                                    <i class="bi bi-check"></i>
                                <?php else: ?>
                                    <?php echo $i; ?>
                                <?php endif; ?>
                            </div>
                            <div class="step-label">
                                <?php
                                $labels = [
                                    1 => 'Personal Info',
                                    2 => 'Academic Background',
                                    3 => 'Documents',
                                    4 => 'Review & Submit'
                                ];
                                echo $labels[$i];
                                ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Applications List Header -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    My Applications
                </h5>
                <button class="btn btn-primary btn-sm" onclick="showNewApplicationForm()">
                    <i class="bi bi-plus-circle me-2"></i>New Application
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($isApplicationForm && $selectedProgram): ?>
    <!-- Multi-Step Application Form -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="POST" id="applicationForm">
                        <input type="hidden" name="action" value="submit_application">
                        <input type="hidden" name="program_id" value="<?php echo $selectedProgramId; ?>">
                        <input type="hidden" name="step" value="<?php echo $currentStep; ?>">
                        
                        <?php if ($currentStep === 1): ?>
                            <!-- Step 1: Personal Information -->
                            <div class="step-content">
                                <h6 class="mb-3"><i class="bi bi-person me-2"></i>Personal Information</h6>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">First Name *</label>
                                        <input type="text" class="form-control" name="first_name" 
                                               value="<?php echo htmlspecialchars($currentUser['first_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" name="last_name" 
                                               value="<?php echo htmlspecialchars($currentUser['last_name']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email *</label>
                                        <input type="email" class="form-control" name="email" 
                                               value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" name="phone" 
                                               value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Date of Birth *</label>
                                        <input type="date" class="form-control" name="date_of_birth" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nationality *</label>
                                        <input type="text" class="form-control" name="nationality" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Address *</label>
                                    <textarea class="form-control" name="address" rows="3" required></textarea>
                                </div>
                            </div>
                            
                        <?php elseif ($currentStep === 2): ?>
                            <!-- Step 2: Academic Background -->
                            <div class="step-content">
                                <h6 class="mb-3"><i class="bi bi-mortarboard me-2"></i>Academic Background</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label">Highest Education Level *</label>
                                    <select class="form-select" name="education_level" required>
                                        <option value="">Select education level...</option>
                                        <option value="high_school">High School</option>
                                        <option value="diploma">Diploma</option>
                                        <option value="bachelor">Bachelor's Degree</option>
                                        <option value="master">Master's Degree</option>
                                        <option value="phd">PhD</option>
                                    </select>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Institution Name *</label>
                                        <input type="text" class="form-control" name="institution_name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Graduation Year</label>
                                        <input type="number" class="form-control" name="graduation_year" min="1950" max="2030">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">GPA/CGPA</label>
                                        <input type="number" class="form-control" name="gpa" step="0.01" min="0" max="4">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Grade Scale</label>
                                        <select class="form-select" name="grade_scale">
                                            <option value="">Select scale...</option>
                                            <option value="4.0">4.0 Scale</option>
                                            <option value="5.0">5.0 Scale</option>
                                            <option value="100">100 Scale</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Motivation Letter *</label>
                                    <textarea class="form-control" name="motivation" rows="5" 
                                              placeholder="Explain why you want to join this program and your career goals..." required></textarea>
                                    <small class="form-text text-muted">Minimum 200 characters</small>
                                </div>
                            </div>
                            
                        <?php elseif ($currentStep === 3): ?>
                            <!-- Step 3: Documents -->
                            <div class="step-content">
                                <h6 class="mb-3"><i class="bi bi-file-earmark me-2"></i>Required Documents</h6>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Transcript/Certificate *</label>
                                        <input type="file" class="form-control" name="transcript" accept=".pdf,.jpg,.jpeg,.png" required>
                                        <small class="form-text text-muted">PDF, JPG, or PNG (max 5MB)</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Passport/ID Copy *</label>
                                        <input type="file" class="form-control" name="passport" accept=".pdf,.jpg,.jpeg,.png" required>
                                        <small class="form-text text-muted">PDF, JPG, or PNG (max 5MB)</small>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">CV/Resume</label>
                                        <input type="file" class="form-control" name="cv" accept=".pdf,.doc,.docx">
                                        <small class="form-text text-muted">PDF or DOC (max 5MB)</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Passport Photo</label>
                                        <input type="file" class="form-control" name="photo" accept=".jpg,.jpeg,.png">
                                        <small class="form-text text-muted">JPG or PNG (max 2MB)</small>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Note:</strong> All documents will be verified. Please ensure they are clear and legible.
                                </div>
                            </div>
                            
                        <?php elseif ($currentStep === 4): ?>
                            <!-- Step 4: Review & Submit -->
                            <div class="step-content">
                                <h6 class="mb-3"><i class="bi bi-check-circle me-2"></i>Review & Submit</h6>
                                
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Program Information</h6>
                                        <p><strong>Program:</strong> <?php echo htmlspecialchars($selectedProgram['program_name']); ?></p>
                                        <p><strong>Code:</strong> <?php echo htmlspecialchars($selectedProgram['program_code']); ?></p>
                                        <p><strong>Application Fee:</strong> $<?php echo number_format($selectedProgram['application_fee'] ?? 0); ?></p>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="terms" required>
                                        <label class="form-check-label" for="terms">
                                            I agree to the <a href="#" target="_blank">Terms and Conditions</a> and confirm that all information provided is accurate.
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="privacy" required>
                                        <label class="form-check-label" for="privacy">
                                            I consent to the processing of my personal data as described in the <a href="#" target="_blank">Privacy Policy</a>.
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Navigation Buttons -->
                        <div class="d-flex justify-content-between mt-4">
                            <?php if ($currentStep > 1): ?>
                                <a href="?panel=applications&form=new&program_id=<?php echo $selectedProgramId; ?>&step=<?php echo $currentStep - 1; ?>" 
                                   class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>Previous
                                </a>
                            <?php else: ?>
                                <button type="button" class="btn btn-outline-secondary" onclick="showPanel('applications')">
                                    <i class="bi bi-arrow-left me-2"></i>Back to Applications
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($currentStep < 4): ?>
                                <a href="?panel=applications&form=new&program_id=<?php echo $selectedProgramId; ?>&step=<?php echo $currentStep + 1; ?>" 
                                   class="btn btn-primary">
                                    Next <i class="bi bi-arrow-right ms-2"></i>
                                </a>
                            <?php else: ?>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-send me-2"></i>Submit Application
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
<?php else: ?>
    <!-- Applications List -->
    <div class="row">
        <div class="col-12">
            <?php if (empty($applications)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                        <h5 class="text-muted mt-3">No Applications Yet</h5>
                        <p class="text-muted">Start your academic journey by applying to a program.</p>
                        <button class="btn btn-primary" onclick="showNewApplicationForm()">
                            <i class="bi bi-plus-circle me-2"></i>Create First Application
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body" style="padding: 1rem;">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th style="font-size: 0.875rem;">Program</th>
                                        <th style="font-size: 0.875rem;">Applied Date</th>
                                        <th style="font-size: 0.875rem;">Status</th>
                                        <th style="font-size: 0.875rem;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($applications as $app): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong style="font-size: 0.875rem;"><?php echo htmlspecialchars($app['program_name'] ?? 'Unknown Program'); ?></strong>
                                                    <br>
                                                    <small class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($app['program_code'] ?? ''); ?></small>
                                                </div>
                                            </td>
                                            <td style="font-size: 0.875rem;">
                                                <?php echo date('M j, Y', strtotime($app['created_at'])); ?>
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
                                                ?>" style="font-size: 0.625rem;">
                                                    <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-outline-primary btn-sm" style="font-size: 0.75rem;" 
                                                        onclick="viewApplication(<?php echo $app['id']; ?>)">
                                                    <i class="bi bi-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Program Selection Modal -->
<div class="modal fade" id="programSelectionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-book me-2"></i>Select Program to Apply
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Choose a program to start your application process.</p>
                <div class="row">
                    <?php foreach ($availablePrograms as $program): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($program['program_name']); ?></h6>
                                    <p class="card-text">
                                        <small class="text-muted"><?php echo htmlspecialchars($program['program_code']); ?></small><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($program['department'] ?? 'General'); ?></small><br>
                                        <strong>Fee: $<?php echo number_format($program['application_fee'] ?? 0); ?></strong>
                                    </p>
                                    <button class="btn btn-primary btn-sm w-100" 
                                            onclick="selectProgramForApplication(<?php echo $program['id']; ?>)">
                                        <i class="bi bi-file-earmark-plus me-1"></i>Apply Now
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewApplication(appId) {
    // TODO: Implement application detail view
    alert('Application details for ID: ' + appId);
}

function showNewApplicationForm() {
    // Show program selection modal
    const modal = new bootstrap.Modal(document.getElementById('programSelectionModal'));
    modal.show();
}

// Handle program selection for new application
function selectProgramForApplication(programId) {
    // Redirect to application form with selected program
    const url = new URL(window.location);
    url.searchParams.set('panel', 'applications');
    url.searchParams.set('form', 'new');
    url.searchParams.set('program_id', programId);
    url.searchParams.set('step', '1');
    window.history.pushState({}, '', url);
    
    // Reload the page to show the form
    window.location.reload();
}
</script>
