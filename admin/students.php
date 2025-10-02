<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
$pdo = $database->getConnection();
$studentModel = new Student($pdo);
$userModel = new User($pdo);

// Check admin access
requireRole(['admin', 'admissions_officer']);

$pageTitle = 'Student Management';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'Students', 'url' => '/admin/students.php']
];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_student':
                // Create new student
                $validator = new Validator($_POST);
                $validator->required(['first_name', 'last_name', 'email', 'phone', 'date_of_birth', 'gender', 'nationality', 'address', 'city', 'state', 'postal_code', 'country'])
                         ->email('email')
                         ->phone('phone')
                         ->date('date_of_birth')
                         ->minAge('date_of_birth', 16);
                
                if (!$validator->hasErrors()) {
                    $data = $validator->getValidatedData();
                    
                    // Check if email already exists
                    if (!$studentModel->emailExists($data['email'])) {
                        if ($studentModel->create($data)) {
                            $message = 'Student created successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to create student. Please try again.';
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'Email address already exists.';
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Please correct the errors below.';
                    $messageType = 'danger';
                }
                break;
                
            case 'update_student':
                // Update student
                $studentId = $_POST['student_id'];
                $validator = new Validator($_POST);
                $validator->required(['first_name', 'last_name', 'email', 'phone'])
                         ->email('email')
                         ->phone('phone');
                
                if (!$validator->hasErrors()) {
                    $data = $validator->getValidatedData();
                    
                    // Check if email exists for other students
                    if (!$studentModel->emailExists($data['email'], $studentId)) {
                        if ($studentModel->update($studentId, $data)) {
                            $message = 'Student updated successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to update student. Please try again.';
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'Email address already exists for another student.';
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Please correct the errors below.';
                    $messageType = 'danger';
                }
                break;
                
            case 'delete_student':
                // Delete student
                $studentId = $_POST['student_id'];
                if ($studentModel->delete($studentId)) {
                    $message = 'Student deleted successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete student. Please try again.';
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = RECORDS_PER_PAGE;

// Get filter parameters
$filters = [];
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}
if (!empty($_GET['nationality'])) {
    $filters['nationality'] = $_GET['nationality'];
}
if (!empty($_GET['gender'])) {
    $filters['gender'] = $_GET['gender'];
}

// Get students with pagination
$studentsData = $studentModel->getAll($page, $limit, $filters);
$students = $studentsData['students'] ?? [];
$totalPages = $studentsData['pages'] ?? 1;

// Get unique nationalities for filter
$nationalities = [];
foreach ($students as $student) {
    if (!in_array($student['nationality'], $nationalities)) {
        $nationalities[] = $student['nationality'];
    }
}
sort($nationalities);

include '../includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createStudentModal">
            <i class="bi bi-plus-circle me-2"></i>Add New Student
        </button>
    </div>
    <div class="col-md-6">
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" onclick="exportTableToCSV('studentsTable', 'students.csv')">
                <i class="bi bi-download me-2"></i>Export CSV
            </button>
            <button class="btn btn-outline-secondary" onclick="printPage()">
                <i class="bi bi-printer me-2"></i>Print
            </button>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                       placeholder="Name, email, or student ID">
            </div>
            <div class="col-md-3">
                <label for="nationality" class="form-label">Nationality</label>
                <select class="form-select" id="nationality" name="nationality">
                    <option value="">All Nationalities</option>
                    <?php foreach ($nationalities as $nationality): ?>
                        <option value="<?php echo $nationality; ?>" 
                                <?php echo ($_GET['nationality'] ?? '') === $nationality ? 'selected' : ''; ?>>
                            <?php echo $nationality; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="gender" class="form-label">Gender</label>
                <select class="form-select" id="gender" name="gender">
                    <option value="">All Genders</option>
                    <option value="male" <?php echo ($_GET['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                    <option value="female" <?php echo ($_GET['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                    <option value="other" <?php echo ($_GET['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
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

<!-- Students Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-people me-2"></i>Students
            <span class="badge bg-primary ms-2"><?php echo $studentsData['total'] ?? 0; ?></span>
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($students)): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="studentsTable">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Nationality</th>
                            <th>Gender</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $student['student_id']; ?></strong>
                                </td>
                                <td>
                                    <?php echo $student['first_name'] . ' ' . $student['last_name']; ?>
                                </td>
                                <td>
                                    <a href="mailto:<?php echo $student['email']; ?>">
                                        <?php echo $student['email']; ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="tel:<?php echo $student['phone']; ?>">
                                        <?php echo $student['phone']; ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $student['nationality']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo ucfirst($student['gender']); ?></span>
                                </td>
                                <td>
                                    <?php echo formatDate($student['created_at']); ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="viewStudent(<?php echo $student['id']; ?>)"
                                                title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning" 
                                                onclick="editStudent(<?php echo $student['id']; ?>)"
                                                title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteStudent(<?php echo $student['id']; ?>)"
                                                title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Students pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($filters); ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
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
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-people display-1 text-muted"></i>
                <h4 class="text-muted mt-3">No Students Found</h4>
                <p class="text-muted">No students match your current filters.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createStudentModal">
                    <i class="bi bi-plus-circle me-2"></i>Add First Student
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Student Modal -->
<div class="modal fade" id="createStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="createStudentForm">
                <input type="hidden" name="action" value="create_student">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_of_birth" class="form-label">Date of Birth *</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="gender" class="form-label">Gender *</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="nationality" class="form-label">Nationality *</label>
                                <input type="text" class="form-control" id="nationality" name="nationality" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="passport_number" class="form-label">Passport Number</label>
                                <input type="text" class="form-control" id="passport_number" name="passport_number">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address *</label>
                        <textarea class="form-control" id="address" name="address" rows="2" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="city" class="form-label">City *</label>
                                <input type="text" class="form-control" id="city" name="city" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="state" class="form-label">State *</label>
                                <input type="text" class="form-control" id="state" name="state" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="postal_code" class="form-label">Postal Code *</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="country" class="form-label">Country *</label>
                        <input type="text" class="form-control" id="country" name="country" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewStudent(studentId) {
    // Implement view student functionality
    window.location.href = '/admin/student-details.php?id=' + studentId;
}

function editStudent(studentId) {
    // Implement edit student functionality
    window.location.href = '/admin/edit-student.php?id=' + studentId;
}

function deleteStudent(studentId) {
    if (confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_student">
            <input type="hidden" name="student_id" value="${studentId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>
