<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Student Login';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = sanitizeInput($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  if ($email === '' || $password === '') {
    $error = 'Enter both email and password.';
  } else {
    try {
      $db = new Database();
      $pdo = $db->getConnection();
      $stmt = $pdo->prepare('SELECT id, email, password FROM users WHERE email = ? AND role = "student" AND is_active = 1 LIMIT 1');
      $stmt->execute([$email]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($user && password_verify($password, $user['password'])) {
        $_SESSION['student_id'] = $user['id'];
        header('Location: /student/dashboard?panel=applications');
        exit;
      }
      $error = 'Invalid credentials.';
    } catch (Throwable $e) {
      $error = APP_DEBUG ? ('Login error: '.$e->getMessage()) : 'System error. Try again later.';
    }
  }
}

$hideTopActions = true;
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrapper">
  <div class="auth-card">
    <h2 class="auth-title">Student Sign in</h2>
    <p class="muted">Use your student account.</p>
    <?php if ($error): ?><div class="muted" style="color:#f87171;margin:8px 0;"><?php echo $error; ?></div><?php endif; ?>
    <form method="post">
      <label class="form-label">Email</label>
      <input class="input" type="email" name="email" required>
      <label class="form-label">Password</label>
      <input class="input" type="password" name="password" required>
      <div class="form-actions">
        <button class="btn block" type="submit"><i class="bi bi-box-arrow-in-right"></i> Login</button>
        <a class="btn secondary" href="/student/signup">Create account</a>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>


