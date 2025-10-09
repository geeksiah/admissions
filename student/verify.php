<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Verify Email';
$msg = '';$ok=false;
try {
  $token = $_GET['token'] ?? '';
  if ($token==='') throw new RuntimeException('Invalid link');
  $db = new Database(); $pdo = $db->getConnection();
  $pdo->exec('CREATE TABLE IF NOT EXISTS email_verifications (user_id INT UNSIGNED PRIMARY KEY, token VARCHAR(64), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
  $stmt = $pdo->prepare('SELECT user_id FROM email_verifications WHERE token = ?');
  $stmt->execute([$token]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) throw new RuntimeException('Invalid or expired token');
  $userId = (int)$row['user_id'];
  $pdo->prepare('UPDATE users SET is_active=1 WHERE id=? AND role="student"')->execute([$userId]);
  $pdo->prepare('DELETE FROM email_verifications WHERE user_id=?')->execute([$userId]);
  $ok=true; $msg='Email verified. You can now log in.';
} catch (Throwable $e) { $msg = APP_DEBUG ? $e->getMessage() : 'Verification failed.'; }

include __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrapper">
  <div class="auth-card">
    <h2 class="auth-title">Email Verification</h2>
    <p class="muted" style="color:<?php echo $ok?'#10b981':'#f87171'; ?>"><?php echo htmlspecialchars($msg); ?></p>
    <div class="form-actions">
      <a class="btn" href="/student/login"><i class="bi bi-box-arrow-in-right"></i> Go to Login</a>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>


