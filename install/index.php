<?php
/**
 * Secure Installer
 * Implements security improvements as recommended in Improvements.txt
 */

// Check if already installed
if (file_exists(__DIR__ . '/../config/installed.lock')) {
    http_response_code(403);
    die('System already installed. Delete config/installed.lock to reinstall.');
}

require_once __DIR__ . '/../config/config.php';

$step = (int)($_GET['step'] ?? 1);
$error = '';
$ok = '';

// Pre-installation checks
if ($step === 1) {
    $checks = [];
    
    // PHP version check
    $checks['php_version'] = version_compare(PHP_VERSION, '8.0.0', '>=');
    
    // Required extensions
    $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'fileinfo'];
    $checks['extensions'] = true;
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            $checks['extensions'] = false;
            break;
        }
    }
    
    // Directory permissions
    $checks['writable_config'] = is_writable(__DIR__ . '/../config/');
    $checks['writable_uploads'] = is_writable(__DIR__ . '/../uploads/');
    $checks['writable_logs'] = is_writable(__DIR__ . '/../logs/');
    
    $allChecksPass = array_reduce($checks, function($carry, $check) { return $carry && $check; }, true);
    
    if (!$allChecksPass) {
        $error = 'Pre-installation checks failed. Please ensure PHP 8.0+, required extensions, and writable directories.';
    }
}

