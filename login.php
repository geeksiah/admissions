<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Login';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error = 'Enter both username and password.';
    } else {
        try {
            $db = new Database();
            $pdo = $db->getConnection();
            $stmt = $pdo->prepare('SELECT id, username, email, role, password FROM users WHERE (username = ? OR email = ?) AND is_active = 1 LIMIT 1');
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                header('Location: /admin/dashboard');
                exit;
            }
            $error = 'Invalid credentials.';
        } catch (Throwable $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                $error = 'Login error: ' . $e->getMessage();
            } else {
                $error = 'System configuration error. Please run the installer again.';
            }
        }
    }
}

$hideTopActions = true;
include __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
  <div class="auth-card">
    <h2 class="auth-title">Sign in</h2>
    <p class="muted">Use your administrator account.</p>
    <?php if ($error): ?><div class="muted" style="color:#f87171;margin:8px 0;"><?php echo $error; ?></div><?php endif; ?>
    <form method="post">
      <label class="form-label">Username or Email</label>
      <input class="input" name="username" required>
      <label class="form-label">Password</label>
      <input class="input" type="password" name="password" required>
      <div class="form-actions">
        <button class="btn block" type="submit"><i class="bi bi-box-arrow-in-right"></i> Login</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>


