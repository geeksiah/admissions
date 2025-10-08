<?php
require_once __DIR__ . '/../config/config.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME; ?></title>
  <script>
    // Initialize theme before paint
    (function(){
      try{
        var theme = localStorage.getItem('ams_theme') || 'dark';
        var root = document.documentElement, body=document.body;
        if(theme==='light'){ root.classList.add('light'); body.classList.add('light'); }
        else { root.classList.remove('light'); body.classList.remove('light'); }
        root.setAttribute('data-theme', theme);
      }catch(e){}
    })();
  </script>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
  <div class="nav">
    <div></div>
    <div style="display:flex;align-items:center;gap:10px">
      <button id="sidebarToggleTop" class="btn secondary mobile-only" style="margin-right:8px"><i class="bi bi-list"></i></button>
      <button id="themeToggle" class="btn secondary" style="margin-right:8px"><i class="bi bi-circle-half"></i> Theme</button>
      <?php if (isLoggedIn()): ?>
        <div class="user-menu" style="position:relative">
          <?php
            $avatarPath = '/uploads/avatars/' . ($_SESSION['user_id'] ?? '0') . '.png';
            $avatarFs = $_SERVER['DOCUMENT_ROOT'] . $avatarPath;
            $initials = strtoupper(substr($_SESSION['username'] ?? 'U',0,2));
          ?>
          <div id="avatarBtn" style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent-2));display:flex;align-items:center;justify-content:center;color:#fff;cursor:pointer;overflow:hidden">
            <?php if (file_exists($avatarFs)): ?>
              <img src="<?php echo $avatarPath; ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover">
            <?php else: ?>
              <span style="font-size:12px;font-weight:700"><?php echo $initials; ?></span>
            <?php endif; ?>
          </div>
          <div id="userDropdown" style="position:absolute;right:0;top:44px;background:var(--card);border:1px solid var(--border);border-radius:10px;min-width:200px;display:none;box-shadow:0 6px 18px rgba(0,0,0,.15);z-index:1000">
            <a href="/admin/dashboard?panel=profile" style="display:flex;gap:8px;align-items:center;padding:12px 14px;color:var(--text);text-decoration:none"><i class="bi bi-person"></i> Profile</a>
            <a href="/admin/dashboard?panel=notifications" style="display:flex;gap:8px;align-items:center;padding:12px 14px;color:var(--text);text-decoration:none"><i class="bi bi-bell"></i> Notifications</a>
            <a href="/admin/dashboard?panel=settings" style="display:flex;gap:8px;align-items:center;padding:12px 14px;color:var(--text);text-decoration:none"><i class="bi bi-gear"></i> Settings</a>
            <div style="height:1px;background:var(--border);"></div>
            <a href="/logout" style="display:flex;gap:8px;align-items:center;padding:12px 14px;color:var(--text);text-decoration:none"><i class="bi bi-box-arrow-right"></i> Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="/login"><i class="bi bi-box-arrow-in-right"></i> Login</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="container">
  <script>
    window.addEventListener('DOMContentLoaded', function(){
      var btn = document.getElementById('themeToggle');
      if(!btn) return;
      btn.addEventListener('click', function(){
        var root = document.documentElement, body=document.body;
        var isLight = !root.classList.contains('light');
        if(isLight){ root.classList.add('light'); body.classList.add('light'); } else { root.classList.remove('light'); body.classList.remove('light'); }
        var theme = isLight ? 'light' : 'dark';
        root.setAttribute('data-theme', theme);
        try{ localStorage.setItem('ams_theme', theme); }catch(e){}
        btn.classList.toggle('active', isLight);
      });
      // set visual state on load
      var isLight = document.documentElement.classList.contains('light');
      btn.classList.toggle('active', isLight);

      var avatarBtn = document.getElementById('avatarBtn');
      var dropdown = document.getElementById('userDropdown');
      if (avatarBtn && dropdown) {
        avatarBtn.addEventListener('click', function(e){
          e.stopPropagation();
          dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        });
        document.addEventListener('click', function(){ dropdown.style.display = 'none'; });
      }

      // mobile sidebar toggle
      var topToggle = document.getElementById('sidebarToggleTop');
      var sb = document.getElementById('sidebarNav');
      if (topToggle && sb) {
        topToggle.addEventListener('click', function(e){
          e.stopPropagation();
          sb.classList.toggle('show');
          topToggle.innerHTML = sb.classList.contains('show') ? '<i class="bi bi-x"></i>' : '<i class="bi bi-list"></i>';
        });
        document.addEventListener('click', function(){ if (sb.classList.contains('show')) { sb.classList.remove('show'); topToggle.innerHTML = '<i class="bi bi-list"></i>'; } });
        sb.addEventListener('click', function(e){ e.stopPropagation(); });
      }
    });
  </script>

