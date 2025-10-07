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
            $stmt = $pdo->prepare('SELECT id, username, role, password FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$username]);
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
            $error = 'Login error.';
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:480px;margin:40px auto;">
  <h2 class="mb-2">Sign in</h2>
  <p class="muted">Use your administrator account.</p>
  <?php if ($error): ?><div class="muted" style="color:#f87171;margin:8px 0;"><?php echo $error; ?></div><?php endif; ?>
  <form method="post">
    <div class="row">
      <label>Username</label>
      <input class="input" name="username" required>
    </div>
    <div class="row">
      <label>Password</label>
      <input class="input" type="password" name="password" required>
    </div>
    <button class="btn" type="submit"><i class="bi bi-box-arrow-in-right"></i> Login</button>
  </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>


