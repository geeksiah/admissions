<?php
// Profile panel - User profile management

$msg=''; $type='';

// Handle profile updates
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  try {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) { 
      throw new RuntimeException('Invalid request'); 
    }
    
    if ($action==='update_profile') {
      $firstName = trim($_POST['first_name'] ?? '');
      $lastName = trim($_POST['last_name'] ?? '');
      $email = trim($_POST['email'] ?? '');
      $phone = trim($_POST['phone'] ?? '');
      
      if (!$firstName || !$lastName || !$email) {
        throw new RuntimeException('First name, last name, and email are required');
      }
      
      // Update user profile
      $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=? WHERE id=?");
      $stmt->execute([$firstName, $lastName, $email, $phone, $_SESSION['user_id']]);
      
      // Update session data
      $_SESSION['first_name'] = $firstName;
      $_SESSION['last_name'] = $lastName;
      $_SESSION['email'] = $email;
      
      $msg='Profile updated successfully'; $type='success';
      
    } elseif ($action==='change_password') {
      $currentPassword = $_POST['current_password'] ?? '';
      $newPassword = $_POST['new_password'] ?? '';
      $confirmPassword = $_POST['confirm_password'] ?? '';
      
      if (!$currentPassword || !$newPassword || !$confirmPassword) {
        throw new RuntimeException('All password fields are required');
      }
      
      if ($newPassword !== $confirmPassword) {
        throw new RuntimeException('New passwords do not match');
      }
      
      if (strlen($newPassword) < 8) {
        throw new RuntimeException('New password must be at least 8 characters');
      }
      
      // Verify current password
      $stmt = $pdo->prepare("SELECT password FROM users WHERE id=?");
      $stmt->execute([$_SESSION['user_id']]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);
      
      if (!$user || !password_verify($currentPassword, $user['password'])) {
        throw new RuntimeException('Current password is incorrect');
      }
      
      // Update password
      $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
      $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
      
      $msg='Password changed successfully'; $type='success';
    }
  } catch (Throwable $e) { 
    $msg='Failed: '.$e->getMessage(); 
    $type='danger'; 
  }
}

// Get current user data
$userData = [];
try {
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
  $stmt->execute([$_SESSION['user_id']]);
  $userData = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* ignore */ }

// Get user login history
$loginHistory = [];
try {
  $stmt = $pdo->prepare("SELECT * FROM login_history WHERE user_id=? ORDER BY login_time DESC LIMIT 10");
  $stmt->execute([$_SESSION['user_id']]);
  $loginHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* ignore */ }
?>

<?php if($msg): ?>
<div class="card" style="border-left:4px solid <?php echo $type==='success'?'#10b981':'#ef4444'; ?>;margin-bottom:12px;"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<div class="panel-card">
  <h3>Profile Information</h3>
  <p class="muted">Manage your account information and security settings.</p>
  
  <div class="profile-grid">
    <div class="panel-card profile-avatar-wrapper">
      <div class="avatar-lg">
        <?php if(isset($userData['first_name']) && isset($userData['last_name'])): ?>
          <div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:24px;font-weight:700;color:white">
            <?php echo strtoupper(substr($userData['first_name'], 0, 1) . substr($userData['last_name'], 0, 1)); ?>
          </div>
        <?php endif; ?>
      </div>
      <button class="btn secondary" disabled>Upload Avatar (coming soon)</button>
      <div style="text-align:center;margin-top:12px">
        <div style="font-weight:500"><?php echo htmlspecialchars(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '')); ?></div>
        <div class="muted" style="font-size:12px"><?php echo htmlspecialchars($userData['role'] ?? 'admin'); ?></div>
      </div>
    </div>
    
    <div class="panel-card">
      <h4>Personal Information</h4>
      <form method="post" action="?panel=profile" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <input type="hidden" name="action" value="update_profile">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
        
        <div>
          <label class="form-label">First Name *</label>
          <input class="input" name="first_name" value="<?php echo htmlspecialchars($userData['first_name'] ?? ''); ?>" required>
        </div>
        
        <div>
          <label class="form-label">Last Name *</label>
          <input class="input" name="last_name" value="<?php echo htmlspecialchars($userData['last_name'] ?? ''); ?>" required>
        </div>
        
        <div>
          <label class="form-label">Email *</label>
          <input class="input" name="email" type="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
        </div>
        
        <div>
          <label class="form-label">Phone</label>
          <input class="input" name="phone" type="tel" value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>">
        </div>
        
        <div style="grid-column:1/-1">
          <button class="btn" type="submit">
            <i class="bi bi-save"></i> Update Profile
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="panel-card">
  <h3>Change Password</h3>
  <form method="post" action="?panel=profile" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <input type="hidden" name="action" value="change_password">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    
    <div>
      <label class="form-label">Current Password *</label>
      <input class="input" name="current_password" type="password" required>
    </div>
    
    <div>
      <label class="form-label">New Password *</label>
      <input class="input" name="new_password" type="password" minlength="8" required>
    </div>
    
    <div>
      <label class="form-label">Confirm New Password *</label>
      <input class="input" name="confirm_password" type="password" minlength="8" required>
    </div>
    
    <div style="grid-column:1/-1">
      <button class="btn" type="submit">
        <i class="bi bi-key"></i> Change Password
      </button>
    </div>
  </form>
