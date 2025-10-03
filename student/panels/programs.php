<?php
// Get available programs
$programs = $programModel->getAll(['status' => 'active']);
?>

<div class="row mb-3">
    <div class="col-12">
        <h5 class="mb-0">
            <i class="bi bi-book me-2"></i>
            Available Programs
        </h5>
        <p class="text-muted mt-1 mb-0">Browse and apply to programs offered by the institution.</p>
    </div>
</div>

<div class="row">
    <?php if (empty($programs)): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-book text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">No Programs Available</h5>
                    <p class="text-muted">No programs are currently accepting applications.</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($programs as $program): ?>
            <div class="col-lg-6 col-xl-4 mb-3">
                <div class="card h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0" style="font-size: 0.875rem;">
                            <?php echo htmlspecialchars($program['program_name']); ?>
                        </h6>
                        <small class="text-muted" style="font-size: 0.75rem;">
                            <?php echo htmlspecialchars($program['program_code']); ?>
                        </small>
                    </div>
                    <div class="card-body" style="padding: 1rem;">
                        <div class="mb-3">
                            <small class="text-muted d-block" style="font-size: 0.75rem;">
                                <i class="bi bi-building me-1"></i>
                                <?php echo htmlspecialchars($program['department'] ?? 'General'); ?>
                            </small>
                            <small class="text-muted d-block" style="font-size: 0.75rem;">
                                <i class="bi bi-award me-1"></i>
                                <?php echo htmlspecialchars($program['level_name'] ?? 'Undergraduate'); ?>
                            </small>
                            <small class="text-muted d-block" style="font-size: 0.75rem;">
                                <i class="bi bi-clock me-1"></i>
                                Duration: <?php echo htmlspecialchars($program['duration'] ?? 'N/A'); ?>
                            </small>
                            <small class="text-muted d-block" style="font-size: 0.75rem;">
                                <i class="bi bi-credit-card me-1"></i>
                                Fee: $<?php echo number_format($program['application_fee'] ?? 0); ?>
                            </small>
                        </div>
                        
                        <?php if (!empty($program['description'])): ?>
                            <p class="text-muted" style="font-size: 0.75rem; line-height: 1.4;">
                                <?php echo htmlspecialchars(substr($program['description'], 0, 120)) . (strlen($program['description']) > 120 ? '...' : ''); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($program['requirements'])): ?>
                            <div class="mb-3">
                                <small class="text-muted" style="font-size: 0.75rem;">
                                    <strong>Requirements:</strong><br>
                                    <?php echo htmlspecialchars(substr($program['requirements'], 0, 100)) . (strlen($program['requirements']) > 100 ? '...' : ''); ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-transparent border-0" style="padding: 0 1rem 1rem 1rem;">
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary btn-sm" onclick="viewProgram(<?php echo $program['id']; ?>)">
                                <i class="bi bi-eye me-2"></i>View Details
                            </button>
                            <button class="btn btn-outline-primary btn-sm" onclick="applyToProgram(<?php echo $program['id']; ?>)">
                                <i class="bi bi-file-earmark-plus me-2"></i>Apply Now
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Program Detail Modal -->
<div class="modal fade" id="programDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="programDetailTitle">
                    <i class="bi bi-book me-2"></i>Program Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="programDetailBody">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="applyFromDetailBtn">
                    <i class="bi bi-file-earmark-plus me-2"></i>Apply to this Program
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentProgramId = null;

function viewProgram(programId) {
    currentProgramId = programId;
    
    // TODO: Load program details via AJAX
    // For now, show a placeholder
    document.getElementById('programDetailTitle').innerHTML = '<i class="bi bi-book me-2"></i>Program Details';
    document.getElementById('programDetailBody').innerHTML = '<p>Loading program details...</p>';
    document.getElementById('applyFromDetailBtn').style.display = 'block';
    
    const modal = new bootstrap.Modal(document.getElementById('programDetailModal'));
    modal.show();
}

function applyToProgram(programId) {
    // Redirect to applications panel with program pre-selected
    const url = new URL(window.location);
    url.searchParams.set('panel', 'applications');
    url.searchParams.set('program_id', programId);
    window.history.pushState({}, '', url);
    
    // Trigger panel switch
    const event = new Event('click');
    document.querySelector('[data-panel="applications"]').dispatchEvent(event);
}

// Handle apply button in modal
document.getElementById('applyFromDetailBtn').addEventListener('click', function() {
    if (currentProgramId) {
        applyToProgram(currentProgramId);
        bootstrap.Modal.getInstance(document.getElementById('programDetailModal')).hide();
    }
});
</script>