if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = sanitizeInput($_POST['db_host'] ?? '');
    $name = sanitizeInput($_POST['db_name'] ?? '');
    $user = sanitizeInput($_POST['db_user'] ?? '');
    $pass = $_POST['db_pass'] ?? '';
    $reset = isset($_POST['reset']) ? true : false;

    try {
        // Test connection
        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        $pdo = new \PDO($dsn, $user, $pass, [\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);

        if ($reset) {
            // Drop all tables in target DB (disable FK checks to avoid constraint issues)
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($tables as $t) { $pdo->exec("DROP TABLE IF EXISTS `{$t}`"); }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        // Write dedicated secrets file to avoid fragile regex updates
        $secrets = "<?php\n".
                  "define('DB_HOST','".$host."');\n".
                  "define('DB_NAME','".$name."');\n".
                  "define('DB_USER','".$user."');\n".
                  "define('DB_PASS','".$pass."');\n";
        file_put_contents(__DIR__ . '/../config/db_secrets.php', $secrets);

        // Verify secrets persisted
        if (!file_exists(__DIR__ . '/../config/db_secrets.php')) {
            throw new \RuntimeException('Failed to write config/db_secrets.php. Please ensure the config directory is writable.');
        }
        // Load and verify values
        require __DIR__ . '/../config/db_secrets.php';
        if (empty(DB_USER) && empty(DB_PASS)) {
            throw new \RuntimeException('Database credentials appear empty. Please re-enter and try again.');
        }

        // Create schema
        $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
        $pdo->exec($schema);

        // Create admin account with secure credentials
        $adminUsername = sanitizeInput($_POST['admin_username'] ?? 'admin');
        $adminEmail = sanitizeInput($_POST['admin_email'] ?? '');
        $adminPassword = $_POST['admin_password'] ?? '';
        
        // Validate admin credentials
        if (empty($adminUsername) || empty($adminEmail) || empty($adminPassword)) {
            throw new \RuntimeException('Admin credentials are required');
        }
        
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid admin email address');
        }
        
        if (strlen($adminPassword) < 8) {
            throw new \RuntimeException('Admin password must be at least 8 characters');
        }
        
        // Check if admin user exists
        $exists = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ((int)$exists === 0) {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, first_name, last_name, is_active) VALUES (?, ?, ?, 'admin', ?, ?, 1)");
            $stmt->execute([
                $adminUsername,
                $adminEmail, 
                password_hash($adminPassword, PASSWORD_DEFAULT),
                'Admin',
                'User'
            ]);
        }

        file_put_contents(__DIR__ . '/../config/installed.lock', "OK\n" . date('c'));
        $ok = 'Installation complete. You can now login with your admin credentials.';
        $step = 3;
    } catch (Throwable $e) {
        $error = 'Install failed: ' . $e->getMessage();
        $step = 2;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:720px;margin:24px auto;">
  <h2>Installer</h2>
  <?php if ($error): ?><div style="color:#f87171;"><?php echo $error; ?></div><?php endif; ?>
  <?php if ($ok): ?><div style="color:#10b981;"><?php echo $ok; ?></div><?php endif; ?>

  <?php if ($step === 1): ?>
    <div class="pre-install-checks">
      <h3>Pre-installation Checks</h3>
      <div class="check-item <?php echo version_compare(PHP_VERSION, '8.0.0', '>=') ? 'success' : 'error'; ?>">
        <i class="bi bi-<?php echo version_compare(PHP_VERSION, '8.0.0', '>=') ? 'check-circle' : 'x-circle'; ?>"></i>
        PHP Version: <?php echo PHP_VERSION; ?> (Required: 8.0+)
      </div>
      <div class="check-item <?php echo extension_loaded('pdo') && extension_loaded('pdo_mysql') ? 'success' : 'error'; ?>">
        <i class="bi bi-<?php echo extension_loaded('pdo') && extension_loaded('pdo_mysql') ? 'check-circle' : 'x-circle'; ?>"></i>
        PDO MySQL Extension
      </div>
      <div class="check-item <?php echo is_writable(__DIR__ . '/../config/') ? 'success' : 'error'; ?>">
        <i class="bi bi-<?php echo is_writable(__DIR__ . '/../config/') ? 'check-circle' : 'x-circle'; ?>"></i>
        Config Directory Writable
      </div>
      <div class="check-item <?php echo is_writable(__DIR__ . '/../uploads/') ? 'success' : 'error'; ?>">
        <i class="bi bi-<?php echo is_writable(__DIR__ . '/../uploads/') ? 'check-circle' : 'x-circle'; ?>"></i>
        Uploads Directory Writable
      </div>
    </div>
    <p class="muted">Click continue to configure your database and create admin account.</p>
    <a class="btn" href="?step=2"><i class="bi bi-gear"></i> Continue</a>
  <?php elseif ($step === 2): ?>
    <form method="post">
      <h3>Database Configuration</h3>
      <div class="row"><label>DB Host</label><input class="input" name="db_host" value="localhost" required></div>
      <div class="row"><label>DB Name</label><input class="input" name="db_name" required></div>
      <div class="row"><label>DB User</label><input class="input" name="db_user" required></div>
      <div class="row"><label>DB Password</label><input class="input" type="password" name="db_pass"></div>
      <div class="row"><label><input type="checkbox" name="reset"> Reset and overwrite database (DROPS ALL TABLES)</label></div>
      
      <h3>Admin Account</h3>
      <div class="row"><label>Admin Username</label><input class="input" name="admin_username" value="admin" required></div>
      <div class="row"><label>Admin Email</label><input class="input" type="email" name="admin_email" required></div>
      <div class="row"><label>Admin Password</label><input class="input" type="password" name="admin_password" minlength="8" required></div>
      
      <button class="btn" type="submit"><i class="bi bi-check2-circle"></i> Install</button>
    </form>
  <?php else: ?>
    <div class="success-message">
      <i class="bi bi-check-circle" style="font-size:48px;color:#10b981;margin-bottom:16px;"></i>
      <h3>Installation Complete!</h3>
      <p>Your admissions management system is ready to use.</p>
      <p><strong>Important:</strong> Delete the <code>install/</code> directory for security.</p>
    </div>
    <a class="btn" href="/login"><i class="bi bi-box-arrow-in-right"></i> Go to Login</a>
  <?php endif; ?>
</div>

<style>
.pre-install-checks {
  margin: 20px 0;
}
.check-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 0;
  border-bottom: 1px solid var(--border);
}
.check-item.success {
  color: #10b981;
}
.check-item.error {
  color: #f87171;
}
.success-message {
  text-align: center;
  padding: 20px;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>


