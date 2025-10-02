<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
$programModel = new Program($database);
$pdfGenerator = new PDFGenerator($database);

// Check if user is logged in (optional for public download)
$isLoggedIn = isLoggedIn();

$pageTitle = 'Download Application Form';
$breadcrumbs = [
    ['name' => 'Home', 'url' => '/'],
    ['name' => 'Download Application', 'url' => '/student/download-application.php']
];

// Get program ID from URL
$programId = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'download_form') {
        $programId = (int)$_POST['program_id'];
        $applicantName = sanitizeInput($_POST['applicant_name']);
        $applicantEmail = sanitizeInput($_POST['applicant_email']);
        
        // Validate program
        $program = $programModel->getById($programId);
        if (!$program) {
            $message = 'Invalid program selected.';
            $messageType = 'danger';
        } else {
            // Generate application number
            $applicationNumber = 'OFF' . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Generate PDF
            $pdf = $pdfGenerator->generateApplicationForm($programId, $applicationNumber);
            
            if ($pdf) {
                // Save application record for tracking
                $offlineAppModel = new OfflineApplication($database);
                $applicationData = [
                    'application_number' => $applicationNumber,
                    'program_id' => $programId,
                    'student_first_name' => explode(' ', $applicantName)[0] ?? '',
                    'student_last_name' => implode(' ', array_slice(explode(' ', $applicantName), 1)) ?? '',
                    'student_email' => $applicantEmail,
                    'application_date' => date('Y-m-d'),
                    'entry_method' => 'download',
                    'status' => 'downloaded',
                    'received_by' => 1, // System user
                    'conversion_notes' => 'Form downloaded by applicant'
                ];
                
                $offlineAppModel->create($applicationData);
                
                // Send acknowledgment email if email provided
                if ($applicantEmail && filter_var($applicantEmail, FILTER_VALIDATE_EMAIL)) {
                    $notificationModel = new Notification($database);
                    $subject = 'Application Form Downloaded - ' . $applicationNumber;
                    $message = "Dear {$applicantName},\n\n";
                    $message .= "Thank you for downloading the application form for {$program['program_name']}.\n\n";
                    $message .= "Your application tracking number is: {$applicationNumber}\n\n";
                    $message .= "Please complete the form and submit it along with all required documents.\n\n";
                    $message .= "For any questions, please contact our admissions office.\n\n";
                    $message .= "Best regards,\nAdmissions Office";
                    
                    $notificationModel->sendEmail($applicantEmail, $subject, $message);
                }
                
                // Output PDF
                $filename = 'Application_Form_' . $applicationNumber . '.pdf';
                $pdfGenerator->outputPDF($pdf, $filename);
                exit;
            } else {
                $message = 'Failed to generate application form. Please try again.';
                $messageType = 'danger';
            }
        }
    }
}

