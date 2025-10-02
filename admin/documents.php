<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize database and models
$database = new Database();
$documentModel = new Document($database);
$applicationModel = new Application($database);

// Check admin access
requireRole(['admin', 'admissions_officer', 'reviewer']);

$pageTitle = 'Document Management';
$breadcrumbs = [
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'Documents', 'url' => '/admin/documents.php']
];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'verify_document':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $documentId = $_POST['document_id'];
                    $verified = $_POST['verified'] === '1' ? 'verified' : 'rejected';
                    $verificationNotes = sanitizeInput($_POST['verification_notes']);
                    
                    if ($documentModel->verifyDocument($documentId, $verified, $_SESSION['user_id'], $verificationNotes)) {
                        $message = 'Document verification updated successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to update document verification. Please try again.';
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'delete_document':
                if (validateCSRFToken($_POST['csrf_token'])) {
                    $documentId = $_POST['document_id'];
                    if ($documentModel->delete($documentId)) {
                        $message = 'Document deleted successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to delete document. Please try again.';
                        $messageType = 'danger';
                    }
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
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (!empty($_GET['document_type'])) {
    $filters['document_type'] = $_GET['document_type'];
}
if (!empty($_GET['application_id'])) {
    $filters['application_id'] = $_GET['application_id'];
}
if (!empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}

// Get documents with pagination
$documentsData = $documentModel->getAll($page, $limit, $filters);
$documents = $documentsData['documents'] ?? [];
$totalPages = $documentsData['pages'] ?? 1;

// Get document types and statuses
$documentTypes = $documentModel->getDocumentTypes();
$documentStatuses = $documentModel->getDocumentStatuses();

// Get statistics
$statistics = $documentModel->getStatistics();

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
                        <h4 class="mb-0"><?php echo number_format($statistics['total_documents'] ?? 0); ?></h4>
                        <p class="mb-0">Total Documents</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-file-earmark display-4"></i>
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
                        <h4 class="mb-0"><?php echo number_format($statistics['pending_documents'] ?? 0); ?></h4>
                        <p class="mb-0">Pending Review</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-clock display-4"></i>
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
                        <h4 class="mb-0"><?php echo number_format($statistics['verified_documents'] ?? 0); ?></h4>
                        <p class="mb-0">Verified</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-check-circle display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $this->formatBytes($statistics['total_size'] ?? 0); ?></h4>
                        <p class="mb-0">Total Size</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-hdd display-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <h5 class="mb-0">
            <i class="bi bi-file-earmark me-2"></i>Document Management
            <span class="badge bg-primary ms-2"><?php echo $documentsData['total'] ?? 0; ?></span>
        </h5>
    </div>
    <div class="col-md-6">
        <div class="d-flex gap-2 justify-content-end">
            <button class="btn btn-outline-secondary" onclick="exportTableToCSV('documentsTable', 'documents.csv')">
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
            <div class="col-md-3">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                       placeholder="Document name, application number, or student name">
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <?php foreach ($documentStatuses as $value => $label): ?>
                        <option value="<?php echo $value; ?>" 
                                <?php echo ($_GET['status'] ?? '') === $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="document_type" class="form-label">Type</label>
                <select class="form-select" id="document_type" name="document_type">
                    <option value="">All Types</option>
                    <?php foreach ($documentTypes as $value => $label): ?>
                        <option value="<?php echo $value; ?>" 
                                <?php echo ($_GET['document_type'] ?? '') === $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="application_id" class="form-label">Application ID</label>
                <input type="number" class="form-control" id="application_id" name="application_id" 
                       value="<?php echo $_GET['application_id'] ?? ''; ?>" 
                       placeholder="Application ID">
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
        </form>
        
        <!-- Date Range Filter -->
        <div class="row mt-3">
            <div class="col-md-3">
                <label for="date_from" class="form-label">From Date</label>
                <input type="date" class="form-control" id="date_from" name="date_from" 
                       value="<?php echo $_GET['date_from'] ?? ''; ?>">
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label">To Date</label>
                <input type="date" class="form-control" id="date_to" name="date_to" 
                       value="<?php echo $_GET['date_to'] ?? ''; ?>">
            </div>
        </div>
    </div>
</div>

<!-- Documents Table -->
<div class="card">
    <div class="card-body">
        <?php if (!empty($documents)): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="documentsTable">
                    <thead>
                        <tr>
                            <th>Document</th>
                            <th>Application</th>
                            <th>Student</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Status</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $document): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo $document['document_name']; ?></strong>
                                        <br><small class="text-muted"><?php echo $document['mime_type']; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <a href="/admin/application-details.php?id=<?php echo $document['application_id']; ?>" 
                                       class="text-decoration-none">
                                        <?php echo $document['application_number']; ?>
                                    </a>
                                </td>
                                <td>
                                    <?php echo $document['student_first_name'] . ' ' . $document['student_last_name']; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $documentTypes[$document['document_type']] ?? ucfirst($document['document_type']); ?></span>
                                </td>
                                <td>
                                    <?php echo $this->formatBytes($document['file_size']); ?>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = 'secondary';
                                    switch ($document['status']) {
                                        case 'pending':
                                            $statusClass = 'warning';
                                            break;
                                        case 'verified':
                                            $statusClass = 'success';
                                            break;
                                        case 'rejected':
                                            $statusClass = 'danger';
                                            break;
                                        case 'expired':
                                            $statusClass = 'dark';
                                            break;
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                        <?php echo $documentStatuses[$document['status']] ?? ucfirst($document['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div>
                                        <?php echo formatDate($document['created_at']); ?>
                                        <br><small class="text-muted">by <?php echo $document['uploaded_by_first_name'] . ' ' . $document['uploaded_by_last_name']; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="viewDocument(<?php echo $document['id']; ?>)"
                                                title="View Document">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-success" 
                                                onclick="downloadDocument(<?php echo $document['id']; ?>)"
                                                title="Download">
                                            <i class="bi bi-download"></i>
                                        </button>
                                        <?php if ($document['status'] === 'pending'): ?>
                                            <button class="btn btn-sm btn-outline-warning" 
                                                    onclick="verifyDocument(<?php echo $document['id']; ?>)"
                                                    title="Verify Document">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteDocument(<?php echo $document['id']; ?>)"
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
                <nav aria-label="Documents pagination">
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
                <i class="bi bi-file-earmark display-1 text-muted"></i>
                <h4 class="text-muted mt-3">No Documents Found</h4>
                <p class="text-muted">No documents match your current filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Verify Document Modal -->
<div class="modal fade" id="verifyDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Verify Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="verifyDocumentForm">
                <input type="hidden" name="action" value="verify_document">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="document_id" id="verifyDocumentId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Verification Result *</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="verified" id="verify_approved" value="1" checked>
                            <label class="form-check-label text-success" for="verify_approved">
                                <i class="bi bi-check-circle me-2"></i>Approve Document
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="verified" id="verify_rejected" value="0">
                            <label class="form-check-label text-danger" for="verify_rejected">
                                <i class="bi bi-x-circle me-2"></i>Reject Document
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="verification_notes" class="form-label">Verification Notes</label>
                        <textarea class="form-control" id="verification_notes" name="verification_notes" rows="3" 
                                  placeholder="Add any notes about the verification process..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Verification</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewDocument(documentId) {
    window.location.href = '/admin/document-viewer.php?id=' + documentId;
}

function downloadDocument(documentId) {
    window.location.href = '/admin/download-document.php?id=' + documentId;
}

function verifyDocument(documentId) {
    document.getElementById('verifyDocumentId').value = documentId;
    new bootstrap.Modal(document.getElementById('verifyDocumentModal')).show();
}

function deleteDocument(documentId) {
    if (confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_document">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="document_id" value="${documentId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Format bytes helper function
function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}
</script>

<?php
// Helper function for formatting bytes
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>

<?php include '../includes/footer.php'; ?>
