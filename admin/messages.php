<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
$messageModel = new Message($database);
$userModel = new User($database);

// Check admin access
requireRole(['admin', 'admissions_officer', 'reviewer']);

$pageTitle = 'Internal Messages';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'Messages', 'url' => '/admin/messages.php']
];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'send_message':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $data = [
                        'sender_id' => $_SESSION['user_id'],
                        'recipient_id' => $_POST['recipient_id'],
                        'subject' => sanitizeInput($_POST['subject']),
                        'message_body' => sanitizeInput($_POST['message_body']),
                        'message_type' => $_POST['message_type'],
                        'priority' => $_POST['priority'],
                        'related_application_id' => $_POST['related_application_id'] ?? null,
                        'related_student_id' => $_POST['related_student_id'] ?? null
                    ];
                    
                    if ($messageModel->send($data)) {
                        $message = 'Message sent successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to send message. Please try again.';
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'mark_read':
                $messageId = $_POST['message_id'];
                if ($messageModel->markAsRead($messageId, $_SESSION['user_id'])) {
                    $message = 'Message marked as read.';
                    $messageType = 'success';
                }
                break;
                
            case 'mark_unread':
                $messageId = $_POST['message_id'];
                if ($messageModel->markAsUnread($messageId, $_SESSION['user_id'])) {
                    $message = 'Message marked as unread.';
                    $messageType = 'success';
                }
                break;
                
            case 'delete_message':
                $messageId = $_POST['message_id'];
                if ($messageModel->delete($messageId, $_SESSION['user_id'])) {
                    $message = 'Message deleted successfully.';
                    $messageType = 'success';
                }
                break;
        }
    }
}

// Get current tab
$tab = $_GET['tab'] ?? 'inbox';

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = RECORDS_PER_PAGE;

// Get filter parameters
$filters = [];
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (!empty($_GET['message_type'])) {
    $filters['message_type'] = $_GET['message_type'];
}
if (!empty($_GET['priority'])) {
    $filters['priority'] = $_GET['priority'];
}
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

// Get messages based on tab
if ($tab === 'inbox') {
    $messagesData = $messageModel->getInbox($_SESSION['user_id'], $page, $limit, $filters);
} else {
    $messagesData = $messageModel->getSent($_SESSION['user_id'], $page, $limit, $filters);
}

$messages = $messagesData['messages'] ?? [];
$totalPages = $messagesData['pages'] ?? 1;

// Get users for recipient selection
$users = $userModel->getAll(1, 1000)['users'] ?? [];

// Get message types and priorities
$messageTypes = $messageModel->getMessageTypes();
$priorityLevels = $messageModel->getPriorityLevels();

// Get unread count
$unreadCount = $messageModel->getUnreadCount($_SESSION['user_id']);

// Get statistics
$statistics = $messageModel->getStatistics($_SESSION['user_id']);

include '../includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $statistics['total_messages'] ?? 0; ?></h4>
                        <p class="mb-0">Total Messages</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-envelope display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $unreadCount; ?></h4>
                        <p class="mb-0">Unread Messages</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-envelope-exclamation display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $statistics['read_messages'] ?? 0; ?></h4>
                        <p class="mb-0">Read Messages</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-envelope-check display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $statistics['high_priority_messages'] ?? 0; ?></h4>
                        <p class="mb-0">High Priority</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-exclamation-triangle display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeMessageModal">
            <i class="bi bi-plus-circle me-2"></i>Compose Message
        </button>
    </div>
    <div class="col-md-6">
        <div class="d-flex gap-2 justify-content-end">
            <button class="btn btn-outline-secondary" onclick="exportTableToCSV('messagesTable', 'messages.csv')">
                <i class="bi bi-download me-2"></i>Export CSV
            </button>
        </div>
    </div>
</div>

<!-- Message Tabs -->
<ul class="nav nav-tabs mb-4" id="messageTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $tab === 'inbox' ? 'active' : ''; ?>" 
                id="inbox-tab" data-bs-toggle="tab" data-bs-target="#inbox" 
                type="button" role="tab" onclick="switchTab('inbox')">
            <i class="bi bi-inbox me-2"></i>Inbox
            <?php if ($unreadCount > 0): ?>
                <span class="badge bg-danger ms-2"><?php echo $unreadCount; ?></span>
            <?php endif; ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $tab === 'sent' ? 'active' : ''; ?>" 
                id="sent-tab" data-bs-toggle="tab" data-bs-target="#sent" 
                type="button" role="tab" onclick="switchTab('sent')">
            <i class="bi bi-send me-2"></i>Sent
        </button>
    </li>
