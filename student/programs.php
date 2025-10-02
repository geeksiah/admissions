<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
$programModel = new Program($database);
$studentModel = new Student($database);
$applicationModel = new Application($database);

// Check student access
requireRole(['student']);

$pageTitle = 'Available Programs';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'Programs', 'url' => '/student/programs.php']
];

// Get current user's student record
$currentUser = $userModel->getById($_SESSION['user_id']);
$student = $studentModel->getByEmail($currentUser['email']);

// Get filter parameters
$filters = [];
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}
if (!empty($_GET['department'])) {
    $filters['department'] = $_GET['department'];
}
if (!empty($_GET['degree_level'])) {
    $filters['degree_level'] = $_GET['degree_level'];
}

// Get available programs
$programs = $programModel->getActive();

// Get student's applications to check which programs they've already applied to
$myApplications = [];
if ($student) {
    $myApplications = $applicationModel->getByStudent($student['id']);
}

// Create a map of applied program IDs
$appliedProgramIds = [];
foreach ($myApplications as $app) {
    if (!in_array($app['status'], ['rejected', 'withdrawn'])) {
        $appliedProgramIds[] = $app['program_id'];
    }
}

// Get unique departments for filter
$departments = $programModel->getDepartments();
$degreeLevels = $programModel->getDegreeLevels();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h5 class="mb-0">
            <i class="bi bi-mortarboard me-2"></i>Available Programs
            <span class="badge bg-primary ms-2"><?php echo count($programs); ?></span>
        </h5>
    </div>
    <div class="col-md-6">
        <div class="d-flex gap-2 justify-content-end">
            <a href="/student/applications.php" class="btn btn-outline-primary">
                <i class="bi bi-file-text me-2"></i>My Applications
            </a>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">Search Programs</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                       placeholder="Program name, code, or description">
            </div>
            <div class="col-md-3">
                <label for="department" class="form-label">Department</label>
                <select class="form-select" id="department" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?php echo $department; ?>" 
                                <?php echo ($_GET['department'] ?? '') === $department ? 'selected' : ''; ?>>
                            <?php echo $department; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="degree_level" class="form-label">Degree Level</label>
                <select class="form-select" id="degree_level" name="degree_level">
                    <option value="">All Levels</option>
                    <?php foreach ($degreeLevels as $level): ?>
                        <option value="<?php echo $level; ?>" 
                                <?php echo ($_GET['degree_level'] ?? '') === $level ? 'selected' : ''; ?>>
                            <?php echo ucfirst($level); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-2"></i>Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Programs Grid -->
<?php if (!empty($programs)): ?>
    <div class="row">
        <?php foreach ($programs as $program): ?>
            <?php
            $isApplied = in_array($program['id'], $appliedProgramIds);
            $hasCapacity = $programModel->hasCapacity($program['id']);
            $isDeadlinePassed = $program['application_deadline'] && strtotime($program['application_deadline']) < time();
            ?>
            <div class="col-lg-6 col-xl-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title mb-1"><?php echo $program['program_name']; ?></h6>
                                <small class="opacity-75"><?php echo $program['program_code']; ?></small>
                            </div>
                            <span class="badge bg-light text-dark"><?php echo ucfirst($program['degree_level']); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Department:</span>
                                <strong><?php echo $program['department']; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Duration:</span>
                                <strong><?php echo $program['duration_months']; ?> months</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Tuition Fee:</span>
                                <strong><?php echo $program['tuition_fee'] ? formatCurrency($program['tuition_fee']) : 'N/A'; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Application Fee:</span>
                                <strong><?php echo $program['application_fee'] ? formatCurrency($program['application_fee']) : 'N/A'; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Capacity:</span>
                                <strong><?php echo $program['current_enrolled'] . '/' . ($program['max_capacity'] ?? '∞'); ?></strong>
                            </div>
                            <?php if ($program['application_deadline']): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Deadline:</span>
                                    <strong class="<?php echo $isDeadlinePassed ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo formatDate($program['application_deadline']); ?>
                                    </strong>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($program['description']): ?>
                            <div class="mb-3">
                                <p class="card-text text-muted small">
                                    <?php echo substr($program['description'], 0, 150) . (strlen($program['description']) > 150 ? '...' : ''); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($program['requirements']): ?>
                            <div class="mb-3">
                                <h6 class="text-primary mb-2">Requirements:</h6>
                                <p class="card-text text-muted small">
                                    <?php echo substr($program['requirements'], 0, 100) . (strlen($program['requirements']) > 100 ? '...' : ''); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-transparent">
                        <div class="d-grid gap-2">
                            <?php if ($isApplied): ?>
                                <button class="btn btn-success" disabled>
                                    <i class="bi bi-check-circle me-2"></i>Already Applied
                                </button>
                            <?php elseif (!$hasCapacity): ?>
                                <button class="btn btn-secondary" disabled>
                                    <i class="bi bi-x-circle me-2"></i>Capacity Full
                                </button>
                            <?php elseif ($isDeadlinePassed): ?>
                                <button class="btn btn-secondary" disabled>
                                    <i class="bi bi-clock me-2"></i>Deadline Passed
                                </button>
                            <?php else: ?>
                                <a href="/student/apply.php?program_id=<?php echo $program['id']; ?>" 
                                   class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-2"></i>Apply Now
                                </a>
                            <?php endif; ?>
                            
                            <button class="btn btn-outline-primary" 
                                    onclick="viewProgramDetails(<?php echo $program['id']; ?>)">
                                <i class="bi bi-info-circle me-2"></i>View Details
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="text-center py-5">
        <i class="bi bi-mortarboard display-1 text-muted"></i>
        <h4 class="text-muted mt-3">No Programs Available</h4>
        <p class="text-muted">No programs match your current filters or there are no active programs at the moment.</p>
    </div>
<?php endif; ?>

<!-- Program Details Modal -->
<div class="modal fade" id="programDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Program Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="programDetailsContent">
                <!-- Program details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="applyFromModal" style="display: none;">
                    <i class="bi bi-plus-circle me-2"></i>Apply Now
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function viewProgramDetails(programId) {
    // You could implement AJAX loading of program details here
    // For now, we'll show a placeholder
    document.getElementById('programDetailsContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading program details...</p>
        </div>
    `;
    
    new bootstrap.Modal(document.getElementById('programDetailsModal')).show();
    
    // Simulate loading (replace with actual AJAX call)
    setTimeout(() => {
        document.getElementById('programDetailsContent').innerHTML = `
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                Program details would be loaded here via AJAX. This would include:
                <ul class="mb-0 mt-2">
                    <li>Complete program description</li>
                    <li>Detailed requirements</li>
                    <li>Curriculum information</li>
                    <li>Faculty information</li>
                    <li>Career prospects</li>
                </ul>
            </div>
        `;
    }, 1000);
}

// Auto-select program if coming from apply page
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const programId = urlParams.get('program_id');
    
    if (programId) {
        // Scroll to the program card and highlight it
        const programCard = document.querySelector(`[data-program-id="${programId}"]`);
        if (programCard) {
            programCard.scrollIntoView({ behavior: 'smooth' });
            programCard.classList.add('border-primary', 'border-3');
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
