<?php
// Users panel - Admin user management with RBAC

$msg=''; $type='';
try { 
  $pdo->exec("CREATE TABLE IF NOT EXISTS user_roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    permissions JSON,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  
  $pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE,
    email VARCHAR(150) UNIQUE,
    password VARCHAR(255),
    role VARCHAR(50) DEFAULT 'admin',
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(50),
    is_active TINYINT(1) DEFAULT 1,
    last_login TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_role(role),
    INDEX idx_active(is_active)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  
  // Insert default roles if they don't exist
  $roles = [
    ['super_admin', 'Super Administrator', '{"all": true}'],
    ['admin', 'Administrator', '{"users": true, "applications": true, "reports": true, "settings": true}'],
    ['admissions_officer', 'Admissions Officer', '{"applications": true, "students": true, "reports": true}'],
    ['reviewer', 'Application Reviewer', '{"applications": true}'],
    ['finance', 'Finance Officer', '{"payments": true, "reports": true}']
  ];
  
  foreach ($roles as [$name, $desc, $perms]) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO user_roles (name, description, permissions) VALUES (?, ?, ?)");
    $stmt->execute([$name, $desc, $perms]);
  }
} catch (Throwable $e) { /* ignore */ }

// Handle create/update/delete actions
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  try {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) { 
      throw new RuntimeException('Invalid request'); 
    }
    
    if ($action==='create') {
      $username = trim($_POST['username'] ?? '');
      $email = trim($_POST['email'] ?? '');
      $password = $_POST['password'] ?? '';
      $role = $_POST['role'] ?? 'admin';
      $firstName = trim($_POST['first_name'] ?? '');
      $lastName = trim($_POST['last_name'] ?? '');
      $phone = trim($_POST['phone'] ?? '');
      
      if (!$username || !$email || !$password || !$firstName || !$lastName) {
        throw new RuntimeException('All required fields must be filled');
      }
      
      if (strlen($password) < 8) {
        throw new RuntimeException('Password must be at least 8 characters');
      }
      
      // Check if username or email already exists
      $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
      $stmt->execute([$username, $email]);
      if ($stmt->fetchColumn() > 0) {
        throw new RuntimeException('Username or email already exists');
      }
      
      $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, first_name, last_name, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
      $stmt->execute([$username, $email, $hashedPassword, $role, $firstName, $lastName, $phone]);
      $msg='User created successfully'; $type='success';
      
    } elseif ($action==='update') {
      $id = (int)($_POST['id'] ?? 0);
      $role = $_POST['role'] ?? 'admin';
      $firstName = trim($_POST['first_name'] ?? '');
      $lastName = trim($_POST['last_name'] ?? '');
      $phone = trim($_POST['phone'] ?? '');
      $isActive = isset($_POST['is_active']) ? 1 : 0;
      
      if (!$id || !$firstName || !$lastName) {
        throw new RuntimeException('ID and names are required');
      }
      
      $stmt = $pdo->prepare("UPDATE users SET role=?, first_name=?, last_name=?, phone=?, is_active=? WHERE id=?");
      $stmt->execute([$role, $firstName, $lastName, $phone, $isActive, $id]);
      $msg='User updated successfully'; $type='success';
      
    } elseif ($action==='reset_password') {
      $id = (int)($_POST['id'] ?? 0);
      $newPassword = $_POST['new_password'] ?? '';
      
      if (!$id || !$newPassword || strlen($newPassword) < 8) {
        throw new RuntimeException('Valid ID and password (8+ chars) required');
      }
      
      $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("UPDATE users SET password=?, login_attempts=0, locked_until=NULL WHERE id=?");
      $stmt->execute([$hashedPassword, $id]);
      $msg='Password reset successfully'; $type='success';
      
    } elseif ($action==='toggle_status') {
      $id = (int)($_POST['id'] ?? 0);
      $isActive = (int)($_POST['is_active'] ?? 0);
      
      if (!$id) throw new RuntimeException('User ID required');
      
      $stmt = $pdo->prepare("UPDATE users SET is_active=? WHERE id=?");
      $stmt->execute([$isActive, $id]);
      $msg='User status updated'; $type='success';
    }
  } catch (Throwable $e) { 
    $msg='Failed: '.$e->getMessage(); 
    $type='danger'; 
  }
}

// Fetch users with pagination
$q = trim($_GET['q'] ?? '');
$role = trim($_GET['role'] ?? '');
$status = trim($_GET['status'] ?? '');
$page = max(1,(int)($_GET['page'] ?? 1));
$per=15; $offset=($page-1)*$per;

