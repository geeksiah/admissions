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
    <div>
      <a href="/" class="active"><i class="bi bi-mortarboard"></i> <?php echo APP_NAME; ?></a>
    </div>
    <div>
      <button id="themeToggle" class="btn secondary" style="margin-right:8px"><i class="bi bi-circle-half"></i> Theme</button>
      <?php if (isLoggedIn()): ?>
        <a href="/admin/dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="/logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
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
    });
  </script>

