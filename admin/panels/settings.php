<?php
// Settings panel - branding, institution and uploads
// Expects $pdo and session to be available from dashboard.php

if (!function_exists('upsert_setting')) {
    function upsert_setting(PDO $pdo, string $key, string $value): void {
        $stmt = $pdo->prepare("INSERT INTO system_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
        $stmt->execute([$key, $value]);
    }
}

$message = '';
$messageType = '';

// Ensure uploads dirs
@mkdir($_SERVER['DOCUMENT_ROOT'] . '/uploads', 0775, true);
@mkdir($_SERVER['DOCUMENT_ROOT'] . '/uploads/logos', 0775, true);
@mkdir($_SERVER['DOCUMENT_ROOT'] . '/uploads/avatars', 0775, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine if this POST is intended for Settings panel to avoid cross-panel toasts
    $allowedActions = ['add_gateway','update_gateway','toggle_gateway','delete_gateway','add_sms_gateway','update_sms_gateway','toggle_sms_gateway','delete_sms_gateway'];
    $whitelist = [
        'brand_color','institution_name','institution_email','institution_phone','timezone','currency',
        // Payments
        'paystack_enabled','paystack_public_key','paystack_secret_key','paystack_mode',
        'flutter_enabled','flutter_public_key','flutter_secret_key','flutter_mode',
        'stripe_enabled','stripe_public_key','stripe_secret_key','stripe_mode',
        // Email/SMS
        'smtp_host','smtp_port','smtp_user','smtp_pass','smtp_encryption',
        'sms_provider','sms_key','sms_sender',
        'email_notifications_enabled','sms_notifications_enabled',
        // Application options
        'mode_toggle','application_fee','acceptance_fee','academic_session','voucher_required',
        'documents_required'
    ];
    $isSettingsPost = (
        (isset($_POST['panel']) && $_POST['panel'] === 'settings') ||
        in_array($_POST['action'] ?? '', $allowedActions, true) ||
        (count(array_intersect(array_keys($_POST), $whitelist)) > 0) ||
        (!empty($_FILES['logo']['name']) || !empty($_FILES['avatar']['name']))
    );

    if ($isSettingsPost) {
    try {
        // CSRF optional if using generateCSRFToken
        if (function_exists('validateCSRFToken')) {
            if (!validateCSRFToken($_POST['csrf'] ?? '')) {
                throw new RuntimeException('Invalid CSRF token');
            }
        }

        // Handle gateway CRUD actions first (so toast shows proper message)
        $action = $_POST['action'] ?? '';

        // Ensure payment_methods and sms_gateways tables exist
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS payment_methods (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) UNIQUE NOT NULL,
                gateway VARCHAR(50),
                is_online TINYINT(1) DEFAULT 1,
                is_active TINYINT(1) DEFAULT 1,
                config JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) { /* ignore */ }
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS sms_gateways (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) UNIQUE NOT NULL,
                provider VARCHAR(100),
                is_active TINYINT(1) DEFAULT 1,
                config JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) { /* ignore */ }

        if ($action === 'add_gateway') {
            $name = trim($_POST['gw_name'] ?? '');
            $gateway = trim($_POST['gw_code'] ?? 'custom');
            $isOnline = (int)($_POST['gw_online'] ?? 1);
            $config = $_POST['gw_config'] ?? '{}';
            if (!$name) throw new RuntimeException('Gateway name required');
            $st = $pdo->prepare("INSERT INTO payment_methods (name, gateway, is_online, is_active, config) VALUES (?,?,?,?,?)");
            $st->execute([$name, $gateway, $isOnline, 1, $config]);
            $message = 'Payment gateway added'; $messageType = 'success';
        } elseif ($action === 'update_gateway') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['gw_name'] ?? '');
            $gateway = trim($_POST['gw_code'] ?? 'custom');
            $isOnline = (int)($_POST['gw_online'] ?? 1);
            $isActive = (int)($_POST['gw_active'] ?? 1);
            $config = $_POST['gw_config'] ?? '{}';
            if (!$id || !$name) throw new RuntimeException('Gateway id and name required');
            $st = $pdo->prepare("UPDATE payment_methods SET name=?, gateway=?, is_online=?, is_active=?, config=? WHERE id=?");
            $st->execute([$name, $gateway, $isOnline, $isActive, $config, $id]);
            $message = 'Payment gateway updated'; $messageType = 'success';
        } elseif ($action === 'toggle_gateway') {
            $id = (int)($_POST['id'] ?? 0);
            $isActive = (int)($_POST['is_active'] ?? 0);
            if (!$id) throw new RuntimeException('Gateway id required');
            $st = $pdo->prepare("UPDATE payment_methods SET is_active=? WHERE id=?");
            $st->execute([$isActive, $id]);
            $message = 'Payment gateway status updated'; $messageType = 'success';
        } elseif ($action === 'delete_gateway') {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new RuntimeException('Gateway id required');
            $st = $pdo->prepare("DELETE FROM payment_methods WHERE id=?");
            $st->execute([$id]);
            $message = 'Payment gateway deleted'; $messageType = 'success';
        } elseif ($action === 'add_sms_gateway') {
            $name = trim($_POST['sms_name'] ?? '');
            $provider = trim($_POST['sms_provider_name'] ?? 'custom');
            $config = $_POST['sms_config'] ?? '{}';
            if (!$name) throw new RuntimeException('SMS gateway name required');
            $st = $pdo->prepare("INSERT INTO sms_gateways (name, provider, is_active, config) VALUES (?,?,1,?)");
            $st->execute([$name, $provider, $config]);
            $message = 'SMS gateway added'; $messageType = 'success';
        } elseif ($action === 'update_sms_gateway') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['sms_name'] ?? '');
            $provider = trim($_POST['sms_provider_name'] ?? 'custom');
            $isActive = (int)($_POST['sms_active'] ?? 1);
            $config = $_POST['sms_config'] ?? '{}';
            if (!$id || !$name) throw new RuntimeException('SMS gateway id and name required');
            $st = $pdo->prepare("UPDATE sms_gateways SET name=?, provider=?, is_active=?, config=? WHERE id=?");
            $st->execute([$name, $provider, $isActive, $config, $id]);
            $message = 'SMS gateway updated'; $messageType = 'success';
        } elseif ($action === 'toggle_sms_gateway') {
            $id = (int)($_POST['id'] ?? 0);
            $isActive = (int)($_POST['is_active'] ?? 0);
            if (!$id) throw new RuntimeException('SMS gateway id required');
            $st = $pdo->prepare("UPDATE sms_gateways SET is_active=? WHERE id=?");
            $st->execute([$isActive, $id]);
            $message = 'SMS gateway status updated'; $messageType = 'success';
        } elseif ($action === 'delete_sms_gateway') {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new RuntimeException('SMS gateway id required');
            $st = $pdo->prepare("DELETE FROM sms_gateways WHERE id=?");
            $st->execute([$id]);
            $message = 'SMS gateway deleted'; $messageType = 'success';
        }

        // Persist scalar settings (whitelist defined above)
        foreach ($whitelist as $key) {
            if (isset($_POST[$key])) {
                upsert_setting($pdo, $key, trim((string)$_POST[$key]));
            }
        }

        // Logo upload
        if (!empty($_FILES['logo']['name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['png','jpg','jpeg','webp'])) {
                throw new RuntimeException('Unsupported logo format');
            }
            $logoTarget = '/uploads/logos/logo.' . ($ext === 'jpeg' ? 'jpg' : $ext);
            $abs = $_SERVER['DOCUMENT_ROOT'] . $logoTarget;
            move_uploaded_file($_FILES['logo']['tmp_name'], $abs);
            upsert_setting($pdo, 'logo_path', $logoTarget);
        }

        // Avatar upload for current user
        if (!empty($_FILES['avatar']['name']) && is_uploaded_file($_FILES['avatar']['tmp_name']) && isset($_SESSION['user_id'])) {
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['png','jpg','jpeg','webp'])) {
                throw new RuntimeException('Unsupported avatar format');
            }
            $avatarTarget = '/uploads/avatars/' . $_SESSION['user_id'] . '.png';
            $abs = $_SERVER['DOCUMENT_ROOT'] . $avatarTarget;
            // Normalize to PNG by moving as-is; conversion skipped for simplicity
            move_uploaded_file($_FILES['avatar']['tmp_name'], $abs);
        }

        $message = 'Settings saved successfully';
        $messageType = 'success';
    } catch (Throwable $e) {
        $message = 'Save failed: ' . $e->getMessage();
        $messageType = 'danger';
    }
    }
}

