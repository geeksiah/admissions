<?php
/**
 * Student Application Form
 * Complete end-to-end application flow as recommended in Improvements.txt
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/SecurityMiddleware.php';

// Security checks
$security = SecurityMiddleware::getInstance();
$security->requireRole('student');

$database = new Database();
$pdo = $database->getConnection();

$applicationId = (int)($_GET['id'] ?? 0);
$programId = (int)($_GET['program'] ?? 0);
$step = (int)($_GET['step'] ?? 1);

$message = '';
$messageType = '';

// Get student info
$studentId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header('Location: /student/login');
    exit;
}

// Get application if editing
$application = null;
if ($applicationId) {
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ? AND student_id = ?");
    $stmt->execute([$applicationId, $studentId]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        header('Location: /student/dashboard');
        exit;
    }
}

// Get program info
if ($programId) {
    $stmt = $pdo->prepare("SELECT * FROM programs WHERE id = ? AND is_active = 1");
    $stmt->execute([$programId]);
    $program = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $program = null;
}

// Get form template
$formTemplate = null;
if ($program) {
    $stmt = $pdo->prepare("SELECT * FROM form_templates WHERE program_id = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$programId]);
    $formTemplate = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$formTemplate) {
    // Get general template
    $stmt = $pdo->prepare("SELECT * FROM form_templates WHERE program_id IS NULL AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    $formTemplate = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$formTemplate) {
    die('No application form template available');
}

$formStructure = json_decode($formTemplate['form_structure'], true);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $security->enforceCSRF();
        
        $action = $_POST['action'] ?? '';
        
        if ($action === 'save_draft') {
            // Save as draft
            $formData = json_encode($_POST['form_data'] ?? []);
            $status = 'draft';
            
            if ($application) {
                $stmt = $pdo->prepare("UPDATE applications SET form_data = ?, status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$formData, $status, $applicationId]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO applications (student_id, program_id, form_data, status, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$studentId, $programId, $formData, $status]);
                $applicationId = $pdo->lastInsertId();
            }
            
            $message = 'Application saved as draft';
            $messageType = 'success';
            
        } elseif ($action === 'submit') {
            // Submit application
            $formData = json_encode($_POST['form_data'] ?? []);
            $status = 'submitted';
            
            // Validate required fields
            $errors = validateFormData($_POST['form_data'] ?? [], $formStructure);
            if (!empty($errors)) {
                throw new RuntimeException('Please fill in all required fields: ' . implode(', ', $errors));
            }
            
            if ($application) {
                $stmt = $pdo->prepare("UPDATE applications SET form_data = ?, status = ?, submitted_at = NOW(), updated_at = NOW() WHERE id = ?");
                $stmt->execute([$formData, $status, $applicationId]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO applications (student_id, program_id, form_data, status, submitted_at, created_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$studentId, $programId, $formData, $status]);
                $applicationId = $pdo->lastInsertId();
            }
            
            // Log application submission
            $security->logSecurityEvent('application_submitted', [
                'application_id' => $applicationId,
                'program_id' => $programId,
                'student_id' => $studentId
            ]);
            
            $message = 'Application submitted successfully';
            $messageType = 'success';
            
            // Redirect to dashboard
            header('Location: /student/dashboard?message=' . urlencode($message) . '&type=' . $messageType);
            exit;
        }
        
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

function validateFormData($data, $structure) {
    $errors = [];
    
    foreach ($structure['sections'] as $section) {
        foreach ($section['fields'] as $field) {
            if ($field['required'] && empty($data[$field['name']])) {
                $errors[] = $field['label'];
            }
        }
    }
    
    return $errors;
}

// Get existing form data
$existingData = [];
if ($application && $application['form_data']) {
    $existingData = json_decode($application['form_data'], true) ?: [];
}

$pageTitle = 'Application Form';
include __DIR__ . '/../includes/header.php';
?>

<div class="application-form-container">
    <div class="form-header">
        <h1><?php echo htmlspecialchars($program['name'] ?? 'Application Form'); ?></h1>
        <?php if ($program): ?>
            <p class="muted">Apply for <?php echo htmlspecialchars($program['name']); ?></p>
        <?php endif; ?>
        
        <!-- Progress Indicator -->
        <div class="progress-indicator">
            <div class="progress-step <?php echo $step >= 1 ? 'active' : ''; ?>">
                <div class="step-number">1</div>
                <div class="step-label">Personal Info</div>
            </div>
            <div class="progress-step <?php echo $step >= 2 ? 'active' : ''; ?>">
                <div class="step-number">2</div>
                <div class="step-label">Academic Info</div>
            </div>
            <div class="progress-step <?php echo $step >= 3 ? 'active' : ''; ?>">
                <div class="step-number">3</div>
                <div class="step-label">Documents</div>
            </div>
            <div class="progress-step <?php echo $step >= 4 ? 'active' : ''; ?>">
                <div class="step-number">4</div>
                <div class="step-label">Review & Submit</div>
            </div>
        </div>
    </div>

    <form method="post" id="applicationForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="save_draft">
        
        <?php foreach ($formStructure['sections'] as $sectionIndex => $section): ?>
            <div class="form-section" data-section="<?php echo $sectionIndex; ?>">
                <h3><?php echo htmlspecialchars($section['title']); ?></h3>
                
                <div class="form-fields">
                    <?php foreach ($section['fields'] as $fieldIndex => $field): ?>
                        <div class="form-group">
                            <label class="form-label <?php echo $field['required'] ? 'required' : ''; ?>" for="field_<?php echo $field['name']; ?>">
                                <?php echo htmlspecialchars($field['label']); ?>
                            </label>
                            
                            <?php if ($field['type'] === 'text' || $field['type'] === 'email' || $field['type'] === 'phone' || $field['type'] === 'number'): ?>
                                <div class="input-icon">
                                    <i class="bi bi-<?php echo $field['type'] === 'email' ? 'envelope' : ($field['type'] === 'phone' ? 'telephone' : 'person'); ?> icon"></i>
                                    <input 
                                        type="<?php echo $field['type']; ?>" 
                                        id="field_<?php echo $field['name']; ?>" 
                                        name="form_data[<?php echo $field['name']; ?>]" 
                                        class="input" 
                                        value="<?php echo htmlspecialchars($existingData[$field['name']] ?? ''); ?>"
                                        placeholder="<?php echo htmlspecialchars($field['placeholder'] ?? ''); ?>"
                                        <?php echo $field['required'] ? 'required' : ''; ?>
                                    >
                                </div>
                                
                            <?php elseif ($field['type'] === 'textarea'): ?>
                                <textarea 
                                    id="field_<?php echo $field['name']; ?>" 
                                    name="form_data[<?php echo $field['name']; ?>]" 
                                    class="input" 
                                    rows="4"
                                    placeholder="<?php echo htmlspecialchars($field['placeholder'] ?? ''); ?>"
                                    <?php echo $field['required'] ? 'required' : ''; ?>
                                ><?php echo htmlspecialchars($existingData[$field['name']] ?? ''); ?></textarea>
                                
                            <?php elseif ($field['type'] === 'select'): ?>
                                <select 
                                    id="field_<?php echo $field['name']; ?>" 
                                    name="form_data[<?php echo $field['name']; ?>]" 
                                    class="input"
                                    <?php echo $field['required'] ? 'required' : ''; ?>
                                >
                                    <option value="">Select an option...</option>
                                    <?php foreach (($field['options'] ?? []) as $option): ?>
                                        <option value="<?php echo htmlspecialchars($option); ?>" 
                                                <?php echo ($existingData[$field['name']] ?? '') === $option ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                            <?php elseif ($field['type'] === 'radio'): ?>
                                <div class="radio-group">
                                    <?php foreach (($field['options'] ?? []) as $option): ?>
                                        <label class="radio-option">
                                            <input 
                                                type="radio" 
                                                name="form_data[<?php echo $field['name']; ?>]" 
                                                value="<?php echo htmlspecialchars($option); ?>"
                                                <?php echo ($existingData[$field['name']] ?? '') === $option ? 'checked' : ''; ?>
                                                <?php echo $field['required'] ? 'required' : ''; ?>
                                            >
                                            <span><?php echo htmlspecialchars($option); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                
                            <?php elseif ($field['type'] === 'checkbox'): ?>
                                <div class="checkbox-group">
                                    <?php foreach (($field['options'] ?? []) as $option): ?>
                                        <label class="checkbox-option">
                                            <input 
                                                type="checkbox" 
                                                name="form_data[<?php echo $field['name']; ?>][]" 
                                                value="<?php echo htmlspecialchars($option); ?>"
                                                <?php echo in_array($option, $existingData[$field['name']] ?? []) ? 'checked' : ''; ?>
                                            >
                                            <span><?php echo htmlspecialchars($option); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                
                            <?php elseif ($field['type'] === 'file'): ?>
                                <input 
                                    type="file" 
                                    id="field_<?php echo $field['name']; ?>" 
                                    name="form_data[<?php echo $field['name']; ?>]" 
                                    class="input"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    <?php echo $field['required'] ? 'required' : ''; ?>
                                >
                                <div class="file-help">Accepted formats: PDF, JPG, PNG (Max 5MB)</div>
                                
                            <?php elseif ($field['type'] === 'date'): ?>
                                <input 
                                    type="date" 
                                    id="field_<?php echo $field['name']; ?>" 
                                    name="form_data[<?php echo $field['name']; ?>]" 
                                    class="input"
                                    value="<?php echo htmlspecialchars($existingData[$field['name']] ?? ''); ?>"
                                    <?php echo $field['required'] ? 'required' : ''; ?>
                                >
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="saveDraft()">
                <i class="bi bi-save"></i> Save Draft
            </button>
            <button type="button" class="btn btn-primary" onclick="submitApplication()">
                <i class="bi bi-send"></i> Submit Application
            </button>
        </div>
    </form>
</div>

<style>
.application-form-container {
    max-width: 800px;
    margin: 0 auto;
    padding: var(--space-6);
}

.form-header {
    margin-bottom: var(--space-8);
    text-align: center;
}

.progress-indicator {
    display: flex;
    justify-content: center;
    gap: var(--space-4);
    margin: var(--space-6) 0;
}

.progress-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--space-2);
    opacity: 0.5;
    transition: var(--transition);
}

.progress-step.active {
    opacity: 1;
}

.step-number {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--border);
    color: var(--text);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: var(--text-sm);
}

.progress-step.active .step-number {
    background: var(--primary);
    color: white;
}

.step-label {
    font-size: var(--text-xs);
    color: var(--muted);
    text-align: center;
}

.form-section {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: var(--space-6);
    margin-bottom: var(--space-6);
}

.form-section h3 {
    margin: 0 0 var(--space-4) 0;
    color: var(--text);
    font-size: var(--text-lg);
    border-bottom: 1px solid var(--border);
    padding-bottom: var(--space-2);
}

.radio-group, .checkbox-group {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
}

.radio-option, .checkbox-option {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    cursor: pointer;
    padding: var(--space-2);
    border-radius: var(--radius-sm);
    transition: var(--transition);
}

.radio-option:hover, .checkbox-option:hover {
    background: var(--surface-hover);
}

.radio-option input, .checkbox-option input {
    margin: 0;
}

.file-help {
    font-size: var(--text-xs);
    color: var(--muted);
    margin-top: var(--space-1);
}

.form-actions {
    display: flex;
    gap: var(--space-3);
    justify-content: center;
    margin-top: var(--space-8);
    padding-top: var(--space-6);
    border-top: 1px solid var(--border);
}
</style>

<script>
function saveDraft() {
    document.querySelector('input[name="action"]').value = 'save_draft';
    document.getElementById('applicationForm').submit();
}

function submitApplication() {
    if (confirm('Are you sure you want to submit this application? You will not be able to make changes after submission.')) {
        document.querySelector('input[name="action"]').value = 'submit';
        document.getElementById('applicationForm').submit();
    }
}

// Auto-save draft every 30 seconds
setInterval(() => {
    if (document.querySelector('input[name="action"]').value === 'save_draft') {
        saveDraft();
    }
}, 30000);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