</div>

<div class="panel-card">
  <h3>Account Information</h3>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px">
    <div style="text-align:center;padding:16px;background:var(--surface-hover);border-radius:8px">
      <div style="font-size:24px;font-weight:700;color:#2563eb"><?php echo htmlspecialchars($userData['username'] ?? 'N/A'); ?></div>
      <div class="muted">Username</div>
    </div>
    <div style="text-align:center;padding:16px;background:var(--surface-hover);border-radius:8px">
      <div style="font-size:24px;font-weight:700;color:#10b981"><?php echo htmlspecialchars(ucfirst($userData['role'] ?? 'admin')); ?></div>
      <div class="muted">Role</div>
    </div>
    <div style="text-align:center;padding:16px;background:var(--surface-hover);border-radius:8px">
      <div style="font-size:24px;font-weight:700;color:#f59e0b"><?php echo $userData['is_active'] ? 'Active' : 'Inactive'; ?></div>
      <div class="muted">Status</div>
    </div>
    <div style="text-align:center;padding:16px;background:var(--surface-hover);border-radius:8px">
      <div style="font-size:24px;font-weight:700;color:#8b5cf6"><?php echo $userData['last_login'] ? date('M j', strtotime($userData['last_login'])) : 'Never'; ?></div>
      <div class="muted">Last Login</div>
    </div>
  </div>
</div>

<?php if(!empty($loginHistory)): ?>
<div class="panel-card">
  <h3>Recent Login History</h3>
  <div class="card" style="overflow:auto;max-height:300px">
    <?php foreach($loginHistory as $login): ?>
      <div style="padding:12px;border-bottom:1px solid var(--border)">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
          <div style="flex:1">
            <div style="display:flex;gap:12px;align-items:center;margin-bottom:4px">
              <span style="font-size:12px;padding:2px 6px;border-radius:4px;background:<?php echo $login['status'] === 'success' ? '#10b981' : '#ef4444'; ?>;color:white">
                <?php echo strtoupper($login['status']); ?>
              </span>
              <span style="font-family:monospace;font-size:12px"><?php echo htmlspecialchars($login['ip_address'] ?? '-'); ?></span>
            </div>
            <div class="muted" style="font-size:12px">
              <?php if($login['session_duration']): ?>
                Session: <?php echo gmdate('H:i:s', $login['session_duration']); ?>
              <?php endif; ?>
              <?php if($login['failure_reason']): ?>
                • Reason: <?php echo htmlspecialchars($login['failure_reason']); ?>
              <?php endif; ?>
            </div>
          </div>
          <div style="font-size:12px;color:var(--muted)">
            <?php echo date('M j, Y g:i A', strtotime($login['login_time'])); ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