// Load current values
$defaults = [
    'brand_color' => '#2563eb',
    'logo_path' => '/uploads/logos/logo.png',
    'institution_name' => '',
    'institution_email' => '',
    'institution_phone' => '',
    'timezone' => 'UTC',
    'currency' => 'GHS',
    // Payments defaults
    'paystack_enabled' => '0', 'paystack_public_key' => '', 'paystack_secret_key' => '', 'paystack_mode' => 'test',
    'flutter_enabled' => '0', 'flutter_public_key' => '', 'flutter_secret_key' => '', 'flutter_mode' => 'test',
    'stripe_enabled' => '0', 'stripe_public_key' => '', 'stripe_secret_key' => '', 'stripe_mode' => 'test',
    // Email/SMS
    'smtp_host' => '', 'smtp_port' => '587', 'smtp_user' => '', 'smtp_pass' => '', 'smtp_encryption' => 'tls',
    'sms_provider' => '', 'sms_key' => '', 'sms_sender' => '',
    'email_notifications_enabled' => '1', 'sms_notifications_enabled' => '0',
    // Application options
    'mode_toggle' => 'pay_after', 'application_fee' => '0', 'acceptance_fee' => '0', 'academic_session' => date('Y'),
    'voucher_required' => '0', 'documents_required' => '1'
];
$settings = $defaults;
try {
    $stmt = $pdo->query("SELECT config_key, config_value FROM system_config");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $settings[$row['config_key']] = $row['config_value'];
    }
} catch (Throwable $e) { /* ignore */ }

