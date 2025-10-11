<?php
/**
 * Enhanced Student Dashboard
 * Implements modern overview with progress indicators as recommended in Improvements.txt
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/SecurityMiddleware.php';

// Security checks
$security = SecurityMiddleware::getInstance();
$security->requireRole('student');

$database = new Database();
$pdo = $database->getConnection();

$studentId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Get student info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header('Location: /student/login');
    exit;
}

// Get dashboard statistics
$stats = [];

// Total applications
$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ?");
$stmt->execute([$studentId]);
$stats['total_applications'] = $stmt->fetchColumn();

// Pending applications
$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ? AND status = 'pending'");
$stmt->execute([$studentId]);
$stats['pending_applications'] = $stmt->fetchColumn();

// Approved applications
$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ? AND status = 'approved'");
$stmt->execute([$studentId]);
$stats['approved_applications'] = $stmt->fetchColumn();

// Pending payments
$stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE student_id = ? AND status = 'pending'");
$stmt->execute([$studentId]);
$stats['pending_payments'] = $stmt->fetchColumn();

// Recent applications
$stmt = $pdo->prepare("
    SELECT a.*, p.name as program_name, p.status as program_status
    FROM applications a 
    LEFT JOIN programs p ON a.program_id = p.id 
    WHERE a.student_id = ? 
    ORDER BY a.created_at DESC 
    LIMIT 5
");
$stmt->execute([$studentId]);
$recentApplications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent notifications
$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE student_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$studentId]);
$recentNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Available programs
$stmt = $pdo->prepare("SELECT * FROM programs WHERE status = 'active' ORDER BY name");
$stmt->execute();
$availablePrograms = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Student Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="student-dashboard">
    <!-- Welcome Section -->
    <div class="welcome-section">
        <h1>Welcome back, <?php echo htmlspecialchars($student['first_name'] ?? 'Student'); ?>!</h1>
        <p class="muted">Track your applications and stay updated on your admission progress.</p>
    </div>

    <!-- Quick Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon applications">
                <i class="bi bi-file-text"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon pending">
                <i class="bi bi-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['pending_applications']; ?></div>
                <div class="stat-label">Under Review</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon approved">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['approved_applications']; ?></div>
                <div class="stat-label">Approved</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon payments">
                <i class="bi bi-credit-card"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['pending_payments']; ?></div>
                <div class="stat-label">Pending Payments</div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="dashboard-grid">
        <!-- Recent Applications -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3>Recent Applications</h3>
                <a href="?panel=applications" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="card-content">
                <?php if (empty($recentApplications)): ?>
                    <div class="empty-state">
                        <i class="bi bi-file-text"></i>
                        <h4>No Applications Yet</h4>
                        <p>Start your journey by applying to a program.</p>
                        <a href="#new-application" class="btn btn-primary">Apply Now</a>
                    </div>
                <?php else: ?>
                    <div class="application-list">
                        <?php foreach ($recentApplications as $app): ?>
                            <div class="application-item">
                                <div class="app-info">
                                    <h4><?php echo htmlspecialchars($app['program_name'] ?? 'Unknown Program'); ?></h4>
                                    <p class="muted">Applied <?php echo date('M j, Y', strtotime($app['created_at'])); ?></p>
                                </div>
                                <div class="app-status">
                                    <span class="status-badge status-<?php echo $app['status']; ?>">
                                        <?php echo ucfirst($app['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3>Quick Actions</h3>
            </div>
            <div class="card-content">
                <div class="quick-actions">
                    <a href="#new-application" class="action-btn">
                        <i class="bi bi-plus-circle"></i>
                        <span>New Application</span>
                    </a>
                    <a href="?panel=documents" class="action-btn">
                        <i class="bi bi-upload"></i>
                        <span>Upload Documents</span>
                    </a>
                    <a href="?panel=payments" class="action-btn">
                        <i class="bi bi-credit-card"></i>
                        <span>Make Payment</span>
                    </a>
                    <a href="?panel=profile" class="action-btn">
                        <i class="bi bi-person"></i>
                        <span>Update Profile</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Notifications -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3>Recent Notifications</h3>
                <a href="?panel=notifications" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="card-content">
                <?php if (empty($recentNotifications)): ?>
                    <div class="empty-state">
                        <i class="bi bi-bell"></i>
                        <h4>No Notifications</h4>
                        <p>You'll see important updates here.</p>
                    </div>
                <?php else: ?>
                    <div class="notification-list">
                        <?php foreach ($recentNotifications as $notif): ?>
                            <div class="notification-item">
                                <div class="notif-icon">
                                    <i class="bi bi-<?php echo $notif['type'] === 'email' ? 'envelope' : 'bell'; ?>"></i>
                                </div>
                                <div class="notif-content">
                                    <h5><?php echo htmlspecialchars($notif['title']); ?></h5>
                                    <p><?php echo htmlspecialchars(substr($notif['message'], 0, 100)) . '...'; ?></p>
                                    <small class="muted"><?php echo date('M j, Y', strtotime($notif['created_at'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Available Programs -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3>Available Programs</h3>
            </div>
            <div class="card-content">
                <?php if (empty($availablePrograms)): ?>
                    <div class="empty-state">
                        <i class="bi bi-book"></i>
                        <h4>No Programs Available</h4>
                        <p>Check back later for available programs.</p>
                    </div>
                <?php else: ?>
                    <div class="program-list">
                        <?php foreach ($availablePrograms as $program): ?>
                            <div class="program-item">
                                <div class="program-info">
                                    <h4><?php echo htmlspecialchars($program['name']); ?></h4>
                                    <p class="muted"><?php echo htmlspecialchars($program['description'] ?? ''); ?></p>
                                </div>
                                <div class="program-actions">
                                    <a href="/student/application-form.php?program=<?php echo $program['id']; ?>" class="btn btn-sm btn-primary">
                                        Apply Now
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.student-dashboard {
    padding: var(--space-6);
    max-width: 1200px;
    margin: 0 auto;
}

.welcome-section {
    margin-bottom: var(--space-8);
    text-align: center;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--space-4);
    margin-bottom: var(--space-8);
}

.stat-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: var(--space-4);
    display: flex;
    align-items: center;
    gap: var(--space-3);
    transition: var(--transition);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-xl);
    color: white;
}

.stat-icon.applications { background: var(--primary); }
.stat-icon.pending { background: var(--warning); }
.stat-icon.approved { background: var(--success); }
.stat-icon.payments { background: var(--info); }

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: var(--text-2xl);
    font-weight: 700;
    color: var(--text);
    line-height: 1;
}

.stat-label {
    font-size: var(--text-sm);
    color: var(--muted);
    margin-top: var(--space-1);
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--space-6);
}

.dashboard-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
}

.card-header {
    padding: var(--space-4);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.card-header h3 {
    margin: 0;
    font-size: var(--text-lg);
    color: var(--text);
}

.card-content {
    padding: var(--space-4);
}

.application-item, .notification-item, .program-item {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-3) 0;
    border-bottom: 1px solid var(--border);
}

.application-item:last-child, .notification-item:last-child, .program-item:last-child {
    border-bottom: none;
}

.status-badge {
    padding: var(--space-1) var(--space-2);
    border-radius: var(--radius-sm);
    font-size: var(--text-xs);
    font-weight: 600;
    text-transform: uppercase;
}

.status-pending { background: var(--warning-light); color: var(--warning); }
.status-approved { background: var(--success-light); color: var(--success); }
.status-rejected { background: var(--danger-light); color: var(--danger); }
.status-draft { background: var(--muted); color: var(--text); }

.quick-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--space-3);
}

.action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-4);
    background: var(--surface-hover);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    text-decoration: none;
    color: var(--text);
    transition: var(--transition);
}

.action-btn:hover {
    background: var(--primary-light);
    border-color: var(--primary);
    color: var(--primary);
}

.action-btn i {
    font-size: var(--text-xl);
}

.notif-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--primary-light);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.program-item {
    justify-content: space-between;
}

.program-info h4 {
    margin: 0 0 var(--space-1) 0;
    font-size: var(--text-base);
}

.program-info p {
    margin: 0;
    font-size: var(--text-sm);
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
