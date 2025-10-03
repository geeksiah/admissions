<?php
// Handle form submissions
if ($_POST) {
    if (isset($_POST['action']) && $_POST['action'] === 'new_application') {
        $programId = (int)$_POST['program_id'];
        $motivation = trim($_POST['motivation'] ?? '');
        
        if ($programId && $motivation) {
            try {
                $applicationData = [
                    'student_id' => $student['id'],
                    'program_id' => $programId,
                    'motivation' => $motivation,
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                if ($applicationModel->create($applicationData)) {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i>Application submitted successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                          </div>';
                    
                    // Refresh applications list
                    $applications = $applicationModel->getByStudent($student['id']);
                } else {
                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-circle me-2"></i>Failed to submit application. Please try again.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                          </div>';
                }
            } catch (Exception $e) {
                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i>Error submitting application: ' . htmlspecialchars($e->getMessage()) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                      </div>';
            }
        }
    }
}
?>

<!-- New Application Button -->
<div class="row mb-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-file-earmark-text me-2"></i>
                My Applications
            </h5>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newApplicationModal">
                <i class="bi bi-plus-circle me-2"></i>New Application
            </button>
        </div>
    </div>
</div>

<!-- Applications List -->
<div class="row">
    <div class="col-12">
        <?php if (empty($applications)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">No Applications Yet</h5>
                    <p class="text-muted">Start your academic journey by applying to a program.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newApplicationModal">
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

<!-- New Application Modal -->
<div class="modal fade" id="newApplicationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-file-earmark-plus me-2"></i>New Application
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="new_application">
                    
                    <div class="mb-3">
                        <label for="program_id" class="form-label">Select Program *</label>
                        <select class="form-select" id="program_id" name="program_id" required>
                            <option value="">Choose a program...</option>
                            <?php foreach ($availablePrograms as $program): ?>
                                <option value="<?php echo $program['id']; ?>">
                                    <?php echo htmlspecialchars($program['program_name'] . ' (' . $program['program_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="motivation" class="form-label">Motivation Letter *</label>
                        <textarea class="form-control" id="motivation" name="motivation" rows="4" 
                                  placeholder="Explain why you want to join this program..." required></textarea>
                        <small class="form-text text-muted">Minimum 100 characters</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-2"></i>Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewApplication(appId) {
    // TODO: Implement application detail view
    alert('Application details for ID: ' + appId);
}
</script>
