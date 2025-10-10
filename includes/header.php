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
      <?php if (!isset($hideTopActions)): ?>
      <button id="sidebarToggleTop" class="btn secondary mobile-only" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
      <?php endif; ?>
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
        <?php if (!isset($hideTopActions)): ?>
          <a href="/login" class="btn secondary"><i class="bi bi-box-arrow-in-right"></i> Login</a>
        <?php endif; ?>
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

<!-- Global Toast Area -->
<div id="toastArea" class="toast-area"></div>

<!-- Global Confirm Modal -->
<div id="confirmModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:10000;align-items:center;justify-content:center">
  <div style="background:var(--card);border:1px solid var(--border);border-radius:14px;min-width:340px;max-width:90vw;padding:18px">
    <div id="confirmText" style="margin-bottom:16px;font-weight:600">Are you sure?</div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button id="confirmCancel" class="btn secondary">Cancel</button>
      <button id="confirmOk" class="btn"><i class="bi bi-check2"></i> OK</button>
    </div>
  </div>
  <div></div>
  <script>
    (function(){
      let resolver=null;
      function showConfirm(message){
        return new Promise(function(resolve){
          resolver=resolve;
          var modal=document.getElementById('confirmModal');
          document.getElementById('confirmText').textContent=message||'Are you sure?';
          modal.style.display='flex';
        });
      }
      function closeConfirm(val){
        document.getElementById('confirmModal').style.display='none';
        if(resolver){ resolver(val); resolver=null; }
      }
      document.getElementById('confirmOk').addEventListener('click', function(){ closeConfirm(true); });
      document.getElementById('confirmCancel').addEventListener('click', function(){ closeConfirm(false); });
      window.confirmDialog = showConfirm;
      // Intercept any form with data-confirm
      document.addEventListener('submit', async function(e){
        const form = e.target.closest('form[data-confirm]');
        if (!form) return;
        e.preventDefault();
        const msg = form.getAttribute('data-confirm') || 'Are you sure?';
        const ok = await showConfirm(msg);
        if (ok) {
          // avoid infinite loop
          form.removeAttribute('data-confirm');
          form.submit();
        }
      }, true);
    })();
  </script>
</div>

<!-- Global toast helpers (shadcn style) -->
<script>
  (function(){
    function createToast(content, variant){
      var area = document.getElementById('toastArea');
      if (!area) { return; }
      var t = document.createElement('div');
      t.className = 'toast ' + (variant || 'info');
      t.innerHTML = '<div style="display:flex;align-items:center;gap:8px">'
        + (variant==='success' ? '<i class="bi bi-check-circle" style="color:#10b981"></i>'
           : variant==='error' ? '<i class="bi bi-x-circle" style="color:#ef4444"></i>'
           : '<i class="bi bi-info-circle" style="color:#3b82f6"></i>')
        + '<span>'+content+'</span></div>'
        + '<button class="close" aria-label="Close">&times;</button>';
      t.querySelector('.close').onclick = function(){ t.remove(); };
      area.appendChild(t);
      setTimeout(function(){ try{ t.remove(); }catch(e){} }, 3800);
    }
    window.toast = function(opts){
      var msg = (typeof opts === 'string') ? opts : (opts.message || opts.title || '');
      var variant = (typeof opts === 'object' && opts.variant) ? opts.variant : (opts.type || 'info');
      if (!msg) return; createToast(msg, variant);
    };
    window.clearToasts = function(){
      var area = document.getElementById('toastArea');
      if (area) { area.innerHTML = ''; }
    };
  })();
</script>

<!-- Global Toast Area -->
<div id="toastArea" class="toast-area"></div>
<script>
  // shadcn-style toast helpers
  (function(){
    function createToast(content, variant){
      var area = document.getElementById('toastArea');
      if (!area) { return; }
      var t = document.createElement('div');
      t.className = 'toast ' + (variant || 'info');
      t.innerHTML = '<div style="display:flex;align-items:center;gap:8px">'
        + (variant==='success' ? '<i class="bi bi-check-circle" style="color:#10b981"></i>'
           : variant==='error' ? '<i class="bi bi-x-circle" style="color:#ef4444"></i>'
           : '<i class="bi bi-info-circle" style="color:#3b82f6"></i>')
        + '<span>'+content+'</span></div>'
        + '<button class="close" aria-label="Close">&times;</button>';
      t.querySelector('.close').onclick = function(){ t.remove(); };
      area.appendChild(t);
      setTimeout(function(){ try{ t.remove(); }catch(e){} }, 3800);
    }
    window.toast = function(opts){
      var msg = (typeof opts === 'string') ? opts : (opts.message || opts.title || '');
      var variant = (typeof opts === 'object' && opts.variant) ? opts.variant : (opts.type || 'info');
      if (!msg) return; createToast(msg, variant);
    };
    window.clearToasts = function(){
      var area = document.getElementById('toastArea');
      if (area) { area.innerHTML = ''; }
    };
  })();
</script>