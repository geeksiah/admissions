<?php
require_once __DIR__ . '/../config/config.php';

$step = (int)($_GET['step'] ?? 1);
$error = '';
$ok = '';

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

        // Seed admin user if none
        $exists = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ((int)$exists === 0) {
            $stmt = $pdo->prepare("INSERT INTO users (username,email,password,role) VALUES (?,?,?, 'admin')");
            $stmt->execute(['admin','admin@example.com', password_hash('admin123', PASSWORD_DEFAULT)]);
        }

        file_put_contents(__DIR__ . '/../config/installed.lock', "OK\n" . date('c'));
        $ok = 'Installation complete. You can now login with admin / admin123';
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
    <p class="muted">Welcome. Click continue to configure your database. You can also reset and overwrite existing data.</p>
    <a class="btn" href="?step=2"><i class="bi bi-gear"></i> Continue</a>
  <?php elseif ($step === 2): ?>
    <form method="post">
      <div class="row"><label>DB Host</label><input class="input" name="db_host" value="localhost" required></div>
      <div class="row"><label>DB Name</label><input class="input" name="db_name" required></div>
      <div class="row"><label>DB User</label><input class="input" name="db_user" required></div>
      <div class="row"><label>DB Password</label><input class="input" type="password" name="db_pass"></div>
      <div class="row"><label><input type="checkbox" name="reset"> Reset and overwrite database (DROPS ALL TABLES)</label></div>
      <button class="btn" type="submit"><i class="bi bi-check2-circle"></i> Install</button>
    </form>
  <?php else: ?>
    <a class="btn" href="/login"><i class="bi bi-box-arrow-in-right"></i> Go to Login</a>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>


