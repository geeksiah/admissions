<?php
/**
 * Students Panel - Student Management
 */

// Get students with pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    $students = $studentModel->getAll($limit, $offset);
    $totalStudents = $studentModel->getTotalCount();
    $totalPages = ceil($totalStudents / $limit);
} catch (Exception $e) {
    $students = [];
    $totalStudents = 0;
    $totalPages = 0;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">Student Management</h2>
        <p class="text-muted mb-0">Manage enrolled students</p>
    </div>
    <div>
        <button class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>Add Student
        </button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Students (<?php echo number_format($totalStudents); ?>)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($students)): ?>
            <div class="text-center py-5">
                <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-2">No students found</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Program</th>
                            <th>Status</th>
                            <th>Enrolled Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <?php echo htmlspecialchars($student['program_name'] ?? 'Unknown'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo ($student['status'] ?? 'active') === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($student['status'] ?? 'active'); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($student['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" title="View">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-success" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" title="Delete">
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