</ul>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="tab" value="<?php echo $tab; ?>">
            <div class="col-md-3">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                       placeholder="Subject or message content">
            </div>
            <?php if ($tab === 'inbox'): ?>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="unread" <?php echo ($_GET['status'] ?? '') === 'unread' ? 'selected' : ''; ?>>Unread</option>
                    <option value="read" <?php echo ($_GET['status'] ?? '') === 'read' ? 'selected' : ''; ?>>Read</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <label for="message_type" class="form-label">Type</label>
                <select class="form-select" id="message_type" name="message_type">
                    <option value="">All Types</option>
                    <?php foreach ($messageTypes as $value => $label): ?>
                        <option value="<?php echo $value; ?>" 
                                <?php echo ($_GET['message_type'] ?? '') === $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="priority" class="form-label">Priority</label>
                <select class="form-select" id="priority" name="priority">
                    <option value="">All Priorities</option>
                    <?php foreach ($priorityLevels as $value => $label): ?>
                        <option value="<?php echo $value; ?>" 
                                <?php echo ($_GET['priority'] ?? '') === $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Messages Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-envelope me-2"></i><?php echo ucfirst($tab); ?> Messages
            <span class="badge bg-primary ms-2"><?php echo $messagesData['total'] ?? 0; ?></span>
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($messages)): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="messagesTable">
                    <thead>
                        <tr>
                            <?php if ($tab === 'inbox'): ?>
                                <th>From</th>
                            <?php else: ?>
                                <th>To</th>
                            <?php endif; ?>
                            <th>Subject</th>
                            <th>Type</th>
                            <th>Priority</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $msg): ?>
                            <tr class="<?php echo $msg['status'] === 'unread' ? 'table-warning' : ''; ?>">
                                <td>
                                    <?php if ($tab === 'inbox'): ?>
                                        <div>
                                            <strong><?php echo $msg['sender_first_name'] . ' ' . $msg['sender_last_name']; ?></strong>
                                            <?php if ($msg['application_number']): ?>
                                                <br><small class="text-muted">App: <?php echo $msg['application_number']; ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div>
                                            <strong><?php echo $msg['recipient_first_name'] . ' ' . $msg['recipient_last_name']; ?></strong>
                                            <?php if ($msg['application_number']): ?>
                                                <br><small class="text-muted">App: <?php echo $msg['application_number']; ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo $msg['subject']; ?></strong>
                                        <br><small class="text-muted"><?php echo substr(strip_tags($msg['message_body']), 0, 100) . '...'; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $messageTypes[$msg['message_type']] ?? ucfirst($msg['message_type']); ?></span>
                                </td>
                                <td>
                                    <?php
                                    $priorityClass = 'secondary';
                                    switch ($msg['priority']) {
                                        case 'high':
                                            $priorityClass = 'warning';
                                            break;
                                        case 'urgent':
                                            $priorityClass = 'danger';
                                            break;
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $priorityClass; ?>">
                                        <?php echo $priorityLevels[$msg['priority']] ?? ucfirst($msg['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo formatDateTime($msg['created_at']); ?>
                                </td>
                                <td>
                                    <?php if ($tab === 'inbox'): ?>
                                        <span class="badge bg-<?php echo $msg['status'] === 'read' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($msg['status']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-info">Sent</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="viewMessage(<?php echo $msg['id']; ?>)"
                                                title="View Message">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if ($tab === 'inbox'): ?>
                                            <?php if ($msg['status'] === 'read'): ?>
                                                <button class="btn btn-sm btn-outline-warning" 
                                                        onclick="markUnread(<?php echo $msg['id']; ?>)"
                                                        title="Mark as Unread">
                                                    <i class="bi bi-envelope"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-success" 
                                                        onclick="markRead(<?php echo $msg['id']; ?>)"
                                                        title="Mark as Read">
                                                    <i class="bi bi-envelope-check"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteMessage(<?php echo $msg['id']; ?>)"
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
                <nav aria-label="Messages pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?tab=<?php echo $tab; ?>&page=<?php echo $page - 1; ?>&<?php echo http_build_query($filters); ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?tab=<?php echo $tab; ?>&page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?tab=<?php echo $tab; ?>&page=<?php echo $page + 1; ?>&<?php echo http_build_query($filters); ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-envelope display-1 text-muted"></i>
                <h4 class="text-muted mt-3">No Messages Found</h4>
                <p class="text-muted">No messages match your current filters.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeMessageModal">
                    <i class="bi bi-plus-circle me-2"></i>Compose First Message
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Compose Message Modal -->
<div class="modal fade" id="composeMessageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Compose Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="composeMessageForm">
                <input type="hidden" name="action" value="send_message">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="recipient_id" class="form-label">Recipient *</label>
                                <select class="form-select" id="recipient_id" name="recipient_id" required>
                                    <option value="">Select Recipient</option>
                                    <?php foreach ($users as $user): ?>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <option value="<?php echo $user['id']; ?>">
                                                <?php echo $user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['role'] . ')'; ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority">
                                    <?php foreach ($priorityLevels as $value => $label): ?>
                                        <option value="<?php echo $value; ?>" <?php echo $value === 'normal' ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message_type" class="form-label">Message Type</label>
                        <select class="form-select" id="message_type" name="message_type">
                            <?php foreach ($messageTypes as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $value === 'general' ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="subject" class="form-label">Subject *</label>
                        <input type="text" class="form-control" id="subject" name="subject" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message_body" class="form-label">Message *</label>
                        <textarea class="form-control" id="message_body" name="message_body" rows="6" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Message</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    window.location.href = '?tab=' + tab;
}

function viewMessage(messageId) {
    window.location.href = '/admin/message-details.php?id=' + messageId;
}

function markRead(messageId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="mark_read">
        <input type="hidden" name="message_id" value="${messageId}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function markUnread(messageId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="mark_unread">
        <input type="hidden" name="message_id" value="${messageId}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function deleteMessage(messageId) {
    if (confirm('Are you sure you want to delete this message?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_message">
            <input type="hidden" name="message_id" value="${messageId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>
