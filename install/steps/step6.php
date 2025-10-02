<?php
/**
 * Step 6: Installation Progress
 */
?>

<div class="text-center">
    <h4>Installing System Components</h4>
    <p class="text-muted">Please wait while we set up your system...</p>
    
    <div class="spinner-border text-primary mb-3" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    
    <div class="alert alert-info">
        <h6>Installation in Progress</h6>
        <p class="mb-0">This may take a few moments...</p>
    </div>
    
    <form method="POST" id="installForm">
        <button type="submit" class="btn btn-success btn-lg">
            <i class="bi bi-check-circle me-2"></i>
            Installation complete!
        </button>
    </form>
    
    <script>
        // Auto-submit the form after a short delay
        setTimeout(function() {
            document.getElementById('installForm').submit();
        }, 2000);
    </script>
</div>