$params=[]; $where=[];
if ($q!==''){ 
  $where[]='(first_name LIKE ? OR last_name LIKE ? OR username LIKE ? OR email LIKE ?)'; 
  $params = array_merge($params, ["%$q%", "%$q%", "%$q%", "%$q%"]);
}
if ($role!==''){ $where[]='role=?'; $params[]=$role; }
if ($status!==''){ 
  if ($status==='active') $where[]='is_active=1';
  if ($status==='inactive') $where[]='is_active=0';
  if ($status==='locked') $where[]='locked_until > NOW()';
}

$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

$total=0; 
try{ 
  $st=$pdo->prepare("SELECT COUNT(*) FROM users $whereSql"); 
  $st->execute($params); 
  $total=(int)$st->fetchColumn(); 
} catch(Throwable $e){}

$pages=max(1,(int)ceil($total/$per));

$rows=[]; 
try{ 
  $st=$pdo->prepare("SELECT * FROM users $whereSql ORDER BY created_at DESC LIMIT $offset,$per"); 
  $st->execute($params); 
  $rows=$st->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e){}

// Fetch roles for dropdowns
$roles=[]; 
try{ 
  $st=$pdo->query("SELECT name, description FROM user_roles WHERE is_active=1 ORDER BY name"); 
  $roles=$st->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e){}
?>

