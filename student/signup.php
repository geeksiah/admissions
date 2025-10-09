<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Student Signup';
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if (!validateCSRFToken($_POST['csrf'] ?? '')) { throw new RuntimeException('Invalid request'); }
    $first = sanitizeInput($_POST['first_name'] ?? '');
    $last = sanitizeInput($_POST['last_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($first==='' || $email==='' || strlen($password) < 8) { throw new RuntimeException('Provide name, email and 8+ char password'); }
    $db = new Database(); $pdo = $db->getConnection();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ((int)$stmt->fetchColumn() > 0) { throw new RuntimeException('Email already registered'); }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $token = bin2hex(random_bytes(16));
    // Ensure user has role student
    $pdo->prepare('INSERT IGNORE INTO user_roles(name,description,permissions) VALUES("student","Student","{}")')->execute();
    $stmt = $pdo->prepare('INSERT INTO users(username,email,password,role,first_name,last_name,is_active) VALUES(?,?,?,?,?,?,0)');
    $stmt->execute([$email, $email, $hash, 'student', $first, $last]);
    $userId = (int)$pdo->lastInsertId();
    $pdo->exec('CREATE TABLE IF NOT EXISTS email_verifications (user_id INT UNSIGNED PRIMARY KEY, token VARCHAR(64), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
    $pdo->prepare('REPLACE INTO email_verifications(user_id, token) VALUES(?,?)')->execute([$userId, $token]);
    $verifyUrl = (isset($_SERVER['HTTPS'])?'https://':'http://').$_SERVER['HTTP_HOST'].'/student/verify?token='.$token;
    // Simple mail using PHP mail() as placeholder (hook to SMTP later)
    if (!empty($email)) { @mail($email, 'Verify your email', "Click to verify: $verifyUrl"); }
    $message = 'Account created. Check your email to verify your account.';
  } catch (Throwable $e) {
    $error = APP_DEBUG ? $e->getMessage() : 'Signup failed.';
  }
}

$hideTopActions = true;
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrapper">
  <div class="auth-card">
    <h2 class="auth-title">Create Student Account</h2>
    <?php if ($error): ?><div class="muted" style="color:#f87171;margin:8px 0;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($message): ?><div class="muted" style="color:#10b981;margin:8px 0;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
      <label class="form-label">First name</label>
      <input class="input" name="first_name" required>
      <label class="form-label">Last name</label>
      <input class="input" name="last_name">
      <label class="form-label">Email</label>
      <input class="input" type="email" name="email" required>
      <label class="form-label">Password</label>
      <input class="input" type="password" name="password" minlength="8" required>
      <div class="form-actions">
        <button class="btn" type="submit"><i class="bi bi-person-plus"></i> Create account</button>
        <a class="btn secondary" href="/student/login">Sign in</a>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>


