<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();
requireRole(['admin','super_admin','admissions_officer','reviewer']);

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="grid cols-3">
  <div class="card">
    <div class="muted">Total Applications</div>
    <div style="font-size:28px;font-weight:700">0</div>
  </div>
  <div class="card">
    <div class="muted">Pending Review</div>
    <div style="font-size:28px;font-weight:700">0</div>
  </div>
  <div class="card">
    <div class="muted">Active Programs</div>
    <div style="font-size:28px;font-weight:700">0</div>
  </div>
</div>

<div class="card">
  <h3>Quick Actions</h3>
  <a class="btn" href="#"><i class="bi bi-list-check"></i> Review Applications</a>
  <a class="btn secondary" href="#"><i class="bi bi-people"></i> Manage Students</a>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>