<?php if($msg): ?>
<div class="card" style="border-left:4px solid <?php echo $type==='success'?'#10b981':'#ef4444'; ?>;margin-bottom:12px;"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<div class="panel-card">
  <h3>User Management</h3>
  
  <form method="get" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
    <input type="hidden" name="panel" value="users">
    <input class="input" name="q" placeholder="Search users..." value="<?php echo htmlspecialchars($q); ?>" style="min-width:240px">
    <select class="input" name="role" style="max-width:160px">
      <option value="">All Roles</option>
      <?php foreach($roles as $r): ?>
        <option value="<?php echo htmlspecialchars($r['name']); ?>" <?php echo $role===$r['name']?'selected':''; ?>><?php echo htmlspecialchars($r['description']); ?></option>
      <?php endforeach; ?>
    </select>
    <select class="input" name="status" style="max-width:140px">
      <option value="">All Status</option>
      <option value="active" <?php echo $status==='active'?'selected':''; ?>>Active</option>
      <option value="inactive" <?php echo $status==='inactive'?'selected':''; ?>>Inactive</option>
      <option value="locked" <?php echo $status==='locked'?'selected':''; ?>>Locked</option>
    </select>
    <button class="btn" type="submit"><i class="bi bi-search"></i> Filter</button>
  </form>

  <div class="card" style="overflow:auto">
    <table style="width:100%;border-collapse:collapse">
      <thead>
        <tr style="text-align:left;border-bottom:1px solid var(--border)">
          <th style="padding:10px">User</th>
          <th style="padding:10px">Role</th>
          <th style="padding:10px">Status</th>
          <th style="padding:10px">Last Login</th>
          <th style="padding:10px">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if(!$rows): ?>
        <tr><td colspan="5" style="padding:14px" class="muted">No users found.</td></tr>
        <?php else: foreach($rows as $r): ?>
        <tr style="border-bottom:1px solid var(--border)">
          <td style="padding:10px">
            <div>
              <div style="font-weight:500"><?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']); ?></div>
              <div class="muted" style="font-size:12px"><?php echo htmlspecialchars($r['username'].' • '.$r['email']); ?></div>
            </div>
          </td>
          <td style="padding:10px">
            <span class="muted"><?php echo htmlspecialchars($r['role']); ?></span>
          </td>
          <td style="padding:10px">
            <?php if($r['locked_until'] && strtotime($r['locked_until']) > time()): ?>
              <span style="color:#ef4444;font-size:12px">Locked</span>
            <?php elseif($r['is_active']): ?>
              <span style="color:#10b981;font-size:12px">Active</span>
            <?php else: ?>
              <span style="color:#f59e0b;font-size:12px">Inactive</span>
            <?php endif; ?>
          </td>
          <td style="padding:10px">
            <span class="muted" style="font-size:12px">
              <?php echo $r['last_login'] ? date('M j, Y g:i A', strtotime($r['last_login'])) : 'Never'; ?>
            </span>
          </td>
          <td style="padding:10px;display:flex;gap:4px;flex-wrap:wrap">
            <button class="btn secondary" onclick="editUser(<?php echo htmlspecialchars(json_encode($r)); ?>)">
              <i class="bi bi-pencil"></i> Edit
            </button>
            <button class="btn secondary" onclick="resetPassword(<?php echo (int)$r['id']; ?>)">
              <i class="bi bi-key"></i> Reset
            </button>
            <form method="post" action="?panel=users" style="display:inline" onsubmit="return confirm('Toggle user status?')">
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
              <input type="hidden" name="is_active" value="<?php echo $r['is_active'] ? 0 : 1; ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
              <button class="btn secondary" type="submit">
                <i class="bi bi-toggle2-<?php echo $r['is_active']?'on':'off'; ?>"></i> <?php echo $r['is_active']?'Deactivate':'Activate'; ?>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if($pages>1): ?>
  <div style="display:flex;gap:6px;justify-content:flex-end;margin-top:10px">
    <?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++): ?>
      <a class="btn secondary" href="?panel=users&page=<?php echo $i; ?>&q=<?php echo urlencode($q); ?>&role=<?php echo urlencode($role); ?>&status=<?php echo urlencode($status); ?>" style="padding:6px 10px;border-radius:6px;<?php echo $i===$page?'background:var(--surface-hover)':''; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<div class="panel-card">
  <h3>Add New User</h3>
  <form method="post" action="?panel=users" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px">
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    
    <div>
      <label class="form-label">Username *</label>
      <input class="input" name="username" required>
    </div>
    <div>
      <label class="form-label">Email *</label>
      <input class="input" name="email" type="email" required>
    </div>
    <div>
      <label class="form-label">Password *</label>
      <input class="input" name="password" type="password" required minlength="8">
    </div>
    <div>
      <label class="form-label">Role</label>
      <select class="input" name="role" required>
        <?php foreach($roles as $r): ?>
          <option value="<?php echo htmlspecialchars($r['name']); ?>"><?php echo htmlspecialchars($r['description']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="form-label">First Name *</label>
      <input class="input" name="first_name" required>
    </div>
    <div>
      <label class="form-label">Last Name *</label>
      <input class="input" name="last_name" required>
    </div>
    <div>
      <label class="form-label">Phone</label>
      <input class="input" name="phone" type="tel">
    </div>
    <div style="display:flex;align-items:flex-end">
      <button class="btn" type="submit"><i class="bi bi-plus-lg"></i> Add User</button>
    </div>
  </form>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999">
  <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--card);border-radius:16px;padding:24px;min-width:500px;max-width:90vw;max-height:90vh;overflow:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3>Edit User</h3>
      <button onclick="closeEditModal()" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
    </div>
    <form method="post" action="?panel=users" id="editUserForm">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="editUserId">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
      
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        <div>
          <label class="form-label">First Name *</label>
          <input class="input" name="first_name" id="editFirstName" required>
        </div>
        <div>
          <label class="form-label">Last Name *</label>
          <input class="input" name="last_name" id="editLastName" required>
        </div>
        <div>
          <label class="form-label">Role</label>
          <select class="input" name="role" id="editRole" required>
            <?php foreach($roles as $r): ?>
              <option value="<?php echo htmlspecialchars($r['name']); ?>"><?php echo htmlspecialchars($r['description']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Phone</label>
          <input class="input" name="phone" id="editPhone" type="tel">
        </div>
      </div>
      
      <div style="display:flex;gap:12px;margin-bottom:16px">
        <label style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" name="is_active" id="editIsActive" value="1">
          <span>Active User</span>
        </label>
      </div>
      
      <div style="display:flex;gap:12px;justify-content:flex-end">
        <button type="button" class="btn secondary" onclick="closeEditModal()">Cancel</button>
        <button type="submit" class="btn">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999">
  <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--card);border-radius:16px;padding:24px;min-width:400px;max-width:90vw">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3>Reset Password</h3>
      <button onclick="closeResetModal()" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
    </div>
    <form method="post" action="?panel=users" id="resetPasswordForm">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="id" id="resetUserId">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
      
      <div style="margin-bottom:16px">
        <label class="form-label">New Password *</label>
        <input class="input" name="new_password" type="password" required minlength="8" placeholder="Minimum 8 characters">
      </div>
      
      <div style="display:flex;gap:12px;justify-content:flex-end">
        <button type="button" class="btn secondary" onclick="closeResetModal()">Cancel</button>
        <button type="submit" class="btn">Reset Password</button>
      </div>
    </form>
  </div>
</div>

<script>
function editUser(user) {
  document.getElementById('editUserId').value = user.id;
  document.getElementById('editFirstName').value = user.first_name || '';
  document.getElementById('editLastName').value = user.last_name || '';
  document.getElementById('editRole').value = user.role || 'admin';
  document.getElementById('editPhone').value = user.phone || '';
  document.getElementById('editIsActive').checked = user.is_active == '1';
  document.getElementById('editUserModal').style.display = 'block';
}

function closeEditModal() {
  document.getElementById('editUserModal').style.display = 'none';
}

function resetPassword(userId) {
  document.getElementById('resetUserId').value = userId;
  document.getElementById('resetPasswordForm').reset();
  document.getElementById('resetPasswordModal').style.display = 'block';
}

function closeResetModal() {
  document.getElementById('resetPasswordModal').style.display = 'none';
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
  if (e.target.id === 'editUserModal') closeEditModal();
  if (e.target.id === 'resetPasswordModal') closeResetModal();
});
</script>