// Get all active programs
$programs = $programModel->getActive();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">
                        <i class="bi bi-download me-2"></i>Download Application Form
                    </h4>
                </div>
                <div class="card-body">
                    <?php if (isset($message)): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                            <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Instructions:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Select the program you wish to apply for</li>
                            <li>Fill in your contact information for tracking purposes</li>
                            <li>Download the fillable PDF form</li>
                            <li>Complete the form digitally or print and fill manually</li>
                            <li>Submit the completed form along with required documents</li>
                        </ul>
                    </div>

                    <form method="POST" id="downloadForm">
                        <input type="hidden" name="action" value="download_form">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="program_id" class="form-label">Select Program *</label>
                                    <select class="form-select" id="program_id" name="program_id" required>
                                        <option value="">Choose a program...</option>
                                        <?php foreach ($programs as $program): ?>
                                            <option value="<?php echo $program['id']; ?>" 
                                                    <?php echo $programId == $program['id'] ? 'selected' : ''; ?>>
                                                <?php echo $program['program_name']; ?>
                                                <?php if ($program['department']): ?>
                                                    - <?php echo $program['department']; ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Select the program you wish to apply for</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="applicant_name" class="form-label">Your Full Name *</label>
                                    <input type="text" class="form-control" id="applicant_name" name="applicant_name" 
                                           value="<?php echo $isLoggedIn ? $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] : ''; ?>" 
                                           required>
                                    <div class="form-text">Enter your full name as it appears on your documents</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="applicant_email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="applicant_email" name="applicant_email" 
                                           value="<?php echo $isLoggedIn ? $_SESSION['email'] : ''; ?>" 
                                           required>
                                    <div class="form-text">We'll send you a tracking number and updates</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="applicant_phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="applicant_phone" name="applicant_phone" 
                                           value="<?php echo $isLoggedIn ? $_SESSION['phone'] ?? '' : ''; ?>">
                                    <div class="form-text">Optional - for important updates</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="agree_terms" required>
                                <label class="form-check-label" for="agree_terms">
                                    I agree to the <a href="/terms" target="_blank">Terms and Conditions</a> and 
                                    <a href="/privacy" target="_blank">Privacy Policy</a>
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-download me-2"></i>Download Application Form
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Program Information -->
            <?php if ($programId && $program = $programModel->getById($programId)): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-info-circle me-2"></i>Program Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Program Details</h6>
                                <p><strong>Program:</strong> <?php echo $program['program_name']; ?></p>
                                <p><strong>Department:</strong> <?php echo $program['department']; ?></p>
                                <p><strong>Degree Level:</strong> <?php echo ucfirst($program['degree_level']); ?></p>
                                <p><strong>Duration:</strong> <?php echo $program['duration_months']; ?> months</p>
                            </div>
                            <div class="col-md-6">
                                <h6>Fees</h6>
                                <p><strong>Application Fee:</strong> <?php echo $program['application_fee'] ? formatCurrency($program['application_fee']) : 'Free'; ?></p>
                                <p><strong>Tuition Fee:</strong> <?php echo $program['tuition_fee'] ? formatCurrency($program['tuition_fee']) : 'Contact for details'; ?></p>
                                <?php if ($program['application_deadline']): ?>
                                    <p><strong>Deadline:</strong> <?php echo formatDate($program['application_deadline']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($program['description']): ?>
                            <div class="mt-3">
                                <h6>Description</h6>
                                <p><?php echo nl2br($program['description']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($program['requirements']): ?>
                            <div class="mt-3">
                                <h6>Requirements</h6>
                                <p><?php echo nl2br($program['requirements']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Required Documents -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-file-earmark-text me-2"></i>Required Documents
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Academic Documents</h6>
                            <ul>
                                <li>Birth Certificate</li>
                                <li>Academic Transcripts/Results</li>
                                <li>Previous School Transfer Certificate</li>
                                <li>Recommendation Letters (2)</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Other Documents</h6>
                            <ul>
                                <li>Passport Photograph (2 copies)</li>
                                <li>Medical Certificate</li>
                                <li>National ID/Passport Copy</li>
                                <li>Any other program-specific documents</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> All documents must be clear, legible, and properly certified where required. 
                        Submit both original and photocopies of documents.
                    </div>
                </div>
            </div>
            
            <!-- Submission Instructions -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-send me-2"></i>How to Submit
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center">
                                <i class="bi bi-download display-4 text-primary"></i>
                                <h6 class="mt-2">1. Download Form</h6>
                                <p class="text-muted">Download and complete the application form</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <i class="bi bi-file-earmark-check display-4 text-success"></i>
                                <h6 class="mt-2">2. Prepare Documents</h6>
                                <p class="text-muted">Gather all required documents</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <i class="bi bi-send display-4 text-info"></i>
                                <h6 class="mt-2">3. Submit</h6>
                                <p class="text-muted">Submit in person or by mail</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h6>Submission Methods:</h6>
                        <ul>
                            <li><strong>In Person:</strong> Visit our admissions office during business hours</li>
                            <li><strong>By Mail:</strong> Send to our mailing address (registered mail recommended)</li>
                            <li><strong>Email:</strong> Scan and email completed form and documents</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('downloadForm').addEventListener('submit', function(e) {
    const programId = document.getElementById('program_id').value;
    const applicantName = document.getElementById('applicant_name').value;
    const applicantEmail = document.getElementById('applicant_email').value;
    const agreeTerms = document.getElementById('agree_terms').checked;
    
    if (!programId || !applicantName || !applicantEmail || !agreeTerms) {
        e.preventDefault();
        alert('Please fill in all required fields and agree to the terms.');
        return;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Generating PDF...';
    submitBtn.disabled = true;
});

// Program selection handler
document.getElementById('program_id').addEventListener('change', function() {
    const programId = this.value;
    if (programId) {
        // Reload page with program info
        window.location.href = '?program_id=' + programId;
    }
});
</script>

<?php include '../includes/footer.php'; ?>
