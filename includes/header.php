<?php
require_once __DIR__ . '/../config/config.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME; ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
  <div class="nav">
    <div>
      <a href="/" class="active"><i class="bi bi-mortarboard"></i> <?php echo APP_NAME; ?></a>
    </div>
    <div>
      <?php if (isLoggedIn()): ?>
        <a href="/admin/dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="/logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
      <?php else: ?>
        <a href="/login"><i class="bi bi-box-arrow-in-right"></i> Login</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="container">

