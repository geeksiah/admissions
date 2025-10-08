<?php
require_once __DIR__ . '/../config/config.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' . APP_NAME : APP_NAME; ?></title>
  <script>
    (function(){
      try {
        var theme = localStorage.getItem('ams_theme') || 'dark';
        document.documentElement.classList.toggle('light', theme === 'light');
        document.documentElement.setAttribute('data-theme', theme);
      } catch(e){}
    })();
  </script>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="/assets/css/dashboard.css">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
  <nav class="nav">
    <div>
      <button id="sidebarToggleTop" class="btn secondary mobile-only"><i class="bi bi-list"></i></button>
    </div>
    <div class="nav-actions">
      <button id="themeToggle" class="btn secondary"><i class="bi bi-circle-half"></i><span class="desktop-only"> Theme</span></button>
      <?php if (isLoggedIn()): ?>
        <div class="user-menu">
          <?php
            $avatarPath = '/uploads/avatars/' . ($_SESSION['user_id'] ?? '0') . '.png';
            $avatarFs = $_SERVER['DOCUMENT_ROOT'] . $avatarPath;
            $initials = strtoupper(substr($_SESSION['username'] ?? 'U',0,2));
          ?>
          <button id="avatarBtn" class="avatar-btn" aria-label="User menu">
            <?php if (file_exists($avatarFs)): ?>
              <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Avatar" class="avatar-img">
            <?php else: ?>
              <span class="avatar-initials"><?php echo htmlspecialchars($initials); ?></span>
            <?php endif; ?>
          </button>
          <div id="userDropdown" class="user-dropdown">
            <a href="/admin/dashboard?panel=profile" class="dropdown-item"><i class="bi bi-person"></i> Profile</a>
            <a href="/admin/dashboard?panel=notifications" class="dropdown-item"><i class="bi bi-bell"></i> Notifications</a>
            <a href="/admin/dashboard?panel=settings" class="dropdown-item"><i class="bi bi-gear"></i> Settings</a>
            <div class="dropdown-divider"></div>
            <a href="/logout" class="dropdown-item"><i class="bi bi-box-arrow-right"></i> Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="/login" class="btn secondary"><i class="bi bi-box-arrow-in-right"></i> Login</a>
      <?php endif; ?>
    </div>
  </nav>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
      const doc = document.documentElement;
      themeToggle.addEventListener('click', function(){
        const isLight = doc.classList.toggle('light');
        const theme = isLight ? 'light' : 'dark';
        doc.setAttribute('data-theme', theme);
        try { localStorage.setItem('ams_theme', theme); } catch(e){}
      });
    }

    const avatarBtn = document.getElementById('avatarBtn');
    const dropdown = document.getElementById('userDropdown');
    if (avatarBtn && dropdown) {
      avatarBtn.addEventListener('click', function(e){
        e.stopPropagation();
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
      });
      document.addEventListener('click', function(){
        dropdown.style.display = 'none';
      });
    }
  });
</script>