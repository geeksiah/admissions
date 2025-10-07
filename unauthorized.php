<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Access Denied';
include __DIR__ . '/includes/header.php';
?>
<div class="card" style="max-width:700px;margin:40px auto;">
  <h2><i class="bi bi-shield-lock"></i> 403 - Access Denied</h2>
  <p class="muted">You don’t have permission to access this page.</p>
  <a class="btn secondary" href="/login"><i class="bi bi-box-arrow-in-right"></i> Login</a>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>


