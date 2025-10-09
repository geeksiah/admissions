<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Touch session
$_SESSION['diag_touch'] = ($_SESSION['diag_touch'] ?? 0) + 1;

$now = date('Y-m-d H:i:s');
$sessStatus = session_status();
$sessId = session_id();
$sessName = session_name();
$sessPath = function_exists('session_save_path') ? session_save_path() : '(n/a)';
$sessionDir = __DIR__ . '/logs/sessions';
$sessionDirExists = is_dir($sessionDir);
$sessionDirWritable = $sessionDirExists ? (is_writable($sessionDir) ? 'yes' : 'no') : 'no';

$dbOk = false; $dbErr = '';
try { $db = new Database(); $pdo = $db->getConnection(); $pdo->query('SELECT 1'); $dbOk = true; } catch (Throwable $e) { $dbErr = $e->getMessage(); }

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>System Diagnostics</title>
  <style>
    body{font-family:system-ui, -apple-system, Segoe UI, Roboto, Inter, Arial, sans-serif;background:#0f172a;color:#e5e7eb;margin:0;padding:24px}
    h1{margin:0 0 16px 0;font-size:22px}
    .card{background:#111827;border:1px solid #1f2937;border-radius:12px;padding:16px;margin-bottom:16px}
    .kv{display:grid;grid-template-columns:260px 1fr;gap:8px;align-items:start}
    .k{color:#9ca3af}
    code{background:#0b1220;padding:2px 6px;border-radius:6px}
  </style>
  </head>
<body>
  <h1>Admissions Management - Diagnostics</h1>

  <div class="card">
    <div class="kv">
      <div class="k">Time</div><div><?php echo htmlspecialchars($now); ?></div>
      <div class="k">PHP Version</div><div><?php echo PHP_VERSION; ?></div>
      <div class="k">APP_DEBUG</div><div><?php echo defined('APP_DEBUG') && APP_DEBUG ? 'true' : 'false'; ?></div>
    </div>
  </div>

  <div class="card">
    <div class="kv">
      <div class="k">Session Status</div><div><?php echo $sessStatus; ?> (<?php echo $sessStatus===PHP_SESSION_ACTIVE?'ACTIVE':($sessStatus===PHP_SESSION_NONE?'NONE':'DISABLED'); ?>)</div>
      <div class="k">Session Name</div><div><?php echo htmlspecialchars($sessName); ?></div>
      <div class="k">Session ID</div><div><code><?php echo htmlspecialchars($sessId); ?></code></div>
      <div class="k">Session Save Path</div><div><code><?php echo htmlspecialchars($sessPath); ?></code></div>
      <div class="k">logs/sessions exists?</div><div><?php echo $sessionDirExists ? 'yes' : 'no'; ?></div>
      <div class="k">logs/sessions writable?</div><div><?php echo $sessionDirWritable; ?></div>
      <div class="k">Session Touch Count</div><div><?php echo (int)$_SESSION['diag_touch']; ?></div>
      <div class="k">Logged In?</div><div><?php echo isLoggedIn() ? 'yes' : 'no'; ?></div>
      <div class="k">User</div><div><?php echo isLoggedIn() ? htmlspecialchars(($_SESSION['username'] ?? '').' ('.($_SESSION['role'] ?? '').')') : '-'; ?></div>
    </div>
  </div>

  <div class="card">
    <div class="kv">
      <div class="k">DB Connection</div><div><?php echo $dbOk ? 'OK' : ('ERROR: '.htmlspecialchars($dbErr)); ?></div>
    </div>
  </div>

  <div class="card">
    <div class="kv">
      <div class="k">Cookies</div>
      <div><code><?php echo htmlspecialchars(json_encode($_COOKIE, JSON_PRETTY_PRINT)); ?></code></div>
    </div>
  </div>

</body>
</html>