?>

<div id="toastArea" class="toast-area"></div>
<?php if ($message): ?>
  <script>
    document.addEventListener('DOMContentLoaded',function(){
      function makeToast(text,type){
        var area=document.getElementById('toastArea');
        var t=document.createElement('div');
        t.className='toast '+(type||'info');
        t.innerHTML='<span>'+text+'</span><button class="close" aria-label="Close">&times;</button>';
        t.querySelector('.close').onclick=function(){ t.remove(); };
        area.appendChild(t);
        setTimeout(function(){ t.remove(); },4000);
      }
      makeToast('<?php echo htmlspecialchars($message); ?>','<?php echo $messageType==='success' ? 'success' : 'error'; ?>');
    });
  </script>
<?php endif; ?>

<style>
.tabs{display:flex;gap:6px;margin-bottom:12px;flex-wrap:wrap}
.tab-btn{background:var(--card);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:8px;cursor:pointer}
.tab-btn.active{background:var(--surface-hover)}
.tab-pane{display:none}
.tab-pane.active{display:block}
</style>

<div class="panel-card">
  <div class="tabs">
    <button class="tab-btn active" data-tab="tab-branding">Branding</button>
    <button class="tab-btn" data-tab="tab-institution">Institution</button>
    <button class="tab-btn" data-tab="tab-payments">Payments</button>
    <button class="tab-btn" data-tab="tab-email">Email/SMS</button>
    <button class="tab-btn" data-tab="tab-options">Application Options</button>
  </div>

  <form method="post" enctype="multipart/form-data" id="settingsForm">
    <input type="hidden" name="csrf" value="<?php echo function_exists('generateCSRFToken') ? generateCSRFToken() : ''; ?>">
    <div id="tab-branding" class="tab-pane active">
      <div class="grid cols-2">
      <div>
        <label class="form-label">Brand Color</label>
        <input class="input" type="color" name="brand_color" value="<?php echo htmlspecialchars($settings['brand_color']); ?>">
      </div>
      <div>
        <label class="form-label">Logo</label>
        <input type="file" name="logo" class="input">
        <?php if (!empty($settings['logo_path'])): ?>
          <div class="muted" style="margin-top:6px;">Current: <?php echo htmlspecialchars($settings['logo_path']); ?></div>
        <?php endif; ?>
      </div>
      </div>
    </div>

    <div id="tab-institution" class="tab-pane">
      <div class="grid cols-2">
      <div>
        <label class="form-label">Institution Name</label>
        <input class="input" name="institution_name" value="<?php echo htmlspecialchars($settings['institution_name']); ?>">
      </div>
      <div>
        <label class="form-label">Email</label>
        <input class="input" name="institution_email" value="<?php echo htmlspecialchars($settings['institution_email']); ?>">
      </div>
      <div>
        <label class="form-label">Phone</label>
        <input class="input" name="institution_phone" value="<?php echo htmlspecialchars($settings['institution_phone']); ?>">
      </div>
      <div>
        <label class="form-label">Timezone</label>
        <input class="input" name="timezone" value="<?php echo htmlspecialchars($settings['timezone']); ?>">
      </div>
      <div>
        <label class="form-label">Currency</label>
        <input class="input" name="currency" value="<?php echo htmlspecialchars($settings['currency']); ?>">
      </div>
      <div>
        <label class="form-label">My Avatar</label>
        <input type="file" name="avatar" class="input">
      </div>
      </div>
    </div>

    <div id="tab-payments" class="tab-pane">
      <div class="panel-card">
        <h4 class="muted">Add Payment Gateway</h4>
        <form method="post" action="?panel=settings" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px">
          <input type="hidden" name="action" value="add_gateway">
          <input type="hidden" name="csrf" value="<?php echo function_exists('generateCSRFToken') ? generateCSRFToken() : ''; ?>">
          <div><label class="form-label">Display Name *</label><input class="input" name="gw_name" required placeholder="e.g., Paystack"></div>
          <div><label class="form-label">Code</label><input class="input" name="gw_code" placeholder="e.g., paystack"></div>
          <div><label class="form-label">Online Processor</label><select class="input" name="gw_online"><option value="1">Yes</option><option value="0">No</option></select></div>
          <div style="grid-column:1/-1"><label class="form-label">Config (JSON)</label><textarea class="input" name="gw_config" rows="3" placeholder='{"public_key":"","secret_key":"","mode":"test"}'></textarea></div>
          <div style="display:flex;align-items:flex-end"><button class="btn" type="submit"><i class="bi bi-plus-lg"></i> Add Gateway</button></div>
        </form>
      </div>
      <?php
        $gateways=[]; try{ $st=$pdo->query("SELECT * FROM payment_methods ORDER BY name"); $gateways=$st->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){}
      ?>
      <div class="panel-card">
        <h4 class="muted">Gateways</h4>
        <?php if(!$gateways): ?><div class="muted">No gateways added.</div><?php else: ?>
        <div class="card" style="overflow:auto">
          <table style="width:100%;border-collapse:collapse">
            <thead><tr style="text-align:left;border-bottom:1px solid var(--border)"><th style="padding:10px">Name</th><th style="padding:10px">Code</th><th style="padding:10px">Online</th><th style="padding:10px">Active</th><th style="padding:10px">Actions</th></tr></thead>
            <tbody>
            <?php foreach($gateways as $g): ?>
              <tr style="border-bottom:1px solid var(--border)">
                <td style="padding:10px"><?php echo htmlspecialchars($g['name']); ?></td>
                <td style="padding:10px"><?php echo htmlspecialchars($g['gateway']); ?></td>
                <td style="padding:10px"><span class="muted"><?php echo $g['is_online']?'Yes':'No'; ?></span></td>
                <td style="padding:10px"><span class="muted"><?php echo $g['is_active']?'Active':'Inactive'; ?></span></td>
                <td style="padding:10px;display:flex;gap:6px;flex-wrap:wrap">
                  <button class="btn secondary" type="button" onclick='editGateway(<?php echo json_encode($g, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>)'><i class="bi bi-pencil"></i> Edit</button>
                  <form method="post" action="?panel=settings" style="display:inline">
                    <input type="hidden" name="action" value="toggle_gateway"><input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
                    <input type="hidden" name="is_active" value="<?php echo $g['is_active']?0:1; ?>">
                    <input type="hidden" name="csrf" value="<?php echo function_exists('generateCSRFToken') ? generateCSRFToken() : ''; ?>">
                    <button class="btn secondary" type="submit"><i class="bi bi-toggle2-<?php echo $g['is_active']?'on':'off'; ?>"></i> <?php echo $g['is_active']?'Disable':'Enable'; ?></button>
                  </form>
                  <form method="post" action="?panel=settings" style="display:inline" onsubmit="return confirm('Delete this gateway?')">
                    <input type="hidden" name="action" value="delete_gateway"><input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
                    <input type="hidden" name="csrf" value="<?php echo function_exists('generateCSRFToken') ? generateCSRFToken() : ''; ?>">
                    <button class="btn secondary" type="submit" style="color:#ef4444"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div id="tab-email" class="tab-pane">
      <div class="grid cols-2">
        <div>
          <h4 class="muted">SMTP</h4>
          <label class="form-label">Host</label>
          <input class="input" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host']); ?>">
          <label class="form-label">Port</label>
          <input class="input" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port']); ?>">
          <label class="form-label">Username</label>
          <input class="input" name="smtp_user" value="<?php echo htmlspecialchars($settings['smtp_user']); ?>">
          <label class="form-label">Password</label>
          <input class="input" name="smtp_pass" value="<?php echo htmlspecialchars($settings['smtp_pass']); ?>">
          <label class="form-label">Encryption</label>
          <select class="input" name="smtp_encryption"><option value="tls" <?php echo $settings['smtp_encryption']=='tls'?'selected':''; ?>>TLS</option><option value="ssl" <?php echo $settings['smtp_encryption']=='ssl'?'selected':''; ?>>SSL</option></select>
          <label class="form-label">Enable Email Notifications</label>
          <select class="input" name="email_notifications_enabled"><option value="1" <?php echo $settings['email_notifications_enabled']=='1'?'selected':''; ?>>Yes</option><option value="0" <?php echo $settings['email_notifications_enabled']=='0'?'selected':''; ?>>No</option></select>
        </div>
        <div>
          <h4 class="muted">SMS Gateways</h4>
          <form method="post" action="?panel=settings" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px">
            <input type="hidden" name="action" value="add_sms_gateway">
            <input type="hidden" name="csrf" value="<?php echo function_exists('generateCSRFToken') ? generateCSRFToken() : ''; ?>">
            <div><label class="form-label">Name *</label><input class="input" name="sms_name" required placeholder="e.g., Twilio"></div>
            <div><label class="form-label">Provider</label><input class="input" name="sms_provider_name" placeholder="e.g., twilio"></div>
            <div style="grid-column:1/-1"><label class="form-label">Config (JSON)</label><textarea class="input" name="sms_config" rows="3" placeholder='{"sid":"","token":"","from":""}'></textarea></div>
            <div style="display:flex;align-items:flex-end"><button class="btn" type="submit"><i class="bi bi-plus-lg"></i> Add SMS Gateway</button></div>
          </form>
          <?php $smsg=[]; try{ $st=$pdo->query("SELECT * FROM sms_gateways ORDER BY name"); $smsg=$st->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){} ?>
          <div class="card" style="overflow:auto;margin-top:12px">
            <table style="width:100%;border-collapse:collapse">
              <thead><tr style="text-align:left;border-bottom:1px solid var(--border)"><th style="padding:10px">Name</th><th style="padding:10px">Provider</th><th style="padding:10px">Active</th><th style="padding:10px">Actions</th></tr></thead>
              <tbody>
                <?php foreach($smsg as $g): ?>
                <tr style="border-bottom:1px solid var(--border)">
                  <td style="padding:10px"><?php echo htmlspecialchars($g['name']); ?></td>
                  <td style="padding:10px"><?php echo htmlspecialchars($g['provider']); ?></td>
                  <td style="padding:10px"><span class="muted"><?php echo $g['is_active']?'Active':'Inactive'; ?></span></td>
                  <td style="padding:10px;display:flex;gap:6px;flex-wrap:wrap">
                    <button class="btn secondary" type="button" onclick='editSmsGateway(<?php echo json_encode($g, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>)'><i class="bi bi-pencil"></i> Edit</button>
                    <form method="post" action="?panel=settings" style="display:inline">
                      <input type="hidden" name="action" value="toggle_sms_gateway"><input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
                      <input type="hidden" name="is_active" value="<?php echo $g['is_active']?0:1; ?>">
                      <input type="hidden" name="csrf" value="<?php echo function_exists('generateCSRFToken') ? generateCSRFToken() : ''; ?>">
                      <button class="btn secondary" type="submit"><i class="bi bi-toggle2-<?php echo $g['is_active']?'on':'off'; ?>"></i> <?php echo $g['is_active']?'Disable':'Enable'; ?></button>
                    </form>
                    <form method="post" action="?panel=settings" style="display:inline" onsubmit="return confirm('Delete this gateway?')">
                      <input type="hidden" name="action" value="delete_sms_gateway"><input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
                      <input type="hidden" name="csrf" value="<?php echo function_exists('generateCSRFToken') ? generateCSRFToken() : ''; ?>">
                      <button class="btn secondary" type="submit" style="color:#ef4444"><i class="bi bi-trash"></i></button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div id="tab-options" class="tab-pane">
      <div class="grid cols-2">
        <div>
          <h4 class="muted">Mode & Fees</h4>
          <label class="form-label">Application Mode</label>
          <select class="input" name="mode_toggle"><option value="pay_after" <?php echo $settings['mode_toggle']=='pay_after'?'selected':''; ?>>Pay during/after application</option><option value="voucher" <?php echo $settings['mode_toggle']=='voucher'?'selected':''; ?>>Voucher serial/PIN required</option></select>
          <label class="form-label">Application Fee</label>
          <input class="input" name="application_fee" value="<?php echo htmlspecialchars($settings['application_fee']); ?>">
          <label class="form-label">Acceptance Fee</label>
          <input class="input" name="acceptance_fee" value="<?php echo htmlspecialchars($settings['acceptance_fee']); ?>">
          <label class="form-label">Academic Session</label>
          <input class="input" name="academic_session" value="<?php echo htmlspecialchars($settings['academic_session']); ?>">
        </div>
        <div>
          <h4 class="muted">Requirements</h4>
          <label class="form-label">Voucher Required</label>
          <select class="input" name="voucher_required"><option value="1" <?php echo $settings['voucher_required']=='1'?'selected':''; ?>>Yes</option><option value="0" <?php echo $settings['voucher_required']=='0'?'selected':''; ?>>No</option></select>
          <label class="form-label">Documents Required</label>
          <select class="input" name="documents_required"><option value="1" <?php echo $settings['documents_required']=='1'?'selected':''; ?>>Yes</option><option value="0" <?php echo $settings['documents_required']=='0'?'selected':''; ?>>No</option></select>
        </div>
      </div>
    </div>
    <div class="form-actions">
      <button class="btn" type="submit"><i class="bi bi-check2-circle"></i> Save Settings</button>
    </div>
  </form>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    // After saving, backend persists brand; reflect immediately if present in URL
    const form = document.getElementById('settingsForm');
    if (form) {
      form.addEventListener('submit', function(){
        // brief live feedback; backend will persist and on reload will apply
        try { clearToasts(); toast({ message: 'Saving settings...', variant: 'info' }); } catch(e){}
      });
    }
  });
  // Simple tabs
  (function(){
    const tabs=document.querySelectorAll('.tab-btn');
    const panes=document.querySelectorAll('.tab-pane');
    tabs.forEach(btn=>btn.addEventListener('click',function(){
      tabs.forEach(b=>b.classList.remove('active')); this.classList.add('active');
      const id=this.dataset.tab; panes.forEach(p=>p.classList.toggle('active', p.id===id));
    }));
  })();

  // Edit modals (inline) for gateways
  function editGateway(g){
    const json = (typeof g === 'string') ? JSON.parse(g) : g;
    const cfg = JSON.stringify(JSON.parse(json.config||'{}'), null, 2);
    const html = `
    <div style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;display:flex;align-items:center;justify-content:center" id="gwModal">
      <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;max-width:700px;width:90vw;max-height:90vh;overflow:auto">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><h3>Edit Gateway</h3><button class="btn secondary" onclick="document.getElementById('gwModal').remove()">Close</button></div>
        <form method="post" action="?panel=settings" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px">
          <input type="hidden" name="action" value="update_gateway"><input type="hidden" name="id" value="${json.id}">
          <input type="hidden" name="csrf" value="<?php echo function_exists('generateCSRFToken') ? generateCSRFToken() : ''; ?>">
          <div><label class="form-label">Display Name *</label><input class="input" name="gw_name" required value="${json.name||''}"></div>
          <div><label class="form-label">Code</label><input class="input" name="gw_code" value="${json.gateway||''}"></div>
          <div><label class="form-label">Online</label><select class="input" name="gw_online"><option value="1" ${json.is_online==1?'selected':''}>Yes</option><option value="0" ${json.is_online==0?'selected':''}>No</option></select></div>
          <div><label class="form-label">Active</label><select class="input" name="gw_active"><option value="1" ${json.is_active==1?'selected':''}>Yes</option><option value="0" ${json.is_active==0?'selected':''}>No</option></select></div>
          <div style="grid-column:1/-1"><label class="form-label">Config (JSON)</label><textarea class="input" rows="6" name="gw_config">${cfg}</textarea></div>
          <div style="grid-column:1/-1;display:flex;justify-content:flex-end"><button class="btn" type="submit"><i class="bi bi-save"></i> Save</button></div>
        </form>
      </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
  }

  function editSmsGateway(g){
    const json = (typeof g === 'string') ? JSON.parse(g) : g;
    const cfg = JSON.stringify(JSON.parse(json.config||'{}'), null, 2);
    const html = `
    <div style=\"position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;display:flex;align-items:center;justify-content:center\" id=\"smsModal\">
      <div style=\"background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;max-width:700px;width:90vw;max-height:90vh;overflow:auto\">
        <div style=\"display:flex;justify-content:space-between;align-items:center;margin-bottom:12px\"><h3>Edit SMS Gateway</h3><button class=\"btn secondary\" onclick=\"document.getElementById('smsModal').remove()\">Close</button></div>
        <form method=\"post\" action=\"?panel=settings\" style=\"display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px\">
          <input type=\"hidden\" name=\"action\" value=\"update_sms_gateway\"><input type=\"hidden\" name=\"id\" value=\"${json.id}\">
          <input type=\"hidden\" name=\"csrf\" value=\"<?php echo function_exists('generateCSRFToken') ? generateCSRFToken() : ''; ?>\">
          <div><label class=\"form-label\">Name *</label><input class=\"input\" name=\"sms_name\" required value=\"${json.name||''}\"></div>
          <div><label class=\"form-label\">Provider</label><input class=\"input\" name=\"sms_provider_name\" value=\"${json.provider||''}\"></div>
          <div><label class=\"form-label\">Active</label><select class=\"input\" name=\"sms_active\"><option value=\"1\" ${json.is_active==1?'selected':''}>Yes</option><option value=\"0\" ${json.is_active==0?'selected':''}>No</option></select></div>
          <div style=\"grid-column:1/-1\"><label class=\"form-label\">Config (JSON)</label><textarea class=\"input\" rows=\"6\" name=\"sms_config\">${cfg}</textarea></div>
          <div style=\"grid-column:1/-1;display:flex;justify-content:flex-end\"><button class=\"btn\" type=\"submit\"><i class=\"bi bi-save\"></i> Save</button></div>
        </form>
      </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
  }
</script>


